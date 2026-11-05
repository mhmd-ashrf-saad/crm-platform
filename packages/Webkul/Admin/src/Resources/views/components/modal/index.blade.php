@props([
    'isActive' => false,
    'position' => 'center',
    'size' => 'normal',
])

<v-modal
    is-active="{{ $isActive }}"
    position="{{ $position }}"
    size="{{ $size }}"
    {{ $attributes }}
>
    @isset($toggle)
        <template v-slot:toggle>
            {{ $toggle }}
        </template>
    @endisset

    @isset($header)
        <template v-slot:header="{ toggle, isOpen }">
            {{--
                The header is a fixed row of the panel (`shrink-0`), and the close control is anchored
                to the panel's physical right corner in both reading directions, exactly like the filter
                drawer's (`components/drawer/index.blade.php`): riding the end of the flex row put it on
                the physical *left* in RTL, the opposite corner from the drawer's. `pr-10` reserves the
                corner the control occupies so header content never runs under it; it is a physical
                reserve, matching the control's physical position.
            --}}
            <div {{ $header->attributes->merge(['class' => 'relative flex w-full shrink-0 items-center justify-between gap-2.5 border-b px-4 py-3 pr-10 dark:border-gray-800']) }}>
                {{ $header }}

                <div class="absolute inset-y-0 right-2 flex items-center">
                    <span
                        class="icon-cross-large cursor-pointer text-3xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                        @click="toggle"
                    >
                    </span>
                </div>
            </div>
        </template>
    @endisset

    @isset($content)
        <template v-slot:content>
            {{--
                The body is the panel's only growing, scrolling row: `min-h-0` lets it shrink below its
                content height and it hands the overflow to its own scrollbar, so the header and footer
                rows above and below it never move.

                `w-full` on all three rows is what keeps them full width now that the panel is a flex
                column: the centred positions put `items-center` on the panel, and a stretched row is no
                longer the default once flex is in play.
            --}}
            <div {{ $content->attributes->merge(['class' => 'min-h-0 w-full flex-1 overflow-y-auto border-b px-4 py-2.5 dark:border-gray-800']) }}>
                {{ $content }}
            </div>
        </template>
    @endisset

    @isset($footer)
        <template v-slot:footer>
            <div {{ $footer->attributes->merge(['class' => 'flex w-full shrink-0 justify-end px-4 py-2.5']) }}>
                {{ $footer }}
            </div>
        </template>
    @endisset
</v-modal>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-modal-template"
    >
        <div>
            <div @click="toggle">
                <slot name="toggle">
                </slot>
            </div>

            <transition
                tag="div"
                name="modal-overlay"
                enter-class="duration-300 ease-[cubic-bezier(.4,0,.2,1)]"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-class="duration-200 ease-[cubic-bezier(.4,0,.2,1)]"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    class="fixed inset-0 z-[10002] bg-gray-500 bg-opacity-50 transition-opacity"
                    v-show="isOpen"
                ></div>
            </transition>

            <transition
                tag="div"
                name="modal-content"
                enter-class="duration-300 ease-[cubic-bezier(.4,0,.2,1)]"
                :enter-from-class="enterFromLeaveToClasses"
                enter-to-class="translate-y-0 opacity-100"
                leave-class="duration-300 ease-[cubic-bezier(.4,0,.2,1)]"
                leave-from-class="translate-y-0 opacity-100"
                :leave-to-class="enterFromLeaveToClasses"
            >
                <div
                    class="fixed inset-0 z-[10003] transform overflow-y-auto transition"
                    v-if="isOpen"
                >
                    <div class="flex min-h-full items-center justify-center max-md:p-4">
                        {{--
                            Tall modals: the panel is a bounded flex column and its body is the only
                            scroller, so the title bar and the close control cannot scroll away.

                            The panel is pinned to the viewport (bottom-4 / centred) and had no height of
                            its own, so a tall modal grew upward past the top of the viewport, where the
                            surrounding scroller cannot follow -- measured at a 400px window, the mail
                            composer's panel top sat at -324 and neither its header nor its first field
                            was reachable. Capping the panel against the viewport fixed that but made the
                            panel itself the scroller, so scrolling down to the footer carried the title
                            and the close control off the top of the screen: at the same 400px window the
                            header sat at -155 and the way out of the modal was gone.

                            So the panel keeps its cap (`max-h-[80vh]`) but clips rather than scrolls
                            (`overflow-hidden` + `flex flex-col`), the body row is the scroller
                            (`min-h-0 flex-1 overflow-y-auto`) and the header and footer rows are
                            `shrink-0`. A modal shorter than the viewport has nothing to scroll, so only
                            its header height changes: the close control no longer contributes its 30px
                            line box to a 42px title row, which makes a normal-height panel 13px shorter
                            (measured on the tags modal at 800x400: 260px tall before, 247px after, body
                            unchanged at 151px).

                            Every utility used here is already in the built stylesheet; no asset rebuild
                            was available to this fix.
                        --}}
                        <div
                            class="box-shadow z-[999] flex max-h-[80vh] w-full flex-col overflow-hidden rounded-lg bg-white dark:bg-gray-900 sm:absolute"
                            :class="[finalPositionClass, sizeClass]"
                        >
                            <!-- Header Slot -->
                            <slot
                                name="header"
                                :toggle="toggle"
                                :isOpen="isOpen"
                            >
                            </slot>

                            <!-- Content Slot -->
                            <slot name="content"></slot>
                            
                            <!-- Footer Slot -->
                            <slot name="footer"></slot>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </script>

    <script type="module">
        app.component('v-modal', {
            template: '#v-modal-template',

            props: [
                'isActive',
                'position',
                'size'
            ],

            emits: [
                'toggle',
                'open',
                'close',
            ],

            data() {
                return {
                    isOpen: this.isActive,

                    isMobile: window.innerWidth < 640,
                };
            },

            created() {
                window.addEventListener('resize', this.checkScreenSize);
            },

            beforeUnmount() {
                window.removeEventListener('resize', this.checkScreenSize);
            },

            computed: {
                positionClass() {
                    return {
                        'center': 'items-center justify-center',
                        'top-center': 'top-4',
                        'bottom-center': 'bottom-4',
                        'bottom-right': 'bottom-4 right-4',
                        'bottom-left': 'bottom-4 left-4',
                        'top-right': 'top-4 right-4',
                        'top-left': 'top-4 left-4',
                    }[this.position];
                },

                finalPositionClass() {
                    return this.isMobile 
                        ? 'items-center justify-center' 
                        : this.positionClass;
                },

                sizeClass() {
                    return {
                        'normal': 'max-w-[525px]',
                        'medium': 'max-w-[768px]',
                        'large': 'max-w-[950px]',
                    }[this.size] || 'max-w-[525px]';
                },

                enterFromLeaveToClasses() {
                    const effectivePosition = this.isMobile ? 'center' : this.position;
                    
                    return {
                        'center': '-translate-y-4 opacity-0',
                        'top-center': '-translate-y-4 opacity-0',
                        'bottom-center': 'translate-y-4 opacity-0',
                        'bottom-right': 'translate-y-4 opacity-0',
                        'bottom-left': 'translate-y-4 opacity-0',
                        'top-right': '-translate-y-4 opacity-0',
                        'top-left': '-translate-y-4 opacity-0',
                    }[effectivePosition];
                }
            },

            methods: {
                checkScreenSize() {
                    this.isMobile = window.innerWidth < 640;
                },
                
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
            }
        });
    </script>
@endPushOnce