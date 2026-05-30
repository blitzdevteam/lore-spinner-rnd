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
    <div class="home-section-gap w-full">
        <!-- Title + intro (left-aligned with other homepage sections) -->
        <div class="home-section-header">
            <h2 class="home-section-title">Frequently Asked Questions</h2>
            <p class="home-section-description">
                Can’t find what you need? Visit our community forum, reach out on Twitter, or email our
                support team.
            </p>
        </div>

        <!-- Collapsed: Show More -->
        <button
            v-if="!sectionOpen"
            type="button"
            class="relative h-12 w-[9.5rem] shrink-0 overflow-hidden rounded-[0.75rem] text-left shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)] transition-opacity hover:opacity-90"
            aria-expanded="false"
            @click="toggleSection"
        >
            <span class="pointer-events-none absolute inset-0 rounded-[0.75rem] bg-[rgba(53,53,53,0.6)]" />
            <span
                class="pointer-events-none absolute inset-0 rounded-[0.75rem] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter backdrop-blur-[3px]"
            />
            <span
                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
            />
            <span class="relative flex h-full items-center justify-center gap-[0.625rem] pl-[0.8125rem]">
                <span class="flex h-[0.5625rem] w-[1.125rem] items-center justify-center text-white">
                    <LucideChevronDown class="size-[1.125rem] shrink-0 stroke-[2]" aria-hidden="true" />
                </span>
                <span class="text-center text-[1rem] font-medium leading-normal text-white">Show More</span>
            </span>
        </button>

        <!-- Expanded: glass panel -->
        <div
            v-else
            class="relative w-full min-h-[39rem] overflow-hidden rounded-[0.875rem] shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]"
        >
            <span class="pointer-events-none absolute inset-0 rounded-[0.875rem] bg-[rgba(53,53,53,0.6)]" />
            <span
                class="pointer-events-none absolute inset-0 rounded-[0.875rem] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter backdrop-blur-[3px]"
            />
            <span
                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
            />

            <div class="relative px-[1.125rem] pb-6 pt-[4.5rem]">
                <button
                    type="button"
                    class="absolute left-[0.8125rem] top-px z-10 flex h-[3.1875rem] items-center justify-center gap-[0.625rem] text-primary hover:opacity-90"
                    aria-expanded="true"
                    @click="toggleSection"
                >
                    <span class="flex h-[0.5625rem] w-[1.125rem] items-center justify-center">
                        <LucideChevronDown class="size-[1.125rem] shrink-0 -rotate-180 stroke-[2]" aria-hidden="true" />
                    </span>
                    <span class="text-center text-[1rem] font-medium leading-normal">Show Less</span>
                </button>

                <div class="mx-auto flex w-full max-w-[61.375rem] flex-col gap-[0.625rem]">
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
                        class="w-full pt-1 text-center text-[0.875rem] font-normal leading-[2.0625rem] text-primary hover:underline"
                    >
                        View More
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
