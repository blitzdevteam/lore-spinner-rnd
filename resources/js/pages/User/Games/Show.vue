<script setup lang="ts">
import GameOpeningNarration from '@/components/GameOpeningNarration.vue';
import GameplayChatCard from '@/components/GameplayChatCard.vue';
import GameplaySidebarJournalEventCard from '@/components/GameplaySidebarJournalEventCard.vue';
import GameplayLayout from '@/layouts/GameplayLayout.vue';
import { EventInterface, GameInterface } from '@/types';
import { router } from '@inertiajs/vue3';
import { store as storePrompt } from '@/wayfinder/actions/App/Http/Controllers/User/Game/PromptController';
import { LucideUser } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';

const CONTINUE_MARKER = '__continue__';

const props = defineProps<{
    game: GameInterface;
}>();

const journalEvents = computed(() => {
    const seen = new Set<number>();
    const events: (EventInterface & { isCurrent: boolean })[] = [];

    for (const prompt of prompts.value) {
        if (prompt.event && !seen.has(prompt.event.id)) {
            seen.add(prompt.event.id);
            events.push({
                ...prompt.event,
                isCurrent: prompt.event.id === props.game.current_event_id,
            });
        }
    }

    return events;
});

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
            const relevantFacts = eventFacts.filter(
                (f) => isSolo || f.toLowerCase().includes(nameLower),
            );

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
const pendingSelection = ref<Record<string, string>>({});
const shouldAnimate = ref(false);

const prompts = computed(() => props.game.prompts ?? []);
const hasPrompts = computed(() => prompts.value.length > 0);
const storyOpening = computed(() => props.game.story?.opening ?? null);
const showOpening = computed(() => !hasPrompts.value && !!storyOpening.value);

const canSubmitInput = computed(() => {
    if (isSubmitting.value) return false;
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

onMounted(() => {
    if (hasPrompts.value) {
        nextTick(() => {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        });
    } else if (!storyOpening.value) {
        isAutoBeginning.value = true;
        handleBegin();
    }
});
</script>

<template>
    <!-- Opening narration phase -->
    <GameOpeningNarration
        v-if="showOpening"
        :opening="storyOpening!"
        :story-title="game.story?.title ?? 'the story'"
        @begin="handleBegin"
    />

    <!-- Loading state while auto-beginning (no opening available) -->
    <div v-else-if="isAutoBeginning" class="grid h-svh place-items-center bg-gray-950">
        <div class="flex flex-col items-center gap-4">
            <div class="size-8 animate-spin rounded-full border-2 border-primary-400 border-t-transparent" />
            <p class="text-sm text-gray-400">Preparing your adventure...</p>
        </div>
    </div>

    <!-- Gameplay phase -->
    <GameplayLayout v-else :input-disabled="!canSubmitInput" :game-id="game.id" @submit="handleSubmit" @back="handleBack">
        <template #header>
            <div class="hidden flex-col gap-1.5 md:flex">
                <h1 class="text-xl uppercase md:text-3xl">{{ (game as any).currentEvent?.title ?? 'Adventure' }}</h1>
                <div v-if="(game as any).currentEvent?.chapter?.position">
                    <span class="rounded-full bg-gray-800 px-2 py-1 text-sm">
                        Chapter {{ (game as any).currentEvent.chapter.position }}
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
                :is-latest="prompt.id === prompts[prompts.length - 1]?.id"
                :pending-choice="pendingSelection[prompt.id]"
                :is-submitting="isSubmitting"
                :animate="shouldAnimate && prompt.id === prompts[prompts.length - 1]?.id"
                @choice-selected="handleChoiceSelected"
                @continue="handleContinue"
            />

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
            <p v-if="!journalEvents.length" class="text-sm text-gray-500">No events yet.</p>
            <GameplaySidebarJournalEventCard
                v-for="event in journalEvents"
                :key="event.id"
                :title="event.title"
                :objective="event.objectives"
                :is-current="event.isCurrent"
            />
        </template>

        <template #characters>
            <p v-if="!characters.length" class="text-sm text-gray-500">No characters introduced yet.</p>
            <div v-for="char in characters" :key="char.name" class="rounded-xl border border-gray-700/50 bg-gray-800/40 p-4 transition-all hover:border-gray-600">
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
                        <p v-if="char.log.length > 1" class="text-[10px] font-semibold uppercase tracking-wide text-gray-600">{{ entry.event }}</p>
                        <ul class="flex flex-col gap-0.5">
                            <li v-for="(fact, j) in entry.facts" :key="j" class="text-xs leading-relaxed text-gray-400">
                                · {{ fact }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </template>
    </GameplayLayout>
</template>

<style scoped></style>
