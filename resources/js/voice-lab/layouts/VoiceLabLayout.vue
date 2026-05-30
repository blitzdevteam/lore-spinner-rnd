<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import { LucideChevronLeft } from 'lucide-vue-next';

const emit = defineEmits<{
    back: [];
}>();
</script>

<template>
    <div class="xen-layout relative h-svh overflow-hidden bg-gray-950">
        <!-- Background inherits xen primary-400 tiffany tint via CSS var override -->
        <BaseBackgroundGradient />

        <div class="relative flex h-full flex-col">
            <!-- Top bar -->
            <div class="z-10 flex h-20 shrink-0 items-center justify-between px-4 sm:px-8 md:h-28 lg:px-24">
                <div class="flex-1">
                    <BaseButton severity="glass" :icon-only="true" class="xen-back-btn size-12!" @click="emit('back')">
                        <LucideChevronLeft class="size-8" :stroke-width="1.5" />
                    </BaseButton>
                </div>

                <div class="flex-3 text-center">
                    <slot name="header">
                        <h1 class="xen-title text-xl uppercase tracking-[0.4em] md:text-3xl">XEN</h1>
                    </slot>
                </div>

                <div class="flex flex-1 items-center justify-end gap-3">
                    <slot name="actions" />
                </div>
            </div>

            <!-- Centered orb area -->
            <div class="relative flex flex-1 flex-col items-center justify-center">
                <slot name="orb" />
            </div>

            <!-- Bottom controls -->
            <div v-if="$slots.controls" class="z-10 shrink-0 px-4 pb-8 md:px-0">
                <div class="mx-auto max-w-lg">
                    <slot name="controls" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/*
 * Layout-level tiffany tokens.
 * These are the CSS vars set on .xen-tint in Index.vue; the layout inherits
 * them through normal cascade since Index wraps this component.
 * Fallbacks use the tiffany anchors directly so the layout looks correct
 * even if rendered standalone.
 */

/* XEN title — tiffany gradient lettering */
.xen-title {
    background: linear-gradient(
        135deg,
        var(--color-primary-200, #8beee8) 0%,
        var(--color-primary-400, #2bd9d0) 40%,
        var(--color-primary-600, #089490) 100%
    );
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    filter: drop-shadow(0 0 18px color-mix(in srgb, var(--color-primary-500, #00c6de) 35%, transparent));
}

/* Back button — tiffany ring on hover */
.xen-back-btn {
    color: var(--color-primary-300, #5fdbf0) !important;
    border-color: color-mix(in srgb, var(--color-primary-500, #00c6de) 25%, transparent) !important;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.xen-back-btn:hover {
    border-color: color-mix(in srgb, var(--color-primary-400, #2bd9d0) 55%, transparent) !important;
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-primary-500, #00c6de) 20%, transparent),
                0 0 12px color-mix(in srgb, var(--color-primary-500, #00c6de) 15%, transparent);
}
</style>
