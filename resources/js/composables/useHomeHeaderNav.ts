import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useHomeHeaderNav() {
    const page = usePage();

    const pageUrl = computed(() => page.url);

    const activeMood = computed(() => {
        const query = pageUrl.value.split('?')[1] ?? '';
        return new URLSearchParams(query).get('mood');
    });

    const isHomeActive = computed(() => {
        const p = pageUrl.value.split('?')[0];
        return p === '/' || p === '';
    });

    const isLibraryActive = computed(() => {
        const u = pageUrl.value;
        const p = u.split('?')[0];
        return p === storiesIndex().url && !u.includes('ref=bookmarks') && !activeMood.value;
    });

    const isBookmarksActive = computed(() => pageUrl.value.includes('ref=bookmarks'));

    const isMoodsActive = computed(() => activeMood.value !== null);

    const moodLinks: { title: string; slug: string; href: string }[] = [
        { title: 'Heartfelt', slug: 'heartfelt', href: `${storiesIndex().url}?mood=heartfelt` },
        { title: 'Adventurous', slug: 'adventurous', href: `${storiesIndex().url}?mood=adventurous` },
        { title: 'Mysterious', slug: 'mysterious', href: `${storiesIndex().url}?mood=mysterious` },
        { title: 'Epic', slug: 'epic', href: `${storiesIndex().url}?mood=epic` },
        { title: 'Whimsical', slug: 'whimsical', href: `${storiesIndex().url}?mood=whimsical` },
    ];

    return {
        activeMood,
        isHomeActive,
        isLibraryActive,
        isBookmarksActive,
        isMoodsActive,
        moodLinks,
        homeHref: index().url,
        libraryHref: storiesIndex().url,
        bookmarksHref: `${storiesIndex().url}?ref=bookmarks`,
    };
}
