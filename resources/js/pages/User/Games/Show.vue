<script setup lang="ts">
import GameCinematicOpening from '@/components/GameCinematicOpening.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import GameplayLayout from '@/layouts/GameplayLayout.vue';
import { GameInterface } from '@/types';
import { store as storePrompt } from '@/wayfinder/actions/App/Http/Controllers/User/Game/PromptController';
import { router } from '@inertiajs/vue3';
import { LucideUser } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const CONTINUE_MARKER = '__continue__';

const props = defineProps<{
    game: GameInterface;
}>();


interface CharacterEntry {
    event: string;
    facts: string[];
}

interface CharacterSheet {
    name: string;
    firstEvent: string;
    appearanceCount: number;
    log: CharacterEntry[];
}

const ATTR_CATEGORIES: Record<string, string> = {
    'Persistent physical conditions:': 'Condition',
    'Objects:': 'Objects',
    'Environmental conditions:': 'Environment',
    'Factual dialogue:': 'Dialogue',
    'Location:': 'Location',
};

function parseAttrCategory(attr: string): { category: string; label: string; items: string[] } | null {
    for (const [prefix, label] of Object.entries(ATTR_CATEGORIES)) {
        if (attr.startsWith(prefix)) {
            const items = attr
                .slice(prefix.length)
                .split('|')
                .map((s) => s.trim())
                .filter(Boolean);
            return { category: prefix, label, items };
        }
    }
    return null;
}

const characters = computed(() => {
    const charMap = new Map<string, CharacterSheet>();
    const seenFacts = new Map<string, Set<string>>();

    for (const prompt of prompts.value) {
        const attrs = prompt.event?.attributes;
        if (!attrs) continue;

        const eventTitle = prompt.event?.title ?? 'Unknown';
        let charNames: string[] = [];
        const eventFacts: string[] = [];

        for (const attr of attrs) {
            if (attr.startsWith('Characters physically present:')) {
                charNames = attr
                    .replace('Characters physically present:', '')
                    .split('|')
                    .map((n) => n.trim())
                    .filter(Boolean);
                continue;
            }

            const parsed = parseAttrCategory(attr);
            if (parsed) {
                for (const item of parsed.items) {
                    eventFacts.push(item);
                }
            }
        }

        for (const name of charNames) {
            const nameLower = name.toLowerCase();
            const isSolo = charNames.length === 1;
            const relevantFacts = eventFacts.filter((f) => isSolo || f.toLowerCase().includes(nameLower));

            if (!relevantFacts.length) continue;

            const existing = charMap.get(name);
            const seen = seenFacts.get(name) ?? new Set<string>();

            const newFacts = relevantFacts.filter((f) => !seen.has(f));
            for (const f of newFacts) seen.add(f);
            seenFacts.set(name, seen);

            if (!newFacts.length && existing) {
                existing.appearanceCount++;
                continue;
            }

            if (existing) {
                existing.appearanceCount++;
                existing.log.push({ event: eventTitle, facts: newFacts });
            } else {
                charMap.set(name, {
                    name,
                    firstEvent: eventTitle,
                    appearanceCount: 1,
                    log: [{ event: eventTitle, facts: newFacts }],
                });
            }
        }
    }

    return Array.from(charMap.values());
});

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

const journalMeta = computed(() => ({
    storyTitle: props.game.story?.title,
    episodeLabel: (props.game as { currentEvent?: { chapter?: { position?: number } } }).currentEvent?.chapter?.position
        ? `Episode ${(props.game as { currentEvent: { chapter: { position: number } } }).currentEvent.chapter.position}`
        : null,
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

// Cinematic opening: shown on first visit (no prompts yet); hidden once begin fires
const showCinematic = ref(!hasPrompts.value);
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

// Autoplay: when a new response arrives on the latest prompt, play it automatically
watch(
    () => {
        const latest = prompts.value[prompts.value.length - 1];
        return latest ? `${latest.id}:${latest.response ?? ''}` : null;
    },
    (key, prevKey) => {
        if (!gameplaySettings.autoplay) return;
        if (!key || key === prevKey) return;
        const latest = prompts.value[prompts.value.length - 1];
        if (!latest?.response) return;
        // Skip the very first prompt that came from the cinematic (already handled above)
        if (cameFromCinematic.value) return;
        tts.play(String(props.game.id), String(latest.id));
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
    <GameCinematicOpening v-if="showCinematic" @prepare="handleCinemaPrepare" @done="handleCinematicDone" />

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
                    v-if="(game as any).currentEvent?.chapter?.position"
                    :label="`Episode ${(game as any).currentEvent.chapter.position}`"
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
            <p v-if="!characters.length" class="text-sm text-gray-500">No characters introduced yet.</p>
            <div
                v-for="char in characters"
                :key="char.name"
                class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4 transition-all hover:border-gray-600"
            >
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-secondary-400/10 text-secondary-400">
                        <LucideUser class="size-5" :stroke-width="1.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h6 class="text-base leading-snug text-gray-100">{{ char.name }}</h6>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ char.firstEvent }}
                            <span v-if="char.appearanceCount > 1"> · {{ char.appearanceCount }} appearances</span>
                        </p>
                    </div>
                </div>
                <div v-if="char.log.length" class="mt-3 flex flex-col gap-2.5 border-t border-gray-700/40 pt-3">
                    <div v-for="(entry, i) in char.log" :key="i" class="flex flex-col gap-1">
                        <p v-if="char.log.length > 1" class="text-[10px] font-semibold tracking-wide text-gray-600 uppercase">{{ entry.event }}</p>
                        <ul class="flex flex-col gap-0.5">
                            <li v-for="(fact, j) in entry.facts" :key="j" class="text-xs leading-relaxed text-gray-400">· {{ fact }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </template>
    </GameplayLayout>
</template>

<style scoped>
.gp-timeline__item:last-child .gp-timeline__line {
    display: none;
}
</style>
