/**
 * Report rendering for the responsive audit harness.
 *
 * The audit itself lives in `responsive-audit.spec.ts`; this module only turns the collected
 * cells into the two evidence artifacts the audit exists to produce — `matrix.json`, which is
 * machine readable, and `matrix.md`, which is the pass/fail table a human reads.
 *
 * Kept separate so the markdown can be regenerated from an existing `matrix.json` without
 * re-running 133 browser cells.
 */

export type Rect = { x: number; y: number; width: number; height: number };

export type Overshoot = {
    selector: string;
    tag: string;
    rect: Rect;
    overshootPx: number;
    side: "left" | "right";
    overflowX: string;
    /**
     * Nearest ancestor that clips or scrolls horizontally. An element that sits past the viewport
     * edge *inside* such a container does not make the page overflow — it is the container's own
     * (usually deliberate) scroll. Recorded so the ranked offender list is not misread.
     */
    insideScroller: string | null;
    text: string;
};

export type InternalScroll = {
    selector: string;
    scrollWidth: number;
    clientWidth: number;
    deltaPx: number;
    overflowX: string;
    /** `scrolls` when overflow-x is auto/scroll, `clipped` when the overflow is hidden. */
    kind: "scrolls" | "clipped";
};

export type TouchTarget = {
    selector: string;
    tag: string;
    text: string;
    width: number;
    height: number;
    minSide: number;
    /** `low` for inline text links, whose padding is not the hit area; `high` otherwise. */
    severity: "low" | "high";
};

export type SmallInput = {
    selector: string;
    tag: string;
    type: string;
    fontSizePx: number;
    width: number;
    height: number;
};

export type OverlayRisk = {
    selector: string;
    tag: string;
    position: string;
    zIndex: string;
    height: number;
    viewportFraction: number;
};

/**
 * One element measured past the viewport edge *while the page was scrolled*.
 *
 * Kept separate from `Overshoot` because the scrolled pass does not need the rest of the cell
 * metrics and should not pretend to produce them: it answers one question — what overflows once the
 * scroll-direction state is live.
 */
export type ScrolledCulprit = {
    selector: string;
    tag: string;
    classes: string[];
    position: string;
    rect: Rect;
    overshootPx: number;
    side: "left" | "right";
};

/**
 * The page measured a second time, after scrolling to the bottom.
 *
 * The audit used to measure each route only at scroll position 0, which is the one state in which a
 * `body.is-scrolling-down` rule cannot be seen at all. That blind spot is exactly where a 16px
 * horizontal scrollbar lived on every page with a `scroll-reactive-sticky` header bar.
 */
export type ScrolledOverflow = {
    /** Whether `document.body` carried `is-scrolling-down` at the moment of measurement. */
    applied: boolean;
    scrollY: number;
    /** How far the document could scroll at all; `0` means there was no scroll state to engage. */
    maxScrollY: number;
    /** `documentElement.scrollWidth - clientWidth`; anything above 1 fails the cell. */
    overflowPxScrolled: number;
    bodyOverflowPxScrolled: number;
    innerWidth: number;
    clientWidth: number;
    /**
     * Geometry of the reactive header bar, when the page has one. Recorded so "the bar bleeds past
     * its column" can be told apart from "the bar was removed", which measure the same overflow.
     */
    stickyBar: { left: number; right: number; width: number; marginInlineStart: string; marginInlineEnd: string } | null;
    culprits: ScrolledCulprit[];
};

export type CellMetrics = {
    dir: string | null;
    lang: string | null;
    title: string;
    innerWidth: number;
    innerHeight: number;
    documentScrollWidth: number;
    documentScrollHeight: number;
    bodyScrollWidth: number;
    overflowPx: number;
    bodyOverflowPx: number;
    culprits: Overshoot[];
    internalScroll: InternalScroll[];
    clippedOverflowCount: number;
    touchTargets: { count: number; lowSeverityCount: number; worst: TouchTarget[] };
    smallFontInputs: { count: number; worst: SmallInput[] };
    overlayRisk: { count: number; worst: OverlayRisk[] };
    headerCollision: {
        headerFound: boolean;
        headerHeight: number;
        headerWrapped: boolean;
        collidingCount: number;
        worst: { selector: string; overlapHeightPx: number; rect: Rect }[];
    };
    debugbar: { nodes: number; visibleNodes: number; styleTagPresent: boolean };
    /**
     * The `x-admin::table` component's wrappers, found by its `data-e2e-shared-table` marker rather
     * than by `querySelector('table')`: several audited routes render this component's wrapper with
     * no rows, and "the table is on the page" and "the table has content" are different answers.
     */
    sharedTable: {
        wrappers: number;
        visible: number;
        rendered: boolean;
        entries: {
            visible: boolean;
            wrapperWidth: number;
            wrapperClientWidth: number;
            tableWidth: number;
            internalScrollPx: number;
            overflowX: string;
            layout: string | null;
            minWidth: string | null;
            columns: number;
            rows: number;
        }[];
    };
};

export type AuditCell = {
    index: number;
    variantSlug: string;
    variantLabel: string;
    path: string;
    width: number;
    height: number;
    url: string;
    status: number | string;
    redirectedToLogin: boolean;
    settled: boolean;
    screenshot: string | null;
    error: string | null;
    metrics: CellMetrics | null;
    /**
     * Uncaught exceptions raised while this cell was loading. Recorded because a Vue failure leaves
     * the server-rendered shell in place: the page then measures as a clean zero overflow while
     * rendering nothing usable.
     */
    pageErrors: string[];
    /** Console messages of type `error` raised while this cell was loading. */
    consoleErrors: string[];
    /**
     * The same page measured again at the bottom of the scroll, where the scroll-direction rules
     * apply. `null` only when the measurement itself could not be taken.
     */
    scrolledOverflow: ScrolledOverflow | null;
    verdict: "PASS" | "FAIL" | "ERROR";
    failReasons: string[];
};

export type AuditMatrix = {
    generatedAt: string;
    baseUrl: string;
    height: number;
    widths: number[];
    expectedCells: number;
    notes: string[];
    cells: AuditCell[];
};

export type ProbeResult = {
    name: string;
    width: number;
    ok: boolean;
    notes: string[];
    trigger: string | null;
    panel: {
        selector: string;
        rect: Rect;
        position: string;
        zIndex: string;
        scrollHeight: number;
        clientHeight: number;
        exceedsViewportHeight: boolean;
        exceedsViewportWidth: boolean;
        overflowPxRight: number;
        overflowPxLeft: number;
        internalScrollY: boolean;
        /**
         * The tallest visible block inside the panel. A modal opens as a full-viewport backdrop that
         * *contains* the dialog, and the backdrop is the viewport by definition, so the dialog is
         * measured separately: it is the box that can actually be too tall or too wide.
         */
        content: {
            selector: string;
            rect: Rect;
            scrollHeight: number;
            clientHeight: number;
            exceedsViewportHeight: boolean;
            exceedsViewportWidth: boolean;
            internalScrollY: boolean;
        } | null;
    } | null;
    metrics: CellMetrics | null;
};

const escapeCell = (value: string): string => value.replace(/\|/g, "\\|").replace(/\n/g, " ");

const cellLabel = (cell: AuditCell | undefined): string => {
    if (! cell) {
        return "MISSING";
    }

    if (cell.verdict === "PASS") {
        return "PASS";
    }

    const reason = cell.failReasons.length ? cell.failReasons[0] : "unknown";

    return `FAIL ${ escapeCell(reason) }`;
};

const top = <T,>(items: T[], n: number): T[] => items.slice(0, n);

const rankOverflow = (cells: AuditCell[]): AuditCell[] =>
    cells
        .filter((cell) => cell.metrics && cell.metrics.overflowPx > 1)
        .sort((a, b) => (b.metrics!.overflowPx - a.metrics!.overflowPx));

const rankScrolled = (cells: AuditCell[]): AuditCell[] =>
    cells
        .filter((cell) => cell.scrolledOverflow !== null && cell.scrolledOverflow.overflowPxScrolled > 1)
        .sort((a, b) => b.scrolledOverflow!.overflowPxScrolled - a.scrolledOverflow!.overflowPxScrolled);

const rankTouch = (cells: AuditCell[]): AuditCell[] =>
    cells
        .filter((cell) => cell.metrics && cell.metrics.touchTargets.count > 0)
        .sort((a, b) => b.metrics!.touchTargets.count - a.metrics!.touchTargets.count);

const rankFont = (cells: AuditCell[]): AuditCell[] =>
    cells
        .filter((cell) => cell.metrics && cell.metrics.smallFontInputs.count > 0)
        .sort((a, b) => b.metrics!.smallFontInputs.count - a.metrics!.smallFontInputs.count);

const rankOverlay = (cells: AuditCell[]): AuditCell[] =>
    cells
        .filter((cell) => cell.metrics && cell.metrics.overlayRisk.count > 0)
        .sort((a, b) => b.metrics!.overlayRisk.count - a.metrics!.overlayRisk.count);

const rankInternalScroll = (cells: AuditCell[]): AuditCell[] =>
    cells
        .filter((cell) => cell.metrics && cell.metrics.internalScroll.some((item) => item.kind === "scrolls"))
        .sort((a, b) => {
            const maxA = Math.max(0, ...a.metrics!.internalScroll.map((item) => item.deltaPx));

            const maxB = Math.max(0, ...b.metrics!.internalScroll.map((item) => item.deltaPx));

            return maxB - maxA;
        });

/**
 * Render `matrix.md`: the pass/fail table, the totals, the metric rankings, the route findings and
 * the interaction probes.
 */
export function renderMarkdown(matrix: AuditMatrix, probes: ProbeResult[] = []): string {
    const widths = matrix.widths;
    const cells = matrix.cells;
    const byCell = new Map<string, AuditCell>();

    for (const cell of cells) {
        byCell.set(`${ cell.variantSlug }@${ cell.width }`, cell);
    }

    const variantOrder: { slug: string; label: string; path: string }[] = [];

    for (const cell of cells) {
        if (! variantOrder.some((variant) => variant.slug === cell.variantSlug)) {
            variantOrder.push({ slug: cell.variantSlug, label: cell.variantLabel, path: cell.path });
        }
    }

    const attempted = cells.length;
    const passed = cells.filter((cell) => cell.verdict === "PASS").length;
    const failed = cells.filter((cell) => cell.verdict === "FAIL").length;
    const errored = cells.filter((cell) => cell.verdict === "ERROR").length;

    const lines: string[] = [];

    lines.push("# Responsive audit — baseline matrix (Phase 1: report only)");
    lines.push("");
    lines.push("No UI change is proposed by this document. It records what the current build does.");
    lines.push("");
    lines.push(`- Generated: ${ matrix.generatedAt }`);
    lines.push(`- Base URL: ${ matrix.baseUrl }`);
    lines.push(`- Viewport: 7 widths x fixed ${ matrix.height }px height`);
    lines.push(`- Cells attempted: ${ attempted } of ${ matrix.expectedCells } expected`);
    lines.push(`- **PASS ${ passed } / FAIL ${ failed } / ERROR ${ errored }**`);
    lines.push("");
    lines.push("Cell verdict rule: **FAIL** when the route did not answer 2xx, or the session was bounced to `/admin/login`, or `documentElement.scrollWidth - innerWidth > 1` px at rest, or `documentElement.scrollWidth - clientWidth > 1` px after scrolling to the bottom (where the `body.is-scrolling-down` rules apply), or the page never settled (Vue never mounted / the network never went idle), or the page threw an uncaught error. Everything else (touch targets, font sizes, overlay height, internal scroll, console errors) is recorded in `matrix.json` and ranked below, but does not change the verdict — internal scroll in particular is informational, not a defect.");
    lines.push("");

    lines.push("## Pass/fail matrix");
    lines.push("");
    lines.push(`| # | Route variant | URL | ${ widths.join(" | ") } |`);
    lines.push(`|---|---|---|${ widths.map(() => "---").join("|") }|`);

    variantOrder.forEach((variant, index) => {
        const row = widths.map((width) => cellLabel(byCell.get(`${ variant.slug }@${ width }`)));

        lines.push(`| ${ index + 1 } | ${ escapeCell(variant.label) } | \`${ escapeCell(variant.path) }\` | ${ row.join(" | ") } |`);
    });

    lines.push("");
    lines.push("### Failure detail");
    lines.push("");

    const failures = cells.filter((cell) => cell.verdict !== "PASS");

    if (! failures.length) {
        lines.push("None.");
    } else {
        for (const cell of failures) {
            lines.push(`- **${ cell.variantLabel } @ ${ cell.width }** — status ${ cell.status }, final URL \`${ cell.url }\`, overflow ${ cell.metrics ? cell.metrics.overflowPx : "n/a" } px — ${ cell.failReasons.join("; ") }`);
        }
    }

    lines.push("");
    lines.push("## Ranked worst offenders");
    lines.push("");

    lines.push("### Page overflow (FAIL condition)");
    lines.push("");
    lines.push("The culprit column prefers an element that is *not* inside a horizontal scroller. A card inside a kanban column that scrolls sideways legitimately sits past the viewport edge and does not make the page overflow, so it is not reported as the cause of one. `matrix.json` keeps both.");
    lines.push("");

    const overflow = rankOverflow(cells);

    if (! overflow.length) {
        lines.push("No cell overflows the page horizontally.");
    } else {
        lines.push("| Rank | Variant | Width | Overflow px | Body scrollWidth delta | Top culprit |");
        lines.push("|---|---|---|---|---|---|");

        top(overflow, 20).forEach((cell, index) => {
            const culprit = cell.metrics!.culprits.find((item) => item.insideScroller === null) ?? cell.metrics!.culprits[0];

            lines.push(`| ${ index + 1 } | ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ cell.metrics!.overflowPx } | ${ cell.metrics!.bodyOverflowPx } | ${ culprit ? escapeCell(`${ culprit.selector } (${ culprit.overshootPx } px past the ${ culprit.side } edge${ culprit.insideScroller ? `, inside scroller ${ culprit.insideScroller }` : "" })`) : "—" } |`);
        });
    }

    lines.push("");
    lines.push("### Scrolled-state overflow (FAIL condition)");
    lines.push("");
    lines.push("Every cell is measured twice: at rest, and again after scrolling to the bottom of the document with `body.is-scrolling-down` engaged. The second pass exists because a rule that only applies *while scrolling* is invisible to a measurement taken at scroll position 0 — which is how a 16px horizontal scrollbar on every page with a reactive header bar survived the first 210-cell sweep. `stickyBar` records the bar's geometry so a fix reads differently from a removal: both measure zero overflow.");
    lines.push("");

    const scrolled = cells.filter((cell) => cell.scrolledOverflow !== null);
    const engaged = scrolled.filter((cell) => cell.scrolledOverflow!.applied);
    const unscrollable = scrolled.filter((cell) => cell.scrolledOverflow!.maxScrollY === 0);
    const unengaged = scrolled.filter((cell) => cell.scrolledOverflow!.maxScrollY > 0 && ! cell.scrolledOverflow!.applied);

    lines.push(`- Cells measured while scrolled (\`is-scrolling-down\` present): ${ engaged.length } of ${ scrolled.length }.`);
    lines.push(`- Cells that could not scroll at all (the document fits the viewport, so there is no scroll state to engage): ${ unscrollable.length }${ unscrollable.length ? ` — ${ top(unscrollable, 8).map((cell) => `${ cell.variantSlug }@${ cell.width }`).join(", ") }${ unscrollable.length > 8 ? ", …" : "" }` : "" }.`);
    lines.push(`- Cells that could scroll but never engaged \`is-scrolling-down\`: ${ unengaged.length }${ unengaged.length ? ` — ${ unengaged.map((cell) => `${ cell.variantSlug }@${ cell.width }`).join(", ") }` : "" }.`);
    lines.push("");

    const scrolledOverflow = rankScrolled(cells);

    if (! scrolledOverflow.length) {
        lines.push("No cell overflows the page horizontally while scrolled.");
    } else {
        lines.push("| Rank | Variant | Width | Scrolled overflow px | At-rest overflow px | Bar geometry | Worst culprit |");
        lines.push("|---|---|---|---|---|---|---|");

        top(scrolledOverflow, 20).forEach((cell, index) => {
            const bar = cell.scrolledOverflow!.stickyBar;
            const culprit = cell.scrolledOverflow!.culprits[0];

            lines.push(
                `| ${ index + 1 } | ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ cell.scrolledOverflow!.overflowPxScrolled } | ${ cell.metrics ? cell.metrics.overflowPx : "n/a" } | ${ bar ? escapeCell(`${ bar.left }..${ bar.right } (${ bar.width }px)`) : "—" } | ${ culprit ? escapeCell(`${ culprit.tag }.${ culprit.classes.slice(0, 2).join(".") } ${ culprit.position } at x=${ culprit.rect.x } y=${ culprit.rect.y } ${ culprit.rect.width }x${ culprit.rect.height } — ${ culprit.overshootPx } px past the ${ culprit.side } edge`) : "—" } |`
            );
        });
    }

    lines.push("");
    lines.push("### Touch targets under 44x44 px (not a fail condition)");
    lines.push("");

    const touch = rankTouch(cells);

    if (! touch.length) {
        lines.push("None reported.");
    } else {
        lines.push("| Rank | Variant | Width | Count | Low-severity (inline links) | Worst target |");
        lines.push("|---|---|---|---|---|---|");

        top(touch, 20).forEach((cell, index) => {
            const worst = cell.metrics!.touchTargets.worst[0];

            lines.push(`| ${ index + 1 } | ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ cell.metrics!.touchTargets.count } | ${ cell.metrics!.touchTargets.lowSeverityCount } | ${ worst ? escapeCell(`${ worst.selector } ${ worst.width }x${ worst.height }`) : "—" } |`);
        });
    }

    lines.push("");
    lines.push("### iOS zoom trigger: text fields under 16px font (not a fail condition)");
    lines.push("");

    const font = rankFont(cells);

    if (! font.length) {
        lines.push("None reported.");
    } else {
        lines.push("| Rank | Variant | Width | Fields < 16px | Smallest |");
        lines.push("|---|---|---|---|---|");

        top(font, 20).forEach((cell, index) => {
            const worst = cell.metrics!.smallFontInputs.worst[0];

            lines.push(`| ${ index + 1 } | ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ cell.metrics!.smallFontInputs.count } | ${ worst ? escapeCell(`${ worst.fontSizePx }px on ${ worst.selector }`) : "—" } |`);
        });
    }

    lines.push("");
    lines.push("### Overlay risk: fixed/absolute elements taller than the viewport (not a fail condition)");
    lines.push("");

    const overlay = rankOverlay(cells);

    if (! overlay.length) {
        lines.push("None reported.");
    } else {
        lines.push("| Rank | Variant | Width | Count | Tallest |");
        lines.push("|---|---|---|---|---|");

        top(overlay, 20).forEach((cell, index) => {
            const worst = cell.metrics!.overlayRisk.worst[0];

            lines.push(`| ${ index + 1 } | ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ cell.metrics!.overlayRisk.count } | ${ worst ? escapeCell(`${ worst.height }px (${ Math.round(worst.viewportFraction * 100) }% of viewport) ${ worst.selector }`) : "—" } |`);
        });
    }

    lines.push("");
    lines.push("### Internal / clipped horizontal scroll (informational)");
    lines.push("");
    lines.push("`scrolls` means the element scrolls internally, which is not a defect. `clipped` means the content is cut off with no way to reach it, which is worth a look in a later phase.");
    lines.push("");

    const internal = rankInternalScroll(cells);

    if (! internal.length) {
        lines.push("None reported.");
    } else {
        lines.push("| Variant | Width | Kind | Delta px | Element |");
        lines.push("|---|---|---|---|---|");

        for (const cell of top(internal, 15)) {
            for (const item of cell.metrics!.internalScroll.slice(0, 2)) {
                lines.push(`| ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ item.kind } | ${ item.deltaPx } | ${ escapeCell(item.selector) } |`);
            }
        }
    }

    lines.push("");
    lines.push("### Runtime errors (uncaught exceptions fail the cell; console errors are recorded)");
    lines.push("");
    lines.push("A page that throws still renders the server-side shell, so it measures as zero overflow. These listeners are what makes a dead page visible instead of clean: `pageerror` is an uncaught exception and fails the cell, `console` errors are collected because a failing XHR or a render-loop warning is worth seeing even when the page survives it.");
    lines.push("");

    const withPageErrors = cells.filter((cell) => (cell.pageErrors?.length ?? 0) > 0);
    const withConsoleErrors = cells.filter((cell) => (cell.consoleErrors?.length ?? 0) > 0);

    lines.push(`- Cells with an uncaught page error: ${ withPageErrors.length }.`);
    lines.push(`- Cells with at least one console error: ${ withConsoleErrors.length }.`);

    if (withPageErrors.length) {
        lines.push("");
        lines.push("| Variant | Width | Error |");
        lines.push("|---|---|---|");

        for (const cell of top(withPageErrors, 20)) {
            lines.push(`| ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ escapeCell(cell.pageErrors[0]) } |`);
        }
    }

    if (withConsoleErrors.length) {
        lines.push("");
        lines.push("| Variant | Width | Console errors | First |");
        lines.push("|---|---|---|---|");

        for (const cell of top(withConsoleErrors, 20)) {
            lines.push(`| ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ cell.consoleErrors.length } | ${ escapeCell(cell.consoleErrors[0]) } |`);
        }
    }

    lines.push("");
    lines.push("### Shared `x-admin::table` callers (the blast radius of the table component)");
    lines.push("");
    lines.push("The table component marks its wrapper with `data-e2e-shared-table`, so `rendered` below means the component itself is on the page — not merely that some `<table>` is. A route can be complete and still render the wrapper with zero rows, and that is recorded rather than scored.");
    lines.push("");

    const tableCells = cells.filter((cell) => cell.metrics && cell.metrics.sharedTable.wrappers > 0);

    if (! tableCells.length) {
        lines.push("No cell rendered the shared table.");
    } else {
        lines.push("| Variant | Width | Wrappers | Rows | Table px | Wrapper px | Internal scroll px | Layout | Min-width |");
        lines.push("|---|---|---|---|---|---|---|---|---|");

        for (const cell of tableCells) {
            const entry = cell.metrics!.sharedTable.entries.find((item) => item.visible) ?? cell.metrics!.sharedTable.entries[0];

            lines.push(
                `| ${ escapeCell(cell.variantLabel) } | ${ cell.width } | ${ cell.metrics!.sharedTable.visible } | ${ entry ? entry.rows : 0 } | ${ entry ? entry.tableWidth : 0 } | ${ entry ? entry.wrapperClientWidth : 0 } | ${ entry ? entry.internalScrollPx : 0 } | ${ entry && entry.layout ? entry.layout : "—" } | ${ entry && entry.minWidth ? entry.minWidth : "—" } |`
            );
        }
    }

    lines.push("");
    lines.push("### Header / content collision (not a fail condition)");
    lines.push("");

    const collisions = cells
        .filter((cell) => cell.metrics && cell.metrics.headerCollision.collidingCount > 0)
        .sort((a, b) => b.metrics!.headerCollision.collidingCount - a.metrics!.headerCollision.collidingCount);

    const wrapped = cells.filter((cell) => cell.metrics?.headerCollision.headerWrapped);

    lines.push(`- Cells where page content sits inside the header band: ${ collisions.length }.`);
    lines.push(`- Cells where the header wrapped to more than 80px tall: ${ wrapped.length }${ wrapped.length ? ` (${ top(wrapped, 6).map((cell) => `${ cell.variantSlug }@${ cell.width } ${ cell.metrics!.headerCollision.headerHeight }px`).join(", ") })` : "" }.`);

    if (collisions.length) {
        for (const cell of top(collisions, 8)) {
            const worst = cell.metrics!.headerCollision.worst[0];

            lines.push(`- ${ cell.variantLabel } @ ${ cell.width }: ${ cell.metrics!.headerCollision.collidingCount } colliding element(s)${ worst ? `, worst \`${ worst.selector }\` overlapping ${ worst.overlapHeightPx } px` : "" }.`);
        }
    }

    lines.push("");
    lines.push("### RTL and locale per cell");
    lines.push("");

    const nonRtl = cells.filter((cell) => cell.metrics && cell.metrics.dir !== "rtl");
    const nonArabic = cells.filter((cell) => cell.metrics && cell.metrics.lang !== "ar");

    lines.push(`- Cells with \`<html dir>\` not equal to \`rtl\`: ${ nonRtl.length }${ nonRtl.length ? ` (${ top(nonRtl, 8).map((cell) => `${ cell.variantSlug }@${ cell.width } dir=${ cell.metrics!.dir }`).join(", ") })` : "" }.`);
    lines.push(`- Cells with \`<html lang>\` not equal to \`ar\`: ${ nonArabic.length }${ nonArabic.length ? ` (${ top(nonArabic, 8).map((cell) => `${ cell.variantSlug }@${ cell.width } lang=${ cell.metrics!.lang }`).join(", ") })` : "" }.`);
    lines.push("");

    lines.push("### Debugbar neutralisation");
    lines.push("");

    const debugbarVisible = cells.filter((cell) => (cell.metrics?.debugbar.visibleNodes ?? 0) > 0);
    const styleMissing = cells.filter((cell) => cell.metrics && ! cell.metrics.debugbar.styleTagPresent);
    const maxNodes = Math.max(0, ...cells.map((cell) => cell.metrics?.debugbar.nodes ?? 0));

    lines.push("Every page context runs an init script that appends `#phpdebugbar,.phpdebugbar,.phpdebugbar *{display:none !important;visibility:hidden !important}` to `document.head` (retried until a root exists, re-applied on `DOMContentLoaded`).");
    lines.push("");
    lines.push(`- Debugbar nodes still present in the DOM but hidden (max observed): ${ maxNodes } — hiding removes them from layout without removing them from the document.`);
    lines.push(`- Cells where any debugbar node still had a non-zero box (\`visibleNodes > 0\`): ${ debugbarVisible.length }. ${ debugbarVisible.length ? "**Neutralisation failed in these cells.**" : "Neutralisation held everywhere." }`);
    lines.push(`- Cells where the kill style tag was missing: ${ styleMissing.length }.`);
    lines.push("");

    if (matrix.notes.length) {
        lines.push("## Route and environment findings");
        lines.push("");

        for (const note of matrix.notes) {
            lines.push(`- ${ note }`);
        }

        lines.push("");
    }

    if (probes.length) {
        lines.push("## Interaction probes (width 360)");
        lines.push("");

        for (const probe of probes) {
            lines.push(`### ${ probe.name }`);
            lines.push("");
            lines.push(`- Trigger: ${ probe.trigger ? `\`${ escapeCell(probe.trigger) }\`` : "not found" }`);
            lines.push(`- Probe ok: ${ probe.ok ? "yes" : "no" }`);

            if (probe.panel) {
                const panel = probe.panel;

                lines.push(`- Panel: \`${ escapeCell(panel.selector) }\` at ${ panel.rect.x },${ panel.rect.y } sized ${ panel.rect.width }x${ panel.rect.height }, position ${ panel.position }, z-index ${ panel.zIndex }`);
                lines.push(`- Panel taller than the viewport (800 px): ${ panel.exceedsViewportHeight ? `**yes** (${ panel.rect.height } px, ${ Math.round((panel.rect.height / 800) * 100) }% of viewport height)` : "no" }`);
                lines.push(`- Panel overflows the viewport horizontally: ${ panel.exceedsViewportWidth ? `**yes** (right +${ panel.overflowPxRight } px / left +${ panel.overflowPxLeft } px)` : "no" }`);
                lines.push(`- Panel scrolls internally (vertical): ${ panel.internalScrollY ? `yes (scrollHeight ${ panel.scrollHeight } vs clientHeight ${ panel.clientHeight })` : "no" }`);

                if (panel.content) {
                    lines.push(`- Dialog inside the panel: \`${ escapeCell(panel.content.selector) }\` at ${ panel.content.rect.x },${ panel.content.rect.y } sized ${ panel.content.rect.width }x${ panel.content.rect.height }`);
                    lines.push(`- Dialog taller than the viewport: ${ panel.content.exceedsViewportHeight ? `**yes** (${ panel.content.rect.height } px)` : "no" }`);
                    lines.push(`- Dialog overflows the viewport horizontally: ${ panel.content.exceedsViewportWidth ? "**yes**" : "no" }`);
                    lines.push(`- Dialog scrolls internally (vertical): ${ panel.content.internalScrollY ? `yes (scrollHeight ${ panel.content.scrollHeight } vs clientHeight ${ panel.content.clientHeight })` : "no" }`);
                }
            }

            if (probe.metrics) {
                lines.push(`- Page overflow while open: ${ probe.metrics.overflowPx } px`);
                lines.push(`- Touch targets under 44 px while open: ${ probe.metrics.touchTargets.count } (${ probe.metrics.touchTargets.lowSeverityCount } of them inline links)`);
                lines.push(`- Overlays taller than the viewport while open: ${ probe.metrics.overlayRisk.count }`);
            }

            for (const note of probe.notes) {
                lines.push(`- ${ note }`);
            }

            lines.push("");
        }
    }

    return `${ lines.join("\n").replace(/\n{3,}/g, "\n\n") }\n`;
}
