<?php

/**
 * scripts/backup-db.sh -- the parts that can be proved without a database.
 *
 * `make backup` itself needs the live MySQL container, so it is rehearsed by hand and its result is
 * recorded in docs/ops/incident-response.md §6. What is left is the logic most likely to rot silently:
 * the retention policy that decides which dumps get deleted, and the manifest checks that decide whether
 * a file is safe to restore. Both are pure file logic, so they are tested here -- black box, through the
 * script's own CLI, with synthetic files. Nothing in this file connects to MySQL, boots Laravel or reads
 * .env; it lives in tests/Unit, which Pest.php deliberately does not bind to Tests\TestCase.
 *
 * The retention expectation is the documented policy, not whatever the code happens to do: the newest
 * dump of each of the last N days, plus the newest of each of the last M ISO weeks, plus the newest of
 * each of the last K calendar months, kept as a union (restic's --keep-daily/--keep-weekly/--keep-monthly).
 * A file kept by any of the three is kept.
 *
 * Temp files: sys_get_temp_dir(), holding empty or few-hundred-byte files that are removed in
 * afterEach. Nothing large is written to a tmpfs.
 */
/**
 * Run the script with a synthetic backup directory and return [exit code, stdout+stderr].
 *
 * The script path is resolved inside the closure rather than through `global`: a Pest test file is
 * included from a method scope, so a variable declared at the top of this file is not a global.
 */
$run = function (string $dir, array $args, array $env = []): array {
    $script = dirname(__DIR__, 2).'/scripts/backup-db.sh';

    $prefix = '';
    foreach ($env as $key => $value) {
        $prefix .= $key.'='.escapeshellarg((string) $value).' ';
    }

    $command = $prefix.'BACKUP_DIR='.escapeshellarg($dir).' bash '.escapeshellarg($script);
    foreach ($args as $arg) {
        $command .= ' '.escapeshellarg($arg);
    }

    $output = [];
    $status = 0;
    exec($command.' 2>&1', $output, $status);

    return [$status, implode("\n", $output)];
};

/**
 * A backup directory holding one dump per name, all empty, plus the manifests `--list` ignores.
 *
 * @param  string[]  $stamps
 */
$makeDir = function (array $stamps, array $extraFiles = []): string {
    $dir = sys_get_temp_dir().'/crm-backup-test-'.bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);

    foreach ($stamps as $stamp) {
        touch($dir.'/krayin-'.$stamp.'.sql.gz');
        touch($dir.'/krayin-'.$stamp.'.manifest');
    }

    foreach ($extraFiles as $name) {
        touch($dir.'/'.$name);
    }

    return $dir;
};

$removeDir = function (string $dir): void {
    foreach (glob($dir.'/{,.}*', GLOB_BRACE) ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
};

/**
 * Write a real, gzipped, verifiable dump plus a manifest describing it, so `--verify` has something
 * honest to agree with. The SQL is minimal but structurally what mysqldump produces for the checks that
 * matter: a CREATE TABLE line and the trailing `-- Dump completed` line.
 */
$makeVerifiableDump = function (string $dir, string $stamp, int $tables = 2): string {
    $name = 'krayin-'.$stamp.'.sql.gz';
    $sql = '';

    for ($i = 0; $i < $tables; $i++) {
        $sql .= "CREATE TABLE `t{$i}` (id int);\n";
    }
    $sql .= "-- Dump completed on 2026-11-09 12:00:00\n";

    file_put_contents($dir.'/'.$name, gzencode($sql, 9));

    $manifest = [
        'manifest_version' => 1,
        'created_at' => '2026-11-09T12:00:00Z',
        'database' => 'krayin',
        'container' => 'crm-platform-mysql',
        'server_version' => '8.4.11',
        'dump_file' => $name,
        'dump_sha256' => hash_file('sha256', $dir.'/'.$name),
        'dump_bytes' => (string) filesize($dir.'/'.$name),
        'uncompressed_bytes' => (string) strlen($sql),
        'compression' => 'gzip -9',
        'dump_duration' => '0.300s',
        'tables' => (string) $tables,
        'create_table_statements' => (string) $tables,
        'rows_measured_at' => '2026-11-09T12:00:00Z',
        'counts_stable' => 'yes',
        'rows_leads' => '27',
    ];

    $lines = [];
    foreach ($manifest as $key => $value) {
        $lines[] = $key.'='.$value;
    }
    file_put_contents($dir.'/krayin-'.$stamp.'.manifest', implode("\n", $lines)."\n");

    return $name;
};

/**
 * The RETENTION column of `--list`, as [name => keep|prune].
 */
$retentionMap = function (string $output): array {
    $map = [];
    foreach (explode("\n", $output) as $line) {
        if (preg_match('/^(krayin-\d{8}T\d{6}Z\.sql\.gz)\s+\d+\s+(keep|prune)$/', trim($line), $m) === 1) {
            $map[$m[1]] = $m[2];
        }
    }

    return $map;
};

it('keeps one dump per day for KEEP_DAILY days and prunes the rest', function () use ($run, $makeDir, $removeDir, $retentionMap) {
    $dir = $makeDir([
        '20261109T030000Z', '20261108T030000Z', '20261107T030000Z',
        '20261106T030000Z', '20261105T030000Z', '20261104T030000Z',
    ]);

    try {
        [$status, $output] = $run($dir, ['--list'], [
            'KEEP_DAILY' => 3, 'KEEP_WEEKLY' => 0, 'KEEP_MONTHLY' => 0,
        ]);

        expect($status)->toBe(0);
        $map = $retentionMap($output);

        expect($map)->toHaveCount(6)
            ->and($map['krayin-20261109T030000Z.sql.gz'])->toBe('keep')
            ->and($map['krayin-20261108T030000Z.sql.gz'])->toBe('keep')
            ->and($map['krayin-20261107T030000Z.sql.gz'])->toBe('keep')
            ->and($map['krayin-20261106T030000Z.sql.gz'])->toBe('prune')
            ->and($map['krayin-20261105T030000Z.sql.gz'])->toBe('prune')
            ->and($map['krayin-20261104T030000Z.sql.gz'])->toBe('prune');
    } finally {
        $removeDir($dir);
    }
});

it('keeps the newest dump of a day, not every dump of that day', function () use ($run, $makeDir, $removeDir, $retentionMap) {
    $dir = $makeDir(['20261109T210000Z', '20261109T120000Z', '20261109T030000Z']);

    try {
        [$status, $output] = $run($dir, ['--list'], [
            'KEEP_DAILY' => 1, 'KEEP_WEEKLY' => 0, 'KEEP_MONTHLY' => 0,
        ]);

        expect($status)->toBe(0);
        $map = $retentionMap($output);

        expect($map['krayin-20261109T210000Z.sql.gz'])->toBe('keep')
            ->and($map['krayin-20261109T120000Z.sql.gz'])->toBe('prune')
            ->and($map['krayin-20261109T030000Z.sql.gz'])->toBe('prune');
    } finally {
        $removeDir($dir);
    }
});

it('keeps a dump that weekly or monthly retention covers, as a union with the daily rule', function () use ($run, $makeDir, $removeDir, $retentionMap) {
    // 2026-11-09 is a Monday, so 11-08 is the last day of the previous ISO week and 10-30 the newest
    // dump of the previous calendar month. With KEEP_DAILY=1 only 11-09 survives the daily rule; the
    // other two survive because a keep-weekly and a keep-monthly are still outstanding, which is what
    // stops a gap between the daily window and the monthly one.
    $dir = $makeDir([
        '20261109T180000Z', '20261109T120000Z',
        '20261108T030000Z',
        '20261107T030000Z',
        '20261030T030000Z',
        '20261020T030000Z',
    ]);

    try {
        [$status, $output] = $run($dir, ['--list'], [
            'KEEP_DAILY' => 1, 'KEEP_WEEKLY' => 2, 'KEEP_MONTHLY' => 2,
        ]);

        expect($status)->toBe(0);
        $map = $retentionMap($output);

        expect($map['krayin-20261109T180000Z.sql.gz'])->toBe('keep')
            ->and($map['krayin-20261109T120000Z.sql.gz'])->toBe('prune')
            ->and($map['krayin-20261108T030000Z.sql.gz'])->toBe('keep')
            ->and($map['krayin-20261107T030000Z.sql.gz'])->toBe('prune')
            ->and($map['krayin-20261030T030000Z.sql.gz'])->toBe('keep')
            ->and($map['krayin-20261020T030000Z.sql.gz'])->toBe('prune');
    } finally {
        $removeDir($dir);
    }
});

it('never manages a hand-taken dump, whatever retention says', function () use ($run, $makeDir, $removeDir) {
    // The emergency copy taken by hand on 2026-11-05 does not match the naming pattern retention prunes
    // by, so it must not appear in the managed table at all -- the failure this prevents is a future
    // narrowing of the pattern quietly deleting the operator's only pre-incident dump.
    $dir = $makeDir(['20261109T030000Z'], ['krayin-2026-11-05-1422.sql', 'operator-notes.txt']);

    try {
        [$status, $output] = $run($dir, ['--list'], ['KEEP_DAILY' => 0, 'KEEP_WEEKLY' => 0, 'KEEP_MONTHLY' => 0]);

        expect($status)->toBe(0);
        expect($output)->toContain('not managed by retention')
            ->and($output)->toContain('krayin-2026-11-05-1422.sql')
            ->and($output)->toContain('operator-notes.txt')
            // The one managed dump is pruned by the zero limits, the hand-taken file is not "kept or
            // pruned" -- it is simply not retention's business.
            ->and($output)->not->toContain('krayin-2026-11-05-1422.sql  ');
    } finally {
        $removeDir($dir);
    }
});

it('verifies a dump that still matches its manifest', function () use ($run, $makeDir, $removeDir, $makeVerifiableDump) {
    $dir = $makeDir([]);

    try {
        $name = $makeVerifiableDump($dir, '20261109T120000Z');

        [$status, $output] = $run($dir, ['--verify', $name]);

        expect($status)->toBe(0)
            ->and($output)->toContain('verify: PASS  sha256')
            ->and($output)->toContain('verify: PASS  bytes')
            ->and($output)->toContain('verify: PASS  gzip integrity')
            ->and($output)->toContain('verify: PASS  dump complete')
            ->and($output)->toContain('verify: PASS  table statements')
            ->and($output)->toContain('verify: OK')
            ->and($output)->not->toContain('verify: FAIL');
    } finally {
        $removeDir($dir);
    }
});

it('refuses a dump whose bytes changed after the manifest was written', function () use ($run, $makeDir, $removeDir, $makeVerifiableDump) {
    $dir = $makeDir([]);

    try {
        $name = $makeVerifiableDump($dir, '20261109T120000Z');

        // A single appended byte: the size and the digest disagree with the manifest, which is the one
        // thing an operator's "the file is there and it is 52 KB" check cannot see.
        file_put_contents($dir.'/'.$name, "\x00", FILE_APPEND);

        [$status, $output] = $run($dir, ['--verify', $name]);

        expect($status)->not->toBe(0)
            ->and($output)->toContain('verify: FAIL  sha256')
            ->and($output)->toContain('verify: FAIL  bytes')
            ->and($output)->toContain('do not restore this file');
    } finally {
        $removeDir($dir);
    }
});

it('skips the decompression checks and fails when the gzip stream is corrupt', function () use ($run, $makeDir, $removeDir, $makeVerifiableDump) {
    $dir = $makeDir([]);

    try {
        $name = $makeVerifiableDump($dir, '20261109T120000Z');

        // Truncated mid-stream: gzip -dc still emits what it read, so without the explicit skip the
        // trailer and CREATE TABLE checks would report PASS on a file that cannot be restored.
        $bytes = (string) file_get_contents($dir.'/'.$name);
        file_put_contents($dir.'/'.$name, substr($bytes, 0, max(1, (int) (strlen($bytes) / 2))));

        [$status, $output] = $run($dir, ['--verify', $name]);

        expect($status)->not->toBe(0)
            ->and($output)->toContain('verify: FAIL  gzip integrity')
            ->and($output)->toContain('verify: SKIP  dump complete')
            ->and($output)->not->toContain('verify: PASS  dump complete');
    } finally {
        $removeDir($dir);
    }
});

it('refuses a dump that has no manifest', function () use ($run, $makeDir, $removeDir) {
    $dir = $makeDir([]);

    try {
        touch($dir.'/krayin-20261109T120000Z.sql.gz');

        [$status, $output] = $run($dir, ['--verify', 'krayin-20261109T120000Z.sql.gz']);

        expect($status)->not->toBe(0)
            ->and($output)->toContain('is missing')
            ->and($output)->toContain('cannot be verified');
    } finally {
        $removeDir($dir);
    }
});

it('verifies the newest dump when no file is named', function () use ($run, $makeDir, $removeDir, $makeVerifiableDump) {
    $dir = $makeDir([]);

    try {
        $old = $makeVerifiableDump($dir, '20261108T120000Z');
        $new = $makeVerifiableDump($dir, '20261109T120000Z');

        // Corrupt the older one: if --verify picked it, the run would fail.
        file_put_contents($dir.'/'.$old, "\x00", FILE_APPEND);

        [$status, $output] = $run($dir, ['--verify']);

        expect($status)->toBe(0)
            ->and($output)->toContain('verify: OK '.$new);
    } finally {
        $removeDir($dir);
    }
});

it('refuses an unknown option and documents itself on --help', function () use ($run, $makeDir, $removeDir) {
    $dir = $makeDir([]);

    try {
        [$status, $output] = $run($dir, ['--not-an-option']);
        expect($status)->not->toBe(0)->and($output)->toContain('unknown option');

        [$status, $help] = $run($dir, ['--help']);
        expect($status)->toBe(0)
            ->and($help)->toContain('Usage:')
            ->and($help)->toContain('--verify')
            ->and($help)->toContain('RETENTION IS EXPLICIT AND ENFORCED HERE');
    } finally {
        $removeDir($dir);
    }
});
