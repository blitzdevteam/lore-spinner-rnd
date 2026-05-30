<script setup lang="ts">
defineProps<{
    title: string;
    /** e.g. "00%" — design shows progress capsule */
    progressLabel: string;
}>();
</script>

<template>
    <div class="flex w-full items-center justify-between gap-4">
        <h1
            class="min-w-0 max-w-[min(100%,359px)] truncate font-['Inter',sans-serif] text-[20px] font-semibold not-italic leading-[26px] text-white md:max-w-none md:overflow-visible md:whitespace-normal md:[word-break:break-word]"
        >
            {{ title }}
        </h1>
        <div
            class="relative h-[28px] w-[48px] shrink-0 overflow-hidden rounded-[27px] bg-[rgba(255,255,255,0.04)] backdrop-blur-[3px]"
            style="box-shadow: 0px 4px 80px rgba(0, 0, 0, 0.2)"
        >
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[27px] mix-blend-plus-lighter bg-[rgba(30,30,30,0.25)]"
            />
            <span
                class="absolute inset-0 flex flex-col justify-center px-1 text-center font-['Inter',sans-serif] text-[13px] font-semibold not-italic text-primary-500"
            >
                {{ progressLabel }}
            </span>
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit]"
                style="
                    box-shadow:
                        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
                        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
                "
            />
        </div>
    </div>
</template>
