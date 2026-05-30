<script setup lang="ts">
defineProps<{
    expanded: boolean;
    dimmed: boolean;
    desktopExpand: boolean;
}>();
</script>

<template>
    <div
        class="story-card-slot"
        :class="[
            desktopExpand && expanded && 'story-card-slot--focused',
            desktopExpand && dimmed && 'story-card-slot--dimmed',
        ]"
    >
        <slot />
    </div>
</template>

<style scoped>
.story-card-slot {
    position: relative;
    flex-shrink: 0;
    transform-origin: center center;
    transition:
        transform 0.2s ease,
        opacity 0.2s ease,
        filter 0.2s ease;
}

.story-card-slot--dimmed {
    opacity: 0.65;
    filter: brightness(0.72);
}

@media (min-width: 1024px) {
    .story-card-slot--focused {
        z-index: 25;
        transform: scale(1.06);
    }
}
</style>
