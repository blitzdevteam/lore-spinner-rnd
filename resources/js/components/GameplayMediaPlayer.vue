<script setup lang="ts">
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { LucidePause, LucidePlay, LucideRepeat, LucideRotateCcw, LucideRotateCw, LucideVolume2, LucideVolumeX, LucideX } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ collapsed?: boolean }>(), { collapsed: false });

const tts = useTextToSpeech();

const volumePercent = computed(() => {
    if (tts.isMuted.value || tts.volume.value === 0) {
        return 0;
    }

    return Math.round(tts.volume.value * 100);
});

const isVolumeMuted = computed(() => tts.isMuted.value || tts.volume.value === 0);

const onVolumeInput = (event: Event) => {
    tts.setVolume(Number((event.target as HTMLInputElement).value) / 100);
};
</script>

<template>
    <Transition name="player-slide">
        <div
            v-if="tts.isActive.value && !props.collapsed"
            class="player-bar pointer-events-auto relative flex items-center gap-2 overflow-hidden rounded-full px-2 py-2 backdrop-blur-md sm:gap-2.5 sm:px-2.5"
        >
            <!-- Play / Pause -->
            <button
                class="bg-muted-glass-effect grid size-9 shrink-0 place-items-center rounded-full text-[#00c6de] transition-[transform,color] hover:scale-105 hover:text-white active:scale-95"
                @click="tts.togglePause"
            >
                <LucidePause v-if="tts.isPlaying.value" class="size-4" fill="currentColor" />
                <LucidePlay v-else class="size-4" fill="currentColor" />
            </button>

            <!-- Time -->
            <span class="min-w-12 text-base font-medium text-[#00c6de] tabular-nums">
                {{ tts.formattedCurrentTime.value }}
            </span>

            <!-- Mute + volume slider -->
            <div class="volume-control hidden items-center gap-2 sm:flex">
                <button
                    class="grid size-7 shrink-0 place-items-center rounded-full text-[#00c6de] transition-[color,opacity] hover:text-white"
                    :class="{ 'opacity-60 hover:opacity-100': isVolumeMuted }"
                    :aria-pressed="isVolumeMuted"
                    aria-label="Toggle mute"
                    @click="tts.toggleMute"
                >
                    <LucideVolumeX v-if="isVolumeMuted" class="size-4" />
                    <LucideVolume2 v-else class="size-4" />
                </button>
                <input
                    type="range"
                    min="0"
                    max="100"
                    :value="volumePercent"
                    class="media-range w-20"
                    :style="{ '--range-fill': `${volumePercent}%` }"
                    aria-label="Volume"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="volumePercent"
                    :aria-valuetext="isVolumeMuted ? 'Muted' : `${volumePercent}%`"
                    @input="onVolumeInput"
                />
            </div>

            <span class="hidden h-6 w-px bg-white/15 sm:block" />

            <!-- Loop -->
            <button
                class="bg-muted-glass-effect grid size-9 shrink-0 place-items-center rounded-full transition-[transform,color] hover:scale-105 hover:text-[#00c6de] active:scale-95"
                :class="tts.isLooping.value ? 'text-[#00c6de] hover:text-white' : 'text-gray-300'"
                @click="tts.toggleLoop"
            >
                <LucideRepeat class="size-4" />
            </button>

            <!-- Skip back 15s -->
            <button
                class="bg-muted-glass-effect relative grid size-9 shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color] hover:scale-105 hover:text-[#00c6de] active:scale-95"
                @click="tts.seekBy(-15)"
            >
                <LucideRotateCcw class="size-5" :stroke-width="1.5" />
                <span class="absolute text-[8px] font-semibold tabular-nums">15</span>
            </button>

            <!-- Skip forward 15s -->
            <button
                class="bg-muted-glass-effect relative grid size-9 shrink-0 place-items-center rounded-full text-gray-300 transition-[transform,color] hover:scale-105 hover:text-[#00c6de] active:scale-95"
                @click="tts.seekBy(15)"
            >
                <LucideRotateCw class="size-5" :stroke-width="1.5" />
                <span class="absolute text-[8px] font-semibold tabular-nums">15</span>
            </button>

            <!-- Close -->
            <button
                class="bg-muted-glass-effect grid size-9 shrink-0 place-items-center rounded-full text-gray-300 transition-colors hover:text-[#00c6de]"
                @click="tts.dismiss"
            >
                <LucideX class="size-4" />
            </button>
        </div>
    </Transition>
</template>

<style scoped>
.player-slide-enter-active,
.player-slide-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.player-bar {
    background-color: #1e1e1e;
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.12),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.1),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.08),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.08),
        inset 0 0 1px 1px rgba(0, 0, 0, 0.4),
        0 8px 30px rgba(0, 0, 0, 0.55);
}

.player-slide-enter-from,
.player-slide-leave-to {
    opacity: 0;
    transform: translateY(-12px) scale(0.95);
}

.volume-control {
    padding: 0.125rem 0.25rem;
}

@property --range-fill {
    syntax: '<percentage>';
    inherits: true;
    initial-value: 0%;
}

.media-range {
    --range-fill: 0%;
    appearance: none;
    height: 12px;
    border-radius: 4px;
    background: transparent;
    outline: none;
    cursor: pointer;
    transition: --range-fill 150ms ease;
}

.media-range:focus-visible {
    outline: 2px solid rgba(0, 198, 222, 0.55);
    outline-offset: 2px;
    border-radius: 9999px;
}

.media-range::-webkit-slider-runnable-track {
    height: 4px;
    border-radius: 4px;
    background: linear-gradient(
        to right,
        #00c6de 0%,
        #00c6de var(--range-fill),
        rgba(0, 198, 222, 0.28) var(--range-fill),
        rgba(0, 198, 222, 0.28) 100%
    );
    transition: background 150ms ease;
}

.media-range::-webkit-slider-thumb {
    appearance: none;
    width: 12px;
    height: 12px;
    margin-top: -4px;
    border-radius: 50%;
    background: #00c6de;
    cursor: pointer;
    transition:
        transform 150ms ease,
        background-color 150ms ease,
        box-shadow 150ms ease;
}

.media-range:active::-webkit-slider-thumb {
    transform: scale(1.1);
}

.media-range:focus-visible::-webkit-slider-thumb {
    box-shadow: 0 0 0 3px rgba(0, 198, 222, 0.35);
}

.media-range::-moz-range-track {
    height: 4px;
    border-radius: 4px;
    background: rgba(0, 198, 222, 0.28);
}

.media-range::-moz-range-progress {
    height: 4px;
    border-radius: 4px;
    background: #00c6de;
    transition: width 150ms ease;
}

.media-range::-moz-range-thumb {
    width: 12px;
    height: 12px;
    border: none;
    border-radius: 50%;
    background: #00c6de;
    cursor: pointer;
    transition:
        transform 150ms ease,
        background-color 150ms ease,
        box-shadow 150ms ease;
}

.media-range:active::-moz-range-thumb {
    transform: scale(1.1);
}

.media-range:focus-visible::-moz-range-thumb {
    box-shadow: 0 0 0 3px rgba(0, 198, 222, 0.35);
}
</style>
