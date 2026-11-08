<x-admin::layouts>
    <x-slot:title>
        {{ $warehouse->name }} - @lang('admin::app.settings.warehouses.products')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <!-- Breadcrumbs -->
                <x-admin::breadcrumbs
                    name="settings.warehouses.view.products"
                    :entity="$warehouse"
                />

                <div class="flex flex-col gap-1">
                    <div class="text-xl font-bold dark:text-white">
                        {{ $warehouse->name }}
                    </div>

                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        @lang('admin::app.settings.warehouses.products')
                    </div>
                </div>
            </div>
        </div>

        {!! view_render_event('admin.settings.warehouses.products.datagrid.before', ['warehouse' => $warehouse]) !!}

        <!-- Products stocked in this warehouse -->
        <x-admin::datagrid :src="route('admin.settings.warehouses.products.index', $warehouse->id)">
            <!-- DataGrid Shimmer -->
            <x-admin::shimmer.datagrid />
        </x-admin::datagrid>

        {!! view_render_event('admin.settings.warehouses.products.datagrid.after', ['warehouse' => $warehouse]) !!}
    </div>
</x-admin::layouts>
