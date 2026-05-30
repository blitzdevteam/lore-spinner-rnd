<script setup lang="ts">
import FrequentlyAskedQuestionItem from '@/components/FrequentlyAskedQuestionItem.vue';
import { LucideChevronDown } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface FaqEntry {
    id: string;
    question: string;
    answer: string;
}

const faqs: FaqEntry[] = [
    {
        id: '1',
        question: 'What is Lorespinner and how does it work?',
        answer:
            'Lorespinner is a curated storytelling platform where readers explore original stories written by verified authors. Users can follow story paths, unlock chapters, and engage with unique worlds created by professional writers.',
    },
    {
        id: '2',
        question: 'Can readers edit or change characters in a story?',
        answer:
            'Readers explore branching paths the author designed. You cannot alter canonical characters or rewrite another creator’s story, but your choices can unlock different scenes and endings where those exist.',
    },
    {
        id: '3',
        question: 'How do I become a writer on Lorespinner?',
        answer:
            'Create an account, complete writer onboarding, and submit your story or pitch through the creator dashboard. Our team reviews submissions to keep quality and rights handling clear for every title.',
    },
    {
        id: '4',
        question: 'Can users generate images or artwork on the platform?',
        answer:
            'Any artwork must follow our content and attribution rules. Generative or third-party assets you upload need the right license; check the creator guidelines for allowed sources and disclosure.',
    },
    {
        id: '5',
        question: 'Are my reading choices private?',
        answer:
            'Your account and reading progress are protected as described in our privacy policy. We use this data to improve recommendations and sync your library, not to sell personal reading history.',
    },
];

const sectionOpen = ref(false);
/** Index of the open accordion item while the section is expanded (matches Figma: first item open). */
const openItemIndex = ref(0);

watch(sectionOpen, (open) => {
    if (open) openItemIndex.value = 0;
});

function toggleSection() {
    sectionOpen.value = !sectionOpen.value;
}

function toggleItem(index: number) {
    openItemIndex.value = openItemIndex.value === index ? -1 : index;
}
</script>

<template>
    <div class="flex w-full max-w-[1018px] flex-col gap-[14px]">
        <!-- Title + intro -->
        <div class="flex w-full flex-col items-center">
            <h2 class="flex h-10 w-full items-center text-[26px] font-bold uppercase leading-[33px] text-white">
                Frequently Asked Questions
            </h2>
            <div class="flex w-full items-start justify-between">
                <p class="max-w-[789px] text-[16px] font-normal leading-[26px] text-[#b6b6b6]">
                    Can’t find what you need? Visit our community forum, reach out on Twitter, or email our
                    support team.
                </p>
            </div>
        </div>

        <!-- Collapsed: Show More -->
        <button
            v-if="!sectionOpen"
            type="button"
            class="relative h-12 w-[152px] shrink-0 overflow-hidden rounded-[12px] text-left shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)] transition-opacity hover:opacity-90"
            aria-expanded="false"
            @click="toggleSection"
        >
            <span class="pointer-events-none absolute inset-0 rounded-[12px] bg-[rgba(53,53,53,0.6)]" />
            <span
                class="pointer-events-none absolute inset-0 rounded-[12px] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter backdrop-blur-[3px]"
            />
            <span
                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
            />
            <span class="relative flex h-full items-center justify-center gap-[10px] pl-[13px]">
                <span class="flex h-[9px] w-[18px] items-center justify-center text-white">
                    <LucideChevronDown class="size-[18px] shrink-0 stroke-[2]" aria-hidden="true" />
                </span>
                <span class="text-center text-[16px] font-medium leading-normal text-white">Show More</span>
            </span>
        </button>

        <!-- Expanded: glass panel -->
        <div
            v-else
            class="relative w-full min-h-[624px] overflow-hidden rounded-[14px] shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]"
        >
            <span class="pointer-events-none absolute inset-0 rounded-[14px] bg-[rgba(53,53,53,0.6)]" />
            <span
                class="pointer-events-none absolute inset-0 rounded-[14px] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter backdrop-blur-[3px]"
            />
            <span
                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
            />

            <div class="relative px-[18px] pb-6 pt-[72px]">
                <button
                    type="button"
                    class="absolute left-[13px] top-px z-10 flex h-[51px] items-center justify-center gap-[10px] text-primary hover:opacity-90"
                    aria-expanded="true"
                    @click="toggleSection"
                >
                    <span class="flex h-[9px] w-[18px] items-center justify-center">
                        <LucideChevronDown class="size-[18px] shrink-0 -rotate-180 stroke-[2]" aria-hidden="true" />
                    </span>
                    <span class="text-center text-[16px] font-medium leading-normal">Show Less</span>
                </button>

                <div class="mx-auto flex w-full max-w-[982px] flex-col gap-[10px]">
                    <FrequentlyAskedQuestionItem
                        v-for="(faq, index) in faqs"
                        :key="faq.id"
                        :question="faq.question"
                        :answer="faq.answer"
                        :expanded="openItemIndex === index"
                        @toggle="toggleItem(index)"
                    />

                    <button
                        type="button"
                        class="w-full pt-1 text-center text-[14px] font-normal leading-[33px] text-primary hover:underline"
                    >
                        View More
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
