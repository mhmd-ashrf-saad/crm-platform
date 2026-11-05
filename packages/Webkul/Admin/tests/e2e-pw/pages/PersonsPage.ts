import { expect, Locator, Page } from "playwright/test";
import CoreLocators from "../locator/CoreLocators";
import { generateEmail, generateName, generatePhoneNumber } from "../utils/faker";
import { organizationData } from "../pages/OrganizationPage";
export type PersonData = {
    name: string;
    emails: string;                     // array to hold multiple emails
    contactNumber: string;             // array to hold multiple contact numbers
    jobTitle: string;
    organizationName: string;
};

/**
 * Build a person with an email and contact number unique to this call.
 *
 * Both fields are unique in Krayin, so two persons built from one shared object cannot both be
 * saved — the second submit fails with "The value has already been taken." Each spec that creates
 * a person therefore needs its own instance, while keeping that instance stable *within* the spec
 * so the create / edit / delete cases all address the same record.
 */
export function buildPersonData(overrides: Partial<PersonData> = {}): PersonData {
    return {
        name: generateName(),
        emails: generateEmail(),
        contactNumber: generatePhoneNumber(),
        jobTitle: generateName(),
        organizationName: organizationData.name,
        ...overrides,
    };
}

/**
 * Default instance kept for existing importers. Do not create a person from this in more than one
 * spec — build a fresh instance instead.
 */
export const personData: PersonData = buildPersonData();

export default class PersonsPage extends CoreLocators {
    readonly page: Page;
    readonly firstPerson:Locator;

    constructor(page: Page) {
        super(page),
            this.page = page
            this.firstPerson= page.locator(`(//div[@class="flex flex-col gap-1.5 dark:text-gray-300"])[1]`);
    }

    async navigageToPersonsPage() {
        await this.page.goto("admin/contacts/persons");
    }
    async createPerson(personData: PersonData) {

        /**
         * The Sales Owner this person is assigned to: the account the suite is signed in as, read
         * from its own account screen. Reading it visits that screen, so it happens before the form
         * is reached and the listing is re-opened afterwards.
         *
         * The picker used to be searched for the literal "Example", a name that existed only because
         * `tests/account.spec.ts` renames the signed-in administrator earlier in a full run. See
         * `currentUserName()`. It is only needed when the person is being assigned an owner, which
         * is what `organizationName` gates.
         */
        const salesOwner = personData.organizationName ? await this.currentUserName() : "";

        await this.navigageToPersonsPage();
        await this.createPersonLink.click();

        // Fill person details (use first email/phone if multiple provided)
        await this.personNameTextbox.fill(personData.name);
        if (personData.emails && personData.emails.length > 0) {
            await this.personEmailTextbox.fill(personData.emails);
        }
        if (personData.contactNumber && personData.contactNumber.length > 0) {
            await this.personPhoneTextbox.fill(personData.contactNumber);
        }
        await this.personJobTitleTextbox.fill(personData.jobTitle || "");

        // Assign organization (if provided)
        if (personData.organizationName) {
            /**
             * The lookup this used to open was the Sales Owner one (`user_id`): the old locator
             * `main .relative > div > .relative` resolved to the first lookup on the page, which is
             * Sales Owner, not Organization. The behaviour is kept deliberately - assigning the
             * Organization lookup would need an organization row to exist first, which would tie
             * this spec to another spec's record - but the picker is now addressed by name and
             * scoped to its own root instead of matching a listitem anywhere on the page.
             */
            await this.selectSalesOwner(salesOwner);
        }
        // Save person
        await this.savePersonButton.click();
        await this.searchInputExact.fill(personData.name);
        await this.page.keyboard.press('Enter');
        await expect(this.page.getByText(personData.name).first()).toBeVisible();

    }
    async updatePerson(personData: PersonData) {
        /** See `createPerson`: the owner comes from the session, not from a hardcoded name. */
        const salesOwner = personData.organizationName ? await this.currentUserName() : "";

        await this.navigageToPersonsPage();
        await this.searchInputExact.fill(personData.name);
        await this.page.keyboard.press('Enter');
        await this.firstEditIcon.click();
        // Fill person details (use first email/phone if multiple provided)
        await this.personNameTextbox.fill(personData.name);
        if (personData.emails && personData.emails.length > 0) {
            await this.personEmailTextbox.fill(personData.emails);
        }
        if (personData.contactNumber && personData.contactNumber.length > 0) {
            await this.personPhoneTextbox.fill(personData.contactNumber);
        }
        await this.personJobTitleTextbox.fill(personData.jobTitle || "");

        // Assign organization (if provided) - Sales Owner lookup, see createPerson above.
        if (personData.organizationName) {
            await this.selectSalesOwner(salesOwner);
        }
        // Save person
        await this.savePersonButton.click();
        await this.searchInputExact.fill(personData.name);
        await expect(this.page.getByText(personData.name).first()).toBeVisible();

    }
    async personDelete() {

        await this.firstDeleteIcon.click();
        await this.agreeButton.click();
        await expect(this.successMessage.first()).toBeVisible();
    }
}