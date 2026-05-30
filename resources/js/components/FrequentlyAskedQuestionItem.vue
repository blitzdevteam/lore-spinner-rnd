<script setup lang="ts">
import { LucideCirclePlus, LucideMinus } from 'lucide-vue-next';

defineProps<{
    question: string;
    answer: string;
    expanded: boolean;
}>();

const emit = defineEmits<{
    toggle: [];
}>();
</script>

<template>
    <div
        class="w-full cursor-pointer rounded-[14px] border border-solid transition-[box-shadow,border-color] duration-200"
        :class="
            expanded
                ? 'flex flex-col items-center bg-[#292929] px-[14px] py-6 shadow-[0_0_18.3px_rgba(106,218,203,0.1)]'
                : 'flex min-h-[70px] flex-col items-center justify-center bg-[#292929] px-[14px] py-[22px]'
        "
        :style="
            expanded
                ? { borderColor: 'rgba(0, 198, 222, 0.6)' }
                : { borderColor: '#373737' }
        "
        role="button"
        tabindex="0"
        :aria-expanded="expanded"
        @click="emit('toggle')"
        @keydown.enter.prevent="emit('toggle')"
        @keydown.space.prevent="emit('toggle')"
    >
        <div class="flex w-full items-center gap-4 md:gap-[125px]">
            <p class="min-w-0 flex-1 text-left text-[18px] font-bold leading-[28px] text-white">
                {{ question }}
            </p>
            <div
                class="flex shrink-0 items-center justify-center text-primary"
                :class="expanded ? 'h-7 w-[34px]' : 'size-[27px]'"
            >
                <LucideMinus v-if="expanded" class="size-7 stroke-[2]" aria-hidden="true" />
                <LucideCirclePlus v-else class="size-[27px] stroke-[1.75]" aria-hidden="true" />
            </div>
        </div>
        <div v-if="expanded" class="mt-5 flex w-full flex-col">
            <p class="w-full text-[14px] font-normal leading-[30px] text-[#b6b6b6]">
                {{ answer }}
            </p>
        </div>
    </div>
</template>
