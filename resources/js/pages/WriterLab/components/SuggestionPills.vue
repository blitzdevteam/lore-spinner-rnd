<script setup lang="ts">
defineProps<{ state: 'clean' | 'pending' | 'accepted'; label?: string }>();
defineEmits<{ (e: 'accept'): void; (e: 'undo'): void }>();
</script>

<template>
    <span v-if="state === 'pending'" class="inline-flex items-center gap-1.5">
        <span class="rounded-full bg-sky-900/50 border border-sky-700/40 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-sky-300">
            AI{{ label ? ' · ' + label : '' }}
        </span>
        <button class="rounded-full bg-emerald-700/60 hover:bg-emerald-600 px-2 py-0.5 text-[10px] text-emerald-50 transition-colors"
                title="Keep this AI suggestion" @click="$emit('accept')">
            ✓ Accept
        </button>
        <button class="rounded-full bg-gray-800 hover:bg-gray-700 px-2 py-0.5 text-[10px] text-gray-300 transition-colors"
                title="Restore your previous value" @click="$emit('undo')">
            ↶ Undo
        </button>
    </span>
    <span v-else-if="state === 'accepted'"
          class="inline-flex items-center gap-1 rounded-full bg-emerald-900/40 border border-emerald-700/40 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-emerald-300">
        ✓ Accepted{{ label ? ' · ' + label : '' }}
    </span>
</template>
