<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    authorName: string;
    avatarUrl?: string | null;
    description: string;
    /** Characters before truncation (approximate). */
    collapseAt?: number;
}>();

const expanded = ref(false);

const limit = computed(() => props.collapseAt ?? 360);

const canExpand = computed(() => props.description.length > limit.value);

const displayPrimary = computed(() => {
    if (!canExpand.value || expanded.value) {
        return props.description;
    }
    return `${props.description.slice(0, limit.value).trim()}…`;
});

function showMore(): void {
    expanded.value = true;
}

const initialLetter = computed(() => props.authorName?.charAt(0)?.toUpperCase() ?? '—');
</script>

<template>
    <div class="flex w-full flex-col gap-2">
        <div class="flex h-[30px] items-center gap-[5px]">
            <div class="relative size-[30px] shrink-0 overflow-hidden rounded-full bg-gray-800">
                <img v-if="avatarUrl" :src="avatarUrl" alt="" class="size-full object-cover" />
                <span
                    v-else
                    class="flex size-full items-center justify-center font-['Inter',sans-serif] text-[13px] font-semibold text-primary-500"
                >
                    {{ initialLetter }}
                </span>
            </div>
            <span
                class="font-['Inter',sans-serif] text-[14px] font-medium not-italic leading-[30px] text-white [word-break:break-word]"
            >
                {{ authorName }}
            </span>
        </div>

        <div class="relative w-full">
            <p
                class="font-['Inter',sans-serif] text-[15px] font-light not-italic leading-6 tracking-[0.3px] text-white [word-break:break-word]"
            >
                {{ displayPrimary }}
            </p>

            <button
                v-if="canExpand && !expanded"
                type="button"
                class="float-end mt-2 ms-4 font-['Inter',sans-serif] text-[13px] font-medium not-italic text-primary-500"
                @click="showMore()"
            >
                Show More
            </button>
            <div class="clear-both" aria-hidden="true" />
        </div>
    </div>
</template>
