# Third-Party Notices

Licences that apply to this product and to the third-party code bundled with it.
This file is a summary and a pointer: the authoritative text is each package's own
licence file, and the pointers below say where to find it.

## Upstream: Krayin CRM

This product is a **fork of Krayin CRM** (`https://github.com/krayin/laravel-crm`),
an open-source Laravel CRM released under the MIT licence.

- Copyright holder of the upstream work: **Webkul Software** — `Copyright 2010-2025,
  Webkul Software`.
- The upstream MIT licence text and that copyright notice are preserved verbatim
  in [`LICENSE`](LICENSE) at the repository root, and are **not** modified by this
  fork.
- Our own modifications are distributed under the same MIT terms.

## No affiliation

This product is an **independent fork**. It is not affiliated with, endorsed by,
or sponsored by Webkul Software, and it is not the official Krayin CRM
distribution or an official Krayin CRM release.

The upstream names are used here and in `README.md` only to identify where the
code came from and to route fixes and credit to the right place. The MIT licence
permits the fork but grants **no trademark rights**, so the upstream name must not
be used in any way that implies the product is official, endorsed or sponsored.

## PHP dependencies

Derived from [`composer.lock`](composer.lock) (read, not guessed). Counts are per
declared identifier, so a package that declares several licences is counted once
for each.

Production (`packages`, 130 packages):

| Identifier | Packages |
| ---------- | -------- |
| MIT | 109 |
| BSD-3-Clause | 8 |
| Apache-2.0 | 4 |
| GPL-2.0-only | 3 |
| LGPL-2.1-or-later | 2 |
| LGPL-3.0 | 2 |
| GPL-3.0-only | 2 |
| LGPL-2.1 | 1 |
| LGPL-3.0-or-later | 1 |
| GPL-2.0-or-later | 1 |
| none declared | 1 |

Development (`packages-dev`, 56 packages): MIT 30, BSD-3-Clause 26.

### Copyleft and unresolved identifiers

These are the non-permissive or unusual ones in `composer.lock`. Nothing here
blocks development; it matters at distribution.

| Package | Declared licence | Note |
| ------- | ---------------- | ---- |
| `mpdf/mpdf` | GPL-2.0-only | Direct dependency |
| `enshrined/svg-sanitize` | GPL-2.0-or-later | Direct dependency |
| `khaled.alshamaa/ar-php` | LGPL-3.0 | Direct dependency |
| `smalot/pdfparser` | LGPL-3.0 | Direct dependency |
| `dompdf/dompdf` | LGPL-2.1 | Via `barryvdh/laravel-dompdf` |
| `dompdf/php-font-lib` | LGPL-2.1-or-later | Via `dompdf/dompdf` |
| `dompdf/php-svg-lib` | LGPL-3.0-or-later | Via `dompdf/dompdf` |
| `ezyang/htmlpurifier` | LGPL-2.1-or-later | Via `phpoffice/phpspreadsheet` |
| `nette/schema` | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | Three-way choice; the BSD-3-Clause option is available |
| `nette/utils` | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | Three-way choice; the BSD-3-Clause option is available |
| `prettus/laravel-validation` | none declared | `composer.lock` and its own `composer.json` carry no identifier; the shipped `LICENSE.md` is MIT (`Copyright (c) 2020 Anderson Andrade`) |

GPL and LGPL are copyleft. Distributing the product as a whole — source or built
image — carries the obligations those licences state, chiefly keeping their
notices and licence texts with the distribution and making the corresponding
source available. That is a factual note, not legal advice: confirm the position
before any distribution.

### Regenerating this list

Read-only, no network. With `vendor/` installed:

```bash
composer licenses
```

Or straight from the lock file, without Composer:

```bash
php -r '$l=json_decode(file_get_contents("composer.lock"),true);
foreach(["packages","packages-dev"] as $k) foreach($l[$k] as $p)
  printf("%s\t%s\n",$p["name"],implode(", ",$p["license"]??["(none declared)"]));'
```

## JavaScript dependencies

Derived from [`package.json`](package.json). `package.json` declares no licence
field for any entry and the repository commits no Node lockfile, so the
identifiers below were read from each package's own manifest; the resolved
version may differ from the cached one inspected, so re-confirm against
`node_modules/<package>/package.json` after `npm install`.
(`vite` is the one mismatch: `^6.4.3` is requested and only a 5.x manifest was
available locally; the identifier below is that package's own.)

| Package | Where | Declared licence |
| ------- | ----- | ---------------- |
| `@fast-csv/format` | dev | MIT |
| `@playwright/test` | dev | Apache-2.0 |
| `axios` | dev | MIT |
| `dotenv` | dev | BSD-2-Clause |
| `fast-csv` | dev | MIT |
| `laravel-vite-plugin` | dev | MIT |
| `vite` | dev | MIT |
| `laravel-echo` | runtime | MIT |
| `pusher-js` | runtime | MIT |

None of the identifiers found on the JavaScript side is copyleft.

## Full texts

Each dependency ships its own licence file, and that file — not this summary —
is authoritative:

- PHP: `vendor/<vendor>/<package>/LICENSE` or `LICENSE.md`
- JavaScript: `node_modules/<package>/LICENSE`, once dependencies are installed

The repository's own licence is [`LICENSE`](LICENSE); the rebrand boundary that
keeps these packages and namespaces untouched is recorded in
`.agents/context/guides/rebrand-boundary.md`.
