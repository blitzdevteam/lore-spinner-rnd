<script setup lang="ts">
/**
 * Lightweight collaboration notes drawer.
 *
 * Minimal by design: a list of chapter-scoped notes with author + body. Each
 * note can be pinned to an event_id. Writers leave context for each other.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { wlError, wlTrace } from '@/lib/wlTrace';

interface Note {
    id: number;
    event_id: number | null;
    author_name: string;
    body: string;
    is_resolved: boolean;
    created_at: string | null;
}

const props = defineProps<{
    storyId: number;
    chapterId: number;
    /** Default name pre-filled into the author field (the logged-in writer) */
    defaultAuthor?: string;
    /** Optional event id to pre-pin the next composed note to */
    contextEventId?: number | null;
}>();

const emit = defineEmits<{ (e: 'close'): void }>();

const xsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

const notes      = ref<Note[]>([]);
const loading    = ref(false);
const composing  = ref(false);
const authorName = ref(props.defaultAuthor ?? '');
const bodyText   = ref('');
const pinToEvent = ref(true);
const showResolved = ref(false);

const baseUrl = `/writer/writer-lab/${props.storyId}/chapters/${props.chapterId}/notes`;

const load = async () => {
    loading.value = true;
    try {
        const res  = await fetch(baseUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        notes.value = data.notes ?? [];
        wlTrace('notes.load', { count: notes.value.length });
    } catch (e) {
        wlError('notes.load.failed', { error: String(e) });
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    const body = bodyText.value.trim();
    const author = authorName.value.trim();
    if (!body || !author) return;

    composing.value = true;
    try {
        const res = await fetch(baseUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                event_id: pinToEvent.value ? props.contextEventId ?? null : null,
                author_name: author,
                body,
            }),
        });
        const data = await res.json();
        if (data.note) {
            notes.value.unshift(data.note);
            bodyText.value = '';
            wlTrace('notes.create.ok', { note_id: data.note.id });
        } else {
            wlError('notes.create.bad_response', { data });
        }
    } catch (e) {
        wlError('notes.create.failed', { error: String(e) });
    } finally {
        composing.value = false;
    }
};

const toggleResolved = async (n: Note) => {
    const res = await fetch(`${baseUrl}/${n.id}/toggle`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
    });
    const data = await res.json();
    if (typeof data.is_resolved === 'boolean') {
        n.is_resolved = data.is_resolved;
    }
};

const destroy = async (n: Note) => {
    if (!confirm('Delete this note?')) return;
    await fetch(`${baseUrl}/${n.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
    });
    notes.value = notes.value.filter(x => x.id !== n.id);
};

const visibleNotes = () => showResolved.value
    ? notes.value
    : notes.value.filter(n => !n.is_resolved);

const formatTime = (iso: string | null): string => {
    if (!iso) return '';
    try { return new Date(iso).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }); }
    catch { return ''; }
};

let pollTimer: number | null = null;
onMounted(() => {
    void load();
    // Light background poll so collaborators see new notes within ~30s.
    pollTimer = window.setInterval(() => { void load(); }, 30_000);
});
onBeforeUnmount(() => {
    if (pollTimer !== null) window.clearInterval(pollTimer);
});

watch(() => props.defaultAuthor, (v) => {
    if (v && !authorName.value) authorName.value = v;
});
</script>

<template>
    <div class="fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col border-l border-gray-800 bg-gray-950 text-white shadow-2xl">
        <header class="flex-none border-b border-gray-800 px-5 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="rounded-full bg-yellow-900/40 px-2 py-0.5 text-xs uppercase tracking-widest text-yellow-300">Notes</span>
                <span class="text-xs text-gray-500">{{ visibleNotes().length }} {{ showResolved ? 'total' : 'open' }}</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <label class="flex items-center gap-1 text-gray-500 select-none cursor-pointer">
                    <input v-model="showResolved" type="checkbox" class="h-3 w-3 accent-primary-500" />
                    show resolved
                </label>
                <button class="rounded-lg px-2 py-1 text-gray-400 hover:bg-gray-800 hover:text-white transition-colors" @click="emit('close')">
                    ✕
                </button>
            </div>
        </header>

        <!-- Composer -->
        <div class="flex-none border-b border-gray-800 px-5 py-3 space-y-2 bg-gray-900/40">
            <input v-model="authorName" type="text" placeholder="Your name"
                   class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-1.5 text-xs text-gray-300 focus:border-primary-500 focus:outline-none" />
            <textarea v-model="bodyText" rows="3"
                      placeholder="Leave a note for your collaborators…"
                      class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-200 focus:border-primary-500 focus:outline-none resize-none"></textarea>
            <div class="flex items-center justify-between text-xs">
                <label v-if="contextEventId" class="flex items-center gap-1.5 text-gray-500 select-none cursor-pointer">
                    <input v-model="pinToEvent" type="checkbox" class="h-3 w-3 accent-primary-500" />
                    Pin to event #{{ contextEventId }}
                </label>
                <span v-else class="text-gray-700 italic">Chapter-level note</span>
                <button class="rounded-lg bg-primary-600 hover:bg-primary-500 disabled:opacity-40 px-3 py-1 text-xs font-medium text-white transition-colors"
                        :disabled="composing || !authorName.trim() || !bodyText.trim()"
                        @click="submit">
                    {{ composing ? 'Posting…' : 'Post note' }}
                </button>
            </div>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
            <div v-if="loading && notes.length === 0" class="text-center text-xs text-gray-600 py-10">
                Loading notes…
            </div>
            <div v-else-if="visibleNotes().length === 0" class="text-center text-xs text-gray-600 py-10">
                No notes yet. Be the first to leave one.
            </div>
            <div v-for="n in visibleNotes()" :key="n.id"
                 class="rounded-xl border border-gray-800 bg-gray-900/40 px-4 py-3 space-y-1.5"
                 :class="n.is_resolved ? 'opacity-60' : ''">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-200">{{ n.author_name }}</span>
                        <span v-if="n.event_id" class="rounded bg-gray-800 px-1.5 py-0.5 text-[10px] text-gray-400">
                            event #{{ n.event_id }}
                        </span>
                    </div>
                    <span class="text-[10px] text-gray-600">{{ formatTime(n.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-300 leading-relaxed whitespace-pre-wrap">{{ n.body }}</p>
                <div class="flex items-center justify-end gap-2 text-[10px]">
                    <button class="rounded px-1.5 py-0.5 text-gray-500 hover:text-emerald-400 transition-colors"
                            @click="toggleResolved(n)">
                        {{ n.is_resolved ? '↺ Reopen' : '✓ Resolve' }}
                    </button>
                    <button class="rounded px-1.5 py-0.5 text-gray-600 hover:text-red-400 transition-colors"
                            @click="destroy(n)">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
