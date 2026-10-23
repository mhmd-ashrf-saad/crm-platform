<?php

namespace App\Providers;

use App\Support\Mail\ReportingMailManager;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Makes outgoing mail loud.
 *
 * The deployment this fork runs could not deliver mail and said nothing about it: the
 * development `.env` pointed at a `mailhog` container that was never created, and the
 * production template shipped `MAIL_MAILER=log`, where every message is written to the log
 * file and delivered to nobody — a driver that cannot fail, so nothing failed. On top of
 * that, the workflow paths that send mail (`packages/Webkul/Automation/src/Helpers/Entity/*`)
 * swallow the exception in an empty catch block, so even a real SMTP failure left no trace.
 *
 * Two things are fixed here, both outside `packages/` so the `2.2` merge stays clean:
 *
 *  1. Every mailer is built with a transport that reports a failed send before re-throwing
 *     it ({@see ReportingTransport}), so no catch block anywhere can hide it.
 *  2. A production instance configured with a driver that never delivers says so once per
 *     boot, at critical level, instead of silently accepting every message.
 *
 * This provider does not change how mail is sent, and it is deliberately not fatal: a
 * misconfigured mailer must not take the CRM down, it must be impossible to miss.
 */
class MailDeliveryServiceProvider extends ServiceProvider
{
    /**
     * Drivers that accept a message and deliver it nowhere. `log` writes it to the log file,
     * `array` holds it in memory for the life of the request, `null` throws it away.
     */
    protected const DEAD_END_DRIVERS = ['log', 'array', 'null'];

    public function register(): void
    {
        /**
         * The manager is a singleton, so this is also applied when it was resolved before this
         * provider registered: the container runs the callback immediately in that case.
         */
        $this->app->extend(
            'mail.manager',
            fn (MailManager $manager, $app) => new ReportingMailManager($app)
        );
    }

    public function boot(): void
    {
        $this->guardAgainstSilentMailer();
    }

    /**
     * Say, once, that this instance cannot send mail.
     *
     * Production only: a developer who deliberately logs mail instead of sending it is not
     * misconfigured, but a customer-facing instance in that state is not delivering password
     * resets and never will until someone reads this line.
     */
    public function guardAgainstSilentMailer(): void
    {
        $driver = config('mail.default');

        if (! in_array($driver, static::DEAD_END_DRIVERS, true) || ! $this->app->environment('production')) {
            return;
        }

        Log::critical(
            "mail: MAIL_MAILER={$driver} on a production instance sends nothing. Every notification, "
            .'password reset and workflow email is written to the log and delivered to nobody, and no '
            .'send can fail because there is no transport to fail. Set MAIL_MAILER=smtp with the '
            .'MAIL_HOST/MAIL_PORT/MAIL_ENCRYPTION/MAIL_USERNAME/MAIL_PASSWORD of your provider '
            .'(see deploy/.env.deploy.example), then prove it with: make mail-test MAIL_TO=you@example.com'
        );
    }
}
