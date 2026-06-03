<script setup lang="ts">
defineProps<{
    title: string;
}>();
</script>

<template>
    <h1
        class="w-full min-w-0 font-['Inter',sans-serif] text-[1.25rem] font-semibold not-italic leading-[1.625rem] text-white md:[word-break:break-word]"
    >
        {{ title }}
    </h1>
</template>
