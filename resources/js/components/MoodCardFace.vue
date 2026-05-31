<script setup lang="ts">
import type { MoodCardConfig } from '@/data/moodCards';
import type { MoodId } from '@/data/moodBanners';

defineProps<{
    moodId: MoodId;
    config: MoodCardConfig;
    isEpicIcon: boolean;
}>();
</script>

<template>
    <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
        <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
        <div
            class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
        />
    </div>
    <div
        class="mood-card-glow pointer-events-none absolute inset-0 z-[1] rounded-lg"
        :class="`mood-card-glow--${moodId}`"
        aria-hidden="true"
    />
    <div
        class="mood-icon-orbit absolute left-1/2 top-[1.0625rem] z-[2] size-[4.75rem] -translate-x-1/2 overflow-hidden rounded-full"
    >
        <div class="mood-icon-orbit-bg pointer-events-none absolute inset-0 rounded-full" aria-hidden="true">
            <div class="absolute inset-0 rounded-full bg-[rgba(255,175,175,0.03)]" />
            <div
                class="mood-icon-orbit-tint absolute inset-0 rounded-full backdrop-blur-[2.25px] mix-blend-plus-lighter"
                :class="`mood-icon-orbit-tint--${moodId}`"
            />
        </div>
        <div class="absolute inset-0 flex items-center justify-center">
            <div
                v-if="isEpicIcon"
                class="mood-card__icon mood-card__icon--epic flex size-8 items-center justify-center"
                :class="`mood-card__icon--${moodId}`"
            >
                <img
                    :src="config.icon"
                    alt=""
                    width="32"
                    height="32"
                    class="block size-full max-w-none"
                />
            </div>
            <img
                v-else
                :src="config.icon"
                alt=""
                width="32"
                height="32"
                class="mood-card__icon block size-8 max-w-none object-contain"
                :class="`mood-card__icon--${moodId}`"
            />
        </div>
        <div
            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
        />
    </div>
    <p
        class="mood-card__label absolute bottom-[1.125rem] left-1/2 z-[2] -translate-x-1/2 whitespace-nowrap text-center text-[0.9375rem] font-medium capitalize leading-none not-italic"
        :class="`mood-card__label--${moodId}`"
    >
        {{ config.label }}
    </p>
    <div
        class="pointer-events-none absolute inset-0 z-[3] rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
    />
</template>
