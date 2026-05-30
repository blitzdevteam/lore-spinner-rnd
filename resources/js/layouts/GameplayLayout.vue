<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayMediaPlayer from '@/components/GameplayMediaPlayer.vue';
import GameplaySettingsPanel from '@/components/GameplaySettingsPanel.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { LucideAudioLines, LucideBookOpen, LucideChevronLeft, LucideScrollText, LucideSettings, LucideX } from 'lucide-vue-next';
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
    }>(),
    { inputDisabled: false, gameId: undefined },
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
        <BaseBackgroundGradient />
        <div class="relative flex min-h-svh">
            <div class="flex-1">
                <!-- ── Top header bar ── -->
                <div class="sticky top-0 right-0 left-0 z-30 w-full">
                    <div
                        class="z-50 flex h-20 items-center justify-between gap-3 bg-linear-to-b from-gray-950 via-gray-950/60 to-transparent px-4 transition-all duration-300 sm:px-8 md:h-24"
                    >
                        <!-- Left: back + settings -->
                        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                            <BaseButton severity="glass" :icon-only="true" class="size-11!" @click="$emit('back')">
                                <LucideChevronLeft class="size-6 text-gray-50" :stroke-width="1.75" />
                            </BaseButton>
                            <BaseButton severity="glass" :icon-only="true" class="size-11!" @click="toggleSettings">
                                <LucideX v-if="activePanel === 'settings'" class="size-5 text-secondary-300" />
                                <LucideSettings v-else class="size-5 text-secondary-300" />
                            </BaseButton>
                        </div>

                        <!-- Center: media player -->
                        <div class="flex min-w-0 flex-1 justify-center">
                            <GameplayMediaPlayer :collapsed="mediaCollapsed" />
                        </div>

                        <!-- Right: audio / journal / characters -->
                        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                            <BaseButton
                                severity="glass"
                                :icon-only="true"
                                class="size-11!"
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
                                @click="openJournal('journals')"
                            >
                                <LucideX v-if="activePanel === 'journal' && journalTab === 'journals'" class="size-5 text-secondary-300" />
                                <LucideScrollText v-else class="size-5 text-secondary-300" />
                            </BaseButton>
                            <BaseButton
                                severity="glass"
                                :icon-only="true"
                                class="size-11!"
                                @click="openJournal('characters')"
                            >
                                <LucideX v-if="activePanel === 'journal' && journalTab === 'characters'" class="size-5 text-secondary-300" />
                                <LucideBookOpen v-else class="size-5 text-secondary-300" />
                            </BaseButton>
                        </div>
                    </div>
                </div>

                <!-- ── Scrolling content ── -->
                <div
                    class="z-5 mx-auto flex max-w-3xl flex-col px-4 pt-2 pb-40 transition-colors duration-300"
                    :style="{ fontSize: settings.fontSize + 'px', color: settings.fontColor }"
                >
                    <!-- Title + episode -->
                    <div class="mb-2">
                        <slot name="header" />
                    </div>

                    <div :style="{ backgroundColor: settings.backgroundColor || undefined }">
                        <div class="flex flex-col gap-8">
                            <slot name="game" />
                        </div>
                    </div>
                </div>

                <!-- ── Bottom input ── -->
                <div class="sticky right-0 bottom-0 left-0 z-20 w-full">
                    <div
                        class="grid place-items-center bg-linear-to-t from-gray-950 via-gray-950/80 to-transparent px-4 pt-10 pb-6 md:px-0"
                    >
                        <GameplayInput :disabled="props.inputDisabled" @submit="handleInputSubmit" />
                    </div>
                </div>
            </div>

            <Transition name="backdrop-fade">
                <div v-if="activePanel" class="fixed inset-0 z-40 bg-black/50 md:hidden" @click="activePanel = null" />
            </Transition>
            <Transition name="sidebar-slide">
                <!-- Journal panel -->
                <div
                    v-if="activePanel === 'journal'"
                    key="journal"
                    class="fixed inset-y-0 right-0 z-50 flex h-svh w-[85vw] max-w-sm flex-col overflow-hidden border-s border-gray-700 bg-gray-900 md:sticky md:right-auto md:z-0 md:w-md md:max-w-none md:shrink-0"
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
                    class="fixed inset-y-0 right-0 z-50 flex h-svh w-[85vw] max-w-sm flex-col overflow-y-auto border-s border-gray-700 bg-gray-900 md:sticky md:right-auto md:z-0 md:w-sm md:max-w-none md:shrink-0"
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
</style>
