<script setup lang="ts">
import { LucideCopy, LucideMail, LucideRotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const DANIEL_EMAIL = 'danial.nrahimi@gmail.com';

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

const copied = ref(false);
const emailOpened = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | undefined;
let emailTimer: ReturnType<typeof setTimeout> | undefined;

function buildEmailContent() {
    const title = props.storyTitle ? ` - ${props.storyTitle}` : '';
    const subject = `LoreSpinner aurora tuning${title}`;
    const body = `Hi Daniel,\n\nMy aurora background settings from the playground:\n\n${exportJson.value}\n`;
    return { subject, body };
}

function buildMailtoHref(): string {
    const { subject, body } = buildEmailContent();
    return `mailto:${DANIEL_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

const gmailComposeHref = computed(() => {
    const { subject, body } = buildEmailContent();
    const params = new URLSearchParams({
        view: 'cm',
        fs: '1',
        to: DANIEL_EMAIL,
        su: subject,
        body,
    });
    return `https://mail.google.com/mail/?${params.toString()}`;
});

/** Must run synchronously inside the click handler or the OS may block mailto. */
function openMailtoClient() {
    const link = document.createElement('a');
    link.href = buildMailtoHref();
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function openGmailWeb() {
    window.open(gmailComposeHref.value, '_blank', 'noopener,noreferrer');
}

async function copyExport() {
    await navigator.clipboard.writeText(exportJson.value);
    copied.value = true;
    if (copiedTimer) clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
        copied.value = false;
    }, 5000);
}

function emailDaniel() {
    // Mailto first (preserves user-gesture); clipboard after.
    openMailtoClient();
    void copyExport();
    emailOpened.value = true;
    if (emailTimer) clearTimeout(emailTimer);
    emailTimer = setTimeout(() => {
        emailOpened.value = false;
    }, 8000);
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

        <section class="aurora-lab__section aurora-lab__share">
            <h4 class="aurora-lab__section-title">Share with Daniel</h4>
            <p class="aurora-lab__hint">Copy the block below, or open email with everything filled in.</p>

            <div class="aurora-lab__export-wrap">
                <pre class="aurora-lab__export">{{ exportJson }}</pre>
                <button type="button" class="aurora-lab__export-copy" title="Copy JSON" @click="copyExport">
                    <LucideCopy class="size-3.5" />
                    Copy
                </button>
            </div>

            <p v-if="copied && !emailOpened" class="aurora-lab__copied" role="status">
                Copied! Send this in Telegram to Daniel!
            </p>
            <p v-if="emailOpened" class="aurora-lab__copied" role="status">
                JSON copied. If Mail did not open, use <strong class="text-amber-100">Gmail (web)</strong> below and paste, or send on Telegram.
            </p>

            <div class="aurora-lab__share-actions">
                <button type="button" class="aurora-lab__share-btn aurora-lab__share-btn--primary" @click="copyExport">
                    <LucideCopy class="size-4 shrink-0" />
                    Copy JSON
                </button>
                <button type="button" class="aurora-lab__share-btn" @click="emailDaniel">
                    <LucideMail class="size-4 shrink-0" />
                    Email Daniel
                </button>
            </div>
            <button type="button" class="aurora-lab__gmail-fallback" @click="openGmailWeb">
                Gmail (web) — opens compose in the browser
            </button>
        </section>
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

.aurora-lab__share {
    gap: 0.625rem;
}

.aurora-lab__export-wrap {
    position: relative;
}

.aurora-lab__export {
    max-height: 10rem;
    overflow: auto;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.4);
    padding: 0.625rem 4.5rem 0.625rem 0.625rem;
    font-size: 0.625rem;
    line-height: 1.45;
    color: rgba(229, 173, 83, 0.75);
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
}

.aurora-lab__export-copy {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 0.375rem;
    border: 1px solid rgba(229, 173, 83, 0.35);
    background: rgba(229, 173, 83, 0.12);
    padding: 0.25rem 0.5rem;
    font-size: 0.625rem;
    font-weight: 500;
    color: rgba(250, 235, 200, 0.95);
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}

.aurora-lab__export-copy:hover {
    border-color: rgba(229, 173, 83, 0.6);
    background: rgba(229, 173, 83, 0.22);
}

.aurora-lab__copied {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgba(84, 244, 218, 0.95);
    line-height: 1.4;
}

.aurora-lab__share-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.aurora-lab__share-btn {
    display: inline-flex;
    flex: 1;
    min-width: 7.5rem;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    padding: 0.625rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: rgb(209, 213, 219);
    text-decoration: none;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.aurora-lab__share-btn:hover {
    border-color: rgba(229, 173, 83, 0.45);
    color: rgb(250, 235, 200);
}

.aurora-lab__share-btn--primary {
    border-color: rgba(229, 173, 83, 0.4);
    background: rgba(229, 173, 83, 0.14);
    color: rgba(250, 235, 200, 0.95);
}

.aurora-lab__share-btn--primary:hover {
    background: rgba(229, 173, 83, 0.24);
}

.aurora-lab__gmail-fallback {
    width: 100%;
    border: none;
    background: transparent;
    padding: 0.25rem 0;
    font-size: 0.6875rem;
    text-align: center;
    color: rgba(156, 163, 175, 0.95);
    text-decoration: underline;
    text-underline-offset: 2px;
    cursor: pointer;
    transition: color 0.15s;
}

.aurora-lab__gmail-fallback:hover {
    color: rgba(229, 173, 83, 0.95);
}
</style>
