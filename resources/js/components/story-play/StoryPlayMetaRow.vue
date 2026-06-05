<script setup lang="ts">
import { LucideClock } from 'lucide-vue-next';

defineProps<{
    durationLabel?: string | null;
}>();
</script>

<template>
    <div class="flex flex-wrap items-end gap-[0.5rem]">
        <div
            v-if="durationLabel"
            class="flex h-[1.375rem] w-auto min-w-[4.0625rem] items-center justify-start gap-[0.3307rem]"
        >
            <LucideClock class="size-[1.0313rem] shrink-0 text-[#373737]" :stroke-width="2" aria-hidden="true" />
            <span
                class="font-['Inter',sans-serif] text-[0.8125rem] font-semibold not-italic leading-[1.375rem] text-[#373737]"
            >
                {{ durationLabel }}
            </span>
        </div>
    </div>
</template>
