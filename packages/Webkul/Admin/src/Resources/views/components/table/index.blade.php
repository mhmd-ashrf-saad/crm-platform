<div class="w-full overflow-x-auto xl:overflow-x-visible">
    <table {{ $attributes->merge(['class' => 'w-full border border-gray-300 text-left text-sm dark:border-gray-800 xl:table-fixed xl:min-w-[800px]']) }}>
        {{ $slot }}
    </table>
</div>
