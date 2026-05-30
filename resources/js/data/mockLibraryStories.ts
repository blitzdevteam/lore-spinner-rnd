import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.jpg';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';
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
        status: { value: StoryStatusEnum.PUBLISHED, label: 'Published' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: '2024-06-01T00:00:00.000Z',
        updated_at: '2025-04-01T00:00:00.000Z',
        cover: animaCover,
        banner: '',
    },
    {
        id: -2,
        title: 'Alice In Wonderland',
        slug: 'alice-in-wonderland',
        teaser: 'Fall deeper into a world where nothing is as it seems and every choice rewrites the rules.',
        opening: null,
        status: { value: StoryStatusEnum.DRAFT, label: 'Draft' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: null,
        updated_at: '2025-03-15T00:00:00.000Z',
        cover: aliceCover,
        banner: '',
    },
    {
        id: -3,
        title: 'Nocturne',
        slug: 'nocturne',
        teaser:
            'A jazz musician discovers the notes she plays can alter reality — but each performance costs a memory.',
        opening: null,
        status: { value: StoryStatusEnum.DRAFT, label: 'Draft' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: null,
        updated_at: '2025-02-20T00:00:00.000Z',
        cover: nocturneCover,
        banner: '',
    },
    {
        id: -4,
        title: 'Jekyll & Hyde',
        slug: 'jekyll-and-hyde',
        teaser:
            'Step into the fractured mind of a man at war with his own nature — and choose which side survives.',
        opening: null,
        status: { value: StoryStatusEnum.DRAFT, label: 'Draft' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: null,
        updated_at: '2025-01-10T00:00:00.000Z',
        cover: jekyllCover,
        banner: '',
    },
    {
        id: -5,
        title: 'The Wonderful Wizard of Oz',
        slug: 'the-wonderful-wizard-of-oz',
        teaser:
            'Follow the yellow brick road — but every path leads somewhere different, and not all roads lead home.',
        opening: null,
        status: { value: StoryStatusEnum.DRAFT, label: 'Draft' },
        rating: { value: StoryRatingEnum.EVERYONE, label: 'Everyone (All Ages)' },
        published_at: null,
        updated_at: '2024-12-05T00:00:00.000Z',
        cover: wizardOzCover,
        banner: '',
    },
];
