<script setup lang="ts">
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { LucidePause, LucidePlay, LucideRepeat, LucideRotateCcw, LucideRotateCw, LucideVolume2, LucideVolumeX, LucideX } from 'lucide-vue-next';

const props = withDefaults(defineProps<{ collapsed?: boolean }>(), { collapsed: false });

const tts = useTextToSpeech();

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
                class="bg-muted-glass-effect grid size-9 shrink-0 place-items-center rounded-full text-[#00c6de] transition-transform hover:scale-105 active:scale-95"
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
            <div class="hidden items-center gap-2 sm:flex">
                <button
                    class="grid size-7 shrink-0 place-items-center rounded-full text-gray-300 transition-colors hover:text-white"
                    @click="tts.toggleMute"
                >
                    <LucideVolumeX v-if="tts.isMuted.value || tts.volume.value === 0" class="size-4" />
                    <LucideVolume2 v-else class="size-4" />
                </button>
                <input
                    type="range"
                    min="0"
                    max="100"
                    :value="tts.isMuted.value ? 0 : Math.round(tts.volume.value * 100)"
                    class="media-range w-20"
                    @input="onVolumeInput"
                />
            </div>

            <span class="hidden h-6 w-px bg-white/15 sm:block" />

            <!-- Loop -->
            <button
                class="bg-muted-glass-effect grid size-9 shrink-0 place-items-center rounded-full transition-transform hover:scale-105 active:scale-95"
                :class="tts.isLooping.value ? 'text-[#00c6de]' : 'text-gray-300'"
                @click="tts.toggleLoop"
            >
                <LucideRepeat class="size-4" />
            </button>

            <!-- Skip back 15s -->
            <button
                class="bg-muted-glass-effect relative grid size-9 shrink-0 place-items-center rounded-full text-gray-300 transition-transform hover:scale-105 active:scale-95"
                @click="tts.seekBy(-15)"
            >
                <LucideRotateCcw class="size-5" :stroke-width="1.5" />
                <span class="absolute text-[8px] font-semibold tabular-nums">15</span>
            </button>

            <!-- Skip forward 15s -->
            <button
                class="bg-muted-glass-effect relative grid size-9 shrink-0 place-items-center rounded-full text-gray-300 transition-transform hover:scale-105 active:scale-95"
                @click="tts.seekBy(15)"
            >
                <LucideRotateCw class="size-5" :stroke-width="1.5" />
                <span class="absolute text-[8px] font-semibold tabular-nums">15</span>
            </button>

            <!-- Close -->
            <button
                class="bg-muted-glass-effect grid size-9 shrink-0 place-items-center rounded-full text-gray-300 transition-colors hover:text-white"
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
    appearance: none;
    height: 4px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.2);
    outline: none;
}

.media-range::-webkit-slider-thumb {
    appearance: none;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #00c6de;
    cursor: pointer;
}

.media-range::-moz-range-thumb {
    width: 12px;
    height: 12px;
    border: none;
    border-radius: 50%;
    background: #00c6de;
    cursor: pointer;
}
</style>
