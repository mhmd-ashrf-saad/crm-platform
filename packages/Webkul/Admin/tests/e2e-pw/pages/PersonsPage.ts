import { expect, Locator, Page } from "playwright/test";
import CoreLocators from "../locator/CoreLocators";
import { generateEmail, generateFullName, generateName, generatePhoneNumber } from "../utils/faker";
import { organizationData } from "../pages/OrganizationPage";
export type PersonData = {
    name: string;
    emails: string;                     // array to hold multiple emails
    contactNumber: string;             // array to hold multiple contact numbers
    jobTitle: string;
    salesOwnerId: string;                 // id or value representing Sales Owner
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
        salesOwnerId: "1", // Example sales owner id
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
            await this.lookupTrigger("user_id").click();
            await this.lookupSearch("user_id").fill("Example");
            await this.lookupOption("user_id", "Example").click();
        }
        // Save person
        await this.savePersonButton.click();
        await this.searchInputExact.fill(personData.name);
        await this.page.keyboard.press('Enter');
        await expect(this.page.getByText(personData.name).first()).toBeVisible();

    }
    async updatePerson(personData: PersonData) {
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
            await this.lookupTrigger("user_id").click();
            await this.lookupSearch("user_id").fill("Example");
            await this.lookupOption("user_id", "Example").click();
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
    async personMassDelete() {
        await this.multiSelectCheckbox.click();
        await this.deleteButton.click();
        await this.agreeButton.click();
        await expect(this.successMessage.first()).toBeVisible();
    }



}