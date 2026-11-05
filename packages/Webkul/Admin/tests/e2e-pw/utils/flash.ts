import type { Locator, Page } from "@playwright/test";

/**
 * A flash (session) message — the toast the product renders inside the Vue application root.
 *
 * Every assertion about "… created / updated / deleted successfully" goes through this or through
 * `CoreLocators.flash()` (the same thing, for page objects), so none of them can be satisfied by
 * text that is not the product's own.
 *
 * The reason it matters: with `APP_DEBUG=true` the Laravel DebugBar sits in the same document, and
 * its AJAX panel dumps the response body as `sf-dump-str`. A flash the product shows once therefore
 * appears twice — `getByText("Campaign deleted successfully.")` matched the toast and the dump, and a
 * strict-mode assertion on two elements fails. The debug bar is injected before `</body>`, outside
 * `#app`, so scoping to this root cannot see it. Asserting flash text against the whole document
 * would make the suite depend on `APP_DEBUG`, and it must hold whichever way that flag is set.
 */
export function flashOf(page: Page, text: string | RegExp, options?: { exact?: boolean }): Locator {
    return page.locator("#app").getByText(text, options);
}
