<script setup lang="ts">
export interface StoryPlayStatItem {
    label: string;
    value: string;
}

defineProps<{
    items: StoryPlayStatItem[];
}>();
</script>

<template>
    <div class="relative min-h-[80px] w-full shrink-0 overflow-hidden rounded-xl" style="box-shadow: 0px 4px 80px rgba(0, 0, 0, 0.2)">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-xl bg-[rgba(255,255,255,0.04)]" />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 rounded-xl mix-blend-plus-lighter"
            style="
                background-image: linear-gradient(66.938deg, rgba(30, 30, 30, 0.25) 69.183%, rgba(255, 190, 88, 0.25) 113.61%);
            "
        />
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 rounded-xl backdrop-blur-[3px]"
            style="
                background-image: linear-gradient(71.087deg, rgba(0, 227, 255, 0.2) 23.765%, rgba(0, 0, 0, 0.2) 29.569%);
            "
        />

        <div class="relative z-[1] flex min-h-[70px] items-stretch px-1 py-[5px]">
            <div
                v-for="(item, idx) in items"
                :key="item.label"
                :class="idx < items.length - 1 ? 'border-r border-solid border-white/20' : ''"
                class="flex min-h-[70px] flex-1 flex-col items-center justify-center px-2 py-2 text-center font-['Inter',sans-serif] leading-7 text-white not-italic"
            >
                <p class="w-full shrink-0 text-[15px] font-semibold tracking-wide">{{ item.label }}</p>
                <p class="mt-px w-full shrink-0 break-words text-[14px] font-medium">{{ item.value }}</p>
            </div>
        </div>

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
</template>
