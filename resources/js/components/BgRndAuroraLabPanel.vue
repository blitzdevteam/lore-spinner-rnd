<script setup lang="ts">
import { LucideCopy, LucideRotateCcw } from 'lucide-vue-next';
import { computed } from 'vue';

export interface AuroraTuning {
    baseMix: number;
    midFromMix: number;
    midTiffanyMix: number;
    midAmberMix: number;
    accentMix: number;
    highlightMix: number;
    intensity: number;
    secondsPerColor: number;
}

export interface AuroraBrandColors {
    tiffany: string;
    amber: string;
    black: string;
    highlight: string;
}

export interface AuroraPaletteColors {
    from: string;
    via: string;
    accent: string;
}

const tuning = defineModel<AuroraTuning>('tuning', { required: true });
const brand = defineModel<AuroraBrandColors>('brand', { required: true });
const palette = defineModel<AuroraPaletteColors>('palette', { required: true });
const useCustomColors = defineModel<boolean>('useCustomColors', { required: true });

const props = defineProps<{
    storyTitle?: string;
}>();

const emit = defineEmits<{
    reset: [];
    syncFromStory: [];
}>();

const exportJson = computed(() =>
    JSON.stringify(
        {
            useCustomColors: useCustomColors.value,
            palette: palette.value,
            brand: brand.value,
            tuning: tuning.value,
        },
        null,
        2,
    ),
);

function fmt(n: number, decimals = 2): string {
    return n.toFixed(decimals);
}

function patchTuning<K extends keyof AuroraTuning>(key: K, raw: string) {
    tuning.value = { ...tuning.value, [key]: Number(raw) };
}

function patchBrand<K extends keyof AuroraBrandColors>(key: K, value: string) {
    brand.value = { ...brand.value, [key]: value };
}

function patchPalette<K extends keyof AuroraPaletteColors>(key: K, value: string) {
    palette.value = { ...palette.value, [key]: value };
}

async function copyExport() {
    await navigator.clipboard.writeText(exportJson.value);
}
</script>

<template>
    <div class="aurora-lab flex flex-col gap-5 pb-8">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-100">Aurora Lab</h3>
                <p v-if="storyTitle" class="mt-0.5 text-[11px] text-gray-500">{{ storyTitle }}</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="aurora-lab__icon-btn" title="Reset defaults" @click="emit('reset')">
                    <LucideRotateCcw class="size-4" />
                </button>
                <button type="button" class="aurora-lab__icon-btn" title="Copy JSON" @click="copyExport">
                    <LucideCopy class="size-4" />
                </button>
            </div>
        </div>

        <!-- Custom colours -->
        <section class="aurora-lab__section">
            <label class="aurora-lab__toggle">
                <input v-model="useCustomColors" type="checkbox" class="size-4 rounded border-gray-600" />
                <span>Custom story colours</span>
            </label>
            <p class="aurora-lab__hint">Off = palette from the story chip below. On = edit From / Via / Accent.</p>

            <div class="flex flex-col gap-3" :class="useCustomColors ? '' : 'pointer-events-none opacity-45'">
                <div class="aurora-lab__color-row">
                    <label>From</label>
                    <input type="color" :value="palette.from" @input="patchPalette('from', ($event.target as HTMLInputElement).value)" />
                    <input
                        type="text"
                        class="aurora-lab__hex"
                        :value="palette.from"
                        spellcheck="false"
                        @change="patchPalette('from', ($event.target as HTMLInputElement).value)"
                    />
                </div>
                <div class="aurora-lab__color-row">
                    <label>Via</label>
                    <input type="color" :value="palette.via" @input="patchPalette('via', ($event.target as HTMLInputElement).value)" />
                    <input
                        type="text"
                        class="aurora-lab__hex"
                        :value="palette.via"
                        spellcheck="false"
                        @change="patchPalette('via', ($event.target as HTMLInputElement).value)"
                    />
                </div>
                <div class="aurora-lab__color-row">
                    <label>Accent</label>
                    <input type="color" :value="palette.accent" @input="patchPalette('accent', ($event.target as HTMLInputElement).value)" />
                    <input
                        type="text"
                        class="aurora-lab__hex"
                        :value="palette.accent"
                        spellcheck="false"
                        @change="patchPalette('accent', ($event.target as HTMLInputElement).value)"
                    />
                </div>
            </div>

            <button
                type="button"
                class="aurora-lab__text-btn"
                :disabled="!useCustomColors"
                @click="emit('syncFromStory')"
            >
                Load colours from current story
            </button>
        </section>

        <!-- Brand colours -->
        <section class="aurora-lab__section">
            <h4 class="aurora-lab__section-title">Brand</h4>
            <div class="flex flex-col gap-3">
                <div class="aurora-lab__color-row">
                    <label>Tiffany</label>
                    <input type="color" :value="brand.tiffany" @input="patchBrand('tiffany', ($event.target as HTMLInputElement).value)" />
                    <input
                        type="text"
                        class="aurora-lab__hex"
                        :value="brand.tiffany"
                        spellcheck="false"
                        @change="patchBrand('tiffany', ($event.target as HTMLInputElement).value)"
                    />
                </div>
                <div class="aurora-lab__color-row">
                    <label>Amber</label>
                    <input type="color" :value="brand.amber" @input="patchBrand('amber', ($event.target as HTMLInputElement).value)" />
                    <input
                        type="text"
                        class="aurora-lab__hex"
                        :value="brand.amber"
                        spellcheck="false"
                        @change="patchBrand('amber', ($event.target as HTMLInputElement).value)"
                    />
                </div>
                <div class="aurora-lab__color-row">
                    <label>Black</label>
                    <input type="color" :value="brand.black" @input="patchBrand('black', ($event.target as HTMLInputElement).value)" />
                    <input
                        type="text"
                        class="aurora-lab__hex"
                        :value="brand.black"
                        spellcheck="false"
                        @change="patchBrand('black', ($event.target as HTMLInputElement).value)"
                    />
                </div>
                <div class="aurora-lab__color-row">
                    <label>Highlight</label>
                    <input type="color" :value="brand.highlight" @input="patchBrand('highlight', ($event.target as HTMLInputElement).value)" />
                    <input
                        type="text"
                        class="aurora-lab__hex"
                        :value="brand.highlight"
                        spellcheck="false"
                        @change="patchBrand('highlight', ($event.target as HTMLInputElement).value)"
                    />
                </div>
            </div>
        </section>

        <!-- Mix sliders -->
        <section class="aurora-lab__section">
            <h4 class="aurora-lab__section-title">Mix (0 → story / brand, 1 → base)</h4>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Base (via → black)</span>
                    <code>{{ fmt(tuning.baseMix) }}</code>
                </div>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="tuning.baseMix"
                    class="aurora-lab__range"
                    @input="patchTuning('baseMix', ($event.target as HTMLInputElement).value)"
                />
            </div>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Mid from</span>
                    <code>{{ fmt(tuning.midFromMix) }}</code>
                </div>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="tuning.midFromMix"
                    class="aurora-lab__range"
                    @input="patchTuning('midFromMix', ($event.target as HTMLInputElement).value)"
                />
            </div>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Mid Tiffany</span>
                    <code>{{ fmt(tuning.midTiffanyMix) }}</code>
                </div>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="tuning.midTiffanyMix"
                    class="aurora-lab__range"
                    @input="patchTuning('midTiffanyMix', ($event.target as HTMLInputElement).value)"
                />
            </div>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Mid amber</span>
                    <code>{{ fmt(tuning.midAmberMix) }}</code>
                </div>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="tuning.midAmberMix"
                    class="aurora-lab__range"
                    @input="patchTuning('midAmberMix', ($event.target as HTMLInputElement).value)"
                />
            </div>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Accent</span>
                    <code>{{ fmt(tuning.accentMix) }}</code>
                </div>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="tuning.accentMix"
                    class="aurora-lab__range"
                    @input="patchTuning('accentMix', ($event.target as HTMLInputElement).value)"
                />
            </div>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Highlight</span>
                    <code>{{ fmt(tuning.highlightMix) }}</code>
                </div>
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.01"
                    :value="tuning.highlightMix"
                    class="aurora-lab__range"
                    @input="patchTuning('highlightMix', ($event.target as HTMLInputElement).value)"
                />
            </div>
        </section>

        <!-- Shader -->
        <section class="aurora-lab__section">
            <h4 class="aurora-lab__section-title">Shader</h4>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Intensity</span>
                    <code>{{ fmt(tuning.intensity) }}</code>
                </div>
                <input
                    type="range"
                    min="0"
                    max="1.5"
                    step="0.01"
                    :value="tuning.intensity"
                    class="aurora-lab__range"
                    @input="patchTuning('intensity', ($event.target as HTMLInputElement).value)"
                />
            </div>

            <div class="aurora-lab__slider">
                <div class="aurora-lab__slider-head">
                    <span>Seconds per colour</span>
                    <code>{{ fmt(tuning.secondsPerColor, 1) }}</code>
                </div>
                <input
                    type="range"
                    min="1"
                    max="30"
                    step="0.5"
                    :value="tuning.secondsPerColor"
                    class="aurora-lab__range"
                    @input="patchTuning('secondsPerColor', ($event.target as HTMLInputElement).value)"
                />
            </div>
        </section>

        <pre class="aurora-lab__export">{{ exportJson }}</pre>
    </div>
</template>

<style scoped>
.aurora-lab__section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.aurora-lab__section:first-of-type {
    border-top: none;
    padding-top: 0;
}

.aurora-lab__section-title {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(229, 173, 83, 0.85);
}

.aurora-lab__toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: rgb(229, 231, 235);
    cursor: pointer;
}

.aurora-lab__hint {
    font-size: 0.6875rem;
    line-height: 1.4;
    color: rgb(107, 114, 128);
}

.aurora-lab__color-row {
    display: grid;
    grid-template-columns: 4.5rem 2.25rem 1fr;
    align-items: center;
    gap: 0.5rem;
}

.aurora-lab__color-row label {
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: rgb(156, 163, 175);
}

.aurora-lab__color-row input[type='color'] {
    width: 2.25rem;
    height: 2.25rem;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.375rem;
    background: transparent;
    cursor: pointer;
}

.aurora-lab__hex {
    width: 100%;
    border-radius: 0.375rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(0, 0, 0, 0.35);
    padding: 0.375rem 0.5rem;
    font-family: ui-monospace, monospace;
    font-size: 0.6875rem;
    color: rgb(209, 213, 219);
}

.aurora-lab__slider-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.6875rem;
    color: rgb(156, 163, 175);
}

.aurora-lab__slider-head code {
    font-family: ui-monospace, monospace;
    font-size: 0.75rem;
    color: rgba(229, 173, 83, 0.95);
}

.aurora-lab__slider {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.aurora-lab__range {
    appearance: none;
    width: 100%;
    height: 0.25rem;
    border-radius: 0.25rem;
    background: rgba(229, 173, 83, 0.2);
    outline: none;
}

.aurora-lab__range::-webkit-slider-thumb {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #e5ad53;
    border: 2px solid #050409;
    cursor: pointer;
}

.aurora-lab__range::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background: #e5ad53;
    border: 2px solid #050409;
    cursor: pointer;
}

.aurora-lab__icon-btn,
.aurora-lab__text-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    color: rgb(209, 213, 219);
    transition: border-color 0.15s, color 0.15s;
}

.aurora-lab__icon-btn {
    width: 2rem;
    height: 2rem;
}

.aurora-lab__text-btn {
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    width: 100%;
}

.aurora-lab__icon-btn:hover,
.aurora-lab__text-btn:hover:not(:disabled) {
    border-color: rgba(229, 173, 83, 0.45);
    color: rgb(250, 235, 200);
}

.aurora-lab__text-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.aurora-lab__export {
    max-height: 10rem;
    overflow: auto;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.4);
    padding: 0.625rem;
    font-size: 0.625rem;
    line-height: 1.45;
    color: rgba(229, 173, 83, 0.75);
    white-space: pre-wrap;
    word-break: break-all;
}
</style>
