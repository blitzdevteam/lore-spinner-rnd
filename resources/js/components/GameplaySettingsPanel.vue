<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { router } from '@inertiajs/vue3';
import { LucideMinus, LucidePlus, LucideRefreshCw, LucideRotateCcw } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    gameId?: string;
}>();

const { settings, defaults, fontColorPresets, backgroundPresets, reset } = useGameplaySettings();

const clampFontSize = (val: number) => Math.min(28, Math.max(12, val));

const isResetting = ref(false);

const handleResetGame = () => {
    if (!props.gameId || isResetting.value) return;
    const confirmed = window.confirm(
        'Start a fresh game? This will erase all your current progress, including every prompt and choice you have made.',
    );
    if (!confirmed) return;

    isResetting.value = true;
    router.post(
        `/user/games/${props.gameId}/reset`,
        {},
        {
            preserveScroll: false,
            preserveState: false,
            onFinish: () => {
                isResetting.value = false;
            },
        },
    );
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-100">Settings</h3>
            <BaseButton severity="transparent" :icon-only="true" class="size-8!" @click="reset">
                <LucideRotateCcw :size="16" />
            </BaseButton>
        </div>

        <!-- Font Size -->
        <div class="flex flex-col gap-2.5">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Font Size</label>
            <div class="flex items-center gap-3">
                <BaseButton
                    severity="muted"
                    :icon-only="true"
                    class="size-9!"
                    :disabled="settings.fontSize <= 12"
                    @click="settings.fontSize = clampFontSize(settings.fontSize - 1)"
                >
                    <LucideMinus :size="14" />
                </BaseButton>
                <div class="flex-1">
                    <input
                        type="range"
                        :min="12"
                        :max="28"
                        :value="settings.fontSize"
                        class="gameplay-range w-full"
                        @input="settings.fontSize = clampFontSize(Number(($event.target as HTMLInputElement).value))"
                    />
                </div>
                <BaseButton
                    severity="muted"
                    :icon-only="true"
                    class="size-9!"
                    :disabled="settings.fontSize >= 28"
                    @click="settings.fontSize = clampFontSize(settings.fontSize + 1)"
                >
                    <LucidePlus :size="14" />
                </BaseButton>
                <span class="w-9 text-center text-sm text-gray-300">{{ settings.fontSize }}</span>
            </div>
        </div>

        <!-- Font Color -->
        <div class="flex flex-col gap-2.5">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Font Color</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in fontColorPresets"
                    :key="preset.value"
                    :class="[
                        'flex h-9 items-center gap-2 rounded-lg border px-3 transition-all',
                        settings.fontColor === preset.value
                            ? 'border-primary-400 bg-primary-400/10'
                            : 'border-gray-700 hover:border-gray-500',
                    ]"
                    @click="settings.fontColor = preset.value"
                >
                    <span
                        class="size-3.5 rounded-full border border-gray-600"
                        :style="{ backgroundColor: preset.value }"
                    />
                    <span class="text-xs text-gray-300">{{ preset.label }}</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    :value="settings.fontColor"
                    class="size-8 cursor-pointer rounded border-none bg-transparent"
                    @input="settings.fontColor = ($event.target as HTMLInputElement).value"
                />
                <span class="text-xs text-gray-500">Custom color</span>
            </div>
        </div>

        <!-- Background Color -->
        <div class="flex flex-col gap-2.5">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Background</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in backgroundPresets"
                    :key="preset.value"
                    :class="[
                        'flex h-9 items-center gap-2 rounded-lg border px-3 transition-all',
                        settings.backgroundColor === preset.value
                            ? 'border-primary-400 bg-primary-400/10'
                            : 'border-gray-700 hover:border-gray-500',
                    ]"
                    @click="settings.backgroundColor = preset.value"
                >
                    <span
                        v-if="preset.value"
                        class="size-3.5 rounded-full border border-gray-600"
                        :style="{ backgroundColor: preset.value }"
                    />
                    <span v-else class="size-3.5 rounded-full border border-dashed border-gray-500" />
                    <span class="text-xs text-gray-300">{{ preset.label }}</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="color"
                    :value="settings.backgroundColor || '#1a1a1a'"
                    class="size-8 cursor-pointer rounded border-none bg-transparent"
                    @input="settings.backgroundColor = ($event.target as HTMLInputElement).value"
                />
                <span class="text-xs text-gray-500">Custom color</span>
            </div>
        </div>

        <!-- Preview -->
        <div class="flex flex-col gap-2">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Preview</label>
            <div
                class="rounded-xl border border-gray-700/40 p-4"
                :style="{
                    backgroundColor: settings.backgroundColor || 'transparent',
                }"
            >
                <p
                    class="font-light leading-relaxed"
                    :style="{
                        fontSize: settings.fontSize + 'px',
                        color: settings.fontColor,
                    }"
                >
                    The ancient door creaked open, revealing a corridor bathed in moonlight...
                </p>
            </div>
        </div>

        <!-- Game controls -->
        <div v-if="gameId" class="flex flex-col gap-2.5 border-t border-gray-700/40 pt-6">
            <label class="text-xs font-medium uppercase tracking-wider text-gray-400">Game</label>
            <BaseButton
                severity="gray-muted"
                class="w-full"
                :disabled="isResetting"
                @click="handleResetGame"
            >
                <LucideRefreshCw :size="16" :class="{ 'animate-spin': isResetting }" />
                <span>{{ isResetting ? 'Resetting...' : 'Start a fresh game' }}</span>
            </BaseButton>
            <p class="text-xs leading-relaxed text-gray-500">
                Erases all progress for this story and begins a new playthrough from the start.
            </p>
        </div>
    </div>
</template>

<style scoped>
.gameplay-range {
    appearance: none;
    height: 0.25rem;
    background: #373737;
    border-radius: 0.25rem;
    outline: none;
}

.gameplay-range::-webkit-slider-thumb {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #54f4da;
    cursor: pointer;
    border: 2px solid #013231;
}

.gameplay-range::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #54f4da;
    cursor: pointer;
    border: 2px solid #013231;
}
</style>
