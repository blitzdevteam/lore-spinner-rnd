<script setup lang="ts">
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import {
    LucidePause,
    LucidePlay,
    LucideRepeat,
    LucideRotateCcw,
    LucideRotateCw,
    LucideVolume2,
    LucideVolumeX,
    LucideX,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ collapsed?: boolean; compact?: boolean }>(), {
    collapsed: false,
    compact: false,
});

const tts = useTextToSpeech();

const volumeSliderValue = computed(() => {
    if (tts.isMuted.value) {
        return 0;
    }

    return Math.round(tts.volume.value * 100);
});

const volumeSliderStyle = computed(() => ({
    '--volume-percent': `${volumeSliderValue.value}%`,
}));

const isVolumeMuted = computed(() => tts.isMuted.value || tts.volume.value === 0);

const onVolumeInput = (event: Event) => {
    tts.setVolume(Number((event.target as HTMLInputElement).value) / 100);
};
</script>

<template>
    <Transition name="player-slide">
        <div
            v-if="tts.isActive.value && !props.collapsed"
            class="player-bar pointer-events-auto relative flex max-w-full items-center gap-1.5 overflow-hidden rounded-full px-1.5 py-1.5 backdrop-blur-md sm:gap-2 sm:px-2 sm:py-2 lg:gap-2.5 lg:px-2.5"
            :class="props.compact ? 'w-full justify-center' : ''"
        >
            <!-- Play / Pause -->
            <button
                class="player-icon-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-primary-400"
                :class="props.compact ? 'size-8' : 'size-9'"
                @click="tts.togglePause"
            >
                <LucidePause v-if="tts.isPlaying.value" class="size-3.5 sm:size-4" fill="currentColor" />
                <LucidePlay v-else class="size-3.5 sm:size-4" fill="currentColor" />
            </button>

            <!-- Time -->
            <span
                class="shrink-0 font-medium tabular-nums text-primary-400"
                :class="props.compact ? 'min-w-10 text-sm' : 'min-w-12 text-base'"
            >
                {{ tts.formattedCurrentTime.value }}
            </span>

            <!-- Mute + volume slider -->
            <div v-if="!props.compact" class="hidden items-center gap-2 md:flex">
                <button
                    class="player-icon-btn grid size-7 shrink-0 place-items-center rounded-full"
                    :class="isVolumeMuted ? 'text-primary-400' : 'text-gray-300'"
                    :aria-pressed="isVolumeMuted"
                    aria-label="Mute audio"
                    @click="tts.toggleMute"
                >
                    <LucideVolumeX v-if="isVolumeMuted" class="size-4" />
                    <LucideVolume2 v-else class="size-4" />
                </button>
                <input
                    type="range"
                    min="0"
                    max="100"
                    :value="volumeSliderValue"
                    :style="volumeSliderStyle"
                    class="media-range w-20"
                    aria-label="Volume"
                    :aria-valuenow="volumeSliderValue"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    @input="onVolumeInput"
                />
            </div>

            <span v-if="!props.compact" class="hidden h-6 w-px bg-white/15 md:block" />

            <!-- Loop -->
            <button
                class="player-icon-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full"
                :class="[tts.isLooping.value ? 'text-primary-400' : 'text-gray-300', props.compact ? 'size-8' : 'size-9']"
                @click="tts.toggleLoop"
            >
                <LucideRepeat class="size-3.5 sm:size-4" />
            </button>

            <!-- Skip back 15s -->
            <button
                v-if="!props.compact"
                class="player-icon-btn bg-muted-glass-effect relative grid size-9 shrink-0 place-items-center rounded-full text-gray-300"
                @click="tts.seekBy(-15)"
            >
                <LucideRotateCcw class="size-5" :stroke-width="1.5" />
                <span class="absolute text-[8px] font-semibold tabular-nums">15</span>
            </button>

            <!-- Skip forward 15s -->
            <button
                v-if="!props.compact"
                class="player-icon-btn bg-muted-glass-effect relative grid size-9 shrink-0 place-items-center rounded-full text-gray-300"
                @click="tts.seekBy(15)"
            >
                <LucideRotateCw class="size-5" :stroke-width="1.5" />
                <span class="absolute text-[8px] font-semibold tabular-nums">15</span>
            </button>

            <!-- Close -->
            <button
                class="player-icon-btn bg-muted-glass-effect grid shrink-0 place-items-center rounded-full text-gray-300"
                :class="props.compact ? 'size-8' : 'size-9'"
                @click="tts.dismiss"
            >
                <LucideX class="size-3.5 sm:size-4" />
            </button>
        </div>
    </Transition>
</template>

<style scoped>
.player-icon-btn {
    transition:
        transform 150ms ease,
        color 150ms ease;
}

.player-icon-btn:hover {
    transform: scale(1.06);
    color: var(--color-primary-400);
}

.player-icon-btn:active {
    transform: scale(0.95);
}

.player-slide-enter-active,
.player-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.player-bar {
    background-color: #373737;
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.5),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.55),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.4),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.4),
        inset 0 0 1px 1px rgba(153, 153, 153, 0.2),
        0 8px 30px rgba(0, 0, 0, 0.4);
}

.player-slide-enter-from,
.player-slide-leave-to {
    opacity: 0;
    transform: translateY(-12px) scale(0.95);
}

.media-range {
    --media-range-fill: var(--color-primary-400);
    --media-range-track: rgba(255, 255, 255, 0.18);
    --volume-percent: 0%;

    appearance: none;
    -webkit-appearance: none;
    height: 12px;
    background: transparent;
    outline: none;
    cursor: pointer;
}

.media-range:focus-visible::-webkit-slider-thumb {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-400) 45%, transparent);
}

.media-range:focus-visible::-moz-range-thumb {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-400) 45%, transparent);
}

.media-range::-webkit-slider-runnable-track {
    height: 4px;
    border-radius: 4px;
    background: linear-gradient(
        to right,
        var(--media-range-fill) 0%,
        var(--media-range-fill) var(--volume-percent),
        var(--media-range-track) var(--volume-percent),
        var(--media-range-track) 100%
    );
    transition: background 150ms ease;
}

.media-range::-moz-range-track {
    height: 4px;
    border-radius: 4px;
    background: var(--media-range-track);
}

.media-range::-moz-range-progress {
    height: 4px;
    border-radius: 4px;
    background: var(--media-range-fill);
    transition: background 150ms ease;
}

.media-range::-webkit-slider-thumb {
    appearance: none;
    width: 12px;
    height: 12px;
    margin-top: -4px;
    border-radius: 50%;
    background: var(--media-range-fill);
    cursor: pointer;
    transition:
        transform 150ms ease,
        box-shadow 150ms ease;
}

.media-range::-moz-range-thumb {
    width: 12px;
    height: 12px;
    border: none;
    border-radius: 50%;
    background: var(--media-range-fill);
    cursor: pointer;
    transition:
        transform 150ms ease,
        box-shadow 150ms ease;
}
</style>
