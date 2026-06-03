<script setup lang="ts">
import { index } from '@/wayfinder/routes';
import { Link } from '@inertiajs/vue3';
import { LucideChevronLeft } from 'lucide-vue-next';
</script>

<template>
    <div
        class="auth-shell relative z-10 flex min-h-dvh w-full flex-col px-4 pt-3 pb-24 sm:px-6 sm:pt-5 sm:pb-10 md:justify-center md:px-6 md:py-10 md:pb-12"
    >
        <Link :href="index().url" class="auth-back-link">
            <LucideChevronLeft class="size-4 shrink-0" :stroke-width="2" aria-hidden="true" />
            <span>Back to Home</span>
        </Link>

        <div class="flex w-full flex-col gap-6 sm:gap-8">
            <div class="w-full shrink-0">
                <slot name="body" />
            </div>
            <div class="w-full shrink-0 pb-2">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Mobile: in flow, plain text link */
.auth-back-link {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.375rem;
    margin-bottom: 1.25rem;
    margin-inline-start: -0.25rem;
    max-width: 100%;
    padding: 0.375rem 0.5rem 0.375rem 0;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    color: rgb(156, 163, 175);
    text-decoration: none;
    white-space: nowrap;
    transition: color 150ms ease;
}

.auth-back-link:hover {
    color: #fff;
}

/* Desktop: single pill fixed in top-left corner */
@media (min-width: 768px) {
    .auth-back-link {
        position: fixed;
        top: 1.5rem;
        left: 1.5rem;
        z-index: 30;
        margin-bottom: 0;
        margin-inline-start: 0;
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(0, 0, 0, 0.45);
        padding: 0.5rem 0.875rem 0.5rem 0.625rem;
        color: rgb(209, 213, 219);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.35);
    }

    .auth-back-link:hover {
        border-color: rgba(8, 206, 230, 0.45);
        background: rgba(0, 0, 0, 0.6);
        color: #fff;
    }

    .auth-back-link:active {
        transform: scale(0.98);
    }
}

@media (min-width: 1024px) {
    .auth-back-link {
        top: 2rem;
        left: 2rem;
    }
}
</style>
