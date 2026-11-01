{{--
    The shared table.

    Two separate jobs, and they belong at two different breakpoints:

    - `table-fixed` from `xl` (1240px): the table is pinned to the width of whatever column it is
      dropped into, so its columns share that width and wrap. This is what stops a table's own
      min-content — the sum of its widest cells — from deciding the page's width.
    - The 800px floor only from `3xl` (1680px): a floor is a promise that there is room for it, and
      below 1680 there is not. In `/admin/leads/view/{id}?tab=products` the table's column is 573px at
      1240 and 699px at 1366, so an 800px table inside it — inside a wrapper that is
      `xl:overflow-x-visible` — pushed the document to 1438px: 198px of page overflow at 1240, 158px
      at 1280, 41px at 1366. At 1680 and above even a view with a 394px side panel leaves the table
      ~1023px, so the floor keeps the fixed column alignment where it can be honoured.

    Both were measured, not reasoned: removing the fixed layout instead (fixing only the floor) put
    the min-content back in charge and `/admin/quotes/edit/{id}` — eight columns of line items, a
    min-content of 1071px — overflowed the page by 23px at 1280 while its wrapper stayed
    `overflow-x: visible`.

    `data-e2e-shared-table` marks the wrapper for the responsive audit (`tests/e2e-pw`): it is how the
    harness can tell "this page rendered the shared table" from "this page rendered a shell", instead
    of counting any old `<table>` — a view composing `<x-admin::table.thead>` without this component
    does not get the marker.
--}}
<div class="w-full overflow-x-auto xl:overflow-x-visible" data-e2e-shared-table>
    <table {{ $attributes->merge(['class' => 'w-full border border-gray-300 text-left text-sm dark:border-gray-800 xl:table-fixed 3xl:min-w-[800px]']) }}>
        {{ $slot }}
    </table>
</div>
