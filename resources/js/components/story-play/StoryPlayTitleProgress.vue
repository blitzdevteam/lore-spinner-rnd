<script setup lang="ts">
defineProps<{
    title: string;
    seriesLabel?: string | null;
}>();
</script>

<template>
    <div class="flex w-full min-w-0 flex-col gap-1">
        <h1
            class="font-['Inter',sans-serif] text-[1.25rem] font-semibold not-italic leading-[1.625rem] text-white md:[word-break:break-word]"
        >
            {{ title }}
        </h1>
        <p
            v-if="seriesLabel"
            class="font-['Inter',sans-serif] text-[0.8125rem] font-medium not-italic leading-[1.375rem] text-gray-400"
        >
            {{ seriesLabel }}
        </p>
    </div>
</template>
