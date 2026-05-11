<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';

interface Story {
    id: number;
    slug: string;
    title: string;
    adaptation_status: string | null;
    chapters_count: number;
    session_count: number;
}

const props = defineProps<{
    stories: Story[];
}>();

const statusColor = (status: string | null): string => {
    if (!status) return 'text-gray-500';
    if (status === 'completed') return 'text-emerald-400';
    if (status === 'in_progress') return 'text-yellow-400';
    return 'text-gray-400';
};

const logout = () => {
    router.delete('/writer/authentication/logout');
};
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white">
        <!-- Header -->
        <header class="border-b border-gray-800 px-8 py-5">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Writer Lab</h1>
                    <p class="text-sm text-gray-400">Select a story to begin editorial curation</p>
                </div>
                <button
                    class="text-sm text-gray-400 transition-colors hover:text-white"
                    @click="logout"
                >
                    Log out
                </button>
            </div>
        </header>

        <!-- Story list -->
        <main class="mx-auto max-w-6xl px-8 py-10">
            <div v-if="stories.length === 0" class="py-20 text-center text-gray-500">
                No stories available yet.
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="story in stories"
                    :key="story.id"
                    :href="`/writer/writer-lab/${story.id}`"
                    class="group block rounded-2xl border border-gray-800 bg-gray-900 p-6 transition-all hover:border-primary-500/50 hover:bg-gray-800"
                >
                    <div class="mb-4">
                        <h2 class="text-lg font-medium leading-snug group-hover:text-primary-300 transition-colors">
                            {{ story.title }}
                        </h2>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <span class="rounded-md bg-gray-800 px-2 py-1 text-gray-300">
                            {{ story.chapters_count }} chapters
                        </span>
                        <span class="rounded-md bg-gray-800 px-2 py-1 text-gray-300">
                            {{ story.session_count }} sessions adapted
                        </span>
                        <span :class="['rounded-md px-2 py-1 capitalize', statusColor(story.adaptation_status)]">
                            {{ story.adaptation_status ?? 'Not adapted' }}
                        </span>
                    </div>
                </Link>
            </div>
        </main>
    </div>
</template>

<style scoped></style>
