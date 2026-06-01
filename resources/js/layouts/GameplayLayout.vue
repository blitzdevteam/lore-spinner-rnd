<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayAudioPanel from '@/components/GameplayAudioPanel.vue';
import GameplayBottomSheet from '@/components/GameplayBottomSheet.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayJournalPanel, { type JournalMeta } from '@/components/GameplayJournalPanel.vue';
import GameplayMediaPlayer from '@/components/GameplayMediaPlayer.vue';
import GameplaySettingsPanel from '@/components/GameplaySettingsPanel.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { LucideAudioLines, LucideChevronLeft, LucideNotebookText, LucideSettings, LucideZap } from 'lucide-vue-next';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        inputDisabled?: boolean;
        gameId?: string;
        coverUrl?: string | null;
        journalMeta?: JournalMeta;
    }>(),
    { inputDisabled: false, gameId: undefined, coverUrl: undefined, journalMeta: undefined },
);

type UtilityPanel = 'journal' | 'settings' | 'audio' | null;
const activePanel = ref<UtilityPanel>(null);
const journalTab = ref<'timeline' | 'characters'>('timeline');
const mediaCollapsed = ref(false);

const { settings } = useGameplaySettings();
const tts = useTextToSpeech();

const openPanel = (panel: UtilityPanel) => {
    activePanel.value = activePanel.value === panel ? null : panel;
};

const closePanel = () => {
    if (activePanel.value === 'audio') {
        mediaCollapsed.value = false;
    }
    activePanel.value = null;
};

const openJournal = () => {
    openPanel('journal');
};

const openSettings = () => {
    openPanel('settings');
};

const openAudio = () => {
    mediaCollapsed.value = true;
    openPanel('audio');
};

const emit = defineEmits<{
    submit: [prompt: string];
    back: [];
}>();

const handleInputSubmit = (prompt: string) => {
    emit('submit', prompt);
};
</script>

<template>
    <div class="relative h-svh overflow-hidden">
        <BaseBackgroundGradient class="z-0" :cover-url="props.coverUrl" />
        <div class="relative flex h-svh min-h-0 flex-col">
            <!-- ── Top header bar ── -->
            <div class="sticky top-0 right-0 left-0 z-30 w-full shrink-0">
                <div
                    class="z-50 flex h-20 items-center justify-between gap-3 bg-linear-to-b from-gray-950 via-gray-950/60 to-transparent px-4 transition-all duration-300 sm:px-8 md:h-24"
                >
                    <!-- Left: back + settings (desktop) -->
                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                        <BaseButton severity="glass" :icon-only="true" class="size-11!" title="Go back" @click="$emit('back')">
                            <LucideChevronLeft class="size-6 text-gray-50" :stroke-width="1.75" />
                        </BaseButton>
                        <BaseButton
                            severity="glass"
                            :icon-only="true"
                            class="hidden size-11! md:flex"
                            :class="{ 'ring-1 ring-primary/40': activePanel === 'settings' }"
                            title="Settings"
                            @click="openSettings"
                        >
                            <LucideSettings class="size-5 text-secondary-300" />
                        </BaseButton>
                    </div>

                    <!-- Center: compact media player (desktop) -->
                    <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                        <GameplayMediaPlayer :collapsed="mediaCollapsed" />
                    </div>

                    <!-- Right: desktop action buttons -->
                    <div class="hidden shrink-0 items-center gap-2 sm:gap-3 md:flex">
                        <BaseButton
                            severity="glass"
                            :icon-only="true"
                            class="size-11!"
                            :title="settings.autoplay ? 'Autoplay on' : 'Autoplay off'"
                            @click="settings.autoplay = !settings.autoplay"
                        >
                            <LucideZap
                                class="size-5 transition-colors"
                                :class="settings.autoplay ? 'text-primary fill-primary' : 'text-gray-300'"
                            />
                        </BaseButton>
                        <BaseButton
                            severity="glass"
                            :icon-only="true"
                            class="size-11!"
                            :class="{ 'ring-1 ring-primary/40': activePanel === 'audio' }"
                            title="Audio controls"
                            @click="openAudio"
                        >
                            <LucideAudioLines
                                class="size-5"
                                :class="tts.isActive.value || activePanel === 'audio' ? 'text-primary' : 'text-gray-300'"
                            />
                        </BaseButton>
                        <BaseButton
                            severity="glass"
                            :icon-only="true"
                            class="size-11!"
                            :class="{ 'ring-1 ring-primary/40': activePanel === 'journal' }"
                            title="Journal"
                            @click="openJournal"
                        >
                            <LucideNotebookText class="size-5 text-secondary-300" />
                        </BaseButton>
                    </div>

                    <!-- Mobile: action pill -->
                    <div class="mobile-pill flex md:hidden">
                        <button
                            class="mobile-pill__btn"
                            :class="{ 'mobile-pill__btn--active': activePanel === 'settings' }"
                            title="Settings"
                            @click="openSettings"
                        >
                            <LucideSettings class="size-5 text-gray-300" />
                        </button>
                        <button
                            class="mobile-pill__btn"
                            :title="settings.autoplay ? 'Autoplay on' : 'Autoplay off'"
                            @click="settings.autoplay = !settings.autoplay"
                        >
                            <LucideZap
                                class="size-5 transition-colors"
                                :class="settings.autoplay ? 'text-primary fill-primary' : 'text-gray-300'"
                            />
                        </button>
                        <button
                            class="mobile-pill__btn"
                            :class="{ 'mobile-pill__btn--active': activePanel === 'audio' }"
                            title="Audio controls"
                            @click="openAudio"
                        >
                            <LucideAudioLines
                                class="size-5"
                                :class="tts.isActive.value || activePanel === 'audio' ? 'text-primary' : 'text-gray-300'"
                            />
                        </button>
                        <button
                            class="mobile-pill__btn"
                            :class="{ 'mobile-pill__btn--active': activePanel === 'journal' }"
                            title="Journal"
                            @click="openJournal"
                        >
                            <LucideNotebookText class="size-5 text-gray-300" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Scrolling content ── -->
            <div
                class="z-5 mx-auto min-h-0 w-full max-w-3xl flex-1 overflow-y-auto p-4 transition-colors duration-300"
                :style="{ fontSize: settings.fontSize + 'px', color: settings.fontColor }"
            >
                <div class="mb-2">
                    <slot name="header" />
                </div>
                <div class="flex flex-col gap-8">
                    <slot name="game" />
                </div>
            </div>

            <!-- ── Bottom input ── -->
            <div class="sticky right-0 bottom-0 left-0 z-20 w-full shrink-0">
                <div class="flex flex-col items-center gap-3 bg-linear-to-t from-gray-950 via-gray-950/80 to-transparent px-4 pt-10 pb-6 md:px-0">
                    <div class="flex w-full justify-start md:hidden">
                        <GameplayMediaPlayer :collapsed="mediaCollapsed" />
                    </div>
                    <GameplayInput :disabled="props.inputDisabled" @submit="handleInputSubmit" />
                </div>
            </div>
        </div>

        <!-- ── Bottom Sheets ── -->
        <GameplayBottomSheet :open="activePanel === 'journal'" title="Journal" @close="closePanel">
            <GameplayJournalPanel v-model:tab="journalTab" :meta="journalMeta">
                <template #timeline>
                    <slot name="journals" />
                </template>
                <template #characters>
                    <slot name="characters" />
                </template>
            </GameplayJournalPanel>
        </GameplayBottomSheet>

        <GameplayBottomSheet :open="activePanel === 'settings'" title="Settings" @close="closePanel">
            <GameplaySettingsPanel :game-id="props.gameId" />
        </GameplayBottomSheet>

        <GameplayBottomSheet :open="activePanel === 'audio'" title="Audio" @close="closePanel">
            <GameplayAudioPanel />
        </GameplayBottomSheet>
    </div>
</template>

<style scoped>
.mobile-pill {
    align-items: center;
    gap: 2px;
    padding: 6px;
    border-radius: 60px;
    background-color: rgba(51, 51, 51, 0.45);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    box-shadow:
        inset 3px 3px 0.5px -3.5px rgba(255, 255, 255, 0.5),
        inset -3px -3px 0.5px -3.5px rgba(255, 255, 255, 0.55),
        inset 1px 1px 1px -0.5px rgba(255, 255, 255, 0.3),
        inset -1px -1px 1px -0.5px rgba(255, 255, 255, 0.3),
        inset 0 0 1px 1px rgba(153, 153, 153, 0.15),
        0 4px 24px rgba(0, 0, 0, 0.3);
}

.mobile-pill__btn {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s ease;
    flex-shrink: 0;
}

.mobile-pill__btn:active,
.mobile-pill__btn--active {
    background: rgba(84, 244, 218, 0.12);
}

.mobile-pill__btn:active {
    background: rgba(255, 255, 255, 0.08);
}
</style>
