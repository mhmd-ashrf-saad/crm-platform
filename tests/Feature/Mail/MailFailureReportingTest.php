<?php

use App\Providers\MailDeliveryServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Webkul\Admin\Notifications\Common;

/**
 * A send that cannot leave the building has to be visible.
 *
 * Krayin swallows mail failures in upstream code that this fork may not edit: six
 * `catch (\Exception $e) {}` blocks around `Mail::queue()` in
 * `packages/Webkul/Automation/src/Helpers/Entity/{Lead,Quote,Activity}.php`, which is the
 * workflow "send email to person / sales owner" action. Those files are upstream, so the
 * swallow itself cannot be removed without a core edit that collides on every `2.2` merge.
 * `app/Support/Mail/ReportingTransport` therefore fixes the consequence: the transport
 * reports the failure before re-throwing it, so a swallow still leaves an error in the log.
 *
 * These tests drive a real SMTP connection that is refused rather than a fake, because the
 * behaviour under test is what happens to the exception the transport actually throws.
 */
beforeEach(function () {
    /**
     * Loopback port 2: nothing ever listens there, so the connection is refused immediately.
     * No network, no DNS, no waiting on a timeout.
     */
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 2,
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null,
        'mail.mailers.smtp.encryption' => null,
        'mail.mailers.smtp.timeout' => 2,
    ]);

    Mail::purge();
});

it('raises the transport failure instead of turning it into a success', function () {
    /**
     * Asserted by hand rather than with `toThrow(TransportExceptionInterface::class)`:
     * Pest treats a string that is not a *class* as a message substring, and this is an
     * interface.
     */
    $thrown = null;

    try {
        Mail::raw('probe', fn ($message) => $message
            ->to('probe@example.invalid')
            ->subject('probe'));
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(TransportExceptionInterface::class);
});

it('reports the failure even when the caller swallows it, the way the workflow email action does', function () {
    $reported = [];

    $handler = Mockery::mock(ExceptionHandler::class);
    $handler->shouldIgnoreMissing();
    $handler->shouldReceive('report')->andReturnUsing(function (Throwable $e) use (&$reported) {
        $reported[] = $e;
    });

    app()->instance(ExceptionHandler::class, $handler);

    /**
     * The exact shape of packages/Webkul/Automation/src/Helpers/Entity/Lead.php:139-141:
     * a workflow email action queued inside an empty catch block.
     */
    try {
        Mail::queue(new Common([
            'to' => ['probe@example.invalid'],
            'subject' => 'Workflow email',
            'body' => 'Probe',
        ]));
    } catch (Exception $e) {
    }

    expect($reported)->toHaveCount(1);
    expect($reported[0])->toBeInstanceOf(TransportExceptionInterface::class);
});

it('logs a critical warning when production is configured with a mailer that never delivers', function () {
    config(['mail.default' => 'log']);
    app()['env'] = 'production';

    Log::spy();

    (new MailDeliveryServiceProvider(app()))->guardAgainstSilentMailer();

    Log::shouldHaveReceived('critical')
        ->withArgs(fn (string $message) => str_contains($message, 'MAIL_MAILER=log'))
        ->once();
});
