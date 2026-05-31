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
    <div class="relative mx-auto w-[20.875rem] max-w-full shrink-0 lg:mx-0">
        <div class="relative aspect-[334/497] w-full overflow-hidden rounded-[0.875rem] bg-gray-900">
            <img
                v-if="src"
                :alt="title"
                :src="src"
                class="size-full object-cover"
            />
            <template v-else>
                <div class="absolute inset-0 bg-linear-to-br from-gray-700 via-gray-900 to-black" />
                <div class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/70 via-transparent to-black/55" />
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
            </template>

            <div class="pointer-events-none absolute inset-0 z-10">
                <slot name="overlay" />
            </div>
        </div>
    </div>
</template>
