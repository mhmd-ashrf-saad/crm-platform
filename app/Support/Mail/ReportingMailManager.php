<?php

namespace App\Support\Mail;

use Illuminate\Mail\MailManager;

/**
 * Builds every mailer with a transport that reports its own failures.
 *
 * `build()` is the single place Laravel constructs a `Mailer` — the default mailer, every
 * named mailer, and every rebuild after `Mail::purge()` — so wrapping there covers all of
 * them, and no mail configuration has to change to get the behaviour.
 *
 * `Mail::fake()` swaps this manager out entirely, so tests that assert on mail are unaffected.
 */
class ReportingMailManager extends MailManager
{
    public function build($config)
    {
        $mailer = parent::build($config);

        $mailer->setSymfonyTransport(new ReportingTransport($mailer->getSymfonyTransport()));

        return $mailer;
    }
}
