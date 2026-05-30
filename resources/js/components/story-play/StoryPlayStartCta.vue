<script setup lang="ts">
defineProps<{
    label: string;
}>();

defineEmits<{
    click: [];
}>();
</script>

<template>
    <button
        type="button"
        class="flex h-[56px] w-full items-center justify-center overflow-hidden rounded-xl bg-cta-fill px-6 font-['Inter',sans-serif] text-[20px] font-medium not-italic text-cta-text transition-colors hover:bg-cta-hover active:bg-cta-active"
        @click="$emit('click')"
    >
        {{ label }}
    </button>
</template>
