<script setup lang="ts">
export interface StoryPlayStatSlot {
    label: string;
    value: string;
}

defineProps<{
    items: StoryPlayStatSlot[];
}>();

const widths = ['w-[135px]', 'w-[134px]', 'w-[134px]', 'w-[134px]'];
</script>

<template>
    <div class="relative h-[80px] w-full shrink-0 overflow-hidden rounded-xl" style="box-shadow: 0px 4px 80px rgba(0, 0, 0, 0.2)">
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

        <div class="relative z-[1] flex items-center gap-0 px-1 pt-[5px]">
            <template v-for="(item, i) in items" :key="item.label">
                <div
                    :class="[widths[i] ?? widths[widths.length - 1]]"
                    class="flex h-[70px] flex-col items-center justify-center gap-0 px-2.5 py-2 text-center font-['Inter',sans-serif] leading-7 text-white not-italic"
                >
                    <p class="w-full shrink-0 text-[15px] font-semibold tracking-wide">{{ item.label }}</p>
                    <p class="w-full shrink-0 text-[14px] font-medium">{{ item.value }}</p>
                </div>
                <div
                    v-if="i < items.length - 1"
                    role="presentation"
                    class="mx-px h-[70px] w-px shrink-0 bg-linear-to-b from-transparent via-white/25 to-transparent"
                />
            </template>
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
