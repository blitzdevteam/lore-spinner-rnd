<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import StickyFooterLayout from '@/layouts/StickyFooterLayout.vue';
import login from '@/wayfinder/routes/user/authentication/login';
import register from '@/wayfinder/routes/user/authentication/register';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const loginUrl = login.create.url();
const registerUrl = register.create.url();

const isLoginActive = computed(() => window.location.pathname === loginUrl);
const isRegisterActive = computed(() => window.location.pathname === registerUrl);
</script>

<template>
    <BaseBackgroundGradient />
    <StickyFooterLayout class="mx-auto max-w-124">
        <template #body>
            <div class="flex w-full flex-col items-center gap-6 sm:gap-8">
                <BaseLogo class="h-auto w-full max-w-[11.5rem] sm:max-w-[17rem]" />

                <nav class="auth-tabs" aria-label="Authentication">
                    <Link
                        :href="loginUrl"
                        class="auth-tabs__btn"
                        :class="{ 'auth-tabs__btn--active': isLoginActive }"
                        :aria-current="isLoginActive ? 'page' : undefined"
                    >
                        Log In
                    </Link>
                    <Link
                        :href="registerUrl"
                        class="auth-tabs__btn"
                        :class="{ 'auth-tabs__btn--active': isRegisterActive }"
                        :aria-current="isRegisterActive ? 'page' : undefined"
                    >
                        Sign Up
                    </Link>
                </nav>

                <slot name="body"></slot>
            </div>
        </template>
        <template #footer>
            <slot name="footer"></slot>
        </template>
    </StickyFooterLayout>
</template>

<style scoped>
.auth-tabs {
    display: flex;
    width: 100%;
    gap: 0.25rem;
    border-radius: 0.875rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(17, 24, 27, 0.85);
    padding: 0.25rem;
}

.auth-tabs__btn {
    flex: 1;
    display: flex;
    height: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.625rem;
    border: 1px solid transparent;
    font-size: 0.9375rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: rgb(156, 163, 175);
    text-decoration: none;
    transition:
        color 150ms ease,
        background-color 150ms ease,
        border-color 150ms ease,
        box-shadow 150ms ease;
}

.auth-tabs__btn:hover:not(.auth-tabs__btn--active) {
    color: rgb(229, 231, 235);
    background: rgba(255, 255, 255, 0.04);
}

.auth-tabs__btn--active {
    border-color: rgba(8, 206, 230, 0.35);
    background: linear-gradient(180deg, rgba(8, 206, 230, 0.22) 0%, rgba(8, 206, 230, 0.1) 100%);
    color: #fff;
    box-shadow:
        0 0 0 1px rgba(8, 206, 230, 0.12),
        0 4px 20px rgba(8, 206, 230, 0.15);
}
</style>
