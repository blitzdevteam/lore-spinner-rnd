<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { router } from '@inertiajs/vue3';
import { Camera, LucideMessageSquare, Send, X } from 'lucide-vue-next';
import { ref } from 'vue';

const isOpen = ref(false);
const content = ref('');
const isSubmitting = ref(false);

const open = () => {
    isOpen.value = true;
};

const close = () => {
    isOpen.value = false;
    content.value = '';
};

const capturePageScreenshot = async (): Promise<File | null> => {
    try {
        const html2canvas = (await import('html2canvas')).default;

        // Capture only the visible viewport (not the full scrollable page).
        // This keeps the file small (~100-300 KB as JPEG) and avoids PHP's
        // upload_max_filesize limit, which silently drops large uploads.
        // useCORS:true + allowTaint:false skips cross-origin images rather
        // than tainting the canvas (which would cause toBlob to throw).
        const canvas = await html2canvas(document.documentElement, {
            useCORS: true,
            allowTaint: false,
            logging: false,
            scale: 1,
            width: window.innerWidth,
            height: window.innerHeight,
            windowWidth: window.innerWidth,
            windowHeight: window.innerHeight,
            x: window.scrollX,
            y: window.scrollY,
            ignoreElements: (element: Element) => Boolean(element.closest('.feedback-widget-root')),
        });

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.75),
        );

        if (!blob) {
            return null;
        }

        return new File([blob], 'screenshot.jpg', { type: 'image/jpeg' });
    } catch (error) {
        console.error('[FeedbackWidget] Screenshot capture failed:', error);
        return null;
    }
};

const submit = async () => {
    if (!content.value.trim() || isSubmitting.value) return;

    isSubmitting.value = true;

    const screenshot = await capturePageScreenshot();

    const formData = new FormData();
    formData.append('content', content.value);
    if (screenshot) {
        formData.append('screenshot', screenshot);
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
                    <span>A screenshot will be included with your feedback</span>
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

            <div data-feedback-btn class="fixed right-4 bottom-28 z-[998] transition-[left,right] duration-200 md:right-6 md:bottom-6">
                <BaseButton severity="glass" :icon-only="true" class="size-12! shadow-lg shadow-black/30 md:size-14!" @click="open">
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
    transform: translateY(16px) scale(0.95);
}
</style>
