import { Page, Locator, expect } from "@playwright/test";
import CoreLocators from "../locator/CoreLocators";

export class AdminPage extends CoreLocators {
    readonly page: Page;
    readonly emailInput: Locator;
    readonly passwordInput: Locator;
    readonly signInButton: Locator;
    readonly megaSearchInput: Locator;
    readonly profileButton: Locator;
    readonly signOutLink: Locator;

    constructor(page: Page) {
        super(page)
        this.page = page;

        /**
         * The login controls are found by what they do, not by what they say.
         *
         * Everything visible on this page is translated: `sessions/login.blade.php` fills both
         * placeholders and the submit button's text and `aria-label` from `admin::app.users.login.*`,
         * so `getByPlaceholder("Email Address")` and `getByRole("button", { name: "Sign In" })` only
         * match an English interface. On an Arabic-first checkout nothing matched, and since every
         * spec reaches the panel through this one method, the whole suite was blocked at sign-in.
         *
         * The field names are the stable part: they are the contract with
         * `SessionController`/`LoginRequest`, which read `email` and `password` off the request, so
         * they cannot be renamed without breaking authentication itself. `type="email"` is added to
         * the email selector because the Blade hardcodes it; the password's `type` is deliberately
         * *not* pinned, because the eye toggle flips it between `password` and `text`.
         *
         * The submit button carries no `type` attribute (so it is a submit button), and its
         * `primary-button` class is the design system's, not a translation. `v-form` covers the
         * window between the document arriving and Vue mounting that wrapper as a real `<form>`.
         */
        this.emailInput = page.locator('input[name="email"][type="email"]');
        this.passwordInput = page.locator('input[name="password"]');
        this.signInButton = page.locator('form button.primary-button, v-form button.primary-button').first();

        this.megaSearchInput = page.getByPlaceholder("Mega Search").first();
        this.profileButton = page.locator("header button.h-9.w-9.rounded-full").last();

        /**
         * Sign-out is matched on its `href`, not its text.
         *
         * `components/layouts/header/index.blade.php` renders it as an anchor whose label is
         * `@lang('admin::app.layouts.sign-out')` — `Sign Out` in English, `تسجيل الخروج` in Arabic —
         * pointing at `admin.session.destroy`, which is a route name and cannot be translated. The
         * suffix form keeps working whether the app is served from `/` or a subdirectory.
         */
        this.signOutLink = page.locator('a[href$="/admin/logout"]').first();
    }



    async adminLogin(email: string, password: string) {
        await this.page.goto('admin/login');
        await this.emailInput.fill(email);
        await this.passwordInput.fill(password);
        await this.signInButton.click();
    }

    async verifyLogin() {
        await expect(this.megaSearchInput).toBeVisible();
    }

    async adminLogout() {
        await this.page.waitForTimeout(1000);
        await this.profileButton.click();
        await this.signOutLink.click();

    }

    async verifyLogout() {
        await expect(this.passwordInput.first()).toBeVisible();
    }
}
