<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useSpeechToText } from '@/composables/useSpeechToText';
import { LucideArrowUp, LucideLoader, LucideMic, LucideSquare } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        disabled?: boolean;
        /** 'sweep' = idle left↔right loop, 'orbit' = clockwork ring while waiting */
        glowVariant?: 'sweep' | 'orbit';
    }>(),
    { disabled: false },
);

const emit = defineEmits<{
    submit: [prompt: string];
}>();

const inputText = ref('');
const stt = useSpeechToText();

const hasText = computed(() => inputText.value.trim().length > 0);

const handleSubmit = () => {
    if (props.disabled) return;
    const text = inputText.value.trim();
    if (!text) return;
    emit('submit', text);
    inputText.value = '';
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSubmit();
    }
};

const handleMicToggle = async () => {
    if (stt.isRecording.value) {
        const text = await stt.stopRecording();
        if (text) {
            inputText.value = text;
        }
    } else {
        await stt.startRecording();
    }
};

// ── Smooth crossfade when glow variant switches ───────────────────────────────
const displayedVariant = ref(props.glowVariant);
const isFading = ref(false);
let fadeTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.glowVariant,
    (next) => {
        if (fadeTimer) clearTimeout(fadeTimer);
        isFading.value = true;
        // Wait for fade-out, then swap gradient, then fade back in
        fadeTimer = setTimeout(() => {
            displayedVariant.value = next;
            fadeTimer = setTimeout(() => {
                isFading.value = false;
                fadeTimer = null;
            }, 60);
        }, 200);
    },
);

onUnmounted(() => {
    if (fadeTimer) clearTimeout(fadeTimer);
});
</script>

<template>
    <div
        :class="['relative w-full max-w-3xl', props.disabled && 'pointer-events-none']"
    >
        <!-- Gradient border pill -->
        <div
            :class="[
                'flex h-14 items-center rounded-[32px] border border-[#373737] p-2 sm:h-[70px] sm:rounded-[39px] sm:p-2.5',
                displayedVariant === 'sweep' && 'gp-pill--sweep',
                displayedVariant === 'orbit' && 'gp-pill--orbit',
                isFading && 'gp-pill--fading',
            ]"
            :style="!displayedVariant ? { background: 'linear-gradient(90deg, rgba(0, 198, 222, 0.45) 0%, rgba(13, 112, 124, 0.45) 10.577%, rgba(26, 26, 26, 0.2) 21.154%)' } : undefined"
        >
            <!-- Inner dark field -->
            <div class="flex h-full flex-1 items-center gap-2 rounded-[28px] border border-[#373737] bg-[#1c1c1c] sm:gap-3 sm:rounded-[35px] px-1">
                <!-- Recording pulse indicator -->
                <span v-if="stt.isRecording.value" class="inline-block size-2 shrink-0 animate-pulse rounded-full bg-red-500" />

                <PrimeInputText
                    v-model="inputText"
                    class="flex-1 border-none! bg-transparent! py-0! px-2! text-sm! text-white! shadow-none! outline-none! ring-0! placeholder:text-gray-500! sm:text-base!"
                    :placeholder="stt.isRecording.value ? 'Listening...' : stt.isTranscribing.value ? 'Transcribing...' : 'What Do You Do?'"
                    :disabled="props.disabled || stt.isRecording.value || stt.isTranscribing.value"
                    @keydown="handleKeydown"
                />

                <!-- Right action: send arrow when text present, mic otherwise -->
                <button
                    v-if="hasText"
                    type="button"
                    class="gp-send-btn grid size-9 shrink-0 place-items-center rounded-full sm:size-10"
                    :disabled="props.disabled"
                    title="Send"
                    @click="handleSubmit"
                >
                    <LucideArrowUp class="size-4 sm:size-5" :stroke-width="2.75" />
                </button>
                <BaseButton
                    v-else
                    severity="glass"
                    :icon-only="true"
                    class="size-9! shrink-0 sm:size-10!"
                    type="button"
                    @click="handleMicToggle"
                >
                    <LucideLoader v-if="stt.isTranscribing.value" class="size-5 animate-spin text-white" />
                    <LucideSquare v-else-if="stt.isRecording.value" fill="white" class="size-3.5 text-white" />
                    <LucideMic v-else class="size-5 text-gray-300" />
                </BaseButton>
            </div>
        </div>
    </div>
</template>

<style scoped>
.gp-send-btn {
    color: #0a1a1c;
    background: linear-gradient(180deg, #8fcbd3 0%, #6fafba 100%);
    border: 1px solid rgba(143, 203, 211, 0.65);
    box-shadow:
        0 0 0 1px rgba(84, 244, 218, 0.25),
        0 4px 16px rgba(84, 244, 218, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.35);
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
}

.gp-send-btn:hover:not(:disabled) {
    background: linear-gradient(180deg, #a8d8de 0%, #7eb8c4 100%);
    box-shadow:
        0 0 0 1px rgba(84, 244, 218, 0.45),
        0 6px 20px rgba(84, 244, 218, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.45);
}

.gp-send-btn:active:not(:disabled) {
    transform: scale(0.94);
}

.gp-send-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

/* ── Animated pill glow (registered custom props for GPU-smooth animation) ─── */
@property --gp-glow-pos {
    syntax: '<percentage>';
    initial-value: -5%;
    inherits: false;
}

@property --gp-glow-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}

/* Shared opacity transition so state switches feel smooth */
.gp-pill--sweep,
.gp-pill--orbit {
    transition: opacity 0.2s ease;
}

.gp-pill--fading {
    opacity: 0.18;
}

/* Idle: the teal band sweeps left → right → left smoothly */
.gp-pill--sweep {
    background: linear-gradient(
        90deg,
        transparent calc(var(--gp-glow-pos) - 12%),
        rgba(0, 198, 222, 0.48) var(--gp-glow-pos),
        rgba(13, 112, 124, 0.44) calc(var(--gp-glow-pos) + 10%),
        rgba(26, 26, 26, 0.18) calc(var(--gp-glow-pos) + 22%),
        transparent calc(var(--gp-glow-pos) + 32%)
    );
    animation: gp-glow-sweep 5.5s ease-in-out infinite;
}

@keyframes gp-glow-sweep {
    0%, 100% { --gp-glow-pos: -5%; }
    50%       { --gp-glow-pos: 82%; }
}

/* Sending: the glow travels clockwise around the pill perimeter */
.gp-pill--orbit {
    background: conic-gradient(
        from var(--gp-glow-angle) at 50% 50%,
        rgba(8, 206, 230, 0.92) 0deg,
        rgba(8, 206, 230, 0.55) 22deg,
        rgba(26, 26, 26, 0.14) 44deg 316deg,
        rgba(8, 206, 230, 0.55) 338deg,
        rgba(8, 206, 230, 0.92) 360deg
    );
    animation: gp-glow-orbit 2.8s linear infinite;
}

@keyframes gp-glow-orbit {
    from { --gp-glow-angle: 0deg; }
    to   { --gp-glow-angle: 360deg; }
}
</style>
