<script setup lang="ts">
type Panel = 'details' | 'chapters';

const props = defineProps<{
    modelValue: Panel;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Panel];
}>();

function select(value: Panel): void {
    emit('update:modelValue', value);
}

const tabs: { id: Panel; label: string }[] = [
    { id: 'details', label: 'Details' },
    { id: 'chapters', label: 'Chapters' },
];

function isActive(id: Panel): boolean {
    return props.modelValue === id;
}
</script>

<template>
    <!-- Top padding gives shadows/borders room without resizing tabs -->
    <div
        class="story-play-tablist flex gap-[0.625rem] pt-1"
        role="tablist"
        aria-label="Story sections"
    >
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="isActive(tab.id)"
            class="story-play-tab relative z-0 box-border flex h-[2.8125rem] w-[7.8125rem] shrink-0 items-center justify-center rounded-[1.6875rem] border border-solid px-2 outline-none transition-[transform,box-shadow,background-color,border-color,color] duration-200 ease-out focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 focus-visible:ring-offset-black"
            :class="isActive(tab.id) ? 'story-play-tab--active' : 'story-play-tab--inactive'"
            @click="select(tab.id)"
        >
            <span
                class="font-['Inter',sans-serif] text-[1rem] font-medium not-italic leading-[1.1859rem]"
            >
                {{ tab.label }}
            </span>
        </button>
    </div>
</template>

<style scoped>
.story-play-tablist {
    /* Prevent flex parents from clipping painted shadows at the capsule edge */
    overflow: visible;
}

.story-play-tab {
    -webkit-font-smoothing: antialiased;
    /* Single-layer capsule — no overflow:hidden (avoids flat/clipped top arc) */
    overflow: visible;
    background-clip: padding-box;
}

/* Inactive */
.story-play-tab--inactive {
    border-color: rgba(255, 255, 255, 0.06);
    background: rgba(255, 255, 255, 0.03);
    color: rgba(180, 180, 180, 0.85);
    box-shadow: none;
}

.story-play-tab--inactive:hover {
    transform: translateY(-1px);
    border-color: rgba(111, 175, 186, 0.28);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.92);
    box-shadow:
        0 1px 3px rgba(0, 0, 0, 0.28),
        0 0 8px rgba(111, 175, 186, 0.1);
}

/* Active — border + fill are primary; shadow stays tight and low */
.story-play-tab--active {
    border-color: rgba(111, 175, 186, 0.55);
    background: linear-gradient(
        180deg,
        rgba(30, 63, 70, 0.92) 0%,
        rgba(22, 47, 54, 0.96) 100%
    );
    color: #fff;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.06),
        0 1px 2px rgba(0, 0, 0, 0.35),
        0 2px 8px rgba(0, 0, 0, 0.22),
        0 0 12px rgba(111, 175, 186, 0.12);
}

.story-play-tab--active:hover {
    transform: none;
    border-color: rgba(111, 175, 186, 0.62);
    background: linear-gradient(
        180deg,
        rgba(32, 68, 76, 0.94) 0%,
        rgba(24, 52, 58, 0.98) 100%
    );
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.07),
        0 1px 2px rgba(0, 0, 0, 0.35),
        0 2px 8px rgba(0, 0, 0, 0.22),
        0 0 12px rgba(111, 175, 186, 0.16);
}
</style>
