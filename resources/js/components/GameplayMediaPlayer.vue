<script setup lang="ts">
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { LucidePause, LucidePlay, LucideX } from 'lucide-vue-next';

const tts = useTextToSpeech();
</script>

<template>
    <Transition name="player-slide">
        <div
            v-if="tts.isActive.value"
            class="bg-glass-effect pointer-events-auto relative flex items-center gap-3 overflow-hidden rounded-full border border-gray-700/60 py-2 pe-3 ps-2 shadow-2xl bg-gray-900/75! backdrop-blur-xl!"
        >
            <!-- Play / Pause -->
            <button
                class="bg-primary-glass-effect relative grid size-10 shrink-0 place-items-center overflow-hidden rounded-full transition-transform hover:scale-105 active:scale-95"
                @click="tts.togglePause"
            >
                <LucidePause v-if="tts.isPlaying.value" class="size-4 text-gray-950" fill="currentColor" />
                <LucidePlay v-else class="size-4 text-gray-950" fill="currentColor" />
            </button>

            <!-- Time -->
            <span class="min-w-16 text-sm font-medium tabular-nums text-gray-200">
                {{ tts.formattedCurrentTime.value }}
                <span class="text-gray-500">/</span>
                {{ tts.formattedDuration.value }}
            </span>

            <!-- Speed -->
            <button
                class="rounded-full border border-gray-600 px-2.5 py-0.5 text-xs font-semibold tabular-nums text-primary-300 transition-colors hover:border-primary-400 hover:bg-primary-400/10"
                @click="tts.cycleSpeed"
            >
                {{ tts.playbackRate.value }}x
            </button>

            <!-- Close -->
            <button
                class="grid size-7 shrink-0 place-items-center rounded-full text-gray-400 transition-colors hover:bg-gray-700 hover:text-gray-200"
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

.player-slide-enter-from,
.player-slide-leave-to {
    opacity: 0;
    transform: translateY(-0.75rem) scale(0.95);
}
</style>
