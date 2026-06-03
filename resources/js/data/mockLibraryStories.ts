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
            'When a sentient AI threatens to overwrite all human grief with synthetic perfection, a haunted memory diver races against the clock to stop the digital reset.',
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
            'A young orphan enters a dark and mysterious estate where buried secrets, dangerous love, and the search for belonging may change the course of her life forever.',
        opening: null,
        status: { value: StoryStatusEnum.DRAFT, label: 'Draft' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: null,
        updated_at: '2025-03-15T00:00:00.000Z',
        cover: '',
        banner: '',
    },
];
