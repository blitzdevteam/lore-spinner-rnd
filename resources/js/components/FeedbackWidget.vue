<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { router } from '@inertiajs/vue3';
import { Camera, LucideMessageSquare, Send, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const tts = useTextToSpeech();

// Lift the button above the audio player bar when it is visible on mobile
const feedbackBtnStyle = computed(() =>
    tts.isActive.value && !tts.mediaCollapsed.value
        ? { bottom: '165px' }
        : {},
);

const isOpen = ref(false);
const content = ref('');
const isSubmitting = ref(false);
const screenshotStatus = ref<'idle' | 'capturing' | 'ready' | 'failed'>('idle');
const screenshotPreview = ref<string | null>(null);
const screenshotFile = ref<File | null>(null);

const capturePageScreenshot = async (): Promise<File | null> => {
    try {
        const html2canvas = (await import('html2canvas')).default;

        const canvas = await html2canvas(document.documentElement, {
            useCORS: true,
            allowTaint: true,
            logging: false,
            scale: 1,
            width: window.innerWidth,
            height: window.innerHeight,
            windowWidth: window.innerWidth,
            windowHeight: window.innerHeight,
            x: window.scrollX,
            y: window.scrollY,
        });

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.80),
        );

        if (!blob) {
            console.warn('[FeedbackWidget] toBlob returned null');
            return null;
        }

        return new File([blob], 'screenshot.jpg', { type: 'image/jpeg' });
    } catch (error) {
        console.error('[FeedbackWidget] Screenshot capture failed:', error);
        return null;
    }
};

const open = async () => {
    // Capture BEFORE the modal renders so the widget UI doesn't appear in the shot
    screenshotStatus.value = 'capturing';
    screenshotPreview.value = null;
    screenshotFile.value = null;

    const file = await capturePageScreenshot();

    if (file) {
        screenshotFile.value = file;
        screenshotPreview.value = URL.createObjectURL(file);
        screenshotStatus.value = 'ready';
    } else {
        screenshotStatus.value = 'failed';
    }

    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
    content.value = '';
    screenshotStatus.value = 'idle';
    if (screenshotPreview.value) {
        URL.revokeObjectURL(screenshotPreview.value);
        screenshotPreview.value = null;
    }
    screenshotFile.value = null;
};

const submit = async () => {
    if (!content.value.trim() || isSubmitting.value) return;

    isSubmitting.value = true;

    const formData = new FormData();
    formData.append('content', content.value);
    if (screenshotFile.value) {
        formData.append('screenshot', screenshotFile.value);
    }

    router.post('/feedback', formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            close();
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Teleport to="body">
        <div class="feedback-widget-root">
            <Transition name="fade">
                <div v-if="isOpen" class="fixed inset-0 z-[999] bg-black/50 backdrop-blur-sm" @click="close" />
            </Transition>

            <Transition name="feedback-slide">
                <div
                    v-if="isOpen"
                    class="fixed right-4 bottom-44 left-4 z-[1000] max-w-sm rounded-2xl border border-primary-400/20 bg-gray-900 p-6 shadow-2xl shadow-primary-400/5 md:right-6 md:bottom-24 md:left-auto md:w-full"
                >
                <div class="flex items-start justify-between">
                    <h3 class="text-xl font-semibold text-primary-300">Feedback</h3>
                    <button class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-800 hover:text-white" @click="close">
                        <X :size="20" />
                    </button>
                </div>

                <p class="mt-3 text-sm text-gray-300">Tell us what you think about the beta</p>

                <textarea
                    v-model="content"
                    rows="5"
                    placeholder="Share your thoughts, suggestions, or report any issues..."
                    class="mt-3 w-full resize-none rounded-xl border border-gray-700 bg-gray-950 px-4 py-3 text-sm text-gray-200 placeholder-gray-500 outline-none transition-colors focus:border-primary-400/50"
                />

                <div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                    <Camera :size="14" />
                    <span v-if="screenshotStatus === 'capturing'" class="animate-pulse">Capturing screenshot…</span>
                    <span v-else-if="screenshotStatus === 'ready'" class="text-green-400">Screenshot ready</span>
                    <span v-else-if="screenshotStatus === 'failed'" class="text-yellow-500">Screenshot unavailable — feedback will still be sent</span>
                    <span v-else>A screenshot will be included with your feedback</span>
                </div>

                <!-- Screenshot thumbnail preview -->
                <div v-if="screenshotStatus === 'ready' && screenshotPreview" class="mt-2 overflow-hidden rounded-lg border border-gray-700">
                    <img :src="screenshotPreview" alt="Page screenshot preview" class="max-h-28 w-full object-cover object-top" />
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <BaseButton severity="muted" @click="close">Cancel</BaseButton>
                    <BaseButton severity="primary-muted-outline" :processing="isSubmitting" @click="submit">
                        <Send :size="16" class="mr-2" />
                        Send Feedback
                    </BaseButton>
                </div>
                </div>
            </Transition>

            <div data-feedback-btn class="fixed right-4 bottom-32 z-[998] transition-[bottom,left,right] duration-300 md:right-6 md:!bottom-6" :style="feedbackBtnStyle">
                <BaseButton
                    severity="glass"
                    :icon-only="true"
                    :processing="screenshotStatus === 'capturing'"
                    class="size-12! shadow-lg shadow-black/30 md:size-14!"
                    @click="open"
                >
                    <LucideMessageSquare class="size-6 text-primary-300" />
                </BaseButton>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.feedback-slide-enter-active,
.feedback-slide-leave-active {
    transition: all 0.25s ease;
}
.feedback-slide-enter-from,
.feedback-slide-leave-to {
    opacity: 0;
    transform: translateY(1rem) scale(0.95);
}
</style>
