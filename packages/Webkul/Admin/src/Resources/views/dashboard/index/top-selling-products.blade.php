{!! view_render_event('admin.dashboard.index.top_selling_proudcts.before') !!}

<!-- Top Selling Products Vue Component -->
<v-dashboard-top-selling-products>
    <!-- Shimmer -->
    <x-admin::shimmer.dashboard.index.top-selling-products />
</v-dashboard-top-selling-products>

{!! view_render_event('admin.dashboard.index.top_selling_proudcts.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-dashboard-top-selling-products-template"
    >
        <!-- Shimmer -->
        <template v-if="isLoading">
            <x-admin::shimmer.dashboard.index.top-selling-products />
        </template>

        <!-- Total Sales Section -->
        <template v-else>
            <div class="w-full rounded-lg border border-gray-300 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between p-4">
                    <p class="text-base font-semibold text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.top-selling-products.title')
                    </p>
                </div>

                <!-- Top Selling Products Details -->
                <div
                    class="flex flex-col"
                    v-if="report.statistics.length"
                >
                    <!--
                        Column headings for the two amounts every row carries. They reuse the
                        dashboard PDF's own headings for the same two numbers, and they mirror the
                        row's nesting so each one sits directly over the column it names.
                    -->
                    <div class="flex gap-2.5 border-b px-4 py-2 dark:border-gray-800">
                        <div class="flex w-full flex-col gap-1.5">
                            <div class="flex justify-between text-xs font-medium text-gray-500 dark:text-gray-400">
                                <p>@lang('admin::app.dashboard.index.pdf.price')</p>

                                <p>@lang('admin::app.dashboard.index.pdf.revenue')</p>
                            </div>
                        </div>
                    </div>

                    <a
                        :href="'{{ route('admin.products.view', ':id') }}'.replace(':id', item.id)"
                        class="flex gap-2.5 border-b p-4 transition-all last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950"
                        target="_blank"
                        v-for="item in report.statistics"
                    >
                        <!-- Product Details -->
                        <div class="flex w-full flex-col gap-1.5">
                            <p
                                class="text-gray-600 dark:text-gray-300"
                                v-text="item.name"
                            >
                            </p>

                            <div class="flex justify-between">
                                <p class="font-medium text-gray-800 dark:text-white">
                                    @{{ item.formatted_price }}
                                </p>

                                <p class="font-normal text-gray-800 dark:text-white">
                                    @{{ item.formatted_revenue }}
                                </p>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Empty Product Design -->
                <div
                    class="flex flex-col gap-8 p-4"
                    v-else
                >
                    <div class="grid justify-center justify-items-center gap-3.5 py-2.5">
                        <!-- Placeholder Image -->
                        <img
                            src="{{ vite()->asset('images/empty-placeholders/products.svg') }}"
                            class="dark:mix-blend-exclusion dark:invert"
                        >

                        <!-- Add Variants Information -->
                        <div class="flex flex-col items-center">
                            <p class="text-base font-semibold text-gray-400">
                                @lang('admin::app.dashboard.index.top-selling-products.empty-title')
                            </p>

                            <p class="text-gray-400">
                                @lang('admin::app.dashboard.index.top-selling-products.empty-info')
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-dashboard-top-selling-products', {
            template: '#v-dashboard-top-selling-products-template',

            data() {
                return {
                    report: [],

                    isLoading: true,
                }
            },

            mounted() {
                this.getStats({});

                this.$emitter.on('reporting-filter-updated', this.getStats);
            },

            methods: {
                getStats(filters) {
                    this.isLoading = true;

                    var filters = Object.assign({}, filters);

                    filters.type = 'top-selling-products';

                    this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                            params: filters
                        })
                        .then(response => {
                            this.report = response.data;

                            this.isLoading = false;
                        })
                        .catch(error => {});
                }
            }
        });
    </script>
@endPushOnce
