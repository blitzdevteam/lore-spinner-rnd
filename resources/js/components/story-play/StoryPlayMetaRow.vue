<script setup lang="ts">
import { LucideClock } from 'lucide-vue-next';

defineProps<{
    durationLabel?: string | null;
    genreLabel?: string | null;
}>();
</script>

<template>
    <div class="flex flex-wrap items-end gap-[8px]">
        <div
            v-if="durationLabel"
            class="flex h-[22px] w-auto min-w-[65px] items-center justify-start gap-[5.291px]"
        >
            <LucideClock class="size-[16.5px] shrink-0 text-[#373737]" :stroke-width="2" aria-hidden="true" />
            <span
                class="font-['Inter',sans-serif] text-[13px] font-semibold not-italic leading-[22px] text-[#373737]"
            >
                {{ durationLabel }}
            </span>
        </div>
        <div
            v-if="genreLabel"
            class="flex h-[22px] w-auto min-w-0 items-center justify-start gap-[5.291px]"
        >
            <span class="inline-block size-[16px] shrink-0 rounded-sm border-[1.25px] border-[#373737]" aria-hidden="true" />
            <span
                class="min-w-0 font-['Inter',sans-serif] text-[13px] font-semibold not-italic leading-[22px] text-[#373737]"
            >
                {{ genreLabel }}
            </span>
        </div>
    </div>
</template>
