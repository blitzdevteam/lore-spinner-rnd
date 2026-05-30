<script setup lang="ts">
import GameCinematicOpening from '@/components/GameCinematicOpening.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplayLayout from '@/layouts/GameplayLayout.vue';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { GameInterface } from '@/types';
import { router } from '@inertiajs/vue3';
import { store as storePrompt } from '@/wayfinder/actions/App/Http/Controllers/User/Game/PromptController';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const CONTINUE_MARKER = '__continue__';

const props = defineProps<{
    game: GameInterface;
}>();

const handleBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit('/');
    }
};

const isSubmitting = ref(false);
const isAutoBeginning = ref(false);
const beginSettled = ref(false);
const isStartingNextSession = ref(false);
const pendingSelection = ref<Record<string, string>>({});
const shouldAnimate = ref(false);

const prompts = computed(() => props.game.prompts ?? []);
const hasPrompts = computed(() => prompts.value.length > 0);
const sessionComplete = computed(() => props.game.current_session_complete === true);

// Cinematic opening: shown on first visit (no prompts yet); hidden once begin fires
const showCinematic = ref(!hasPrompts.value);
const cameFromCinematic = ref(!hasPrompts.value);

const tts = useTextToSpeech();
// Auto-play the first narration while the showcard is still visible
watch(
    () => prompts.value[0]?.response,
    (response) => {
        if (!response || !cameFromCinematic.value) return;
        const first = prompts.value[0];
        if (!first) return;
        cameFromCinematic.value = false;
        tts.play(String(props.game.id), String(first.id));
    },
);

const canSubmitInput = computed(() => {
    if (isSubmitting.value) return false;
    if (sessionComplete.value) return false;
    const latest = prompts.value[prompts.value.length - 1];
    return latest && !latest.prompt;
});

const handleBegin = () => {
    router.post(
        `/user/games/${props.game.id}/begin`,
        {},
        {
            preserveScroll: false,
            onSuccess: () => {
                shouldAnimate.value = true;
            },
            onFinish: () => {
                beginSettled.value = true;
                isAutoBeginning.value = false;
                nextTick(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            },
        },
    );
};

const handleNextSession = () => {
    if (isStartingNextSession.value) return;
    isStartingNextSession.value = true;

    router.post(
        `/user/games/${props.game.id}/next-session`,
        {},
        {
            preserveScroll: false,
            onSuccess: () => {
                shouldAnimate.value = true;
            },
            onFinish: () => {
                isStartingNextSession.value = false;
                nextTick(() => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            },
        },
    );
};

const handleChoiceSelected = (promptId: string, choice: string) => {
    if (isSubmitting.value) return;
    pendingSelection.value[promptId] = choice;
    submitPrompt(choice);
};

const handleContinue = () => {
    if (isSubmitting.value) return;
    const latestPrompt = prompts.value[prompts.value.length - 1];
    if (latestPrompt) {
        pendingSelection.value[latestPrompt.id] = CONTINUE_MARKER;
    }
    submitPrompt(CONTINUE_MARKER);
};

const handleSubmit = (prompt: string) => {
    if (isSubmitting.value) return;
    submitPrompt(prompt);
};

const submitPrompt = (prompt: string) => {
    isSubmitting.value = true;

    router.post(
        storePrompt(props.game.id),
        { prompt },
        {
            preserveScroll: true,
            onSuccess: () => {
                shouldAnimate.value = true;
            },
            onFinish: () => {
                isSubmitting.value = false;
                pendingSelection.value = {};

                nextTick(() => {
                    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                });
            },
        },
    );
};

const handleCinemaPrepare = () => {
    handleBegin();
};

const handleRetryBegin = () => {
    beginSettled.value = false;
    isAutoBeginning.value = true;
    handleBegin();
};

const handleCinematicDone = () => {
    showCinematic.value = false;
    if (!hasPrompts.value && !beginSettled.value) {
        isAutoBeginning.value = true;
    }
};

onMounted(() => {
    if (hasPrompts.value) {
        showCinematic.value = false;
        nextTick(() => {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        });
    }
});
</script>

<template>
    <!-- ── Cinematic opening sequence (new games only) ── -->
    <GameCinematicOpening
        v-if="showCinematic"
        @prepare="handleCinemaPrepare"
        @done="handleCinematicDone"
    />

    <!-- Loading state while begin POST is in-flight -->
    <div v-else-if="isAutoBeginning" class="grid h-svh place-items-center bg-gray-950">
        <div class="flex flex-col items-center gap-4">
            <div class="size-8 animate-spin rounded-full border-2 border-primary-400 border-t-transparent" />
            <p class="text-sm text-gray-400">Preparing your adventure…</p>
        </div>
    </div>

    <!-- Begin failed — show retry -->
    <div v-else-if="beginSettled && !hasPrompts" class="grid h-svh place-items-center bg-gray-950">
        <div class="flex flex-col items-center gap-6 text-center">
            <p class="text-sm text-gray-400">Opening narration hiccuped — please retry.</p>
            <button
                class="rounded-full border border-primary-400/60 bg-primary-400/10 px-8 py-3 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200"
                @click="handleRetryBegin"
            >
                Retry
            </button>
        </div>
    </div>

    <!-- ── Gameplay phase ── -->
    <GameplayLayout v-else :input-disabled="!canSubmitInput" :game-id="game.id" @submit="handleSubmit" @back="handleBack">
        <template #header>
            <div class="hidden flex-col gap-1.5 md:flex">
                <h1 class="text-xl uppercase md:text-3xl">{{ game.story?.title ?? 'Adventure' }}</h1>
                <div v-if="game.current_session_number">
                    <span class="rounded-full bg-gray-800 px-2 py-1 text-sm">
                        Session {{ game.current_session_number }}
                    </span>
                </div>
            </div>
        </template>

        <template #game>
            <GameplayChatCard
                v-for="prompt in prompts"
                :key="prompt.id"
                :prompt="prompt"
                :game-id="game.id"
                :is-latest="prompt.id === prompts[prompts.length - 1]?.id && !sessionComplete"
                :pending-choice="pendingSelection[prompt.id]"
                :is-submitting="isSubmitting"
                :animate="shouldAnimate && prompt.id === prompts[prompts.length - 1]?.id"
                @choice-selected="handleChoiceSelected"
                @continue="handleContinue"
            />

            <!-- Session complete — next chapter prompt -->
            <div v-if="sessionComplete && !isStartingNextSession" class="flex flex-col items-center gap-6 py-12 text-center">
                <div class="flex flex-col gap-2">
                    <p class="text-lg font-light text-gray-200">Session complete.</p>
                    <p class="text-sm text-gray-500">The story continues in the next chapter.</p>
                </div>
                <button
                    class="rounded-full border border-primary-400/60 bg-primary-400/10 px-8 py-3 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200"
                    @click="handleNextSession"
                >
                    Continue to next chapter
                </button>
            </div>

            <!-- Loading state for next-session call -->
            <div v-if="isStartingNextSession" class="flex flex-col items-center gap-4 py-12">
                <div class="size-8 animate-spin rounded-full border-2 border-primary-400 border-t-transparent" />
                <p class="text-sm text-gray-400">Opening the next chapter…</p>
            </div>

            <!-- Loading skeleton while AI generates the next response -->
            <div v-if="isSubmitting" class="py-8">
                <div class="flex flex-col gap-4 animate-pulse">
                    <div class="h-4 w-full rounded bg-gray-700/50"></div>
                    <div class="h-4 w-5/6 rounded bg-gray-700/50"></div>
                    <div class="h-4 w-3/4 rounded bg-gray-700/50"></div>
                    <div class="h-4 w-4/6 rounded bg-gray-700/40"></div>
                </div>
            </div>
        </template>

        <template #journals>
            <div class="flex flex-col gap-3 py-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Session Progress</p>
                <div class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4">
                    <p class="text-sm text-gray-300">
                        Session <span class="text-primary-300 font-medium">{{ game.current_session_number ?? 1 }}</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ prompts.length }} turn{{ prompts.length === 1 ? '' : 's' }} played this session</p>
                    <p v-if="sessionComplete" class="mt-2 text-xs text-primary-400">✓ Session complete</p>
                </div>
            </div>
        </template>

        <template #characters>
            <p class="text-sm text-gray-500">Character tracking coming soon.</p>
        </template>
    </GameplayLayout>
</template>

<style scoped></style>
