<script setup lang="ts">
import { index } from '@/wayfinder/routes';
import { index as creatorsIndex } from '@/wayfinder/routes/creators';
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

const isHomeActive = computed(() => {
    const p = pageUrl.value.split('?')[0];
    return p === '/' || p === '';
});

const isLibraryActive = computed(() => {
    const u = pageUrl.value;
    const p = u.split('?')[0];
    return p === storiesIndex().url && !u.includes('ref=bookmarks');
});

const isBookmarksActive = computed(() => pageUrl.value.includes('ref=bookmarks'));

const isMoodsActive = computed(() => {
    const p = pageUrl.value.split('?')[0];
    return p === creatorsIndex().url || p.startsWith(`${creatorsIndex().url}/`);
});

const moodLinks: { title: string; href: string }[] = [
    { title: 'Browse library', href: storiesIndex().url },
    { title: 'Creators', href: creatorsIndex().url },
];

const ACTIVE_UNDERLINE =
    'pointer-events-none absolute bottom-0 left-1/2 h-[3px] w-16 -translate-x-1/2 translate-y-px rounded-t-full bg-[#00C6DE] shadow-[0px_4px_4px_rgba(0,0,0,0.25)]';

const itemBase =
    'relative flex h-10 w-[100px] items-center justify-center rounded-lg px-2.5 font-[Inter] text-[16px] font-medium leading-[1.2] transition-colors';

const inactiveText = 'text-[#B4B4B4] hover:text-[#00C6DE]';
const activeText = 'text-[#00C6DE]';

const navClass = (active: boolean): string => `${itemBase} ${active ? activeText : inactiveText}`;
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
                    class="absolute start-0 top-full z-50 mt-1 min-w-[180px] rounded-lg border border-gray-600 bg-[#1a1a1a] py-1 shadow-[0_8px_24px_rgba(0,0,0,0.5)]"
                    role="menu"
                >
                    <Link
                        v-for="m in moodLinks"
                        :key="m.href + m.title"
                        :href="m.href"
                        class="block px-4 py-2.5 text-left text-[15px] font-medium text-[#B4B4B4] transition-colors hover:bg-white/5 hover:text-[#00C6DE]"
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

<style scoped></style>
