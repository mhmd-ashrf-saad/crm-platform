import path from "path";
import { fileURLToPath } from "url";
import type { Page } from "@playwright/test";
import { expect, test } from "../../fixtures/AdminFixtures";
import { DataTransferPage } from "../../pages/settings/DataTransferPage";
import OrganizationPage, { buildOrganizationData } from "../../pages/OrganizationPage";
import PersonsPage, { buildPersonData } from "../../pages/PersonsPage";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const csvFileName = {
    leads: 'leads.csv',
    updatedLeads: 'leads_updated.csv',
    persons: 'persons.csv',
    updatedPersons: 'persons_updated.csv',
    products: 'products.csv',
    updatedProducts: 'products_updated.csv',
}

const csvPath = (name: string) => path.resolve(__dirname, `../../data/data-transfer/${name}`);

/**
 * Create the person a lead CSV's `person_id` column can point at, and answer its name.
 *
 * Every case that needs one builds its own. The three lead cases used to share a describe-scoped
 * `personId` that only the create case wrote, so a failure there left update and delete pointing at
 * whatever the list happened to contain. Email and contact number are unique in Krayin, so the
 * instance is built per call rather than reused.
 */
async function importablePerson(adminPage: Page): Promise<string> {
    const person = new PersonsPage(adminPage);

    await person.navigageToPersonsPage();
    await person.createPerson(buildPersonData());

    return (await person.firstPerson.textContent()) ?? '';
}

/**
 * Create the organization a person CSV's `organization_id` column can point at, and answer its name.
 * Same reasoning as `importablePerson`.
 */
async function importableOrganization(adminPage: Page): Promise<string> {
    const organization = new OrganizationPage(adminPage);

    await organization.navigateToOrganization();
    await organization.createOrganization(buildOrganizationData());

    return (await organization.firstOrganization.textContent()) ?? '';
}

test.describe("data transfer product management", () => {
    test("verify create import product",async({adminPage})=>{
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(csvPath(csvFileName.products),'products');
    }
    )
    test("verify update import product",async({adminPage})=>{
        const dataTransferPage=new DataTransferPage(adminPage );

        /**
         * Its own import: `updateImport` edits the first row of the list, which used to be whatever
         * the create case had left behind.
         */
        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(csvPath(csvFileName.products),'products');

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.updateImport(csvPath(csvFileName.updatedProducts));
        await expect(dataTransferPage.successMessage.first()).toBeVisible();
    }
    )
    test("verify delete import product",async({adminPage})=>{
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(csvPath(csvFileName.products),'products');

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.deleteImport();
        await expect(dataTransferPage.successMessage.first()).toBeVisible();
    }
    )
})


test.describe("data transfer lead management", () => {
    test("verify create import lead",async({adminPage})=>{
        const leadCsv = csvPath(csvFileName.leads);
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.updateCsv(leadCsv, 'person_id', await importablePerson(adminPage));

        /**
         * The `product` column carries its own `id=`, which the importer validates against the
         * products table, so it is pointed at a product that exists on this installation.
         */
        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.updateCsvProductId(leadCsv, await dataTransferPage.firstProductId());
        await dataTransferPage.createImport(leadCsv,'leads');
    }
    )
    test("verify update import lead",async({adminPage})=>{
        const updatedLeadCsv = csvPath(csvFileName.updatedLeads);
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(csvPath(csvFileName.leads),'leads');

        await dataTransferPage.updateCsv(updatedLeadCsv, 'person_id', await importablePerson(adminPage));

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.updateCsvProductId(updatedLeadCsv, await dataTransferPage.firstProductId());
        await dataTransferPage.updateImport(updatedLeadCsv);
        await expect(dataTransferPage.successMessage.first()).toBeVisible();
    }
    )
    test("verify delete import lead",async({adminPage})=>{
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(csvPath(csvFileName.leads),'leads');

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.deleteImport();
        await expect(dataTransferPage.successMessage.first()).toBeVisible();
    }
    )
})

test.describe("data transfer person management", () => {
    test("verify create import person",async({adminPage})=>{
        const personCsv = csvPath(csvFileName.persons);
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.updateCsv(personCsv, 'organization_id', await importableOrganization(adminPage));

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(personCsv,'persons');
    }
    )
    test("verify update import person",async({adminPage})=>{
        const updatedPersonCsv = csvPath(csvFileName.updatedPersons);
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(csvPath(csvFileName.persons),'persons');

        await dataTransferPage.updateCsv(updatedPersonCsv, 'organization_id', await importableOrganization(adminPage));

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.updateImport(updatedPersonCsv);
        await expect(dataTransferPage.successMessage.first()).toBeVisible();
    }

    )
    test("verify delete import person",async({adminPage})=>{
        const dataTransferPage=new DataTransferPage(adminPage );

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.createImport(csvPath(csvFileName.persons),'persons');

        await dataTransferPage.navigateToDataTransfer();
        await dataTransferPage.deleteImport();
        await expect(dataTransferPage.successMessage.first()).toBeVisible();
    }
    )
})
