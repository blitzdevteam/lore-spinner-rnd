<script setup lang="ts">
import GameplayAtmosphere from '@/components/GameplayAtmosphere.vue';
import { provideStoryAtmosphere } from '@/composables/useStoryAtmosphere';
import { toRef } from 'vue';

const props = defineProps<{
    storyId?: number;
    coverUrl?: string | null;
}>();

provideStoryAtmosphere(toRef(props, 'storyId'), toRef(props, 'coverUrl'));
</script>

<template>
    <GameplayAtmosphere />
    <slot />
</template>
