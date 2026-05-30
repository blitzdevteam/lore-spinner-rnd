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
        class="flex h-[56px] w-full items-center justify-center overflow-hidden rounded-xl bg-primary-500 px-6 font-['Inter',sans-serif] text-[20px] font-medium not-italic text-black transition-opacity hover:opacity-95 active:opacity-90"
        @click="$emit('click')"
    >
        {{ label }}
    </button>
</template>
