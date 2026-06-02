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
        <span class="gameplay-capsule__label-wrap">
            <span class="gameplay-capsule__label">{{ label }}</span>
        </span>
    </button>
</template>

<style scoped>
.gameplay-capsule {
    --capsule-ease: cubic-bezier(0.22, 1, 0.36, 1);
    --capsule-duration: 0.45s;

    display: grid;
    grid-template-columns: 45px 0fr;
    align-items: center;
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
        grid-template-columns var(--capsule-duration) var(--capsule-ease),
        padding var(--capsule-duration) var(--capsule-ease),
        background-color 0.2s ease;
}

.gameplay-capsule__icon {
    display: grid;
    place-items: center;
    width: 45px;
    height: 45px;
}

.gameplay-capsule__label-wrap {
    min-width: 0;
    overflow: hidden;
}

.gameplay-capsule__label {
    display: block;
    padding-left: 4px;
    padding-right: 0;
    white-space: nowrap;
    font-size: 1.125rem;
    font-weight: 500;
    line-height: 1;
    letter-spacing: 0.02em;
    color: var(--color-primary-300);
    opacity: 0;
    transform: translateX(-10px);
    transition:
        opacity 0.32s ease,
        transform 0.38s var(--capsule-ease),
        padding 0.45s var(--capsule-ease);
}

.gameplay-capsule--active {
    background: rgba(84, 244, 218, 0.12);
}

@media (min-width: 768px) and (hover: hover) {
    .gameplay-capsule:hover {
        grid-template-columns: 45px 1fr;
        padding-right: 14px;
    }

    .gameplay-capsule:hover .gameplay-capsule__label {
        opacity: 1;
        transform: translateX(0);
        padding-right: 2px;
        transition:
            opacity 0.4s var(--capsule-ease) 0.08s,
            transform 0.5s var(--capsule-ease) 0.05s,
            padding 0.45s var(--capsule-ease);
    }

    .gameplay-capsule:hover.gameplay-capsule--active {
        background: rgba(84, 244, 218, 0.16);
    }
}

.gameplay-capsule:active {
    transform: scale(0.97);
    transition:
        grid-template-columns var(--capsule-duration) var(--capsule-ease),
        padding var(--capsule-duration) var(--capsule-ease),
        background-color 0.2s ease,
        transform 0.12s ease;
}

@media (min-width: 768px) and (hover: hover) {
    .gameplay-capsule:active {
        transform: none;
    }
}
</style>
