
import { Page, Locator, FrameLocator, expect } from '@playwright/test';

import CoreLocators from '../locator/CoreLocators';
import { generateDescription, generateEmail, generateName } from '../utils/faker';

export type MailData = {
    replyTo: string;
    subject: string;
    body: string;
};
/**
 * `generateName()` draws from a 12x12 word list, so it repeats a subject roughly every dozen runs
 * and the mailbox already holds several copies of each. Every step here searches by subject and
 * acts on the first match, so a repeated subject makes a test trash or delete some earlier run's
 * mail and then fail to find its own — which is what made the trash tests flaky rather than
 * broken. The run suffix makes each run's subject its own.
 *
 * The same subject is deliberately reused by every case in a file, which is safe because each case
 * searches inside one folder and selects every same-subject row. A draft that silently failed to
 * save is a different fault: it leaves the folder empty rather than showing someone else's mail.
 */
export const mailData: MailData = {
    replyTo: generateEmail(),
    subject: `${generateName()} ${Date.now().toString().slice(-6)}${Math.floor(Math.random() * 1000)}`,
    body: generateDescription().slice(0, 50),
};
export type emailType = 'bcc' | 'cc' | 'normal'|'both';
export class MailPage extends CoreLocators {
    readonly page: Page;
    readonly composeEmailButton: Locator;
    readonly replyToInput: Locator;
    readonly subjectInput: Locator;
    readonly sendButton: Locator;
    readonly successMessage: Locator;
    readonly editorFrame: FrameLocator;
    readonly saveDraftButton: Locator;
    readonly moveToTrashButton: Locator;
    readonly ccMailInput: Locator;
    readonly bccMailInput: Locator;
    readonly ccButton: Locator;
    readonly bccButton: Locator;

    constructor(page: Page) {
        super(page);
        this.page = page;
        this.composeEmailButton = page.getByRole('button', { name: 'Compose Mail' });
        this.replyToInput = page.getByLabel(/reply to/i).or(page.locator('input[name="temp-reply_to"]'));
        this.subjectInput = page.getByLabel(/subject/i).or(page.locator('input[name="subject"]'));
        this.sendButton = page.getByRole("button", { name: "Send" });
        this.successMessage = page.getByText("Email sent successfully.");
        this.editorFrame = page.frameLocator("iframe.tox-edit-area__iframe");
        this.saveDraftButton = page.getByRole("button", { name: "Draft" });
        this.moveToTrashButton = page.getByRole('button', { name: 'Moved To Trash' })
        this.ccMailInput = page.locator('input[name="temp-cc"]');
        this.bccMailInput = page.locator('input[name="temp-bcc"]');
        this.ccButton = page.getByText('CC', { exact: true });
        this.bccButton = page.getByText('BCC', { exact: true });
    }

    async navigateToMailInboxPage() {
        await this.page.goto("admin/mail/inbox");
    }
    async navigateToMailDraftPage() {
        await this.page.goto("admin/mail/draft");
    }
    async navigateToMailTrashedPage() {
        await this.page.goto("admin/mail/trash");
    }
    async composeEmail(mailData: MailData, type: emailType) {
        await this.composeEmailButton.click();
        await this.page.waitForTimeout(500);

        if (type === 'cc') {
            await this.ccButton.click();
            await this.ccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.ccMailInput.fill("cc" + mailData.replyTo);
        } else if (type === 'bcc') {
            await this.bccButton.click();
            await this.bccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.bccMailInput.fill("bcc" + mailData.replyTo);
        } else if (type === 'both') {
            await this.ccButton.click();
            await this.bccButton.click();
            await this.bccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.ccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.ccMailInput.fill("cc" + mailData.replyTo);
            await this.bccMailInput.fill("bcc" + mailData.replyTo);
        }

        await this.replyToInput.fill(mailData.replyTo);
        await this.subjectInput.fill(mailData.subject);

        await this.fillMailBody(mailData.body);

        await this.sendButton.click();
    }

    /**
     * Write the composer's message body, then wait until the form itself carries it.
     *
     * Two separate windows make the composer lose a body, and both are closed here.
     *
     * 1. TinyMCE creates its iframe before it has finished initializing. A `fill()` that lands in
     *    that window is adopted by the body but never by the editor — the body keeps the raw string,
     *    `getContent()` normalizes it, and no `change`/`keyup` fires — so the field stays empty. The
     *    editor's own `initialized` flag is the state before a user can type into it at all.
     * 2. `fill()` on a contenteditable emits an `input` event, but Krayin's `v-tinymce` copies the
     *    editor content back into the VeeValidate `reply` field on `change`, `paste` and `keyup`
     *    only. So even in a fully initialized editor the field still holds its previous value, and
     *    the sync the editor's blur triggers races the submit handler the same click runs. When the
     *    submit won, VeeValidate rejected the still-empty `reply` as required, the axios POST in
     *    `save()` was never issued, and the draft silently did not exist.
     *
     * The key event makes the field agree with the editor the way real typing does; the textarea is
     * the field's DOM mirror and what `new FormData(mailForm)` submits, so waiting on it is waiting
     * on exactly the value the request would carry.
     */
    async fillMailBody(body: string) {
        await this.page.waitForFunction(() => {
            const tinymce = (window as any).tinymce;

            const editor = tinymce?.get?.('reply') ?? tinymce?.activeEditor;

            return Boolean(editor?.initialized);
        });

        const editorBody = this.editorFrame.locator('body');

        await editorBody.fill(body);

        await editorBody.press('End');

        await expect(this.page.locator('textarea[name="reply"]')).not.toHaveValue('');
    }
    
    async saveDraftEmail(mailData: MailData, type: emailType) {
        await this.composeEmailButton.click();
        await this.page.waitForTimeout(500);

        if (type === 'cc') {
            await this.ccButton.click();
            await this.ccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.ccMailInput.fill("cc" + mailData.replyTo);
        } else if (type === 'bcc') {
            await this.bccButton.click();
            await this.bccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.bccMailInput.fill("bcc" + mailData.replyTo);
        } else if (type === 'both') {
            await this.ccButton.click();
            await this.bccButton.click();
            await this.ccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.ccMailInput.fill("cc" + mailData.replyTo);


            await this.bccMailInput.waitFor({ state: 'visible', timeout: 5000 });
            await this.bccMailInput.fill("bcc" + mailData.replyTo);
        }

        await this.replyToInput.fill(mailData.replyTo);
        await this.subjectInput.fill(mailData.subject);

        await this.fillMailBody(mailData.body);

        /**
         * Armed before the click and awaited before navigating away: the draft exists only once the
         * composer has actually POSTed, and navigating first turned a submit that never happened
         * into a confusing "no records" failure on the Draft page instead of a failure here.
         */
        const draftSaved = this.page.waitForResponse(
            (response) =>
                response.request().method() === 'POST'
                && response.url().endsWith('/admin/mail/create')
                && response.ok(),
            { timeout: 15000 }
        );

        await this.saveDraftButton.click();

        await draftSaved;

        await this.navigateToMailDraftPage();
        await this.searchEmail(mailData.subject);
        await expect(this.page.getByRole('paragraph').filter({ hasText: mailData.subject }).first()).toBeVisible();
    }
    async movedEmailToTrash() {
        /**
         * The caller has just searched, so wait for the checkbox the reloaded grid renders rather
         * than for a fixed delay — clicking it mid-reload selects nothing.
         */
        await expect(this.multiSelectCheckbox).toBeVisible();

        await this.multiSelectCheckbox.click();

        await this.moveToTrashButton.click();

        await this.agreeButton.click();

        await expect(this.page.getByText(/deleted successfully/i).first()).toBeVisible();
    }
    async searchEmail(subject: string) {
        await this.searchInputExact.fill(subject);
        await this.page.keyboard.press('Enter');
    }
    async deleteEmailFromTrash(subject: string) {
        await this.searchEmail(subject);

        /**
         * Wait for the row itself rather than a fixed delay: the grid reloads over XHR after the
         * search, and clicking the select-all checkbox before it settles selects nothing, so the
         * delete then applies to no record and the caller's assertion fails for the wrong reason.
         */
        await expect(
            this.page.getByRole('paragraph').filter({ hasText: subject }).first()
        ).toBeVisible();

        await this.multiSelectCheckbox.click();

        await this.deleteButton.click();

        await this.agreeButton.click();

        /**
         * The delete is confirmed before the caller asserts the row is gone, so the assertion is
         * not racing the request that removes it.
         */
        await expect(this.page.getByText(/deleted successfully/i).first()).toBeVisible();
    }

}