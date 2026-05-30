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
        class="block h-14 w-full rounded-xl bg-[#00c6de] px-6 font-['Inter',sans-serif] text-xl font-medium not-italic text-black transition-colors hover:bg-[#00b3c9] active:bg-[#009fb3]"
        @click="$emit('click')"
    >
        {{ label }}
    </button>
</template>
