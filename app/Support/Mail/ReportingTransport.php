<?php

namespace App\Support\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps a mail transport so a failed send can never disappear.
 *
 * Krayin sends mail from places that swallow the exception outright — six empty
 * `catch (\Exception $e) {}` blocks around `Mail::queue()` in
 * `packages/Webkul/Automation/src/Helpers/Entity/{Lead,Quote,Activity}.php`, which is the
 * workflow "send email to person" / "send email to sales owner" action, and the same shape
 * around the new-user notification. Those files are upstream, so the swallow cannot be
 * removed here without a core edit that collides on every `2.2` merge.
 *
 * This decorator fixes the consequence rather than the symptom. A failure is reported
 * before it is re-thrown, which means:
 *
 *   - a caller that does not swallow still sees the exception, unchanged, and
 *   - a caller that swallows has already put the transport's own error text (Brevo's
 *     `535`/`550` response, the connection refusal, the TLS failure) into the application
 *     log at error level, where an operator can find it.
 *
 * Before it existed, a broken SMTP server produced no log line, no failed job and no
 * session flash from the workflow path: the mail was simply never sent and nothing said so.
 *
 * The message is passed through untouched; nothing here inspects or rewrites it.
 */
class ReportingTransport implements TransportInterface
{
    public function __construct(protected TransportInterface $transport) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            return $this->transport->send($message, $envelope);
        } catch (\Throwable $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * Keeps the transport's own description (`smtp://smtp-relay.brevo.com:587`), so an error
     * that prints the mailer still names the server it tried.
     */
    public function __toString(): string
    {
        return (string) $this->transport;
    }
}
