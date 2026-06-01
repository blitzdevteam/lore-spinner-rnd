<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import StickyFooterLayout from '@/layouts/StickyFooterLayout.vue';
import login from '@/wayfinder/routes/user/authentication/login';
import register from '@/wayfinder/routes/user/authentication/register';

const currentRouteSeverity = (routeUrl: string): 'primary-muted-outline' | 'muted' => {
    return routeUrl === window.location.pathname ? 'primary-muted-outline' : 'muted';
};
</script>

<template>
    <BaseBackgroundGradient />
    <StickyFooterLayout class="mx-auto max-w-124">
        <template #body>
            <div class="flex w-full flex-col items-center gap-8">
                <BaseLogo class="w-68" />
                <div class="flex w-full items-center gap-4">
                    <BaseButton
                        type="internal-link"
                        :href="login.create.url()"
                        :severity="currentRouteSeverity(login.create.url())"
                        class="h-12 flex-1"
                    >
                        Log In
                    </BaseButton>
                    <BaseButton
                        type="internal-link"
                        :href="register.create.url()"
                        :severity="currentRouteSeverity(register.create.url())"
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
