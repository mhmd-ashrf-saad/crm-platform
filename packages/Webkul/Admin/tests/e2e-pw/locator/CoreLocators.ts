import { expect, FrameLocator, Locator, Page } from "playwright/test";

type ElementType = "button" | "textbox" | "link";

export default class CoreLocators {
    readonly page: Page;

    // Person Locators
    readonly createPersonLink: Locator;
    readonly savePersonButton: Locator;
    readonly personNameTextbox: Locator;
    readonly personEmailTextbox: Locator;
    readonly personPhoneTextbox: Locator;
    readonly personJobTitleTextbox: Locator;

    // Links and buttons
    readonly createOrgLink: Locator;
    readonly saveOrganizationButton: Locator;

    // Textboxes
    readonly orgNameTextbox: Locator;
    readonly orgCityTextbox: Locator;
    readonly orgPostcodeTextbox: Locator;

    // Textareas
    readonly orgAddressTextarea: Locator;

    // Select dropdowns
    readonly orgCountryCombobox: Locator;
    readonly orgStateSelect: Locator;

    // Buttons
    readonly saveQuoteButton: Locator;
    readonly saveProductButton: Locator;
    readonly confirmDeleteButton: Locator;
    readonly saveLeadButton: Locator;
    readonly sendEmailButton: Locator;
    readonly saveFileButton: Locator;
    readonly saveNoteButton: Locator;
    readonly saveActivityButton: Locator;
    readonly signInButton: Locator;
    readonly createEventButton: Locator;
    readonly saveEventButton: Locator;
    readonly agreeButton: Locator;
    readonly createTypeButton: Locator;
    readonly saveTypeButton: Locator;
    readonly createGroupButton: Locator;
    readonly saveGroupButton: Locator;
    readonly createUserButton: Locator;
    readonly saveUserButton: Locator;
    readonly composeMailButton: Locator;
    readonly createOrganizationButton: Locator;
    readonly deleteButton: Locator;

    // Links
    readonly createQuoteLink: Locator;
    readonly createProductLink: Locator;
    readonly createLeadLink: Locator;
    readonly signOutLink: Locator;
    readonly createOrganizationLink: Locator;

    // Textboxes - single line text input fields
    readonly subjectTextbox: Locator;
    readonly descriptionTextbox: Locator;
    readonly searchInputField: Locator;
    readonly productNameTextbox: Locator;
    readonly productPriceInput: Locator;
    readonly productQuantityInput: Locator;
    readonly productSkuInput: Locator;
    readonly leadTitleInput: Locator;
    readonly leadValueInput: Locator;
    readonly leadProductSelect: Locator;
    readonly emailReplyToInput: Locator;
    readonly emailSubjectInput: Locator;
    readonly fileTitleInput: Locator;
    readonly fileNameInput: Locator;
    readonly noteCommentTextarea: Locator;
    readonly activityTitleInput: Locator;
    readonly activityLocationInput: Locator;
    readonly eventNameInput: Locator;
    readonly typeNameInput: Locator;
    readonly groupNameInput: Locator;
    readonly userNameInput: Locator;
    readonly userEmailInput: Locator;
    readonly userPasswordInput: Locator;
    readonly userConfirmPasswordInput: Locator;
    readonly organizationNameInput: Locator;
    readonly organizationCityTextbox: Locator;
    readonly organizationAddressTextarea: Locator;
    readonly organizationPostcodeTextbox: Locator;
    readonly organizationCountryCombobox: Locator;
    readonly organizationStateSelect: Locator;

    readonly addPersonButton: Locator;

    // Textboxes - date or specialized text input
    readonly expiredAtTextbox: Locator;
    readonly leadExpectedCloseDateInput: Locator;
    readonly activityScheduleFromInput: Locator;
    readonly activityScheduleToInput: Locator;
    readonly eventDateInput: Locator;

    // Textareas - multi-line input fields
    readonly billingAddressTextarea: Locator;
    readonly shippingAddressTextarea: Locator;
    readonly productDescriptionTextarea: Locator;
    readonly leadDescriptionTextarea: Locator;
    readonly emailReplyTextarea: Locator;
    readonly fileCommentTextarea: Locator;
    readonly noteTextarea: Locator;
    readonly activityCommentTextarea: Locator;
    readonly eventDescriptionTextarea: Locator;
    readonly groupDescriptionTextarea: Locator;

    // Inputs - single line, no explicit role
    readonly billingCityInput: Locator;
    readonly billingPostcodeInput: Locator;
    readonly shippingCityInput: Locator;
    readonly shippingPostcodeInput: Locator;
    readonly leadEmailInput: Locator;
    readonly leadPhoneNumberInput: Locator;

    // Select dropdowns
    readonly salesOwnerSelect: Locator;
    readonly billingCountrySelect: Locator;
    readonly billingStateSelect: Locator;
    readonly shippingCountrySelect: Locator;
    readonly shippingStateSelect: Locator;
    readonly leadSourceSelect: Locator;
    readonly leadTypeSelect: Locator;
    readonly leadUserSelect: Locator;
    readonly leadSourceLabelSelect: Locator;
    readonly leadOrganizationSelect: Locator;
    readonly userRoleSelect: Locator;
    readonly userViewPermissionSelect: Locator;
    readonly userGroupListbox: Locator;
    readonly leadProductAddMoreButton: Locator;
    readonly quoteLinkToLeadButton: Locator;

    // Search related list item selector
    readonly personListItem: (name: string) => Locator;

    // Lead form - person search dropdown
    readonly leadPersonSearchInput: Locator;

    // Lead form - product search dropdown
    readonly leadProductSearchInput: Locator;

    readonly quoteAddPersonButton: Locator;
    readonly addAsNewButton: Locator;
    readonly quoteSelectListPerson: Locator;

    // Edit and delete icons
    readonly firstEditIcon: Locator;
    readonly firstDeleteIcon: Locator;
    // Multi select checkbox
    readonly multiSelectCheckbox: Locator;

    // Activity modal selectors
    readonly addActivityButton: Locator;
    readonly activityCallOption: Locator;
    readonly activityMeetingOption: Locator;
    readonly activityLunchOption: Locator;

    // Login fields
    readonly emailPlaceholderInput: Locator;
    readonly passwordPlaceholderInput: Locator;
    readonly megaSearchPlaceholder: Locator;

    // Lead
    readonly createLeadButton: Locator;

    // Lead form
    readonly titleInput: Locator;
    readonly descriptionTextarea: Locator;
    readonly sourceDropdown: Locator;
    readonly expectedCloseDate: Locator;
    readonly typeDropdown: Locator;
    readonly userDropdown: Locator;
    readonly searchInput: Locator;
    readonly listSearchInput: Locator;

    // Add person
    readonly personEmailInput: Locator;
    readonly personPhoneInput: Locator;
    readonly successMessage: Locator;

    // Add organization
    readonly addOrganizationButton: Locator;
    readonly organizationCreateSuccessMessage: Locator;

    // General
    readonly leadSuccessToast: Locator;
    readonly editLeadButton: Locator;
    readonly listViewButton: Locator;
    readonly deleteLeadButton: Locator;
    readonly noRecordsAvailable: Locator
    readonly searchInputExact:Locator;
    readonly listViewLeadSearch: Locator;
    // Tabs
    readonly mailButton: Locator;
    readonly fileButton: Locator;
    readonly noteButton: Locator;
    readonly activityButton: Locator;

    // Mail
    readonly mailToInput: Locator;
    readonly mailSubjectInput: Locator;
    readonly mailBodyTextarea: Locator;
    readonly sendMailButton: Locator;

    // File

    // Note

    // Activity
    readonly addActivityTitleInput: Locator;
    readonly addActivityCommentTextarea: Locator;
    readonly scheduleFromInput: Locator;
    readonly scheduleToInput: Locator;
    readonly locationInput: Locator;

    // Validation message
    readonly expectedCloseDateMustBeDateAfter: Locator;



    // Other
    readonly appLocator: Locator;

    // Quick Add
    readonly quickAddTrigger: Locator;
    readonly quickAddModalTitle: Locator;
    readonly quickAddTab: (tabLabel: string) => Locator;
    readonly quickAddSaveButton: Locator;
    
    readonly quickAddLeadForm: Locator;
    readonly quickAddPersonForm: Locator;
    readonly quickAddOrganizationForm: Locator;
    readonly quickAddProductForm: Locator;
    readonly quickAddMailForm: Locator;

    readonly quickAddLeadTitleInput: Locator;
    readonly quickAddLeadDescriptionTextarea: Locator;

    readonly quickAddPersonNameInput: Locator;
    readonly quickAddPersonEmailInput: Locator;
    readonly quickAddPersonContactInput: Locator;

    readonly quickAddOrgNameInput: Locator;

    readonly quickAddProductNameInput: Locator;
    readonly quickAddProductDescriptionTextarea: Locator;
    readonly quickAddProductSkuInput: Locator;
    readonly quickAddProductQuantityInput: Locator;
    readonly quickAddProductPriceInput: Locator;

    readonly quickAddMailReplyToInput: Locator;
    readonly quickAddMailCcInput: Locator;
    readonly quickAddMailBccInput: Locator;
    readonly quickAddMailCcButton: Locator;
    readonly quickAddMailBccButton: Locator;
    readonly quickAddMailSubjectInput: Locator;
    readonly quickAddMailEditorFrame: FrameLocator;

    readonly quickAddLeadSuccessMsg: Locator;
    readonly quickAddPersonSuccessMsg: Locator;
    readonly quickAddOrgSuccessMsg: Locator;
    readonly quickAddProductSuccessMsg: Locator;
    readonly quickAddMailSuccessMsg: Locator;

    constructor(page: Page) {
        this.page = page;

        /**
         * The Vue application root — the whole of the product's own markup, and nothing else.
         *
         * It is assigned first because the flash-message locators below are scoped to it. With
         * `APP_DEBUG=true` the Laravel DebugBar sits in the same document, and its AJAX panel dumps
         * a response body as `sf-dump-str` text: a flash the product shows once then appears a second
         * time inside the debug bar, `getByText("Campaign deleted successfully.")` matches two
         * elements, and a strict-mode assertion fails. The debug bar is injected before `</body>`,
         * outside `#app`, so scoping to this root cannot see it. That is what makes the flash
         * assertions independent of the instance's `APP_DEBUG`, which they have to be: they must
         * hold whichever way that flag is set.
         */
        this.appLocator = page.locator("#app");

        // Buttons
        this.saveQuoteButton = page.getByRole("button", { name: "Save Quote" });
        /**
         * `Save Product`, not `Save Products`.
         *
         * The product form's create and edit buttons were both reworded to the singular in `en`
         * (`products.create.save-btn` / `products.edit.save-btn`), so the plural locator waited for
         * a button that no longer exists — and it is the same button the lead and quote specs drive
         * when they build the product they attach, which is why one stale label failed seven cases
         * across three files.
         */
        this.saveProductButton = page.getByRole("button", { name: "Save Product" });
        this.confirmDeleteButton = page.locator("button.transparent-button + button.primary-button:visible");
        this.saveLeadButton = page.getByRole("button", { name: "Save" });
        this.sendEmailButton = page.getByRole("button", { name: "Send" });
        this.saveFileButton = page.getByRole("button", { name: "Save File" });
        this.saveNoteButton = page.getByRole("button", { name: "Save Note" });
        this.saveActivityButton = page.getByRole("button", { name: "Save Activity" });
        /**
         * Sign-in is matched on structure, not text: every string on the login page is translated
         * (`admin::app.users.login.*`). See `pages/AdminPage.ts`, which owns the login flow and
         * overrides these; the names are the contract with `LoginRequest`, the class is the design
         * system's, and `v-form` is the wrapper before Vue mounts it as a real `<form>`.
         */
        this.signInButton = page.locator("form button.primary-button, v-form button.primary-button").first();
        this.createEventButton = page.getByRole("button", { name: "Create Event" });
        this.saveEventButton = page.getByRole("button", { name: "Save Event" });
        this.agreeButton = page.getByRole("button", { name: "Agree", exact: true });
        this.createTypeButton = page.getByRole("button", { name: "Create Type" });
        this.saveTypeButton = page.getByRole("button", { name: "Save Type" });
        this.createGroupButton = page.getByRole("button", { name: "Create group" });
        this.saveGroupButton = page.getByRole("button", { name: "Save Group" });
        this.createUserButton = page.getByRole("button", { name: "Create User" });
        this.saveUserButton = page.getByRole("button", { name: "Save User" });
        this.composeMailButton = page.getByRole("button", { name: "Compose Mail" });
        this.createOrganizationButton = page.getByRole("link", { name: "Create Organization" });
        this.saveOrganizationButton = page.getByRole("button", { name: "Save Organization" });
        this.deleteButton = page.getByRole("button", { name: "Delete" });
        this.leadProductSelect = page.locator('.mb-4.\\!mb-0 > div > .relative.inline-block > .relative').first();
        this.leadProductAddMoreButton = page.locator('//button[@class="flex max-w-max items-center gap-2 text-brandColor"]');
        this.leadOrganizationSelect = page.locator("div").filter({ hasText: /^Click to add$/ }).nth(2);
        this.addPersonButton = page.locator("div", { hasText: /^Click to Add$/ }).nth(1);
        this.quoteAddPersonButton = page.locator(".relative.flex.items-center.justify-between").first();

        // Links
        this.createQuoteLink = page.getByRole("link", { name: "Create Quote" });
        this.createProductLink = page.getByRole("link", { name: "Create Product" });
        this.createLeadLink = page.getByRole("link", { name: "Create Lead" });
        this.signOutLink = page.getByRole("link", { name: "Sign Out" });
        this.createOrganizationLink = page.getByRole("link", { name: "Create Organization" });

        this.addAsNewButton = page.getByText("Add as New");
        this.quoteSelectListPerson = page.locator(`(//li[@class="flex cursor-pointer gap-2 p-2 transition-colors hover:bg-blue-100 dark:text-gray-300 dark:hover:bg-gray-900"])[1]`);

        // Textboxes - single line text input fields
        this.subjectTextbox = page.getByRole("textbox", { name: "Subject *" });
        this.descriptionTextbox = page.getByRole("textbox", { name: "Description" });

        this.productNameTextbox = page.getByRole("textbox", { name: "Name *" });
        this.productPriceInput = page.locator('input[name="price"]');
        this.productQuantityInput = page.locator('input[name="quantity"]');
        this.productSkuInput = page.locator('input[name="sku"]');
        this.leadTitleInput = page.locator('input[name="title"]');
        this.leadValueInput = page.locator('input[name="lead_value"]');
        this.emailReplyToInput = page.locator('input[name="temp-reply_to"]');
        this.emailSubjectInput = page.locator('input[name="subject"]');
        this.fileTitleInput = page.locator('input[name="title"]');
        this.fileNameInput = page.locator('input[name="name"]');
        this.noteCommentTextarea = page.locator('textarea[name="comment"]');
        this.activityTitleInput = page.locator('input[name="title"]');
        this.activityLocationInput = page.locator('input[name="location"]');
        this.eventNameInput = page.locator('input[name="name"]');
        this.typeNameInput = page.locator('input[name="name"]');
        this.groupNameInput = page.locator('input[name="name"]');
        this.userNameInput = page.locator('input[name="name"]');
        this.userEmailInput = page.locator('input[name="email"]');
        this.userPasswordInput = page.locator('input[name="password"]');
        this.userConfirmPasswordInput = page.locator('input[name="confirm_password"]');
        this.organizationNameInput = page.locator('input[name="name"]');
        this.organizationCityTextbox = page.getByRole('textbox', { name: 'City' });
        this.organizationAddressTextarea = page.locator('textarea[name="address\\[address\\]"]');
        this.organizationPostcodeTextbox = page.getByRole('textbox', { name: 'Postcode' });
        /**
         * By name, as the state select beneath it already is: any combobox on the page also matched
         * the debug bar's own folder switcher.
         */
        this.organizationCountryCombobox = page.locator('select[name="address\\[country\\]"]');
        this.organizationStateSelect = page.locator('select[name="address\\[state\\]"]');

        // Textboxes - date or specialized text input
        this.expiredAtTextbox = page.getByRole("textbox", { name: "Expired At *" });
        this.leadExpectedCloseDateInput = page.locator('input[name="expected_close_date"]');
        this.activityScheduleFromInput = page.locator('input[name="schedule_from"]');
        this.activityScheduleToInput = page.locator('input[name="schedule_to"]');
        this.eventDateInput = page.locator('input[name="date"]');

        this.noRecordsAvailable = page.getByText('No Records Available.')

        // Textareas - multi-line input fields
        this.billingAddressTextarea = page.locator('textarea[name="billing_address\\[address\\]"]');
        this.shippingAddressTextarea = page.locator('textarea[name="shipping_address\\[address\\]"]');
        this.productDescriptionTextarea = page.locator('textarea[name="description"]');
        this.leadDescriptionTextarea = page.locator('textarea[name="description"]');
        this.emailReplyTextarea = page.locator('textarea[name="reply"]');
        this.fileCommentTextarea = page.locator('textarea[name="comment"]');
        this.noteTextarea = page.locator('textarea[name="comment"]');
        this.activityCommentTextarea = page.locator('textarea[name="comment"]');
        this.eventDescriptionTextarea = page.locator('textarea[name="description"]');
        this.groupDescriptionTextarea = page.locator('textarea[name="description"]');

        // Inputs - single line, no explicit role
        this.billingCityInput = page.locator('input[name="billing_address\\[city\\]"]');
        this.billingPostcodeInput = page.locator('input[name="billing_address\\[postcode\\]"]');
        this.shippingCityInput = page.locator('input[name="shipping_address\\[city\\]"]');
        this.shippingPostcodeInput = page.locator('input[name="shipping_address\\[postcode\\]"]');
        this.leadEmailInput = page.locator('input[name="person[emails][0][value]"]');
        this.leadPhoneNumberInput = page.locator('input[name="person[contact_numbers][0][value]"]');

        // Select dropdowns
        this.salesOwnerSelect = page.getByLabel("Sales Owner");
        this.billingCountrySelect = page.locator('select[name="billing_address\\[country\\]"]');
        this.billingStateSelect = page.locator('select[name="billing_address\\[state\\]"]');
        this.shippingCountrySelect = page.locator('select[name="shipping_address\\[country\\]"]');
        this.shippingStateSelect = page.locator('select[name="shipping_address\\[state\\]"]');
        this.leadSourceSelect = page.locator('select[name="lead_source_id"]');
        this.leadTypeSelect = page.locator('select[name="lead_type_id"]');
        this.leadUserSelect = page.locator('select[name="user_id"]');
        this.leadSourceLabelSelect = page.getByLabel('Source');
        this.userRoleSelect = page.locator('select[name="role_id"]');
        this.userViewPermissionSelect = page.locator('select[name="view_permission"]');
        this.userGroupListbox = page.getByRole('listbox');

        // Search related list item selector
        this.personListItem = (name: string) => page.getByRole("listitem").filter({ hasText: name });

        // Edit and delete icons
        this.firstEditIcon = page.locator("span.cursor-pointer.icon-edit").first();
        this.firstDeleteIcon = page.locator("span.cursor-pointer.icon-delete").first();

        //mass delte checkbox
        this.multiSelectCheckbox = page.locator('.icon-checkbox-outline').first();

        this.quoteLinkToLeadButton = page.locator('div:nth-child(2) > div > .relative.inline-block > .relative');

        // Activity modal selectors
        this.addActivityButton = page.getByRole("button", { name: " Activity" });
        this.activityCallOption = page.getByText("Call", { exact: true });
        this.activityMeetingOption = page.getByText("Meeting", { exact: true });
        this.activityLunchOption = page.getByText("Lunch", { exact: true });

        // Login fields — by name and type, for the same reason as `signInButton` above.
        this.emailPlaceholderInput = page.locator('input[name="email"][type="email"]');
        this.passwordPlaceholderInput = page.locator('input[name="password"]');
        this.megaSearchPlaceholder = page.getByPlaceholder("Mega Search").first();

        // Organization Locators Initialization
        this.createOrgLink = page.getByRole('link', { name: 'Create Organization' });
        this.orgNameTextbox = page.getByRole('textbox', { name: 'Name *' });
        this.orgAddressTextarea = page.locator('textarea[name="address\\[address\\]"]');
        this.orgCountryCombobox = page.locator('select[name="address\\[country\\]"]');
        this.orgStateSelect = page.locator('select[name="address\\[state\\]"]');
        this.orgCityTextbox = page.getByRole('textbox', { name: 'City' });
        this.orgPostcodeTextbox = page.getByRole('textbox', { name: 'Postcode' });

        // Person Locators Initialization
        this.createPersonLink = page.getByRole('link', { name: 'Create Person' });
        this.savePersonButton = page.getByRole('button', { name: 'Save Person' });
        this.personNameTextbox = page.getByRole('textbox', { name: 'Name *' });
        this.personEmailTextbox = page.getByRole('textbox', { name: 'Emails *' });
        this.personPhoneTextbox = page.getByRole('textbox', { name: 'Contact Numbers' });
        this.personJobTitleTextbox = page.getByRole('textbox', { name: 'Job Title' });

        // Lead
        this.createLeadButton = page.getByRole('link', { name: 'Create Lead' });

        // Lead form
        this.titleInput = page.getByRole('textbox', { name: 'Title *' });
        this.descriptionTextarea = page.locator('textarea[name="description"]');
        this.sourceDropdown = page.locator('select[name="lead_source_id"]');
        this.expectedCloseDate = page.locator('input[name="expected_close_date"]');
        this.typeDropdown = page.locator('select[name="lead_type_id"]');
        this.userDropdown = page.locator('select[name="user_id"]');
        this.leadValueInput = page.locator('input[name="lead_value"]');
        this.listViewLeadSearch= page.getByRole('textbox', { name: 'Search', exact: true });
        this.searchInput = page.getByRole('textbox', { name: 'Search by Title' });
        this.listSearchInput = page.getByRole('textbox', { name: 'Search' });

        // Lead form - person search dropdown
        this.leadPersonSearchInput = page.getByPlaceholder("Search by name, email and number");

        // Lead form - product search dropdown
        this.leadProductSearchInput = page.getByRole('textbox', { name: 'Product Name' });

        // Add person
  
        this.personEmailInput = page.locator('input[name="person[emails][0][value]"]');
        this.personPhoneInput = page.locator('input[name="person[contact_numbers][0][value]"]');
        /**
         * A flash, through `flash()` rather than a whole-document `getByText`: `getByText` matches a
         * substring case-insensitively, so "Success" also matched the debug bar's dump of the AJAX
         * response that carried `... deleted successfully.` — two elements, and it only passed
         * because `.first()` happened to pick the toast. See `appLocator`.
         */
        this.successMessage = this.flash('Success');

        // Add organization
        this.addOrganizationButton = page.locator('div', { hasText: /^Click to add$/ }).nth(2);

        this.saveLeadButton = page.getByRole('button', { name: 'Save' });
        this.organizationCreateSuccessMessage = this.flash('Organization created successfully');

        // General
        this.leadSuccessToast = this.flash('Success', { exact: true });
        this.editLeadButton = page.getByRole('link', { name: '' }).first();
        this.listViewButton = page.getByRole('link', { name: '' });
        this.agreeButton = page.getByRole('button', { name: 'Agree', exact: true });
        this.deleteLeadButton = page.locator(
            '.cursor-pointer.rounded-md.p-1\\.5.text-2xl.transition-all.hover\\:bg-gray-200.dark\\:hover\\:bg-gray-800.max-sm\\:place-self-center.icon-delete'
        ).first();
        this.searchInputField = page.getByRole("textbox", { name: "Search..." });
        this.searchInputExact = page.getByRole("textbox", { name: "Search", exact: true });

        // Tabs
        this.mailButton = page.getByRole('button', { name: ' Mail' });
        this.fileButton = page.getByRole('button', { name: ' File' });
        this.noteButton = page.getByRole('button', { name: ' Note' });
        this.activityButton = page.getByRole('button', { name: ' Activity' });

        // Mail
        this.mailToInput = page.locator('input[name="temp-reply_to"]');
        this.mailSubjectInput = page.locator('input[name="subject"]');
        this.mailBodyTextarea = page.locator('textarea[name="reply"]');
        this.sendMailButton = page.getByRole('button', { name: 'Send' });

        // File
        this.fileTitleInput = page.locator('input[name="title"]');
        this.fileCommentTextarea = page.locator('textarea[name="comment"]');
        this.fileNameInput = page.locator('input[name="name"]');
        this.saveFileButton = page.getByRole('button', { name: 'Save File' });

        // Note
        this.noteCommentTextarea = page.locator('textarea[name="comment"]');
        this.saveNoteButton = page.getByRole('button', { name: 'Save Note' });

        // Activity
        this.addActivityTitleInput = page.locator('input[name="title"]');
        this.addActivityCommentTextarea = page.locator('textarea[name="comment"]');
        this.scheduleFromInput = page.locator('input[name="schedule_from"]');
        this.scheduleToInput = page.locator('input[name="schedule_to"]');
        this.locationInput = page.locator('input[name="location"]');
        this.saveActivityButton = page.getByRole('button', { name: 'Save Activity' });

        // Validation message
        this.expectedCloseDateMustBeDateAfter = page.getByText('The expected close date must be a date after');

        // Quick Add
        this.quickAddTrigger = page.locator("header button.bg-brandColor").first();
        this.quickAddModalTitle = page.locator("p").filter({ hasText: "Quick Add" });
        this.quickAddTab = (tabLabel: string) => page.locator("span.cursor-pointer").filter({ hasText: new RegExp(`^${tabLabel}$`) }).first();
        this.quickAddSaveButton = page.getByRole("button", { name: "Save", exact: true }).last();
        
        this.quickAddLeadForm = page.locator('form:has(input[value="lead"])').first();
        this.quickAddPersonForm = page.locator('form:has(input[value="person"])').first();
        this.quickAddOrganizationForm = page.locator('form:has(input[value="organization"])').first();
        this.quickAddProductForm = page.locator('form:has(input[value="product"])').first();
        this.quickAddMailForm = page.locator('form:has(input[name="is_draft"])').first();

        this.quickAddLeadTitleInput = this.quickAddLeadForm.locator('input[name="title"]');
        this.quickAddLeadDescriptionTextarea = this.quickAddLeadForm.locator('textarea[name="description"]');

        this.quickAddPersonNameInput = this.quickAddPersonForm.locator('input[name="name"]');
        this.quickAddPersonEmailInput = this.quickAddPersonForm.locator('input[name="emails[0][value]"]');
        this.quickAddPersonContactInput = this.quickAddPersonForm.locator('input[name="contact_numbers[0][value]"]');

        this.quickAddOrgNameInput = this.quickAddOrganizationForm.locator('input[name="name"]');

        this.quickAddProductNameInput = this.quickAddProductForm.locator('input[name="name"]');
        this.quickAddProductDescriptionTextarea = this.quickAddProductForm.locator('textarea[name="description"]');
        this.quickAddProductSkuInput = this.quickAddProductForm.locator('input[name="sku"]');
        this.quickAddProductQuantityInput = this.quickAddProductForm.locator('input[name="quantity"]');
        this.quickAddProductPriceInput = this.quickAddProductForm.locator('input[name="price"]');

        this.quickAddMailReplyToInput = this.quickAddMailForm.locator('input[name="temp-reply_to"]');
        this.quickAddMailCcInput = this.quickAddMailForm.locator('input[name="temp-cc"]');
        this.quickAddMailBccInput = this.quickAddMailForm.locator('input[name="temp-bcc"]');
        this.quickAddMailCcButton = this.quickAddMailForm.getByText('CC', { exact: true });
        this.quickAddMailBccButton = this.quickAddMailForm.getByText('BCC', { exact: true });
        this.quickAddMailSubjectInput = this.quickAddMailForm.locator('input[name="subject"]');
        this.quickAddMailEditorFrame = this.quickAddMailForm.frameLocator('iframe.tox-edit-area__iframe');

        this.quickAddLeadSuccessMsg = this.flash("Lead created successfully.");
        this.quickAddPersonSuccessMsg = this.flash("Person created successfully.");
        this.quickAddOrgSuccessMsg = this.flash("Organization created successfully.");
        this.quickAddProductSuccessMsg = this.flash("Product created successfully.");
        this.quickAddMailSuccessMsg = this.flash("Email sent successfully.");
    }

    /**
     * A flash (session) message: the toast the product renders inside `#app`.
     *
     * Every assertion about "... created/updated/deleted successfully" goes through this, so none of
     * them can be satisfied by the debug bar's dump of the response that carried the same sentence.
     */
    flash(text: string | RegExp, options?: { exact?: boolean }): Locator {
        return this.appLocator.getByText(text, options);
    }

    async searchByName(name: string) {
        (await this.getElementByTypeAndName('textbox', 'Search')).fill(name);
        
        await this.page.keyboard.press('Enter');
    }

    async getElementByTypeAndName(type: ElementType, name: string) {
        await expect(this.page.getByRole(`${type}`, { name: `${name}`, exact: true })).toBeVisible();
        return this.page.getByRole(`${type}`, { name: `${name}`, exact: true });
    }

    async selectListItmeByName(value: string) {
        return this.page.getByRole('listitem').filter({ hasText: `${value}` });
    }

    /**
     * A lookup attribute (`<v-lookup-component>`) is identified by the hidden input that carries its
     * field name; the component's root element is that input's parent. Scoping every locator to that
     * root keeps the result list out of the rest of the page: the admin account is named
     * "Example Admin", so a whole-page `getByRole('listitem').filter({ hasText: 'Example' })` matched
     * a row of whatever picker happened to be open anywhere on the page rather than the picker the
     * test had just opened, and matched nothing at all once that popup closed.
     */
    lookupRoot(name: string): Locator {
        return this.page.locator(`input[name="${name}"]`).locator("xpath=..");
    }

    /** The clickable header of a lookup control - the element that opens and closes its popup. */
    lookupTrigger(name: string): Locator {
        return this.lookupRoot(name).locator("div").first();
    }

    /** The popup's own search box, which only exists while the popup is open. */
    lookupSearch(name: string): Locator {
        return this.lookupRoot(name).getByRole("textbox");
    }

    /** One result row of an open lookup popup. */
    lookupOption(name: string, text: string): Locator {
        return this.lookupRoot(name).getByRole("listitem").filter({ hasText: text });
    }

    /**
     * The display name of the account this session is signed in as, read from its own account screen.
     *
     * The person and organization forms need a Sales Owner, and the suite used to type the literal
     * "Example" into that lookup. That only ever matched because `tests/account.spec.ts` renames the
     * signed-in administrator to `Example <suffix>` earlier in the same run: run the lead, quote or
     * organization specs alone on an instance whose administrator still has the name the installer
     * gave them and the picker offered no such user, so every case that creates a person or an
     * organization failed — five cases failing on their own but passing in a full run. Reading the
     * name instead of assuming it makes the choice depend on nothing but the session, which is the
     * one user that provably exists at any point in the run.
     */
    async currentUserName(): Promise<string> {
        await this.page.goto("admin/account");

        const name = (await this.page.locator('input[name="name"]').inputValue()).trim();

        if (! name) {
            throw new Error("Could not read the signed-in user's name from admin/account.");
        }

        return name;
    }

    /**
     * Set a `user_id` lookup (the Sales Owner attribute) to the signed-in account.
     *
     * The picker is searched by the name rather than picked from whatever it lists first, so the
     * record is assigned deliberately and the assertion can name the user it chose.
     */
    async selectSalesOwner(salesOwner: string) {
        await this.lookupTrigger("user_id").click();
        await this.lookupSearch("user_id").fill(salesOwner);
        await this.lookupOption("user_id", salesOwner).click();
    }

    /**
     * Mass-delete exactly one record, named by the caller.
     *
     * Two things were wrong with asserting "the grid is now empty". It is only true when the listing
     * held exactly one row — this instance seeds eight organizations, and the spec creates three more
     * before it runs — and reaching that state meant deleting every row on the page rather than one:
     * the header's select-all checkbox is what the old code ticked, and in this version of the grid
     * the mass action posts the list of selected ids it accumulated, so selecting everything on the
     * page deletes everything on the page. Measured on a scratch copy: eleven organizations before the
     * case, one after it, ten of them not the record under test.
     *
     * So the row's own checkbox is ticked here, after the grid has been narrowed to the one record,
     * and the number of ticked rows is asserted to be one before anything is deleted. The assertion
     * afterwards is about that record: its row is gone. How many other rows the listing holds is then
     * irrelevant to both the test and its outcome.
     */
    async massDelete(name: string) {
        await this.searchByName(name);

        const row = this.page.locator("div.row").filter({ hasText: name }).first();

        await expect(row, "the record to mass-delete must be in the filtered listing").toBeVisible();

        /**
         * Matched by class only, on whatever element carries it, because the grids in this checkout
         * do not agree on the tag. The organizations and events grids render
         * `<input class="peer hidden"><span class="icon-checkbox-outline">` inside a label, which is
         * why this line read `span.icon-checkbox-outline`; the persons grid puts the class on the
         * `<label>` itself (`<input class="peer hidden"><label class="icon-checkbox-outline ...">`),
         * so the span-scoped locator matched nothing inside the row and the click waited for an
         * element that never appeared until the test timeout killed it. Clicking the element that
         * carries the class toggles the input in both shapes: the input is either inside the label or
         * bound to it by `for=`.
         */
        await row.locator(".icon-checkbox-outline").first().click();

        /**
         * Exactly one record ticked — the header's select-all would tick every record on the page,
         * and the mass action posts exactly the records that are ticked. Counted by record id rather
         * than by checked input, because the grid renders each record twice — a desktop row and a
         * mobile card, both bound to the same `v-model` — so one selected record is two checked
         * inputs.
         */
        const tickedRecordIds = await this.page
            .locator('input[name^="mass_action_select_record_"]:checked')
            .evaluateAll((inputs) => [...new Set(inputs.map((input) => (input as HTMLInputElement).value))]);

        await expect(
            tickedRecordIds,
            "the mass action must be scoped to the one record under test"
        ).toHaveLength(1);

        await this.deleteButton.click();
        await this.agreeButton.click();

        /**
         * The confirmation, by its own wording and not through `this.successMessage`.
         *
         * `successMessage` is overridable per page object, and a page object that narrows it for its
         * single-record cases narrows it here too: `EventsPage` asserts
         * `/Event (created|updated|deleted) successfully./`, which cannot match the flash a mass
         * delete shows — `mass-delete-success` is "Events deleted successfully", plural and without
         * the full stop — so the mass delete happened and the assertion failed after it. Every mass
         * delete in this product reports "... deleted successfully" (`delete-success` for one
         * organization, `all-delete-success` for persons, `mass-delete-success` for events,
         * campaigns and users), so that wording is the property the shared helper can rely on.
         */
        await expect(this.flash(/deleted successfully/i).first()).toBeVisible();
        await expect(this.page.getByText(name)).toHaveCount(0);
    }
}
