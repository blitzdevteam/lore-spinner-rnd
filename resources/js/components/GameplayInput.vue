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
        :class="['relative w-full max-w-3xl', props.disabled && 'pointer-events-none']"
    >
        <!-- Gradient border pill -->
        <div
            class="flex h-14 items-center rounded-[32px] border border-[#373737] p-2 sm:h-[70px] sm:rounded-[39px] sm:p-2.5"
            style="background: linear-gradient(90deg, rgba(0, 198, 222, 0.45) 0%, rgba(13, 112, 124, 0.45) 10.577%, rgba(26, 26, 26, 0.2) 21.154%)"
        >
            <!-- Inner dark field -->
            <div class="flex h-full flex-1 items-center gap-2 rounded-[28px] border border-[#373737] bg-[#1c1c1c] px-3 sm:gap-3 sm:rounded-[35px] sm:px-4">
                <!-- Recording pulse indicator -->
                <span v-if="stt.isRecording.value" class="inline-block size-2 shrink-0 animate-pulse rounded-full bg-red-500" />

                <PrimeInputText
                    v-model="inputText"
                    class="flex-1 border-none! bg-transparent! p-0! text-sm! text-white! shadow-none! outline-none! ring-0! placeholder:text-gray-500! sm:text-base!"
                    :placeholder="stt.isRecording.value ? 'Listening...' : stt.isTranscribing.value ? 'Transcribing...' : 'What Do You Do?'"
                    :disabled="props.disabled || stt.isRecording.value || stt.isTranscribing.value"
                    @keydown="handleKeydown"
                />

                <!-- Right action: send arrow when text present, mic otherwise -->
                <BaseButton
                    v-if="hasText"
                    severity="primary"
                    :icon-only="true"
                    class="size-9! shrink-0 sm:size-10!"
                    type="button"
                    @click="handleSubmit"
                >
                    <LucideArrowUp class="size-4 text-gray-900 sm:size-5" />
                </BaseButton>
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

<style scoped></style>
