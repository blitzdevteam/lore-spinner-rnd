<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayMediaPlayer from '@/components/GameplayMediaPlayer.vue';
import GameplaySettingsPanel from '@/components/GameplaySettingsPanel.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { LucideAudioLines, LucideChevronLeft, LucideNotebookText, LucideSettings, LucideX, LucideZap } from 'lucide-vue-next';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        inputDisabled?: boolean;
        gameId?: string;
        coverUrl?: string | null;
    }>(),
    { inputDisabled: false, gameId: undefined, coverUrl: undefined },
);

type RightPanel = 'journal' | 'settings' | null;
const activePanel = ref<RightPanel>(null);
const journalTab = ref<'journals' | 'characters'>('journals');
const mediaCollapsed = ref(false);

const { settings } = useGameplaySettings();
const tts = useTextToSpeech();

const openJournal = (tab: 'journals' | 'characters') => {
    if (activePanel.value === 'journal' && journalTab.value === tab) {
        activePanel.value = null;
        return;
    }
    journalTab.value = tab;
    activePanel.value = 'journal';
};

const toggleJournal = () => {
    activePanel.value = activePanel.value === 'journal' ? null : 'journal';
};

const toggleSettings = () => {
    activePanel.value = activePanel.value === 'settings' ? null : 'settings';
};

const toggleMedia = () => {
    mediaCollapsed.value = !mediaCollapsed.value;
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
    <div class="relative h-svh">
        <BaseBackgroundGradient class="z-0" :cover-url="props.coverUrl" />
        <div class="relative flex min-h-svh">
            <div class="flex-1">
                <!-- ── Top header bar ── -->
                <div class="sticky top-0 right-0 left-0 z-30 w-full">
                    <div
                        class="z-50 flex h-20 items-center justify-between gap-3 bg-linear-to-b from-gray-950 via-gray-950/60 to-transparent px-4 transition-all duration-300 sm:px-8 md:h-24"
                    >
                        <!-- Left: back button (always) + settings (desktop only) -->
                        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                            <BaseButton severity="glass" :icon-only="true" class="size-11!" title="Go back" @click="$emit('back')">
                                <LucideChevronLeft class="size-6 text-gray-50" :stroke-width="1.75" />
                            </BaseButton>
                            <BaseButton severity="glass" :icon-only="true" class="hidden size-11! md:flex" :title="activePanel === 'settings' ? 'Close settings' : 'Settings'" @click="toggleSettings">
                                <LucideX v-if="activePanel === 'settings'" class="size-5 text-secondary-300" />
                                <LucideSettings v-else class="size-5 text-secondary-300" />
                            </BaseButton>
                        </div>

                        <!-- Center: media player (desktop only) -->
                        <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                            <GameplayMediaPlayer :collapsed="mediaCollapsed" />
                        </div>

                        <!-- Right: settings (mobile only) + audio / journal / characters -->
                        <!-- Desktop: individual glass buttons -->
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
                                :title="mediaCollapsed ? 'Show audio player' : 'Hide audio player'"
                                @click="toggleMedia"
                            >
                                <LucideAudioLines
                                    class="size-5"
                                    :class="tts.isActive.value && !mediaCollapsed ? 'text-primary' : 'text-gray-300'"
                                />
                            </BaseButton>
                            <BaseButton
                                severity="glass"
                                :icon-only="true"
                                class="size-11!"
                                :title="activePanel === 'journal' ? 'Close notes' : 'Notes'"
                                @click="toggleJournal"
                            >
                                <LucideX v-if="activePanel === 'journal'" class="size-5 text-secondary-300" />
                                <LucideNotebookText v-else class="size-5 text-secondary-300" />
                            </BaseButton>
                        </div>

                        <!-- Mobile: all action buttons in a single pill -->
                        <div class="mobile-pill flex md:hidden">
                            <button class="mobile-pill__btn" :title="activePanel === 'settings' ? 'Close settings' : 'Settings'" @click="toggleSettings">
                                <LucideX v-if="activePanel === 'settings'" class="size-5 text-secondary-300" />
                                <LucideSettings v-else class="size-5 text-gray-300" />
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
                            <button class="mobile-pill__btn" :title="mediaCollapsed ? 'Show audio player' : 'Hide audio player'" @click="toggleMedia">
                                <LucideAudioLines
                                    class="size-5"
                                    :class="tts.isActive.value && !mediaCollapsed ? 'text-primary' : 'text-gray-300'"
                                />
                            </button>
                            <button class="mobile-pill__btn" :title="activePanel === 'journal' ? 'Close notes' : 'Notes'" @click="toggleJournal">
                                <LucideX v-if="activePanel === 'journal'" class="size-5 text-secondary-300" />
                                <LucideNotebookText v-else class="size-5 text-gray-300" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Scrolling content ── -->
                <div
                    class="z-5 mx-auto flex max-w-3xl flex-col p-4 transition-colors duration-300"
                    :style="{ fontSize: settings.fontSize + 'px', color: settings.fontColor }"
                >
                    <!-- Title + episode -->
                    <div class="mb-2">
                        <slot name="header" />
                    </div>

                    <div>
                        <div class="flex flex-col gap-8">
                            <slot name="game" />
                        </div>
                    </div>
                </div>

                <!-- ── Bottom input ── -->
                <div class="sticky right-0 bottom-0 left-0 z-20 w-full">
                    <div
                        class="flex flex-col items-center gap-3 bg-linear-to-t from-gray-950 via-gray-950/80 to-transparent px-4 pt-10 pb-6 md:px-0"
                    >
                        <!-- Media player: mobile only (desktop lives in the topbar center) -->
                        <div class="flex w-full justify-start md:hidden">
                            <GameplayMediaPlayer :collapsed="mediaCollapsed" />
                        </div>
                        <GameplayInput :disabled="props.inputDisabled" @submit="handleInputSubmit" />
                    </div>
                </div>
            </div>

            <Transition name="backdrop-fade">
                <div v-if="activePanel" class="fixed inset-0 z-40 bg-black/50" @click="activePanel = null" />
            </Transition>
            <Transition name="sidebar-slide">
                <!-- Journal panel -->
                <div
                    v-if="activePanel === 'journal'"
                    key="journal"
                    class="fixed inset-y-0 right-0 z-50 flex h-svh w-[85vw] max-w-sm flex-col overflow-hidden border-s border-gray-700 bg-gray-900 md:sticky md:right-auto md:z-50 md:w-md md:max-w-none md:shrink-0"
                >
                    <div class="flex h-full w-full flex-col">
                        <Tabs v-model:value="journalTab" class="flex h-full w-full flex-col px-4 md:px-8" :show-navigators="false" unstyled>
                            <TabList pt:tab-list="h-20 flex items-center gap-4 md:h-24 shrink-0" pt:content="" pt:active-bar="hidden">
                                <Tab class="flex-1" value="journals" v-slot="slotProps" as-child>
                                    <BaseButton
                                        @click="slotProps.onClick"
                                        class="w-full"
                                        :severity="slotProps.active ? 'secondary-muted-outline' : 'gray-muted'"
                                    >
                                        Journal
                                    </BaseButton>
                                </Tab>
                                <Tab class="flex-1" value="characters" v-slot="slotProps" as-child>
                                    <BaseButton
                                        @click="slotProps.onClick"
                                        class="w-full"
                                        :severity="slotProps.active ? 'secondary-muted-outline' : 'gray-muted'"
                                    >
                                        Characters
                                    </BaseButton>
                                </Tab>
                            </TabList>
                            <TabPanels class="min-h-0 flex-1 overflow-y-auto">
                                <TabPanel value="journals">
                                    <div class="flex flex-col gap-4 pb-8">
                                        <slot name="journals" />
                                    </div>
                                </TabPanel>
                                <TabPanel value="characters">
                                    <div class="flex flex-col gap-3 pb-8">
                                        <slot name="characters" />
                                    </div>
                                </TabPanel>
                            </TabPanels>
                        </Tabs>
                    </div>
                </div>
                <!-- Settings panel -->
                <div
                    v-else-if="activePanel === 'settings'"
                    key="settings"
                    class="fixed inset-y-0 right-0 z-50 flex h-svh w-[85vw] max-w-sm flex-col overflow-y-auto border-s border-gray-700 bg-gray-900 md:sticky md:right-auto md:z-50 md:w-sm md:max-w-none md:shrink-0"
                >
                    <div class="flex h-full w-full flex-col px-6 pt-8">
                        <GameplaySettingsPanel :game-id="props.gameId" />
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.sidebar-slide-enter-active,
.sidebar-slide-leave-active {
    transition: all 0.3s ease;
}

.sidebar-slide-enter-from,
.sidebar-slide-leave-to {
    width: 0;
    opacity: 0;
}

.backdrop-fade-enter-active,
.backdrop-fade-leave-active {
    transition: opacity 0.3s ease;
}

.backdrop-fade-enter-from,
.backdrop-fade-leave-to {
    opacity: 0;
}

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

.mobile-pill__btn:active {
    background: rgba(255, 255, 255, 0.08);
}
</style>
