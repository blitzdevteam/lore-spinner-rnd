<script setup lang="ts">
import { ChapterInterface } from '@/types';
import { ChapterStatusEnum } from '@/types/enum';
import chapterPlaceholder from '@/assets/temp/chapter.png';
import { LucideCheck, LucideLock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    chapter: ChapterInterface;
    /** 1-based episode index for display (Episode N). */
    episodeNumber: number;
}>();

const coverImage = computed(() => props.chapter.cover || chapterPlaceholder);

const isPlayable = computed(
    () => props.chapter.status?.value === ChapterStatusEnum.READY_TO_PLAY,
);

const isLocked = computed(() => !isPlayable.value);

const episodeLabel = computed(() => {
    const n = props.chapter.position ?? props.episodeNumber;
    return `Episode ${n}`;
});

const eventsLabel = computed(() => {
    const count = props.chapter.events_count;
    if (count == null) {
        return null;
    }
    return `Events ${count}`;
});
</script>

<template>
    <article
        class="rounded-lg border border-gray-700 bg-[#1c1c1c] p-1.5"
    >
        <div class="flex items-center gap-2">
            <div class="relative h-[7.25rem] w-[4.9375rem] shrink-0 overflow-hidden rounded-md bg-gray-700">
                <img
                    :src="coverImage"
                    alt=""
                    class="size-full object-cover"
                />
            </div>

            <div class="flex min-h-[6.9375rem] min-w-0 flex-1 flex-col items-end">
                <div class="flex w-full flex-col gap-1.5">
                    <div class="flex w-full flex-col">
                        <div class="flex h-6 items-center justify-between gap-2">
                            <h3
                                class="truncate font-['Inter',sans-serif] text-[0.9375rem] font-semibold leading-normal"
                                :class="isLocked ? 'text-secondary-300/20' : isPlayable ? 'text-white' : 'text-secondary-300'"
                            >
                                {{ episodeLabel }}
                            </h3>
                            <span
                                class="inline-flex h-6 shrink-0 items-center rounded-full bg-[rgba(255,255,255,0.04)] px-2.5 font-['Inter',sans-serif] text-xs font-medium backdrop-blur-[3px]"
                                :class="isLocked ? 'text-secondary-300/20' : 'text-secondary-300'"
                                style="box-shadow: 0 4px 80px rgba(0, 0, 0, 0.2)"
                            >
                                100 Spins
                            </span>
                        </div>
                        <p
                            v-if="eventsLabel"
                            class="font-['Inter',sans-serif] text-[0.8125rem] font-normal leading-normal"
                            :class="isPlayable ? 'text-[#00c6de]' : 'text-[#3f3f3f]'"
                        >
                            {{ isPlayable ? 'Events completed' : eventsLabel }}
                        </p>
                    </div>

                    <p
                        v-if="chapter.teaser"
                        class="line-clamp-2 font-['Inter',sans-serif] text-sm font-light leading-[1.198] tracking-[-0.02625rem] text-[#d8d8d8]"
                    >
                        {{ chapter.teaser }}
                    </p>
                </div>

                <div
                    class="relative mt-auto flex size-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[rgba(255,255,255,0.04)] backdrop-blur-[3px]"
                    style="box-shadow: 0 4px 80px rgba(0, 0, 0, 0.2)"
                >
                    <LucideLock
                        v-if="isLocked"
                        class="size-3.5 text-white/70"
                        :stroke-width="1.75"
                        aria-hidden="true"
                    />
                    <LucideCheck
                        v-else
                        class="size-3.5 text-[#00c6de]"
                        :stroke-width="2.25"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </div>
    </article>
</template>
