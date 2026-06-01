<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import StickyFooterLayout from '@/layouts/StickyFooterLayout.vue';
import completeProfile from '@/wayfinder/routes/user/authentication/complete-profile';
import { CircleCheck, Copy, Check } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    credentials: {
        email: string;
        password: string;
    };
}>();

const copied = ref(false);

const copyCredentials = async () => {
    const text = `Email: ${props.credentials.email}\nPassword: ${props.credentials.password}`;
    await navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};
</script>

<template>
    <BaseBackgroundGradient />
    <StickyFooterLayout class="mx-auto max-w-124">
        <template #body>
            <div class="flex w-full flex-col items-center gap-8">
                <BaseLogo class="w-68" />
                <CircleCheck class="text-primary-500" :size="56" :stroke-width="1.5" />
                <div class="flex flex-col gap-2 text-center">
                    <p class="text-xl font-medium text-white">Account Created!</p>
                    <p class="text-sm text-gray-400">Your account is ready. Save your credentials below before continuing.</p>
                </div>
            </div>
        </template>
        <template #footer>
            <div class="flex w-full flex-col gap-6">
                <div class="rounded-xl border border-gray-700/50 bg-gray-900/80 p-5">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium tracking-wider text-gray-500 uppercase">Your Login Credentials</p>
                            <button
                                class="flex cursor-pointer items-center gap-1.5 text-xs transition-colors"
                                :class="copied ? 'text-primary-400' : 'text-gray-400 hover:text-white'"
                                @click="copyCredentials"
                            >
                                <component :is="copied ? Check : Copy" :size="14" />
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between rounded-lg bg-gray-950/60 px-4 py-3">
                                <span class="text-sm text-gray-400">Email</span>
                                <span class="font-mono text-sm text-white">{{ credentials.email }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-gray-950/60 px-4 py-3">
                                <span class="text-sm text-gray-400">Password</span>
                                <span class="font-mono text-sm text-white">{{ credentials.password }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-yellow-600/20 bg-yellow-500/5 p-4">
                    <p class="text-center text-xs leading-relaxed text-yellow-500/80">
                        This is a <strong>staging platform</strong> under active development. Features may change and data may be reset. Please save your credentials — no recovery emails are sent.
                    </p>
                </div>

                <BaseButton
                    type="internal-link"
                    :href="completeProfile.edit.url()"
                    severity="primary"
                    class="text-lg"
                >
                    Continue
                </BaseButton>
            </div>
        </template>
    </StickyFooterLayout>
</template>

<style scoped></style>
