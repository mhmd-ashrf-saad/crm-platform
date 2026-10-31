<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Marketing\Models\Campaign;
use Webkul\Marketing\Models\Event;

/**
 * Seeds the mail half of the demo: marketing templates, events, campaigns, and the mailbox.
 *
 * The two email templates the installer seeds ("Activity created" and "Activity modified") are
 * left exactly as they are; these are additional templates, named so the wipe can find and remove
 * them again without guessing at ids.
 */
class DemoMailSeeder
{
    public function __construct(
        protected EmailRepository $emailRepository,
    ) {}

    /**
     * Seed templates, events, campaigns and the seeded mailbox.
     *
     * @param  array<string, mixed>  $context
     */
    public function seed(array $context): void
    {
        $templates = $this->seedTemplates();

        $events = $this->seedEvents();

        $this->seedCampaigns($templates, $events);

        $this->seedMailbox($context);
    }

    /**
     * Marketing email templates.
     *
     * @return array<int, int>
     */
    protected function seedTemplates(): array
    {
        $ids = [];

        foreach (DemoFixtures::emailTemplates() as $fixture) {
            $template = EmailTemplate::query()->create([
                'name' => $fixture['name'],
                'subject' => $fixture['subject'],
                'content' => $fixture['content'],
            ]);

            $ids[] = $template->id;
        }

        return $ids;
    }

    /**
     * Marketing events.
     *
     * @return array<int, int>
     */
    protected function seedEvents(): array
    {
        $ids = [];

        foreach (DemoFixtures::marketingEvents() as $fixture) {
            $event = Event::query()->create([
                'name' => $fixture['name'],
                'description' => $fixture['description'],
                'date' => Carbon::today()->addDays($fixture['date_in_days'])->format('Y-m-d'),
            ]);

            $ids[] = $event->id;

            $this->backdate('marketing_events', $event->id);
        }

        return $ids;
    }

    /**
     * Campaigns (حملات).
     *
     * `type` and `mail_to` are NOT NULL in the schema and are deliberately not fillable on the
     * model, so an insert is the only way to write a complete row. No screen in this fork reads
     * either column — the Marketing package ships no controller — and `campaign:process` only
     * looks at `status`, the template and the event date.
     *
     * @param  array<int, int>  $templates
     * @param  array<int, int>  $events
     */
    protected function seedCampaigns(array $templates, array $events): void
    {
        $rows = [];

        foreach (DemoFixtures::campaigns() as $offset => $fixture) {
            $rows[] = [
                'name' => $fixture['name'],
                'subject' => $fixture['subject'],
                'status' => $fixture['status'],
                'type' => 'email',
                'mail_to' => 'persons',
                'spooling' => $fixture['spooling'],
                'marketing_template_id' => $templates[$fixture['template']],
                'marketing_event_id' => $fixture['event'] === null ? null : $events[$fixture['event']],
                'created_at' => Carbon::today()->subDays(6 + $offset * 4),
                'updated_at' => Carbon::today()->subDays(6 + $offset * 4),
            ];
        }

        DB::table('marketing_campaigns')->insert($rows);
    }

    /**
     * The seeded mailbox: threads on leads and contacts, one sent message and one draft.
     *
     * @param  array<string, mixed>  $context
     */
    protected function seedMailbox(array $context): void
    {
        $accountName = config('mail.from.name') ?: config('app.name');
        $accountAddress = config('mail.from.address');

        foreach (DemoFixtures::emailThreads() as $threadIndex => $thread) {
            $personId = $thread['person'] === null ? null : $context['persons'][$thread['person']];
            $leadId = $thread['lead'] === null ? null : $context['leads'][$thread['lead']];

            $person = $thread['person'] === null ? null : DemoFixtures::persons()[$thread['person']];

            $rootId = null;

            foreach ($thread['messages'] as $offset => $message) {
                $incoming = $message['direction'] === 'in';

                $folder = $message['folder'] ?? ($incoming ? 'inbox' : 'sent');

                $from = $incoming ? $person['email'] : $accountAddress;

                $name = $incoming ? $person['name'] : $accountName;

                $messageId = "demo-{$threadIndex}-{$offset}@".DemoIdentity::RESERVED_EMAIL_DOMAIN;

                $email = $this->emailRepository->create([
                    'subject' => $message['subject'],
                    'source' => 'email',
                    'user_type' => $incoming ? 'person' : 'admin',
                    'name' => $name,
                    'from' => $from,
                    'sender' => $from,
                    'reply_to' => [$from],
                    'cc' => [],
                    'bcc' => [],
                    'folders' => [$folder],
                    'reply' => $message['body'],
                    /**
                     * Only incoming mail can be unread: the read flag drives the unread badge on
                     * the inbox, and a message this installation sent is never unread.
                     */
                    'is_read' => $incoming ? ($message['is_read'] ?? 1) : 1,
                    'unique_id' => $messageId,
                    'message_id' => $messageId,
                    'person_id' => $personId,
                    'lead_id' => $leadId,
                    'parent_id' => $rootId,
                    'created_at' => Carbon::now()->subDays($message['days_ago'])->setTime(10 + $offset, 15),
                    'updated_at' => Carbon::now()->subDays($message['days_ago'])->setTime(10 + $offset, 15),
                ]);

                $rootId ??= $email->id;
            }
        }

        $this->tagEmails($context);
    }

    /**
     * Tag a couple of threads so the mail list's tag filter has something to show.
     *
     * @param  array<string, mixed>  $context
     */
    protected function tagEmails(array $context): void
    {
        $tagId = $context['tags']['متابعة عاجلة'];

        $emailIds = DB::table('emails')
            ->whereIn('subject', [
                'استفسار عن خصم التعاقد لثلاث سنوات',
                'مشكلة في تصدير عروض الأسعار',
            ])
            ->pluck('id');

        $rows = [];

        foreach ($emailIds as $emailId) {
            $rows[] = ['tag_id' => $tagId, 'email_id' => $emailId];
        }

        if ($rows !== []) {
            DB::table('email_tags')->insert($rows);
        }
    }

    /**
     * Move a row's timestamps without firing model events.
     */
    protected function backdate(string $table, int $id): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => Carbon::today()->subDays(12),
            'updated_at' => Carbon::today()->subDays(12),
        ]);
    }
}
