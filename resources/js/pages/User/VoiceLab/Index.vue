<script setup lang="ts">
import VoiceLabLayout from '@/layouts/VoiceLabLayout.vue';
import VoiceLabOrb from '@/components/VoiceLabOrb.vue';
import { useVoiceLab } from '@/composables/useVoiceLab';
import { router } from '@inertiajs/vue3';

const { state, audioLevel, errorMessage, activate, clearHistory } = useVoiceLab();

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
</style>
