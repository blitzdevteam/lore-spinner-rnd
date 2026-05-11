<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Chapter {
    id: number;
    position: number;
    title: string;
    events_count: number;
    sessions: number[];
}

interface Story {
    id: number;
    title: string;
    slug: string;
}

const props = defineProps<{
    story: Story;
    chapters: Chapter[];
}>();
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white">
        <!-- Header -->
        <header class="border-b border-gray-800 px-8 py-5">
            <div class="mx-auto flex max-w-6xl items-center gap-4">
                <Link
                    href="/writer/writer-lab"
                    class="text-sm text-gray-400 transition-colors hover:text-white"
                >
                    ← Stories
                </Link>
                <span class="text-gray-700">/</span>
                <h1 class="text-lg font-semibold">{{ story.title }}</h1>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-8 py-10">
            <div class="mb-8">
                <h2 class="text-sm uppercase tracking-widest text-gray-500">Chapters</h2>
                <p class="mt-1 text-gray-400 text-sm">Select a chapter to open the editorial editor.</p>
            </div>

            <div class="grid gap-3">
                <Link
                    v-for="chapter in chapters"
                    :key="chapter.id"
                    :href="`/writer/writer-lab/${story.id}/chapters/${chapter.id}`"
                    class="group flex items-center justify-between rounded-xl border border-gray-800 bg-gray-900 px-6 py-4 transition-all hover:border-primary-500/50 hover:bg-gray-800"
                >
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-mono text-gray-600">{{ String(chapter.position).padStart(2, '0') }}</span>
                        <span class="font-medium group-hover:text-primary-300 transition-colors">{{ chapter.title }}</span>
                    </div>
                    <div class="flex gap-3 text-xs text-gray-500">
                        <span>{{ chapter.events_count }} events</span>
                        <template v-if="chapter.sessions.length > 0">
                            <span class="text-gray-700">·</span>
                            <span>Sessions {{ chapter.sessions.join(', ') }}</span>
                        </template>
                    </div>
                </Link>
            </div>
        </main>
    </div>
</template>

<style scoped></style>
