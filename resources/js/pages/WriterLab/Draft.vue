<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface SourceEvent {
    id: number;
    position: number;
    title: string;
    content: string;
    objectives: string | null;
    attributes: string[] | null;
}

interface SplitPart {
    title: string;
    content: string;
    objectives: string;
    attributes: string[];
    beat_type: string | null;
    requires_choice: boolean;
}

interface Draft {
    id: number;
    type: string;
    status: string;
    rewritten_content: string | null;
    derived_objectives: string | null;
    derived_attributes: string[] | null;
    beat_type: string | null;
    requires_choice: boolean;
    canonical_anchors: string[] | null;
    split_parts: SplitPart[] | null;
    event_order: { event_id: number; new_position: number }[] | null;
    adaptation_patch: Record<string, any> | null;
    session_number: number | null;
}

interface Story { id: number; title: string; slug: string }
interface Chapter { id: number; position: number; title: string }

const props = defineProps<{
    story: Story;
    chapter: Chapter;
    draft: Draft;
    sourceEvents: SourceEvent[];
}>();

// ── Edit form ──────────────────────────────────────────────────────────────
const editForm = useForm({
    rewritten_content: props.draft.rewritten_content ?? '',
    split_parts: props.draft.split_parts ?? [],
    requires_choice: props.draft.requires_choice,
    beat_type: props.draft.beat_type ?? '',
    derived_objectives: props.draft.derived_objectives ?? '',
    derived_attributes: (props.draft.derived_attributes ?? []) as string[],
    adaptation_patch: props.draft.adaptation_patch ?? null,
});

const attributesText = computed<string>({
    get: () => Array.isArray(editForm.derived_attributes)
        ? editForm.derived_attributes.join('\n')
        : '',
    set: (v: string) => {
        editForm.derived_attributes = v
            .split('\n')
            .map(line => line.trim())
            .filter(line => line.length > 0);
    },
});

const BEAT_TYPES = ['setup', 'escalation', 'breath', 'twist', 'resolution'];

const saveEdit = () => {
    editForm.patch(
        `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${props.draft.id}`
    );
};

// ── Approve ────────────────────────────────────────────────────────────────
const approving = ref(false);
const approve = () => {
    approving.value = true;
    router.post(
        `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${props.draft.id}/approve`,
        {},
        { onFinish: () => { approving.value = false; } }
    );
};

// ── Activate ───────────────────────────────────────────────────────────────
const activating = ref(false);
const activate = () => {
    if (!confirm('Activate this draft? It will rewrite the live events table.')) return;
    activating.value = true;
    router.post(
        `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${props.draft.id}/activate`,
        {},
        { onFinish: () => { activating.value = false; } }
    );
};

// ── Discard ────────────────────────────────────────────────────────────────
const discarding = ref(false);
const discard = () => {
    if (!confirm('Discard this draft permanently? Activated drafts cannot be discarded — use Versions to roll back.')) return;
    discarding.value = true;
    router.delete(
        `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${props.draft.id}`,
        { onFinish: () => { discarding.value = false; } }
    );
};

// ── Preview ────────────────────────────────────────────────────────────────
const previewing  = ref(false);
const previewHtml = ref<string | null>(null);
const previewChoices = ref<string[]>([]);
const previewError   = ref<string | null>(null);

const runPreview = async () => {
    previewing.value  = true;
    previewHtml.value = null;
    previewError.value = null;

    try {
        const res = await fetch(
            `/writer/writer-lab/${props.story.id}/chapters/${props.chapter.id}/drafts/${props.draft.id}/preview`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': (() => { const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; })(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );
        const raw = await res.text();
        let data: Record<string, unknown> = {};
        try {
            data = raw ? (JSON.parse(raw) as Record<string, unknown>) : {};
        } catch {
            previewError.value = raw.slice(0, 200) || `HTTP ${res.status}`;
            previewing.value = false;
            return;
        }
        if (!res.ok && data.error === undefined) {
            data.error = `Request failed (${res.status})`;
        }
        if (data.error) {
            previewError.value = String(data.error);
        } else {
            previewHtml.value    = String(data.response ?? '');
            previewChoices.value = (data.choices as string[]) ?? [];
        }
    } catch {
        previewError.value = 'Request failed.';
    } finally {
        previewing.value = false;
    }
};

// ── UI helpers ─────────────────────────────────────────────────────────────
const canApprove   = computed(() => ['draft', 'ai_written'].includes(props.draft.status));
const canActivate  = computed(() => props.draft.status === 'writer_approved');
const canPreview   = computed(() => ['ai_written', 'draft', 'writer_approved'].includes(props.draft.status));

const statusColor = (status: string): string => {
    const map: Record<string, string> = {
        draft: 'bg-gray-700 text-gray-300',
        ai_written: 'bg-blue-900 text-blue-300',
        writer_approved: 'bg-yellow-900 text-yellow-300',
        activated: 'bg-emerald-900 text-emerald-300',
    };
    return map[status] ?? 'bg-gray-700 text-gray-300';
};

const isActivated = computed(() => props.draft.status === 'activated');

// Split part editing
const updateSplitPart = (index: number, field: keyof SplitPart, value: string | boolean) => {
    const parts = [...(editForm.split_parts as SplitPart[])];
    parts[index] = { ...parts[index], [field]: value };
    editForm.split_parts = parts;
};
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white">
        <!-- Header -->
        <header class="border-b border-gray-800 px-8 py-4">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <div class="flex items-center gap-3 text-sm">
                    <Link
                        :href="`/writer/writer-lab/${story.id}/chapters/${chapter.id}`"
                        class="text-gray-400 hover:text-white transition-colors"
                    >
                        ← {{ chapter.title }}
                    </Link>
                    <span class="text-gray-700">/</span>
                    <span class="font-medium">Draft #{{ draft.id }}</span>
                    <span :class="['rounded px-2 py-0.5 text-xs', statusColor(draft.status)]">
                        {{ draft.status.replace(/_/g, ' ') }}
                    </span>
                    <span class="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-400">
                        {{ draft.type }}
                    </span>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center gap-2">
                    <button
                        v-if="canPreview && draft.type !== 'reorder'"
                        class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-all disabled:opacity-40"
                        :disabled="previewing"
                        @click="runPreview"
                    >
                        <span v-if="previewing">Generating…</span>
                        <span v-else>▶ Playground</span>
                    </button>
                    <button
                        v-if="!isActivated"
                        class="rounded-lg bg-gray-700 px-4 py-1.5 text-sm text-gray-200 hover:bg-gray-600 transition-all disabled:opacity-40"
                        :disabled="editForm.processing"
                        @click="saveEdit"
                    >
                        Save
                    </button>
                    <button
                        v-if="canApprove"
                        class="rounded-lg bg-yellow-700 px-4 py-1.5 text-sm font-medium text-yellow-100 hover:bg-yellow-600 transition-all disabled:opacity-40"
                        :disabled="approving"
                        @click="approve"
                    >
                        <span v-if="approving">Approving…</span>
                        <span v-else>Approve</span>
                    </button>
                    <button
                        v-if="canActivate"
                        class="rounded-lg bg-emerald-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-600 transition-all disabled:opacity-40"
                        :disabled="activating"
                        @click="activate"
                    >
                        <span v-if="activating">Activating…</span>
                        <span v-else>Activate</span>
                    </button>
                    <button
                        v-if="!isActivated"
                        class="rounded-lg bg-gray-900 border border-gray-700 px-3 py-1.5 text-sm text-gray-400 hover:text-red-400 hover:border-red-700 transition-all disabled:opacity-40"
                        :disabled="discarding"
                        title="Permanently delete this draft"
                        @click="discard"
                    >
                        <span v-if="discarding">Discarding…</span>
                        <span v-else>Discard</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-8 py-8">
            <div class="grid grid-cols-2 gap-8">

                <!-- LEFT: Source events -->
                <div>
                    <h2 class="mb-4 text-xs uppercase tracking-widest text-gray-500">
                        Source Events ({{ sourceEvents.length }})
                    </h2>
                    <div class="space-y-4">
                        <div
                            v-for="event in sourceEvents"
                            :key="event.id"
                            class="rounded-xl border border-gray-800 bg-gray-900 p-4"
                        >
                            <div class="mb-2 flex items-center gap-2">
                                <span class="text-xs font-mono text-gray-600">{{ event.position }}</span>
                                <span class="text-sm font-medium">{{ event.title }}</span>
                            </div>
                            <p class="text-sm leading-relaxed text-gray-400">{{ event.content }}</p>
                            <p v-if="event.objectives" class="mt-2 text-xs text-gray-600">
                                <span class="text-gray-700">Objectives: </span>{{ event.objectives }}
                            </p>
                        </div>
                    </div>

                    <!-- Reorder preview -->
                    <template v-if="draft.type === 'reorder' && draft.event_order">
                        <h3 class="mb-3 mt-6 text-xs uppercase tracking-widest text-gray-500">New Order</h3>
                        <div class="space-y-2">
                            <div
                                v-for="(item, i) in draft.event_order"
                                :key="item.event_id"
                                class="flex items-center gap-3 rounded-lg bg-gray-900 px-4 py-2 text-sm"
                            >
                                <span class="text-gray-600 font-mono text-xs w-4">{{ i + 1 }}</span>
                                <span class="text-gray-300">Event #{{ item.event_id }} → pos {{ item.new_position }}</span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- RIGHT: Draft editor -->
                <div>
                    <!-- Combine / Edit draft -->
                    <template v-if="draft.type === 'combine' || draft.type === 'edit'">
                        <div class="mb-4">
                            <h2 class="mb-3 text-xs uppercase tracking-widest text-gray-500">Rewritten Content</h2>
                            <textarea
                                v-model="editForm.rewritten_content"
                                :disabled="isActivated"
                                rows="12"
                                class="w-full rounded-xl border border-gray-700 bg-gray-900 px-4 py-3 text-sm text-gray-200 leading-relaxed focus:border-primary-500 focus:outline-none resize-none disabled:opacity-60"
                            ></textarea>
                        </div>

                        <!-- Derived objectives (the same field the runtime narrator reads) -->
                        <div class="mb-4">
                            <h3 class="mb-2 text-xs uppercase tracking-widest text-gray-500">
                                Objectives
                                <span class="ml-1 text-gray-700 normal-case tracking-normal">— observable state change at the end of this event</span>
                            </h3>
                            <textarea
                                v-model="editForm.derived_objectives"
                                :disabled="isActivated"
                                rows="3"
                                placeholder="Subject + observable state change. E.g. &quot;Alice followed the White Rabbit through the hall and discovered a small door hidden behind a curtain.&quot;"
                                class="w-full rounded-xl border border-gray-700 bg-gray-900 px-4 py-3 text-sm text-gray-200 leading-relaxed focus:border-primary-500 focus:outline-none resize-none disabled:opacity-60"
                            ></textarea>
                        </div>

                        <!-- Derived attributes (6-category facts, one line per category) -->
                        <div class="mb-4">
                            <h3 class="mb-2 text-xs uppercase tracking-widest text-gray-500">
                                Attributes
                                <span class="ml-1 text-gray-700 normal-case tracking-normal">— one category per line, pipe-separate facts within a category</span>
                            </h3>
                            <textarea
                                v-model="attributesText"
                                :disabled="isActivated"
                                rows="6"
                                placeholder="Location: hall of doors&#10;Characters physically present: Alice | White Rabbit&#10;Objects: small door hidden behind curtain | golden key on glass table"
                                class="w-full rounded-xl border border-gray-700 bg-gray-900 px-4 py-3 text-sm text-gray-200 leading-relaxed font-mono focus:border-primary-500 focus:outline-none resize-none disabled:opacity-60"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="mb-1 block text-xs text-gray-500">Beat Type</label>
                                <select
                                    v-model="editForm.beat_type"
                                    :disabled="isActivated"
                                    class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-300 focus:border-primary-500 focus:outline-none disabled:opacity-60"
                                >
                                    <option value="">— (not classified) —</option>
                                    <option v-for="b in BEAT_TYPES" :key="b" :value="b">{{ b }}</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2 mt-5">
                                <input
                                    id="requires_choice"
                                    v-model="editForm.requires_choice"
                                    :disabled="isActivated"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-700 bg-gray-900 accent-primary-500"
                                />
                                <label for="requires_choice" class="text-sm text-gray-400">Requires player choice</label>
                            </div>
                        </div>

                        <!-- Canonical anchors checklist (read-only audit list) -->
                        <div v-if="draft.canonical_anchors && draft.canonical_anchors.length > 0" class="mb-4 rounded-xl border border-gray-800 bg-gray-900/40 p-4">
                            <h3 class="mb-2 text-xs uppercase tracking-widest text-gray-500">
                                Canonical Anchors
                                <span class="ml-1 text-gray-700 normal-case tracking-normal">— must each survive in the rewrite above</span>
                            </h3>
                            <ul class="space-y-1">
                                <li
                                    v-for="anchor in draft.canonical_anchors"
                                    :key="anchor"
                                    class="flex items-start gap-2 text-xs text-gray-400"
                                >
                                    <span class="mt-0.5 text-gray-600">·</span>
                                    {{ anchor }}
                                </li>
                            </ul>
                        </div>
                    </template>

                    <!-- Split draft -->
                    <template v-else-if="draft.type === 'split'">
                        <h2 class="mb-4 text-xs uppercase tracking-widest text-gray-500">Split Parts</h2>
                        <div
                            v-for="(part, i) in (editForm.split_parts as SplitPart[])"
                            :key="i"
                            class="mb-6 rounded-xl border border-gray-700 bg-gray-900 p-4"
                        >
                            <div class="mb-2 flex items-center gap-2">
                                <span class="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-400">Part {{ i + 1 }}</span>
                                <input
                                    :value="part.title"
                                    :disabled="isActivated"
                                    type="text"
                                    class="flex-1 rounded border border-transparent bg-transparent px-2 py-1 text-sm text-gray-300 focus:border-gray-700 focus:outline-none"
                                    @input="updateSplitPart(i, 'title', ($event.target as HTMLInputElement).value)"
                                />
                            </div>
                            <textarea
                                :value="part.content"
                                :disabled="isActivated"
                                :placeholder="i === 0 ? 'First part content…' : 'Second part content (write the continuation here)…'"
                                rows="8"
                                class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-gray-300 leading-relaxed focus:border-primary-500 focus:outline-none resize-none disabled:opacity-60"
                                @input="updateSplitPart(i, 'content', ($event.target as HTMLTextAreaElement).value)"
                            ></textarea>
                            <div class="mt-2 flex items-center gap-3">
                                <input
                                    :id="`requires_choice_${i}`"
                                    :checked="part.requires_choice"
                                    :disabled="isActivated"
                                    type="checkbox"
                                    class="h-4 w-4 rounded accent-primary-500"
                                    @change="updateSplitPart(i, 'requires_choice', ($event.target as HTMLInputElement).checked)"
                                />
                                <label :for="`requires_choice_${i}`" class="text-xs text-gray-500">
                                    Requires player choice
                                </label>
                            </div>
                        </div>
                    </template>

                    <!-- Reorder: no editor needed -->
                    <template v-else-if="draft.type === 'reorder'">
                        <div class="rounded-xl border border-gray-800 bg-gray-900 p-6 text-center text-sm text-gray-500">
                            Reorder draft — no content to edit.
                            <br>
                            <span v-if="canActivate" class="text-yellow-400 mt-1 inline-block">
                                Ready to activate — this will rewrite live event positions.
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Preview panel -->
            <div v-if="previewHtml || previewError" class="mt-10 rounded-2xl border border-gray-700 bg-gray-900 p-6">
                <h2 class="mb-4 text-xs uppercase tracking-widest text-gray-500">Narrator Preview</h2>
                <div v-if="previewError" class="text-sm text-red-400">{{ previewError }}</div>
                <template v-else>
                    <div class="prose prose-invert prose-sm max-w-none" v-html="previewHtml"></div>
                    <div v-if="previewChoices.length > 0" class="mt-4 grid grid-cols-3 gap-3">
                        <div
                            v-for="(choice, i) in previewChoices"
                            :key="i"
                            class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-300"
                        >
                            {{ i + 1 }}. {{ choice }}
                        </div>
                    </div>
                </template>
            </div>
        </main>
    </div>
</template>

<style scoped></style>
