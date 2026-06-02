<script setup lang="ts">
import { LucideX } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
}>();

const emit = defineEmits<{ close: [] }>();

const sheetEl = ref<HTMLElement | null>(null);
let touchStartY = 0;
let dragging = false;

function close() {
    emit('close');
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') close();
}

watch(
    () => props.open,
    (val) => {
        if (typeof document === 'undefined') return;
        if (val) {
            document.body.classList.add('overflow-hidden');
            window.addEventListener('keydown', onKeydown);
        } else {
            document.body.classList.remove('overflow-hidden');
            window.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    if (typeof document === 'undefined') return;
    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
});

function onDragStart(e: TouchEvent) {
    const target = e.target as HTMLElement;
    if (target.closest('button')) return;
    touchStartY = e.touches[0]!.clientY;
    dragging = true;
    if (sheetEl.value) sheetEl.value.style.transition = 'none';
}

function onDragMove(e: TouchEvent) {
    if (!dragging) return;
    const delta = e.touches[0]!.clientY - touchStartY;
    if (delta > 0 && sheetEl.value) {
        sheetEl.value.style.transform = `translateY(${delta}px)`;
    }
}

function onDragEnd(e: TouchEvent) {
    if (!dragging) return;
    dragging = false;
    const delta = e.changedTouches[0]!.clientY - touchStartY;
    if (sheetEl.value) {
        sheetEl.value.style.transition = '';
        sheetEl.value.style.transform = '';
    }
    if (delta > 80) close();
}
</script>

<template>
    <Teleport to="body">
        <Transition name="gp-sheet-overlay">
            <div
                v-if="open"
                class="gp-sheet-root fixed inset-0 z-[200] flex items-end justify-center md:items-end md:px-4 md:pb-6"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`gp-sheet-${title}`"
            >
                <!-- Backdrop: dim + blur, gameplay stays visible -->
                <div
                    class="gp-sheet-backdrop absolute inset-0 bg-black/45 backdrop-blur-[6px]"
                    @click="close"
                />

                <!-- Panel -->
                <Transition name="gp-sheet-panel" appear>
                    <div
                        v-if="open"
                        ref="sheetEl"
                        class="gp-sheet-panel relative z-10 flex w-full max-w-xl flex-col overflow-hidden will-change-transform"
                        @click.stop
                    >
                        <!-- Top accent glow -->
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 z-10 h-px bg-gradient-to-r from-transparent via-primary/60 to-transparent"
                            aria-hidden="true"
                        />

                        <!-- Header: drag zone + title + close -->
                        <div
                            class="gp-sheet-header shrink-0 cursor-grab touch-none active:cursor-grabbing md:cursor-default"
                            @touchstart.passive="onDragStart"
                            @touchmove.passive="onDragMove"
                            @touchend="onDragEnd"
                        >
                            <div class="flex flex-col items-center gap-3 px-5 pt-3 pb-2 md:px-6 md:pt-4">
                                <div class="h-1 w-10 rounded-full bg-white/25" aria-hidden="true" />
                                <div class="relative flex w-full items-center justify-center">
                                    <h2
                                        :id="`gp-sheet-${title}`"
                                        class="text-lg font-semibold tracking-tight text-gray-50 md:text-xl"
                                    >
                                        {{ title }}
                                    </h2>
                                    <button
                                        type="button"
                                        class="absolute right-0 grid size-9 place-items-center rounded-full border border-white/10 bg-white/5 text-gray-300 transition hover:border-primary/40 hover:bg-primary/10 hover:text-primary-300"
                                        aria-label="Close"
                                        @click="close"
                                    >
                                        <LucideX class="size-4" :stroke-width="2" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable content -->
                        <div class="gp-sheet-body min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-8 md:px-6 md:pb-10">
                            <slot />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.gp-sheet-panel {
    max-height: min(92svh, 900px);
    border-radius: 1.5rem 1.5rem 0 0;
    border: 1px solid rgba(84, 244, 218, 0.12);
    border-bottom: none;
    background: linear-gradient(
        165deg,
        rgba(18, 28, 32, 0.92) 0%,
        rgba(10, 14, 18, 0.96) 45%,
        rgba(8, 10, 14, 0.98) 100%
    );
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow:
        0 -12px 48px rgba(0, 0, 0, 0.55),
        0 0 0 1px rgba(84, 244, 218, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

@media (min-width: 768px) {
    .gp-sheet-panel {
        max-height: min(72vh, 720px);
        border-radius: 1.25rem;
        border-bottom: 1px solid rgba(84, 244, 218, 0.12);
    }
}

.gp-sheet-body {
    scrollbar-width: thin;
    scrollbar-color: rgba(84, 244, 218, 0.25) transparent;
}

.gp-sheet-body::-webkit-scrollbar {
    width: 4px;
}

.gp-sheet-body::-webkit-scrollbar-thumb {
    background: rgba(84, 244, 218, 0.25);
    border-radius: 2px;
}

/* Backdrop fade — 300ms */
.gp-sheet-overlay-enter-active,
.gp-sheet-overlay-leave-active {
    transition: opacity 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

.gp-sheet-overlay-enter-from,
.gp-sheet-overlay-leave-to {
    opacity: 0;
}

.gp-sheet-overlay-enter-active .gp-sheet-backdrop,
.gp-sheet-overlay-leave-active .gp-sheet-backdrop {
    transition: backdrop-filter 0.3s ease;
}

/* Panel slide-up + fade — 320ms */
.gp-sheet-panel-enter-active {
    transition:
        opacity 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.gp-sheet-panel-leave-active {
    transition:
        opacity 0.28s cubic-bezier(0.4, 0, 0.2, 1),
        transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
}

.gp-sheet-panel-enter-from {
    opacity: 0;
    transform: translateY(100%);
}

.gp-sheet-panel-leave-to {
    opacity: 0;
    transform: translateY(40%);
}

@media (min-width: 768px) {
    .gp-sheet-panel-enter-from {
        opacity: 0;
        transform: translateY(1.25rem);
    }

    .gp-sheet-panel-leave-to {
        opacity: 0;
        transform: translateY(0.75rem);
    }
}
</style>
