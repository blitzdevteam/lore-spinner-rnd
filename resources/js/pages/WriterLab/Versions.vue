<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Version {
    id: number;
    session_number: number;
    version_number: number;
    is_active: boolean;
    note: string | null;
    event_count: number;
    created_at: string;
}

interface Story { id: number; title: string; slug: string }

const props = defineProps<{
    story: Story;
    versions: Version[];
}>();

const restoring = ref<number | null>(null);

const restore = (version: Version) => {
    if (!confirm(`Restore version ${version.version_number} for session ${version.session_number}? This will overwrite live events.`)) return;
    restoring.value = version.id;
    router.post(
        `/writer/writer-lab/${props.story.id}/versions/${version.id}/restore`,
        {},
        { onFinish: () => { restoring.value = null; } }
    );
};

const versionsBySession = computed(() => {
    const groups: Record<number, Version[]> = {};
    for (const v of props.versions) {
        if (!groups[v.session_number]) groups[v.session_number] = [];
        groups[v.session_number].push(v);
    }
    return groups;
});

const sessionKeys = computed(() =>
    Object.keys(versionsBySession.value).map(Number).sort()
);

const formatDate = (s: string) =>
    new Date(s).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white">
        <header class="border-b border-gray-800 px-8 py-5">
            <div class="mx-auto flex max-w-4xl items-center gap-4">
                <Link
                    :href="`/writer/writer-lab/${story.id}`"
                    class="text-sm text-gray-400 hover:text-white transition-colors"
                >
                    ← {{ story.title }}
                </Link>
                <span class="text-gray-700">/</span>
                <h1 class="text-lg font-semibold">Version History</h1>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-8 py-10">
            <div v-if="versions.length === 0" class="py-20 text-center text-gray-500">
                No versions yet. Activating a draft creates a version snapshot.
            </div>

            <div v-for="session in sessionKeys" :key="session" class="mb-10">
                <h2 class="mb-4 text-xs uppercase tracking-widest text-gray-500">Session {{ session }}</h2>

                <div class="space-y-3">
                    <div
                        v-for="version in versionsBySession[session]"
                        :key="version.id"
                        class="flex items-center justify-between rounded-xl border px-5 py-4 transition-all"
                        :class="version.is_active
                            ? 'border-emerald-700/50 bg-emerald-950/20'
                            : 'border-gray-800 bg-gray-900'"
                    >
                        <div class="flex items-center gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="font-medium text-sm">Version {{ version.version_number }}</span>
                                    <span
                                        v-if="version.is_active"
                                        class="rounded-full bg-emerald-900 px-2 py-0.5 text-xs text-emerald-300"
                                    >
                                        Active
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-gray-500">
                                    <span>{{ version.event_count }} events snapshotted</span>
                                    <span>{{ formatDate(version.created_at) }}</span>
                                    <span v-if="version.note">{{ version.note }}</span>
                                </div>
                            </div>
                        </div>

                        <button
                            v-if="!version.is_active"
                            class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-all disabled:opacity-40"
                            :disabled="restoring === version.id"
                            @click="restore(version)"
                        >
                            <span v-if="restoring === version.id">Restoring…</span>
                            <span v-else>Restore</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped></style>
