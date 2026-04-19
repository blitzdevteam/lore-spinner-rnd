<script setup lang="ts">
import VoiceLabLayout from '../layouts/VoiceLabLayout.vue';
import VoiceLabOrb from '../components/VoiceLabOrb.vue';
import { useVoiceLab } from '../composables/useVoiceLab';
import { router } from '@inertiajs/vue3';

const { state, audioLevel, errorMessage, choices, activate, sendChoice, clearHistory } = useVoiceLab();

const handleBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
};

const handleClearHistory = async () => {
    await clearHistory();
};
</script>

<template>
    <VoiceLabLayout @back="handleBack">
        <template #header>
            <div class="flex flex-col gap-1">
                <h1 class="text-xl uppercase tracking-wider md:text-3xl">Voice Lab</h1>
            </div>
        </template>

        <template #actions>
            <button
                class="rounded-full border border-gray-700 px-3 py-1.5 text-xs text-gray-400 transition-colors hover:border-gray-500 hover:text-gray-200"
                @click="handleClearHistory"
            >
                Reset
            </button>
        </template>

        <template #orb>
            <div class="flex flex-col items-center gap-6" @click="activate">
                <VoiceLabOrb
                    :state="state"
                    :audio-level="audioLevel"
                    :size="260"
                    class="cursor-pointer"
                />

                <!-- State label -->
                <Transition name="label-fade" mode="out-in">
                    <p
                        v-if="state === 'error'"
                        key="error"
                        class="text-sm text-red-400"
                    >
                        {{ errorMessage ?? 'Something went wrong' }}
                    </p>
                    <p
                        v-else-if="state === 'listening'"
                        key="listening"
                        class="flex items-center gap-2 text-sm text-primary-400"
                    >
                        <span class="inline-block size-2 animate-pulse rounded-full bg-primary-400" />
                        Listening...
                    </p>
                    <p
                        v-else-if="state === 'thinking'"
                        key="thinking"
                        class="text-sm text-blue-400"
                    >
                        Thinking...
                    </p>
                    <p
                        v-else-if="state === 'speaking'"
                        key="speaking"
                        class="text-sm text-primary-300"
                    >
                        Speaking...
                    </p>
                    <p
                        v-else
                        key="idle"
                        class="text-sm text-gray-500"
                    >
                        Tap to speak
                    </p>
                </Transition>
            </div>
        </template>
        <template #controls>
            <TransitionGroup
                name="choice-pop"
                tag="div"
                class="flex flex-col gap-2"
            >
                <button
                    v-for="(choice, i) in choices"
                    :key="choice"
                    :disabled="state !== 'idle'"
                    class="w-full rounded-xl border border-gray-700/60 bg-gray-900/70 px-4 py-3 text-left text-sm text-gray-200 backdrop-blur transition-all hover:border-primary-500/50 hover:bg-gray-800/70 hover:text-white disabled:pointer-events-none disabled:opacity-40"
                    :style="{ transitionDelay: `${i * 60}ms` }"
                    @click="sendChoice(choice)"
                >
                    {{ choice }}
                </button>
            </TransitionGroup>
        </template>
    </VoiceLabLayout>
</template>

<style scoped>
.label-fade-enter-active,
.label-fade-leave-active {
    transition: opacity 0.2s ease;
}

.label-fade-enter-from,
.label-fade-leave-to {
    opacity: 0;
}

.choice-pop-enter-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
}

.choice-pop-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}

.choice-pop-enter-from {
    opacity: 0;
    transform: translateY(8px) scale(0.96);
}

.choice-pop-leave-to {
    opacity: 0;
    transform: translateY(-4px) scale(0.98);
}
</style>
