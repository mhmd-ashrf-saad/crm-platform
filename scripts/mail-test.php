<?php

/**
 * Send one real email through this installation's own mail configuration, and report what the
 * mail server actually answered.
 *
 * Why this exists: mail on this deployment failed silently. The development `.env` pointed at a
 * `mailhog` container that was never created, and the production template shipped
 * `MAIL_MAILER=log`, where every message is written to the log file and delivered to nobody. In
 * that state a password reset "succeeds": the form says the link was sent, no error is raised,
 * and nothing arrives. The workflow paths that send mail made it worse by swallowing the
 * exception in an empty catch block. Mail is only proven by a mail server answering, which is
 * what this script records.
 *
 * It sends through the application's real mailer, not a fake: `config/mail.php` and `.env` as
 * they are, with the same transport a password reset or a workflow email would use. It prints
 * the SMTP conversation (with the credentials stripped), and it exits non-zero when the send
 * fails, so it can be a check rather than a suggestion.
 *
 * Usage:
 *   make mail-test MAIL_TO=you@example.com
 *   MAIL_TO=you@example.com php scripts/mail-test.php
 *
 * The recipient has no default on purpose: a test mail that goes to a hardcoded address proves
 * delivery to that address and nothing about whether the operator can receive mail.
 *
 * Exit status:
 *   0  the mail server accepted the message (2xx on DATA)
 *   1  the send failed, or the configured driver cannot deliver at all
 *   2  no valid recipient was given, so nothing was attempted
 *
 * Deliberately read-only with respect to configuration: it never writes `.env` and never changes
 * a setting to make itself pass. If the send fails, the fix is the configuration, not the check.
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\SentMessage;

$basePath = dirname(__DIR__);

$fail = static function (string $message, int $status = 1): never {
    fwrite(STDERR, "mail-test: {$message}\n");
    exit($status);
};

/**
 * The recipient comes from MAIL_TO or the first argument, never from this file.
 */
$to = trim(getenv('MAIL_TO') ?: ($argv[1] ?? ''));

if ($to === '') {
    $fail('no recipient. Give one: make mail-test MAIL_TO=you@example.com', 2);
}

if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    $fail("'{$to}' is not a valid email address, so nothing was attempted", 2);
}

require $basePath.'/vendor/autoload.php';

$app = require_once $basePath.'/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

/**
 * The same three drivers MailDeliveryServiceProvider refuses to call delivery on. A `log`
 * mailer reports success for a message that never leaves the machine, so running this script
 * against one would produce a green result that means nothing.
 */
$driver = (string) config('mail.default');
$deadEndDrivers = ['log', 'array', 'null'];

$subject = 'Sijilna mail test '.gmdate('Y-m-d H:i:s').' UTC';

$body = implode("\n", [
    'This is a delivery test from Sijilna CRM.',
    '',
    'Sent:      '.gmdate(DATE_ATOM),
    'Host:      '.gethostname(),
    'Transport: '.$driver.'://'.(config("mail.mailers.{$driver}.host") ?: '-').':'.(config("mail.mailers.{$driver}.port") ?: '-'),
    'From:      '.config('mail.from.address'),
    '',
    'If you received this, the instance can send mail.',
]);

/**
 * Credentials are stripped from the transcript before it is printed. Symfony records the whole
 * SMTP conversation, including the AUTH exchange, whose payloads are base64-encoded
 * credentials — base64 is not encryption, so printing them raw would publish the SMTP password
 * in the terminal, in a log and in whatever report quotes this output.
 *
 * Only server replies and recognised safe verbs survive; anything else becomes `<redacted>`.
 */
$maskTranscript = static function (string $transcript): string {
    $safeVerbs = '/^(?:EHLO|HELO|MAIL FROM|RCPT TO|DATA|QUIT|RSET|NOOP|STARTTLS)\b/i';
    $masked = [];

    foreach (preg_split('/\R/', $transcript) ?: [] as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        /** Server replies are `[timestamp] < text`; outbound commands are `[timestamp] > text`. */
        if (preg_match('/^\[[^\]]+\]\s*(<|>)\s*(.*)$/', $line, $matches) !== 1) {
            $masked[] = '<redacted line>';

            continue;
        }

        [, $direction, $payload] = $matches;

        if ($direction === '<' || preg_match($safeVerbs, $payload) === 1) {
            $masked[] = $direction.' '.$payload;

            continue;
        }

        $masked[] = $direction.' <redacted>';
    }

    return implode("\n", $masked);
};

printf("mail-test: sending to %s\n", $to);
printf("mail-test: driver %s  host %s  port %s  encryption %s\n",
    $driver,
    (string) (config("mail.mailers.{$driver}.host") ?: '-'),
    (string) (config("mail.mailers.{$driver}.port") ?: '-'),
    (string) (config("mail.mailers.{$driver}.encryption") ?: 'none'),
);
printf("mail-test: from %s  (%s)\n",
    (string) (config('mail.from.address') ?: 'NOT SET'),
    (string) (config('mail.from.name') ?: '-'),
);
printf("mail-test: username %s  password %s (values are never printed)\n",
    config("mail.mailers.{$driver}.username") ? 'set' : 'empty',
    config("mail.mailers.{$driver}.password") ? 'set' : 'empty',
);

if (in_array($driver, $deadEndDrivers, true)) {
    $fail(
        "MAIL_MAILER={$driver} cannot deliver. It accepts every message and delivers nothing, "
        .'so this check would pass while no email ever leaves the instance. Set MAIL_MAILER=smtp '
        .'and the MAIL_HOST/MAIL_PORT/MAIL_ENCRYPTION/MAIL_USERNAME/MAIL_PASSWORD of your provider '
        .'(deploy/.env.deploy.example documents the Brevo shape), then run this again.'
    );
}

if (! config('mail.from.address')) {
    $fail('MAIL_FROM_ADDRESS is empty. Most relays reject a message with no From address, so the send would fail for a reason that is not the transport.');
}

$sent = null;
$error = null;

/**
 * The values that must never be printed, as configured: the SMTP password and the SMTP login.
 * Both are redacted from the transcript and from any transport error, because Symfony's
 * authentication failure quotes the username back — "Failed to authenticate on SMTP server
 * with username ..." — and an error report is not a licence to publish a credential.
 */
$secrets = array_values(array_filter([
    config("mail.mailers.{$driver}.password"),
    config("mail.mailers.{$driver}.username"),
], static fn ($secret) => is_string($secret) && $secret !== ''));

$redact = static function (string $text) use ($secrets): string {
    foreach ($secrets as $secret) {
        $text = str_replace([$secret, base64_encode($secret)], '<redacted>', $text);
    }

    return $text;
};

/**
 * Count what is left, so the claim "no secret was printed" is measured rather than asserted.
 */
$leakCount = static function (string $text) use ($secrets): int {
    $leaks = 0;

    foreach ($secrets as $secret) {
        $leaks += substr_count($text, $secret);
        $leaks += substr_count($text, base64_encode($secret));
    }

    return $leaks;
};

try {
    $sent = Mail::raw($body, function ($message) use ($to, $subject) {
        $message->to($to)->subject($subject);
    });
} catch (Throwable $e) {
    $error = $e;
}

if ($error !== null) {
    $errorText = $redact($error->getMessage());

    fwrite(STDERR, "mail-test: FAILED - the mail server did not accept the message.\n");
    fwrite(STDERR, 'mail-test: '.$error::class."\n");
    fwrite(STDERR, 'mail-test: '.$errorText."\n");
    fwrite(STDERR, 'mail-test: credential occurrences in this output: '.$leakCount($errorText)."\n");

    if ($leakCount($errorText) > 0) {
        fwrite(STDERR, "mail-test: the error text still contains a credential; rotate the SMTP key.\n");
    }

    fwrite(STDERR, "mail-test: the same failure is written to the application log at error level, so a send that\n");
    fwrite(STDERR, "mail-test: Krayin swallows is still visible there (storage/logs/laravel.log).\n");
    fwrite(STDERR, "mail-test: provider-side causes, in the order they bite:\n");
    fwrite(STDERR, "mail-test:   * 525 Unauthorized IP address  -> the relay only accepts IPs listed at your\n");
    fwrite(STDERR, "mail-test:     provider. Brevo: SMTP & API -> SMTP -> Authorized IPs. The credentials are fine;\n");
    fwrite(STDERR, "mail-test:     the sender's public IP is not on the key's allowlist.\n");
    fwrite(STDERR, "mail-test:   * 535 Authentication failed     -> wrong MAIL_USERNAME/MAIL_PASSWORD, or a revoked key.\n");
    fwrite(STDERR, "mail-test:   * 550 / 553 sender rejected     -> MAIL_FROM_ADDRESS is not a verified sender at the\n");
    fwrite(STDERR, "mail-test:     provider, or its domain is not authenticated (SPF/DKIM/DMARC).\n");

    exit(1);
}

$transcript = $sent instanceof SentMessage ? (string) $sent->getDebug() : '';
$masked = $redact($maskTranscript($transcript));

$leaks = $leakCount($masked);

printf("mail-test: SMTP conversation, credential lines removed:\n%s\n", $masked !== '' ? $masked : '(the transport recorded no conversation)');

printf("mail-test: message id %s\n", $sent->getMessageId() ?? '-');
printf("mail-test: credential occurrences in the output above: %d\n", $leaks);

if ($leaks > 0) {
    $fail('the SMTP transcript still contains a credential, which means the masking did not work. Nothing above this line was masked correctly; treat the password as exposed and rotate it.');
}

printf("mail-test: DELIVERED to the mail server for %s. If it does not arrive, the remaining cause is\n", $to);
printf("mail-test: deliverability - the provider's sender check, SPF, DKIM or DMARC - not the transport.\n");

exit(0);
