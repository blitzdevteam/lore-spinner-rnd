<script setup lang="ts">
import { index } from '@/wayfinder/routes';
import { Link } from '@inertiajs/vue3';
import { LucideChevronLeft } from 'lucide-vue-next';
</script>

<template>
    <div class="relative z-10">
        <Link
            :href="index().url"
            class="fixed top-4 left-4 z-20 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm text-gray-400 transition-colors hover:text-white sm:top-6 sm:left-6"
        >
            <LucideChevronLeft class="size-4" :stroke-width="2" aria-hidden="true" />
            Back to Home
        </Link>
        <div class="grid min-h-screen place-items-center max-md:block">
            <div class="flex w-full flex-col gap-8 p-4 max-md:min-h-screen">
                <div class="w-full max-md:flex-1">
                    <slot name="body"></slot>
                </div>
                <div class="w-full">
                    <slot name="footer"></slot>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
