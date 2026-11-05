import { test } from "../fixtures/AdminFixtures";
import PersonsPage, { buildPersonData } from "../pages/PersonsPage";

test.describe("person managment", () => {
    /**
     * This spec builds its own person rather than importing the shared `personData`. Email and
     * contact number are unique in Krayin, and the lead and quote specs create a person too — with
     * one shared object all three submit the same email and the later ones fail validation with
     * "The value has already been taken."
     *
     * The instance is created once for the whole describe so that create, edit and delete all
     * address the same record.
     */
    const personData = buildPersonData();

    test("verify create person", async ({ adminPage }) => {
        const personPage = new PersonsPage(adminPage);

        await personPage.navigageToPersonsPage();
        await personPage.createPerson(personData);
    })

    test('verify edit person', async ({ adminPage }) => {
        const person = new PersonsPage(adminPage);

        await person.navigageToPersonsPage();
        await person.updatePerson(personData);
    })

    test('verify person delete', async ({ adminPage }) => {
        const person = new PersonsPage(adminPage);

        await person.navigageToPersonsPage();
        await person.searchByName(personData.name);
        await person.personDelete();
    })

    test('verify mass delete person', async ({ adminPage }) => {
        const person = new PersonsPage(adminPage);

        /**
         * A person of its own: the record created above has just been deleted, but reusing its
         * email would still collide if the delete case is skipped or run out of order.
         */
        const massDeletePersonData = buildPersonData();

        await person.navigageToPersonsPage();
        await person.createPerson(massDeletePersonData);

        /**
         * Mass-deleting this record specifically, through the shared `CoreLocators.massDelete()` the
         * organization spec uses. `PersonsPage.personMassDelete()` — which ticked the grid header's
         * select-all and asserted nothing but the flash — has been deleted rather than fixed: a
         * one-line wrapper around `massDelete(name)` would only duplicate it.
         *
         * The header selects what the page is showing, not the record under test. Measured on a
         * scratch copy, that checkbox ticked all ten rows of an unfiltered persons listing, and the
         * mass action deleted the eight of them the product allowed: two were refused, but nine of
         * the ten were records no case had created. The old case survived it only by accident —
         * `createPerson` leaves the listing filtered to the new name, so the header happened to tick
         * a single row — and it asserted nothing about which record was chosen, so a wrong record, or
         * two persons sharing a name, would have passed just as quietly.
         *
         * `massDelete()` narrows the listing to this name itself, ticks that row's own checkbox,
         * asserts that exactly one record id is selected (counted by id, since the grid renders each
         * record twice — desktop row and mobile card), deletes, and asserts the row is gone.
         */
        await person.massDelete(massDeletePersonData.name);
    })
})
