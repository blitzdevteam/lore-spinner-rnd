<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import StickyFooterLayout from '@/layouts/StickyFooterLayout.vue';

const loginUrl    = '/writer/authentication/login';
const registerUrl = '/writer/authentication/register';

const currentRouteSeverity = (routeUrl: string): 'secondary-muted-outline' | 'muted' => {
    return routeUrl === window.location.pathname ? 'secondary-muted-outline' : 'muted';
};
</script>

<template>
    <BaseBackgroundGradient />
    <StickyFooterLayout class="mx-auto max-w-124">
        <template #body>
            <div class="flex w-full flex-col items-center gap-8">
                <div class="flex flex-col items-center gap-1">
                    <BaseLogo class="w-68" />
                    <span class="text-xs tracking-widest text-primary-400 uppercase">Writer Lab</span>
                </div>
                <div class="flex w-full items-center gap-4">
                    <BaseButton
                        type="internal-link"
                        :href="loginUrl"
                        :severity="currentRouteSeverity(loginUrl)"
                        class="h-12 flex-1"
                    >
                        Log In
                    </BaseButton>
                    <BaseButton
                        type="internal-link"
                        :href="registerUrl"
                        :severity="currentRouteSeverity(registerUrl)"
                        class="h-12 flex-1"
                    >
                        Sign Up
                    </BaseButton>
                </div>
                <slot name="body"></slot>
            </div>
        </template>
        <template #footer>
            <slot name="footer"></slot>
        </template>
    </StickyFooterLayout>
</template>

<style scoped></style>
