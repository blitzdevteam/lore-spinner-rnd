<script setup lang="ts">
import StoryPlayTabPair from '@/components/story-play/StoryPlayTabPair.vue';
import StoryPlayGlassRoundButton from '@/components/story-play/StoryPlayGlassRoundButton.vue';
import { LucideBookmark, LucideShare2 } from 'lucide-vue-next';

type Panel = 'details' | 'chapters';

defineProps<{
    tab: Panel;
    bookmarkFilled?: boolean;
}>();

const emit = defineEmits<{
    'update:tab': [value: Panel];
    bookmark: [];
    share: [];
}>();
</script>

<template>
    <div class="flex w-full max-w-[41.25rem] flex-wrap items-center justify-between gap-x-4 gap-y-4">
        <StoryPlayTabPair :model-value="tab" @update:model-value="emit('update:tab', $event)" />

        <div class="flex items-center gap-[0.625rem]">
            <StoryPlayGlassRoundButton aria-label="Share" @click="$emit('share')">
                <LucideShare2 class="!size-5" :stroke-width="1.65" aria-hidden="true" />
            </StoryPlayGlassRoundButton>
            <StoryPlayGlassRoundButton aria-label="Bookmark" @click="$emit('bookmark')">
                <LucideBookmark
                    class="size-5 text-primary-500 transition-colors"
                    :class="{ 'fill-primary-500 text-primary-500': bookmarkFilled }"
                    :stroke-width="1.65"
                    aria-hidden="true"
                />
            </StoryPlayGlassRoundButton>
        </div>
    </div>
</template>
