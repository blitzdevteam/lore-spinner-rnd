<script setup lang="ts">
withDefaults(defineProps<{
    src?: string | null;
    /** Accessible label for jacket image. */
    title: string;
    headline?: string | null;
    footerCredit?: string | null;
}>(), {
    src: '',
    headline: null,
    footerCredit: null,
});
</script>

<template>
    <div class="relative mx-auto w-full max-w-[20.875rem] shrink-0 lg:mx-0">
        <div class="relative aspect-[334/497] w-full overflow-hidden rounded-[0.875rem]">
            <template v-if="src">
                <img :alt="title" class="absolute inset-0 size-full max-w-none object-cover" :src="src" />
            </template>
            <div v-else class="absolute inset-0 rounded-[0.875rem] bg-linear-to-br from-gray-700 via-gray-900 to-black" />

            <div class="pointer-events-none absolute inset-0 rounded-[0.875rem] bg-linear-to-t from-black/70 via-transparent to-black/55" />

            <div
                v-if="headline"
                class="pointer-events-none absolute inset-x-3 top-[12%] z-[2] px-3 text-center font-['Marcellus_SC','Marcellus SC',serif] text-[0.6875rem] font-normal uppercase leading-snug tracking-[0.2em] text-white sm:text-sm md:inset-x-8 md:text-[0.8125rem]"
            >
                {{ headline }}
            </div>
            <div
                v-if="footerCredit"
                class="pointer-events-none absolute inset-x-0 bottom-6 z-[2] px-4 text-center font-['Inter',sans-serif] text-[0.6875rem] font-medium uppercase tracking-[0.35em] text-white/95"
            >
                {{ footerCredit }}
            </div>

            <slot name="overlay" />
        </div>
    </div>
</template>
