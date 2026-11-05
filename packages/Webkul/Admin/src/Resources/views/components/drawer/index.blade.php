@props([
    'isActive' => false,

    /**
     * The side the panel is pinned to. `right` is the *physical* right edge, in a left-to-right
     * interface and in a right-to-left one alike: an administrative side panel that a reader
     * opens deliberately belongs on the right in Arabic too. `left` keeps mirroring with the
     * reading direction (left in LTR, right in RTL), which is what the mobile navigation sidebar
     * wants.
     */
    'position' => 'right',

    'width' => '500px',
])

<v-drawer
    {{ $attributes }}
    is-active="{{ $isActive }}"
    position="{{ $position }}"
    width="{{ $width }}"
>
    @isset($toggle)
        <template v-slot:toggle>
            {{ $toggle }}
        </template>
    @endisset

    @isset($header)
        <template v-slot:header="{ close }">
            {{--
                `pr-10` reserves the corner the close control is anchored to, so header content never runs
                under it. It is a physical (not mirrored) reserve because the control is on the physical
                right in both directions, and it replaces the old `max-sm:px-4`, whose right padding would
                have overridden the reserve on small screens.
            --}}
            <div {{ $header->attributes->merge(['class' => 'relative flex justify-between items-center gap-y-2.5 border-b p-3 pr-10 dark:border-gray-800']) }}>
                {{ $header }}

                <!--
                    The panel is pinned to the physical right edge, so the close control is too, in
                    both reading directions. It sits outside the flex flow, anchored to the panel
                    corner -- riding the end of the row would put it on the left in RTL.
                -->
                <div class="absolute inset-y-0 right-2 flex items-center">
                    <span
                        class="icon-cross-large cursor-pointer text-3xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                        @click="close"
                    >
                    </span>
                </div>
            </div>
        </template>
    @endisset

    @isset($content)
        <template v-slot:content>
            <div {{ $content->attributes->merge(['class' => 'flex-1 overflow-auto p-3 max-sm:px-4']) }}>
                {{ $content }}
            </div>
        </template>
    @endisset

    @isset($footer)
        <template v-slot:footer>
            <div {{ $footer->attributes->merge(['class' => 'pb-8']) }}>
                {{ $footer }}
            </div>
        </template>
    @endisset
</v-drawer>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-drawer-template"
    >
        <div>
            <!-- Toggler -->
            <div @click="open">
                <slot name="toggle">
                </slot>
            </div>

            <!-- Overlay -->
            <transition
                tag="div"
                name="drawer-overlay"
                enter-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    class="fixed inset-0 z-[10002] bg-gray-500 bg-opacity-50 transition-opacity"
                    v-show="isOpen"
                ></div>
            </transition>

            <!-- Content -->
            <transition
                tag="div"
                name="drawer"
                :enter-from-class="enterFromLeaveToClasses"
                enter-active-class="transform transition duration-200 ease-in-out"
                enter-to-class="translate-x-0"
                leave-from-class="translate-x-0"
                leave-active-class="transform transition duration-200 ease-in-out"
                :leave-to-class="enterFromLeaveToClasses"
            >
                <div
                    class="fixed z-[10003] m-3 rounded-lg bg-white dark:bg-gray-900 max-sm:!w-[calc(100%-24px)]"
                    :class="{
                        'inset-x-0 top-0': position == 'top',
                        'inset-x-0 bottom-0': position == 'bottom',
                        // `right` pins the panel to the physical right edge in both directions, so the
                        // RTL side is stated rather than left to be inferred from a missing flip.
                        'inset-y-0 ltr:right-0 rtl:right-0': position == 'right',
                        'inset-y-0 ltr:left-0 rtl:right-0': position == 'left'
                    }"
                    :style="'width:' + width"
                    v-if="isOpen"
                >
                    <div class="pointer-events-auto h-full w-full overflow-auto rounded-lg bg-white dark:bg-gray-900">
                        <div class="flex h-full w-full flex-col">
                            <div class="min-h-0 min-w-0 flex-1 overflow-auto">
                                <div class="flex h-full flex-col">
                                    <!-- Header Slot-->
                                    <slot
                                        name="header"
                                        :close="close"
                                    >
                                    </slot>

                                    <!-- Content Slot -->
                                    <slot name="content"></slot>

                                    <!-- Footer Slot -->
                                    <slot name="footer"></slot>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </script>

    <script type="module">
        app.component('v-drawer', {
            template: '#v-drawer-template',

            props: [
                'isActive',
                'position',
                'width'
            ],

            emits: [
                'toggle',
                'open',
                'close',
            ],

            data() {
                return {
                    isOpen: this.isActive,
                };
            },

            watch: {
                isActive: function(newVal, oldVal) {
                    this.isOpen = newVal;
                }
            },

            computed: {
                enterFromLeaveToClasses() {
                    if (this.position == 'top') {
                        return '-translate-y-full';
                    } else if (this.position == 'bottom') {
                        return 'translate-y-full';
                    } else if (this.position == 'left') {
                        return 'ltr:-translate-x-full rtl:translate-x-full';
                    } else if (this.position == 'right') {
                        /**
                         * A panel pinned to the right edge slides in from the right in both directions.
                         * `rtl:-translate-x-full` would have slid it in from the left in Arabic.
                         */
                        return 'ltr:translate-x-full rtl:translate-x-full';
                    }
                }
            },

            methods: {
                toggle() {
                    this.isOpen = ! this.isOpen;

                    if (this.isOpen) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow ='auto';
                    }

                    this.$emit('toggle', { isActive: this.isOpen });
                },

                open() {
                    this.isOpen = true;

                    document.body.style.overflow = 'hidden';

                    this.$emit('open', { isActive: this.isOpen });
                },

                close() {
                    this.isOpen = false;

                    document.body.style.overflow = 'auto';

                    this.$emit('close', { isActive: this.isOpen });
                }
            },
        });
    </script>
@endPushOnce