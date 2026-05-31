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
        class="block h-14 w-full rounded-xl bg-cta-fill px-6 font-['Inter',sans-serif] text-xl font-medium not-italic text-cta-text transition-colors hover:bg-cta-hover active:bg-cta-active"
        @click="$emit('click')"
    >
        {{ label }}
    </button>
</template>
