<script setup lang="ts">
defineProps<{
    title: string;
    themes?: string[];
}>();
</script>

<template>
    <div class="flex w-full min-w-0 flex-col gap-1">
        <h1
            class="font-['Inter',sans-serif] text-[1.25rem] font-semibold not-italic leading-[1.625rem] text-white md:[word-break:break-word]"
        >
            {{ title }}
        </h1>
        <div v-if="themes?.length" class="flex flex-wrap items-center gap-1">
            <template v-for="(theme, i) in themes" :key="theme">
                <span
                    v-if="i > 0"
                    class="text-[0.625rem] leading-none text-white/28"
                    aria-hidden="true"
                >•</span>
                <span
                    class="font-['Inter',sans-serif] text-[0.8125rem] font-medium not-italic leading-[1.375rem] text-primary"
                >
                    {{ theme }}
                </span>
            </template>
        </div>
    </div>
</template>
