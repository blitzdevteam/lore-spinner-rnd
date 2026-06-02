<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        title: string;
        active?: boolean;
    }>(),
    { active: false },
);

defineEmits<{
    click: [];
}>();
</script>

<template>
    <button
        type="button"
        class="gameplay-capsule"
        :class="{ 'gameplay-capsule--active': active }"
        :title="title"
        :aria-label="title"
        @click="$emit('click')"
    >
        <span class="gameplay-capsule__icon" aria-hidden="true">
            <slot />
        </span>
        <span class="gameplay-capsule__label">{{ label }}</span>
    </button>
</template>

<style scoped>
.gameplay-capsule {
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    width: 45px;
    height: 45px;
    padding: 0;
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    flex-shrink: 0;
    overflow: hidden;
    background-color: rgba(51, 51, 51, 0.45);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.5),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.55),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.3),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.3),
        inset 0 0 1px 1px rgba(153, 153, 153, 0.15),
        0 4px 24px rgba(0, 0, 0, 0.3);
    transition:
        width 0.28s cubic-bezier(0.4, 0, 0.2, 1),
        padding 0.28s cubic-bezier(0.4, 0, 0.2, 1),
        background-color 0.15s ease;
}

.gameplay-capsule__icon {
    display: grid;
    place-items: center;
    width: 45px;
    height: 45px;
    flex-shrink: 0;
}

.gameplay-capsule__label {
    max-width: 0;
    opacity: 0;
    overflow: hidden;
    white-space: nowrap;
    font-size: 0;
    font-weight: 500;
    line-height: 1;
    letter-spacing: 0.02em;
    color: var(--color-primary-300);
    padding-right: 0;
    transition:
        max-width 0.28s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.2s ease,
        padding 0.28s cubic-bezier(0.4, 0, 0.2, 1),
        font-size 0.1s ease;
}

.gameplay-capsule--active {
    background: rgba(84, 244, 218, 0.12);
}

/* Desktop hover only: expand capsule and reveal label (Figma 7667:671) */
@media (min-width: 768px) and (hover: hover) {
    .gameplay-capsule:hover {
        width: auto;
        padding-right: 14px;
    }

    .gameplay-capsule:hover .gameplay-capsule__label {
        max-width: 14rem;
        opacity: 1;
        font-size: 1.375rem;
        padding-left: 2px;
    }

    .gameplay-capsule:hover.gameplay-capsule--active {
        background: rgba(84, 244, 218, 0.16);
    }
}

.gameplay-capsule:active {
    transform: scale(0.97);
}

@media (min-width: 768px) and (hover: hover) {
    .gameplay-capsule:active {
        transform: none;
    }
}
</style>
