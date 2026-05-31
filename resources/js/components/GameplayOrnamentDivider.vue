<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        color?: string;
    }>(),
    { color: '#ffffff' },
);
</script>

<template>
    <div class="flex w-full items-center justify-center gap-1.5 sm:gap-2">
        <span class="ornament-line" :style="{ '--ornament-color': color }" />
        <span class="ornament-diamond" :style="{ backgroundColor: color }" />
        <span
            class="shrink-0 px-1 text-center text-sm font-normal tracking-wide sm:whitespace-nowrap sm:px-0 sm:text-base"
            :style="{ color }"
        >
            {{ label }}
        </span>
        <span class="ornament-diamond" :style="{ backgroundColor: color }" />
        <span class="ornament-line ornament-line--rtl" :style="{ '--ornament-color': color }" />
    </div>
</template>

<style scoped>
.ornament-line {
    height: 1px;
    flex: 1 1 0;
    max-width: min(232px, 28vw);
    background: linear-gradient(to right, transparent, color-mix(in srgb, var(--ornament-color) 55%, transparent));
}

.ornament-line--rtl {
    background: linear-gradient(to left, transparent, color-mix(in srgb, var(--ornament-color) 55%, transparent));
}

.ornament-diamond {
    width: 5px;
    height: 5px;
    flex-shrink: 0;
    transform: rotate(45deg);
    opacity: 0.9;
}
</style>
