<script setup lang="ts">
import GameplayOrnamentDivider from '@/components/GameplayOrnamentDivider.vue';
import GameplayContinueIcon from '@/components/icons/GameplayContinueIcon.vue';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { useTypewriter } from '@/composables/useTypewriter';
import { PromptInterface } from '@/types';
import { LucideCheck, LucideLoader, LucidePause, LucidePlay } from 'lucide-vue-next';
import { computed, onMounted, watch } from 'vue';

const CONTINUE_MARKER = '__continue__';

const props = defineProps<{
    prompt: PromptInterface;
    gameId: string;
    coverUrl?: string | null;
    isLatest?: boolean;
    pendingChoice?: string;
    isSubmitting?: boolean;
    animate?: boolean;
}>();

const emit = defineEmits<{
    'choice-selected': [promptId: string, choice: string];
    continue: [];
}>();

const tts = useTextToSpeech();
const typewriter = useTypewriter();

const effectiveSelection = computed(() => {
    return props.prompt.prompt || props.pendingChoice || null;
});

const isContinued = computed(() => {
    return effectiveSelection.value === CONTINUE_MARKER;
});

const canInteract = computed(() => {
    return props.isLatest && !effectiveSelection.value && !props.isSubmitting;
});

const hasChoices = computed(() => (props.prompt.choices?.length ?? 0) > 0);

// Player's own typed/spoken action (not one of the listed choices, not a continue)
const showCustomAction = computed(() => {
    if (!effectiveSelection.value || isContinued.value) return false;
    return !props.prompt.choices?.includes(effectiveSelection.value);
});

// Continue is only a functional fallback for beats that offer no choices
const showContinueButton = computed(() => {
    return canInteract.value && !!props.prompt.response && !hasChoices.value;
});

const renderedResponse = computed(() => {
    if (props.animate && props.isLatest) {
        return typewriter.displayedHtml.value;
    }
    return props.prompt.response;
});

const showChoicesAndActions = computed(() => {
    if (props.animate && typewriter.isTyping.value) {
        return false;
    }
    return true;
});

const getChoiceClass = (choice: string) => {
    const base = 'flex min-h-[52px] items-center gap-2 rounded-lg border px-2 py-2 transition-all duration-300 sm:min-h-[60px] sm:gap-2.5 sm:py-1';

    if (!effectiveSelection.value) {
        return `${base} border-[#373737] text-white cursor-pointer hover:border-[#00c6de] hover:bg-[#00c6de]/5`;
    }

    if (effectiveSelection.value === choice) {
        return `${base} border-[#00c6de] bg-[#00c6de]/10 text-white pointer-events-none`;
    }

    return `${base} border-[#373737]/40 text-gray-400 opacity-50 pointer-events-none`;
};

const handleChoiceClick = (choice: string) => {
    if (!canInteract.value) return;
    emit('choice-selected', props.prompt.id, choice);
};

const handleContinue = () => {
    if (!canInteract.value) return;
    emit('continue');
};

const thisKey = computed(() => `${props.gameId}:${props.prompt.id}`);
const isThisPlaying = computed(() => tts.isPlaying.value && tts.activeKey.value === thisKey.value);
const isThisLoading = computed(() => tts.isLoading.value && tts.activeKey.value === thisKey.value);

const handleListenAgain = () => {
    tts.toggle(props.gameId, props.prompt.id);
};

const handleNarrationClick = () => {
    if (typewriter.isTyping.value && props.prompt.response) {
        typewriter.skipToEnd(props.prompt.response);
    }
};

onMounted(() => {
    if (props.animate && props.isLatest && props.prompt.response) {
        typewriter.start(props.prompt.response);
    } else if (props.prompt.response) {
        typewriter.complete(props.prompt.response);
    }
});

watch(
    () => props.prompt.response,
    (newVal) => {
        if (!newVal) return;
        if (props.animate && props.isLatest) {
            typewriter.start(newVal);
        } else {
            typewriter.complete(newVal);
        }
    },
);
</script>

<template>
    <div class="flex flex-col gap-4 sm:gap-5">
        <!-- ── Narration card ── -->
        <div
            v-if="prompt.response"
            class="narration-card rounded-xl border-[0.5px] border-[#999] bg-gray-950 p-4 sm:rounded-[14px] sm:p-5"
            @click="handleNarrationClick"
        >
            <div class="text-justify leading-relaxed font-normal tracking-[0.04em]" style="font-size: inherit" v-html="renderedResponse"></div>
            <span v-if="typewriter.isTyping.value" class="mt-1 inline-block h-5 w-0.5 animate-pulse bg-primary-400 align-middle" />
        </div>

        <!-- ── Player's custom action echo ── -->
        <div v-if="showCustomAction" class="border-l-2 border-primary py-2 pl-4">
            <p class="text-base text-primary italic">"{{ effectiveSelection }}"</p>
        </div>

        <!-- ── Listen Again + Continue ── -->
        <div v-if="prompt.response && showChoicesAndActions" class="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <button
                type="button"
                class="narration-action-pill listen-again-pill relative flex h-[50px] w-auto items-center gap-[5px] overflow-hidden rounded-[60px] p-[6px] pe-3.5"
                @click="handleListenAgain"
            >
                <!-- Background layers -->
                <span aria-hidden class="pointer-events-none absolute inset-0">
                    <span class="absolute inset-0 bg-[#333]" />
                    <img
                        v-if="props.coverUrl"
                        class="absolute top-1/2 left-1/2 h-[58px] w-[195px] max-w-none -translate-x-1/2 -translate-y-1/2 object-cover"
                        :src="props.coverUrl"
                        alt=""
                    />
                    <span class="absolute inset-0 bg-[rgba(51,51,51,0.3)] mix-blend-saturation backdrop-blur-[6px]" />
                </span>
                <!-- Inner shine overlay -->
                <span aria-hidden class="listen-again-shine pointer-events-none absolute inset-0 rounded-[60px]" />
                <!-- Icon -->
                <span class="relative grid size-[37px] shrink-0 place-items-center overflow-hidden rounded-full bg-[#00c6de] text-white">
                    <LucideLoader v-if="isThisLoading" class="size-4 animate-spin" />
                    <LucidePause v-else-if="isThisPlaying" class="size-4" fill="currentColor" />
                    <LucidePlay v-else class="size-4" fill="currentColor" />
                </span>
                <!-- Label -->
                <span class="relative flex min-w-0 flex-col items-start leading-tight tracking-[0.5px]">
                    <span class="text-sm text-[#00c6de]">Listen Again</span>
                    <span class="text-xs font-light text-[#7e7e7e]">Replay Narration</span>
                </span>
            </button>

            <button
                v-if="showContinueButton"
                type="button"
                class="narration-action-pill bg-glass-effect flex h-[50px] w-full min-w-0 items-center gap-2 overflow-hidden rounded-full p-1.5 pe-4 sm:w-auto sm:pe-5"
                @click="handleContinue"
            >
                <span class="bg-muted-glass-effect grid size-9 shrink-0 place-items-center rounded-full text-white">
                    <GameplayContinueIcon />
                </span>
                <span class="flex min-w-0 flex-col items-start leading-tight">
                    <span class="text-sm text-primary-400 sm:hidden">Continue</span>
                    <span class="hidden text-sm text-primary-400 sm:inline">Continue: Hear What Happens Next</span>
                    <span class="text-xs font-light text-[#7e7e7e]">No Choice Right Now</span>
                </span>
            </button>
        </div>

        <!-- ── Choices ── -->
        <div v-if="hasChoices && showChoicesAndActions" class="flex flex-col gap-4">
            <GameplayOrnamentDivider v-if="canInteract" label="Your Turn. What Do You Do?" color="#ffbe58" />

            <div class="flex flex-col gap-2.5">
                <div v-for="choice in prompt.choices" :key="choice" :class="getChoiceClass(choice)" @click="handleChoiceClick(choice)">
                    <span
                        v-if="effectiveSelection === choice"
                        class="grid size-7 shrink-0 place-items-center rounded-full border-2 border-[#00c6de] bg-[#00c6de]/20 text-[#00c6de]"
                    >
                        <LucideCheck class="size-4" />
                    </span>
                    <span v-else class="size-7 shrink-0 rounded-full border border-gray-500" />
                    <p class="text-sm font-normal sm:text-[15px]">{{ choice }}</p>
                </div>
            </div>

            <!-- Off-path note -->
            <div v-if="canInteract" class="flex flex-col gap-0.5">
                <p class="text-base text-secondary-300">Want To Go Off-Path?</p>
                <p class="text-xs font-light tracking-wide text-gray-400">Speak Or Write Your Own Choice.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.narration-action-pill {
    transition:
        transform 150ms ease,
        color 150ms ease;
}

.narration-action-pill:hover {
    transform: scale(1.02);
}

.narration-action-pill:active {
    transform: scale(0.98);
}

.listen-again-pill {
    box-shadow:
        0 0 2px 0 rgba(0, 0, 0, 0.1),
        0 1px 8px 0 rgba(0, 0, 0, 0.12);
}

.listen-again-shine {
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.75),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.8),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.75),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.75),
        inset 0 0 1px 1px rgba(153, 153, 153, 0.15),
        inset 0 0 16px 0 rgba(242, 242, 242, 0.15);
}

.narration-card :deep(p) {
    margin-bottom: 1rem;
}

.narration-card :deep(p:last-child) {
    margin-bottom: 0;
}

.narration-card :deep(blockquote) {
    border-left: 1px solid rgba(255, 255, 255, 0.6);
    padding-left: 0.75rem;
    margin: 1rem 0;
    font-style: italic;
}

.narration-card :deep(em) {
    font-style: italic;
    color: var(--color-primary);
}

.narration-card :deep(strong) {
    font-weight: 600;
    color: var(--color-primary);
}
</style>
