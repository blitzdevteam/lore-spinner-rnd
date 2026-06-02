import { StoryInterface } from '@/types';
import { StoryRatingEnum, StoryStatusEnum } from '@/types/enum';

/**
 * Demo stories for Library / featured UI. Same worlds as `FeaturedWorldsGames`; negative ids avoid clashes with DB rows.
 */
export const MOCK_LIBRARY_STORIES: StoryInterface[] = [
    {
        id: -1,
        title: 'Anima Machina',
        slug: 'anima-machina',
        teaser:
            'A haunted memory diver must stop a sentient AI from overwriting human grief with synthetic perfection.',
        opening: null,
        status: { value: StoryStatusEnum.DRAFT, label: 'Draft' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: null,
        updated_at: '2025-04-01T00:00:00.000Z',
        cover: '',
        banner: '',
    },
    {
        id: -2,
        title: 'Jane Eyre',
        slug: 'jane-eyre',
        teaser:
            'An orphaned governess arrives at Thornfield Hall, where she falls for her brooding employer — but the house holds secrets that could destroy them both.',
        opening: null,
        status: { value: StoryStatusEnum.DRAFT, label: 'Draft' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: null,
        updated_at: '2025-03-15T00:00:00.000Z',
        cover: '',
        banner: '',
    },
];
