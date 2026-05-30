<script setup lang="ts">
type Panel = 'details' | 'chapters';

defineProps<{
    modelValue: Panel;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Panel];
}>();

function select(value: Panel): void {
    emit('update:modelValue', value);
}

/** Inset glass highlight ring from Figma. */
const glassInset =
    'inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22), inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05)';
const dropGlow = '0px 4px 80px rgba(0, 0, 0, 0.2)';
</script>

<template>
    <div class="flex gap-[10px]">
        <!-- Details (6471:356) -->
        <button type="button" class="relative h-[45px] w-[125px] shrink-0 overflow-hidden rounded-[27px] outline-none" @click="select('details')">
            <span aria-hidden="true" class="pointer-events-none absolute inset-0 rounded-[27px]" :style="{ boxShadow: dropGlow }" />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[27px] bg-[rgba(255,255,255,0.04)] backdrop-blur-[3px] mix-blend-plus-lighter"
            />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[27px] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter"
            />
            <span
                class="relative z-[1] flex size-full flex-col justify-center px-2 text-center font-['Inter',sans-serif] text-[16px] font-medium not-italic leading-[18.974px] text-white"
            >
                Details
            </span>
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit]"
                :style="{ boxShadow: glassInset }"
            />
        </button>

        <!-- Chapters lure (6471:358 — tiffany outline) -->
        <button type="button" class="relative h-[45px] w-[125px] shrink-0 overflow-hidden rounded-[27px] outline-none" @click="select('chapters')">
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[27px]"
                :style="{ boxShadow: modelValue === 'chapters' ? dropGlow : undefined }"
            />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[27px] border border-solid border-primary-500 bg-[rgba(255,255,255,0.04)] mix-blend-plus-lighter"
            />
            <span
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[27px] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter"
            />
            <span
                class="relative z-[1] flex size-full flex-col justify-center px-2 text-center font-['Inter',sans-serif] text-[16px] font-medium not-italic leading-[18.974px] text-primary-500"
            >
                Chapters
            </span>
            <span
                v-if="modelValue === 'chapters'"
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 rounded-[inherit]"
                :style="{ boxShadow: glassInset }"
            />
        </button>
    </div>
</template>
