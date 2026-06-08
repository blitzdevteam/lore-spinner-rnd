<script setup lang="ts">
import GameCinematicOpening from '@/components/GameCinematicOpening.vue';
import GameCinematicOutro from '@/components/GameCinematicOutro.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import GameplayLayout from '@/layouts/GameplayLayout.vue';
import { GameInterface } from '@/types';
import { store as storePrompt } from '@/wayfinder/actions/App/Http/Controllers/User/Game/PromptController';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const CONTINUE_MARKER = '__continue__';

const props = defineProps<{
    game: GameInterface;
}>();

const handleBack = () => {
    tts.dismiss();
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
const isLastSession = computed(() => {
    const total = props.game.total_sessions ?? 0;
    const current = props.game.current_session_number ?? 1;
    return total > 0 && current >= total;
});

const episodePosition = computed(() => props.game.currentEvent?.chapter?.position);

const journalMeta = computed(() => ({
    storyTitle: props.game.story?.title,
    episodeLabel: episodePosition.value != null ? `Episode ${episodePosition.value}` : null,
    sessionNumber: props.game.current_session_number,
    turnCount: prompts.value.length,
    sessionComplete: sessionComplete.value,
}));

function stripHtml(html: string): string {
    if (typeof document === 'undefined') {
        return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }
    const el = document.createElement('div');
    el.innerHTML = html;
    return (el.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function excerpt(text: string, max = 120): string {
    const plain = stripHtml(text);
    if (plain.length <= max) return plain;
    return `${plain.slice(0, max).trim()}…`;
}

// Cinematic outro: shown when the full story is completed
const page = usePage();
const showOutro = computed(() => (page.props.flash as Record<string, unknown>)?.story_complete === true);
const handleOutroDone = () => {
    router.visit(storiesIndex().url, { replace: true });
};

// Cinematic opening: shown on first visit (no prompts yet); hidden once begin fires
const showCinematic = ref(!hasPrompts.value && !showOutro.value);
const cameFromCinematic = ref(!hasPrompts.value);

const tts = useTextToSpeech();
const { settings: gameplaySettings } = useGameplaySettings();
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

// Subsequent-prompt autoplay is owned by the typewriter-completion watcher in
// GameplayChatCard.vue — it fires once text is fully generated, same on all platforms.

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
    tts.primeAudio();
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
    tts.primeAudio();
    pendingSelection.value[promptId] = choice;
    submitPrompt(choice);
};

const handleContinue = () => {
    if (isSubmitting.value) return;
    tts.primeAudio();
    const latestPrompt = prompts.value[prompts.value.length - 1];
    if (latestPrompt) {
        pendingSelection.value[latestPrompt.id] = CONTINUE_MARKER;
    }
    submitPrompt(CONTINUE_MARKER);
};

const handleSubmit = (prompt: string) => {
    if (isSubmitting.value) return;
    tts.primeAudio();
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
    tts.primeAudio();
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
    if (!hasPrompts.value) {
        tts.resetForNewStory();
    }

    if (hasPrompts.value) {
        showCinematic.value = false;
        nextTick(() => {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        });
    }
});

onUnmounted(() => {
    tts.dismiss();
});
</script>

<template>
    <!-- ── Cinematic outro sequence (story completed) ── -->
    <GameCinematicOutro v-if="showOutro" :outro-poster="props.game.story?.outro_poster ?? null" @done="handleOutroDone" />

    <!-- ── Cinematic opening sequence (new games only) ── -->
    <GameCinematicOpening v-else-if="showCinematic" @prepare="handleCinemaPrepare" @done="handleCinematicDone" />

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
    <GameplayLayout
        v-else
        :input-disabled="!canSubmitInput"
        :game-id="game.id"
        :cover-url="game.story?.cover ?? null"
        :story-slug="game.story?.slug ?? null"
        :journal-meta="journalMeta"
        @submit="handleSubmit"
        @back="handleBack"
    >
        <template #header>
            <div class="flex flex-col items-center gap-3 pt-2 pb-4">
                <h1 class="text-center text-2xl font-semibold text-white md:text-[28px]">
                    {{ game.story?.title ?? 'Adventure' }}
                </h1>
                <GameplayOrnamentDivider
                    v-if="journalMeta.episodeLabel"
                    :label="journalMeta.episodeLabel"
                    color="#ffffff"
                />
                <span v-if="game.current_session_number" class="rounded-full bg-gray-800 px-2 py-1 text-sm text-gray-300">
                    Session {{ game.current_session_number }}
                </span>
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

            <!-- Session complete — next chapter or end story -->
            <div v-if="sessionComplete && !isStartingNextSession" class="flex flex-col items-center gap-6 py-12 text-center">
                <div class="flex flex-col gap-2">
                    <p class="text-lg font-light text-gray-200">Session complete.</p>
                    <p v-if="!isLastSession" class="text-sm text-gray-500">
                        The story continues in the next chapter.
                    </p>
                </div>
                <button
                    class="rounded-full border border-primary-400/60 bg-primary-400/10 px-8 py-3 text-sm font-medium text-primary-300 transition hover:bg-primary-400/20 hover:text-primary-200"
                    @click="handleNextSession"
                >
                    {{ isLastSession ? 'Click to end story' : 'Continue to next chapter' }}
                </button>
            </div>

            <!-- Loading state for next-session call -->
            <div v-if="isStartingNextSession" class="flex flex-col items-center gap-4 py-12">
                <div class="size-8 animate-spin rounded-full border-2 border-primary-400 border-t-transparent" />
                <p class="text-sm text-gray-400">
                    {{ isLastSession ? 'Ending story…' : 'Opening the next chapter…' }}
                </p>
            </div>

            <!-- Loading skeleton while AI generates the next response -->
            <div v-if="isSubmitting" class="py-8">
                <div class="flex animate-pulse flex-col gap-4">
                    <div class="h-4 w-full rounded bg-gray-700/50"></div>
                    <div class="h-4 w-5/6 rounded bg-gray-700/50"></div>
                    <div class="h-4 w-3/4 rounded bg-gray-700/50"></div>
                    <div class="h-4 w-4/6 rounded bg-gray-700/40"></div>
                </div>
            </div>
        </template>

        <template #journals>
            <!-- Desktop sidebar: classic session progress -->
            <div class="hidden flex-col gap-3 md:flex">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Session Progress</p>
                <div class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4">
                    <p class="text-sm text-gray-300">
                        Session <span class="font-medium text-primary-300">{{ game.current_session_number ?? 1 }}</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500">{{ prompts.length }} turn{{ prompts.length === 1 ? '' : 's' }} played this session</p>
                    <p v-if="sessionComplete" class="mt-2 text-xs text-primary-400">✓ Session complete</p>
                </div>
            </div>

            <div class="gp-timeline flex flex-col">
                <article
                    v-for="(prompt, index) in prompts"
                    :key="prompt.id"
                    class="gp-timeline__item relative flex gap-3 pb-5 last:pb-0"
                >
                    <div class="gp-timeline__rail flex flex-col items-center">
                        <span
                            class="gp-timeline__dot grid size-7 shrink-0 place-items-center rounded-full border border-primary/30 bg-primary/10 text-[10px] font-semibold tabular-nums text-primary-300"
                        >
                            {{ index + 1 }}
                        </span>
                        <span
                            v-if="index < prompts.length - 1"
                            class="gp-timeline__line mt-1 w-px flex-1 min-h-4 bg-gradient-to-b from-primary/30 to-transparent"
                        />
                    </div>
                    <div class="gp-timeline__card min-w-0 flex-1 rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                        <p
                            v-if="index === 0"
                            class="text-[10px] font-semibold uppercase tracking-wider text-gray-500"
                        >
                            Opening
                        </p>
                        <p
                            v-else-if="!prompt.prompt && index === prompts.length - 1"
                            class="text-[10px] font-semibold uppercase tracking-wider text-gray-500"
                        >
                            Now
                        </p>
                        <p
                            class="text-sm leading-relaxed text-gray-300"
                            :class="{ 'mt-2': index === 0 || (!prompt.prompt && index === prompts.length - 1) }"
                        >
                            {{ excerpt(prompt.response ?? '') }}
                        </p>
                        <p
                            v-if="prompt.prompt"
                            class="mt-2 border-t border-white/6 pt-2 text-xs font-medium text-primary-300/90"
                        >
                            You · {{ prompt.prompt === '__continue__' ? 'Continued' : prompt.prompt }}
                        </p>
                    </div>
                </article>
                <p v-if="!prompts.length" class="py-4 text-center text-sm text-gray-500">
                    Your story events will appear here as you play.
                </p>
            </div>
        </template>

        <template #characters>
            <p class="py-6 text-center text-sm text-gray-500">Coming soon</p>
        </template>
    </GameplayLayout>
</template>

<style scoped>
.gp-timeline__item:last-child .gp-timeline__line {
    display: none;
}
</style>
