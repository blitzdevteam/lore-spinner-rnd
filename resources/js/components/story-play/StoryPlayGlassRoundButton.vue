<script setup lang="ts">
defineProps<{
    ariaLabel?: string;
}>();

defineSlots<{
    default(): unknown;
}>();
</script>

<template>
    <button
        type="button"
        class="relative size-[45px] shrink-0 overflow-hidden rounded-[27px] bg-[rgba(255,255,255,0.04)] backdrop-blur-[3px]"
        style="box-shadow: 0px 4px 80px rgba(0, 0, 0, 0.2)"
        :aria-label="ariaLabel"
    >
        <span
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 rounded-[27px] mix-blend-plus-lighter bg-[rgba(30,30,30,0.25)]"
        />
        <span class="relative z-[1] flex size-full items-center justify-center [&>svg]:size-5 [&>svg]:text-white">
            <slot />
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
    </button>
</template>
