<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useSpeechToText } from '@/composables/useSpeechToText';
import { LucideArrowUp, LucideLoader, LucideMic, LucideSquare } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        disabled?: boolean;
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
</script>

<template>
    <div
        :class="['relative w-full max-w-3xl transition-opacity duration-300', props.disabled && 'pointer-events-none opacity-40']"
    >
        <!-- Gradient border pill -->
        <div
            class="flex h-[70px] items-center rounded-full p-px"
            style="background: linear-gradient(90deg, rgba(0, 198, 222, 0.55) 0%, rgba(26, 26, 26, 0.25) 100%)"
        >
            <!-- Inner dark field -->
            <div class="flex h-full flex-1 items-center gap-3 rounded-full bg-[#1c1c1c] px-5">
                <!-- Recording pulse indicator -->
                <span v-if="stt.isRecording.value" class="inline-block size-2 shrink-0 animate-pulse rounded-full bg-red-500" />

                <PrimeInputText
                    v-model="inputText"
                    class="flex-1 border-none! bg-transparent! p-0! text-base! text-white! shadow-none! outline-none! ring-0! placeholder:text-gray-500!"
                    :placeholder="stt.isRecording.value ? 'Listening...' : stt.isTranscribing.value ? 'Transcribing...' : 'What Do You Do?'"
                    :disabled="props.disabled || stt.isRecording.value || stt.isTranscribing.value"
                    @keydown="handleKeydown"
                />

                <!-- Right action: send arrow when text present, mic otherwise -->
                <BaseButton
                    v-if="hasText"
                    severity="primary"
                    :icon-only="true"
                    class="size-10! shrink-0"
                    type="button"
                    @click="handleSubmit"
                >
                    <LucideArrowUp class="size-5 text-gray-900" />
                </BaseButton>
                <BaseButton
                    v-else
                    severity="glass"
                    :icon-only="true"
                    class="size-10! shrink-0"
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

<style scoped></style>
