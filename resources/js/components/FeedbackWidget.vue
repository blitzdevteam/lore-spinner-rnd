<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { router } from '@inertiajs/vue3';
import { LucideMessageSquare, Send, X } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

const tts = useTextToSpeech();

// Lift the button above the audio player bar when it is visible on mobile.
// Sticky bottom area height: pt-4(16) + player(52) + gap(12) + input(~56) + pb-5(20) ≈ 156px
// Use 200px so the 48px button sits comfortably clear of the whole strip.
const feedbackBtnStyle = computed(() =>
    tts.isActive.value && !tts.mediaCollapsed.value
        ? { bottom: '200px' }
        : {},
);

type Phase = 'idle' | 'capturing' | 'sending';

const isOpen = ref(false);
const content = ref('');
const phase = ref<Phase>('idle');
const submitError = ref<string | null>(null);

const isBusy = computed(() => phase.value !== 'idle');

/**
 * Capture the current viewport using html2canvas.
 * Returns null on any failure so callers can degrade gracefully.
 *
 * The feedback widget root carries `data-html2canvas-ignore` so html2canvas
 * skips it entirely — this prevents the loading overlay from appearing in the shot.
 */
const captureScreenshot = async (): Promise<File | null> => {
    try {
        const { default: html2canvas } = await import('html2canvas-pro');

        const canvas = await html2canvas(document.documentElement, {
            useCORS: true,
            allowTaint: true,
            logging: false,
            scale: 1,
            width: window.innerWidth,
            height: window.innerHeight,
            windowWidth: window.innerWidth,
            windowHeight: window.innerHeight,
            scrollX: -window.scrollX,
            scrollY: -window.scrollY,
        });

        return await new Promise<File | null>((resolve) => {
            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        console.warn('[FeedbackWidget] toBlob returned null');
                        resolve(null);
                        return;
                    }
                    resolve(new File([blob], 'screenshot.jpg', { type: 'image/jpeg' }));
                },
                'image/jpeg',
                0.8,
            );
        });
    } catch (error) {
        console.error('[FeedbackWidget] Screenshot capture failed:', error);
        return null;
    }
};

const open = () => {
    if (isBusy.value) {
        return;
    }

    submitError.value = null;
    isOpen.value = true;
};

const close = () => {
    if (isBusy.value) {
        return;
    }

    isOpen.value = false;
    content.value = '';
    submitError.value = null;
};

const submit = async () => {
    if (!content.value.trim() || isBusy.value) {
        return;
    }

    submitError.value = null;

    // Snapshot content now — the ref will be cleared on success
    const feedbackContent = content.value;

    // Step 1: hide modal so the screenshot captures the page behind it cleanly
    phase.value = 'capturing';
    isOpen.value = false;

    await nextTick();
    // Small delay to let the browser repaint without the modal and backdrop
    await new Promise<void>((resolve) => setTimeout(resolve, 200));

    const screenshotFile = await captureScreenshot();

    // Step 2: submit to the server
    phase.value = 'sending';

    const formData = new FormData();
    formData.append('content', feedbackContent);

    if (screenshotFile) {
        formData.append('screenshot', screenshotFile);
    }

    router.post('/feedback', formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            content.value = '';
            phase.value = 'idle';
            // isOpen remains false — widget collapses on success
        },
        onError: (errors) => {
            const firstMessage = Object.values(errors).flat()[0];
            submitError.value = firstMessage ?? 'Something went wrong. Please try again.';
            phase.value = 'idle';
            isOpen.value = true;
        },
        onFinish: () => {
            // Guard: onFinish runs after onSuccess/onError.
            // If phase is still 'sending' here it means neither callback ran — reset safely.
            if (phase.value === 'sending') {
                phase.value = 'idle';
            }
        },
    });
};
</script>

<template>
    <Teleport to="body">
        <div class="feedback-widget-root" data-html2canvas-ignore>
            <!-- Backdrop (only visible when modal is open) -->
            <Transition name="fade">
                <div v-if="isOpen" class="fixed inset-0 z-[999] bg-black/50 backdrop-blur-sm" @click="close" />
            </Transition>

            <!-- Full-screen overlay shown while capturing / sending -->
            <Transition name="fade">
                <div
                    v-if="isBusy"
                    class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/40 backdrop-blur-sm"
                >
                    <div class="flex flex-col items-center gap-3">
                        <div class="size-10 animate-spin rounded-full border-2 border-white/20 border-t-white" />
                        <p class="text-sm text-gray-300">
                            {{ phase === 'capturing' ? 'Capturing screenshot…' : 'Sending feedback…' }}
                        </p>
                    </div>
                </div>
            </Transition>

            <!-- Feedback modal -->
            <Transition name="feedback-slide">
                <div
                    v-if="isOpen"
                    class="fixed right-4 bottom-40 left-4 z-[1000] max-w-sm rounded-2xl border border-primary-400/20 bg-gray-900 p-6 shadow-2xl shadow-primary-400/5 md:right-6 md:bottom-24 md:left-auto md:w-full"
                >
                    <div class="flex items-start justify-between">
                        <h3 class="text-xl font-semibold text-primary-300">Feedback</h3>
                        <button
                            class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-800 hover:text-white"
                            @click="close"
                        >
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

                    <p class="mt-2 text-xs text-gray-500">
                        A screenshot of the current page will be included automatically.
                    </p>

                    <!-- Inline error message -->
                    <p v-if="submitError" class="mt-2 text-xs text-red-400">
                        {{ submitError }}
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <BaseButton severity="muted" @click="close">Cancel</BaseButton>
                        <BaseButton severity="primary-muted-outline" :processing="isBusy" @click="submit">
                            <Send :size="16" class="mr-2" />
                            Send Feedback
                        </BaseButton>
                    </div>
                </div>
            </Transition>

            <!-- Floating trigger button -->
            <div
                data-feedback-btn
                class="fixed right-4 bottom-28 z-[998] transition-[bottom] duration-300 md:right-6 md:!bottom-6"
                :style="feedbackBtnStyle"
            >
                <BaseButton
                    severity="glass"
                    :icon-only="true"
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
