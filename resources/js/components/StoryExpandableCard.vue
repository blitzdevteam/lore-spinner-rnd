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
            desktopExpand && expanded && 'story-card-slot--raised',
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
    transition: opacity 0.22s ease;
}

.story-card-slot--dimmed {
    opacity: 0.88;
}

@media (min-width: 1024px) {
    .story-card-slot--raised {
        z-index: 25;
    }
}
</style>
