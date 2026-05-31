<script setup lang="ts">
import MoodCardFace from '@/components/MoodCardFace.vue';
import { MOOD_CARD_CONFIG_BY_ID } from '@/data/moodCards';
import type { MoodId } from '@/data/moodBanners';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    moodId: MoodId;
    active?: boolean;
    href?: string;
}>();

const emit = defineEmits<{ click: [] }>();

const config = computed(() => MOOD_CARD_CONFIG_BY_ID[props.moodId]);

const cardClass = computed(
    () =>
        [
            'mood-card',
            `mood-card--${props.moodId}`,
            'relative z-0 flex h-[9.25rem] w-[12.1875rem] shrink-0 flex-col items-center overflow-hidden rounded-lg border-0 bg-transparent p-0 text-left transition-[transform] duration-200 hover:scale-[1.02] active:scale-[0.99]',
            props.active ? 'mood-card--active' : '',
            props.href ? 'no-underline' : 'cursor-pointer',
        ].join(' '),
);

const isEpicIcon = computed(() => props.moodId === 'epic');
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="cardClass"
        :aria-current="active ? 'page' : undefined"
        preserve-scroll
    >
        <MoodCardFace :mood-id="moodId" :config="config" :is-epic-icon="isEpicIcon" />
    </Link>
    <button
        v-else
        type="button"
        :class="cardClass"
        :aria-pressed="active"
        @click="emit('click')"
    >
        <MoodCardFace :mood-id="moodId" :config="config" :is-epic-icon="isEpicIcon" />
    </button>
</template>
