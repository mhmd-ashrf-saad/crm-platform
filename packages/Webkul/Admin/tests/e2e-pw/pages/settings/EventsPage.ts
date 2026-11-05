import { Page, Locator, expect } from '@playwright/test';
import { SettingsPage } from '../SettingsPage';
import { generateDescription, generateFullName, generateName, generateRandomDateTime } from '../../utils/faker';

export type EventData = {
    name: string,
    description: string,
    date: string
}
export const eventdata: EventData = {
    name: generateFullName(),
    description: generateDescription(),
    date: "2027-11-18",
}

/**
 * Build an event unique to this call.
 *
 * The shared `eventdata` above is written by the create case and read by the update and delete cases
 * that follow it, so a spec that needs an event of its own must not borrow it — deleting the shared
 * record from a fourth case would empty it for every later case in the same file. `generateName()`
 * is unique within the process, which is what the mass-delete case needs: `massDelete(name)` narrows
 * the listing to one name, and a name two events share would tick two records.
 *
 * The date is always in the future because the form validates `after_or_equal:today`; a fixed date
 * would start failing on its own once it passed.
 */
export function buildEventData(overrides: Partial<EventData> = {}): EventData {
    const inThirtyDays = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000);

    return {
        name: `Mass Delete Event ${generateName()}`,
        description: generateDescription(),
        date: inThirtyDays.toISOString().slice(0, 10),
        ...overrides,
    };
}

export class EventsPage extends SettingsPage {
    readonly page: Page;
    readonly createEventButton: Locator;
    readonly nameInput: Locator;
    readonly descriptionTextarea: Locator;
    readonly dateInput: Locator;
    readonly saveEventButton: Locator;
    readonly editFirstEventButton: Locator;
    readonly deleteFirstEventButton: Locator;
    readonly confirmDeleteButton: Locator;
    readonly successMessage: Locator;

    constructor(page: Page) {
        super(page);
        this.page = page;
        this.createEventButton = page.getByRole('button', { name: 'Create Event' });
        this.nameInput = page.locator('input[name="name"]');
        this.descriptionTextarea = page.locator('textarea[name="description"]');
        this.dateInput = page.locator('input[name="date"]');
        this.saveEventButton = page.getByRole('button', { name: 'Save Event' });
        this.editFirstEventButton = page.locator('.row > div:nth-child(6) > a').first();
        this.deleteFirstEventButton = page.locator('div:nth-child(6) > a:nth-child(2)').first();
        this.confirmDeleteButton = page.getByRole('button', { name: 'Agree', exact: true });
        this.successMessage = this.flash(/Event (created|updated|deleted) successfully./);
    }

    async createEvent(eventdata: EventData) {

        await this.createEventButton.click();
        await this.nameInput.fill(eventdata.name);
        await this.descriptionTextarea.fill(eventdata.name);
        await this.dateInput.fill(eventdata.date);
        await this.saveEventButton.click();
        await expect(this.successMessage).toBeVisible();

    }
    /**
     * Mass-delete exactly one event, named by the caller.
     *
     * This used to tick the grid header's select-all and then assert the listing had become empty.
     * Both halves were the same defect seen twice: the header selects every record on the page, and
     * the mass action posts every id it accumulated, so the case removed the whole listing rather
     * than the one event under test — measured on a scratch copy, the events page held two records
     * and the select-all ticked both, and on a listing holding three it ticked all three.
     * "No records available" then passed only when every record happened to be deletable:
     * `EventController::massDestroy()` refuses an event that still carries campaigns, so on a scratch
     * copy keeping the demo events the case deleted the single unattached record and then failed its
     * own assertion with the listing still populated — a failure that said nothing about whether it
     * had chosen the right record.
     *
     * The shared `CoreLocators.massDelete()` is what the organization and person specs now use:
     * narrow the listing to the one name, tick that row's own checkbox, assert exactly one record is
     * selected (counted by id, because the grid renders each record twice — desktop row and mobile
     * card), delete, and assert that record's row is gone.
     */
    async massDeleteEvents(name: string) {
        await this.massDelete(name);
    }

    /**
     * The record ids the events listing is showing, read from the grid's own mass-action checkboxes.
     *
     * Used to prove that a mass delete removed the record under test and nothing else: the ids are
     * compared before and after, which is a statement about records rather than about how many rows
     * happen to be visible.
     *
     * Two details of the markup it reads:
     *
     * - The checkbox names and values are Vue bindings (`:name`, `:value`), so they only exist once
     *   the application has mounted. `#app[data-vue-ready]` is set by the hydration gate in
     *   `fixtures/AdminFixtures.ts` at exactly that moment, and is the one signal that does not
     *   depend on how many rows the listing holds.
     * - The grid renders every record twice — a desktop row and a mobile card, both bound to the same
     *   checkbox state — so the raw input list repeats each id and is de-duplicated here.
     */
    async eventRecordIds(): Promise<string[]> {
        await expect(this.page.locator("#app[data-vue-ready]")).toBeAttached();

        const massActionCheckboxes = this.page.locator('input[name^="mass_action_select_record_"]');

        /**
         * Mounted is not loaded: the grid fetches its page after mounting, and until it answers, the
         * body is the shimmer skeleton — which is markup full of empty `div.row` elements. Counting
         * those as "rows" reported a loaded grid that had no records at all. The mass-action
         * checkboxes exist only on rendered records, and the empty-state placeholder only once the
         * grid has answered with none, so waiting for one or the other is what actually means
         * "loaded". The wait is bounded by the expectation timeout, so a grid that never settles fails
         * here rather than returning a misleading empty list.
         */
        await expect
            .poll(
                async () =>
                    (await massActionCheckboxes.count()) > 0 || (await this.noRecordsAvailable.count()) > 0,
                {
                    message:
                        "the events grid must finish loading before its record ids are read: its mass-action " +
                        "checkboxes (or its empty state) only exist once the records have been fetched",
                }
            )
            .toBe(true);

        return massActionCheckboxes.evaluateAll((inputs) => [
            ...new Set(inputs.map((input) => (input as HTMLInputElement).value)),
        ]);
    }
    async editEvents(eventData: EventData) {

        await this.firstEditIcon.click();
        await this.nameInput.fill(eventData.name);
        await this.descriptionTextarea.fill(eventData.name);
        await this.dateInput.fill(eventData.date);
        await this.saveEventButton.click();
        await expect(this.successMessage).toBeVisible();
    }
    async deleteEvent() {
        await this.deleteFirstEventButton.click();
        await this.agreeButton.click();
        await expect(this.successMessage).toBeVisible();

    }
    async eventSearch(name: string) {
        await this.searchInputExact.fill(name);
        await this.page.keyboard.press('Enter');

    }



}
