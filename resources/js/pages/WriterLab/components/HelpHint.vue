<script setup lang="ts">
/**
 * Small "i" hover badge that surfaces a Writer-Lab field definition.
 *
 * Usage:  <HelpHint term="event_objectives" />
 *
 * The badge is intentionally subtle — gray-on-gray dot, only the cursor cue
 * (help) hints at the affordance. On hover (or keyboard focus), a positioned
 * popover appears with the title + body from writerLabHints.ts.
 */
import { computed, ref } from 'vue';
import { getHint } from '../writerLabHints';

const props = defineProps<{ term: string; size?: 'sm' | 'xs' }>();

const hint = computed(() => getHint(props.term));
const open = ref(false);

const close = () => { open.value = false; };
</script>

<template>
    <span v-if="hint" class="relative inline-flex items-center align-middle">
        <button type="button"
                class="inline-flex items-center justify-center rounded-full border border-gray-700 bg-gray-900 text-gray-500 hover:border-gray-500 hover:text-gray-200 transition-colors leading-none"
                :class="size === 'xs' ? 'h-3.5 w-3.5 text-[9px]' : 'h-4 w-4 text-[10px]'"
                :aria-label="`Help: ${hint.title}`"
                @mouseenter="open = true" @mouseleave="close"
                @focus="open = true" @blur="close"
                @click.prevent="open = !open">
            i
        </button>
        <transition name="fade">
            <span v-if="open"
                  class="absolute left-1/2 z-40 mt-1 w-64 -translate-x-1/2 top-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-left text-xs text-gray-200 shadow-xl pointer-events-none">
                <span class="block mb-0.5 font-medium text-gray-100">{{ hint.title }}</span>
                <span class="block text-gray-400 leading-relaxed">{{ hint.body }}</span>
            </span>
        </transition>
    </span>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 80ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
