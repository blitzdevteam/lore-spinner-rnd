<script setup lang="ts">
import { index } from '@/wayfinder/routes';
import { index as creatorsIndex } from '@/wayfinder/routes/creators';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, House, LucideIcon, Mic, PenLine, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const writerLoggedIn = computed(() => (page.props as Record<string, unknown>).writerLoggedIn as boolean);

const writerLabLink = computed(() =>
    writerLoggedIn.value ? '/writer/writer-lab' : '/writer/authentication/login',
);

const menu = computed<{ title: string; link: string; icon: LucideIcon }[]>(() => [
    {
        title: 'Home',
        link: index().url,
        icon: House,
    },
    {
        title: 'Library',
        link: storiesIndex().url,
        icon: BookOpen,
    },
    {
        title: 'Creators',
        link: creatorsIndex().url,
        icon: Users,
    },
    {
        title: 'XEN',
        link: '/user/voice-lab',
        icon: Mic,
    },
    {
        title: 'Writer Lab',
        link: writerLabLink.value,
        icon: PenLine,
    },
]);

const getMenuLinkClass = (link: string): string => {
    const activeClass =
        window.location.pathname === link || window.location.pathname.startsWith('/writer/writer-lab')
            ? 'text-primary-400 before:bg-primary-400'
            : 'border-transparent text-muted-foreground hover:text-primary-400';

    return `relative
    before:absolute before:bottom-0 before:left-0 before:right-0 before:w-full before:h-[3px] before:rounded-t-full
    min-w-26 justify-center flex items-center gap-2.5 h-19 pb-[3px] transition ${activeClass}`;
};
</script>

<template>
    <ul class="flex items-center gap-4">
        <li v-for="item in menu" :key="item.title">
            <Link :href="item.link" :class="getMenuLinkClass(item.link)">
                <component :strokeWidth="1.75" :is="item.icon" class="size-4.5" />
                <p>{{ item.title }}</p>
            </Link>
        </li>
    </ul>
</template>

<style scoped></style>
