<script setup lang="ts">
import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Link, usePage } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const moodsWrap = ref<HTMLElement | null>(null);
const moodsOpen = ref(false);

onClickOutside(moodsWrap, () => {
    moodsOpen.value = false;
});

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

const ACTIVE_UNDERLINE =
    'pointer-events-none absolute bottom-0 left-1/2 h-[3px] w-16 -translate-x-1/2 translate-y-px rounded-t-full bg-[#00C6DE] shadow-[0px_4px_4px_rgba(0,0,0,0.25)]';

const itemBase =
    'relative flex h-10 w-[100px] items-center justify-center rounded-lg px-2.5 font-[Inter] text-[16px] font-medium leading-[1.2] transition-colors';

const inactiveText = 'text-[#B4B4B4] hover:text-[#00C6DE]';
const activeText = 'text-[#00C6DE]';

const navClass = (active: boolean): string => `${itemBase} ${active ? activeText : inactiveText}`;
const moodItemClass = (slug: string): string =>
    [
        'flex h-[30px] w-full items-center rounded-[8px] px-[15px] font-[Inter] text-[14px] font-normal leading-[33px] text-white outline-none transition-colors first:text-[16px]',
        activeMood.value === slug
            ? 'border border-[#00C6DE] bg-[#2B4548]'
            : 'border border-transparent hover:border-[#00C6DE] hover:bg-[#2B4548] focus-visible:border-[#00C6DE] focus-visible:bg-[#2B4548]',
    ].join(' ');
</script>

<template>
    <nav aria-label="Primary">
        <ul class="flex items-center gap-1 md:gap-2">
            <li>
                <Link :href="index().url" :class="navClass(isHomeActive)">
                    <span class="relative z-[1]">Home</span>
                    <span v-if="isHomeActive" :class="ACTIVE_UNDERLINE" aria-hidden="true" />
                </Link>
            </li>

            <li ref="moodsWrap" class="relative">
                <button
                    type="button"
                    class="flex h-10 w-[100px] items-center justify-center gap-1 rounded-lg px-2.5 py-2.5 font-[Inter] text-[16px] font-medium transition-colors"
                    :class="isMoodsActive || moodsOpen ? activeText : inactiveText"
                    :aria-expanded="moodsOpen"
                    aria-haspopup="true"
                    @click="moodsOpen = !moodsOpen"
                >
                    Moods
                    <ChevronDown
                        class="size-[22px] shrink-0 text-current transition-transform"
                        :stroke-width="1.75"
                        :class="moodsOpen ? 'rotate-180' : ''"
                    />
                </button>

                <div
                    v-show="moodsOpen"
                    class="moods-dropdown absolute top-full left-1/2 z-50 mt-[14px] w-[189px] -translate-x-1/2 overflow-hidden rounded-[15px] px-[15px] py-[15px]"
                    role="menu"
                >
                    <Link
                        v-for="m in moodLinks"
                        :key="m.href + m.title"
                        :href="m.href"
                        :class="moodItemClass(m.slug)"
                        role="menuitem"
                        @click="moodsOpen = false"
                    >
                        {{ m.title }}
                    </Link>
                </div>
            </li>

            <li>
                <Link :href="storiesIndex().url" :class="navClass(isLibraryActive)">
                    <span class="relative z-[1]">Library</span>
                    <span v-if="isLibraryActive" :class="ACTIVE_UNDERLINE" aria-hidden="true" />
                </Link>
            </li>

            <li>
                <Link :href="`${storiesIndex().url}?ref=bookmarks`" :class="navClass(isBookmarksActive)">
                    <span class="relative z-[1]">Bookmarks</span>
                    <span v-if="isBookmarksActive" :class="ACTIVE_UNDERLINE" aria-hidden="true" />
                </Link>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.moods-dropdown {
    background:
        linear-gradient(180deg, rgba(0, 198, 222, 0.2) 1.34%, rgba(102, 102, 102, 0) 12.75%),
        linear-gradient(0deg, rgba(2, 3, 3, 0.58), rgba(2, 3, 3, 0.58)), rgba(23, 26, 27, 0.86);
    box-shadow:
        0 4px 80px rgba(0, 0, 0, 0.2),
        inset 0.25px 0.5px 0.5px 0.25px rgba(255, 255, 255, 0.22),
        inset -0.2px -0.5px 0.15px 0.5px rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
}

.moods-dropdown > :not(:last-child) {
    margin-bottom: 4px;
}
</style>
