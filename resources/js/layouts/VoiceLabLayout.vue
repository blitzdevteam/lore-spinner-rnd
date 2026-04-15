<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import { LucideChevronLeft } from 'lucide-vue-next';

const emit = defineEmits<{
    back: [];
}>();
</script>

<template>
    <div class="relative h-svh overflow-hidden bg-gray-950">
        <BaseBackgroundGradient />

        <div class="relative flex h-full flex-col">
            <!-- Top bar -->
            <div class="z-10 flex h-20 shrink-0 items-center justify-between px-4 sm:px-8 md:h-28 lg:px-24">
                <div class="flex-1">
                    <BaseButton severity="glass" :icon-only="true" class="size-12!" @click="emit('back')">
                        <LucideChevronLeft class="size-8 text-gray-50" :stroke-width="1.5" />
                    </BaseButton>
                </div>
                <div class="flex-3 text-center">
                    <slot name="header">
                        <h1 class="text-xl uppercase tracking-wider md:text-3xl">Voice Lab</h1>
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

            <!-- Bottom controls (if needed) -->
            <div v-if="$slots.controls" class="z-10 shrink-0 px-4 pb-8 md:px-0">
                <div class="mx-auto max-w-lg">
                    <slot name="controls" />
                </div>
            </div>
        </div>
    </div>
</template>
