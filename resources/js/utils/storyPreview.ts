import type { StorySheetData } from '@/components/StoryDetailsSheet.vue';

export function storyCtaLabel(story: StorySheetData): string {
    if (story.isComingSoon || story.cta === 'coming-soon') return 'Coming Soon';
    if (story.cta === 'continue') return 'Continue';
    return 'Play';
}

export function storyIsInteractive(story: StorySheetData): boolean {
    return !story.isComingSoon && story.cta !== 'coming-soon' && !!story.slug;
}
