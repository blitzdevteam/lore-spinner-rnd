import { ref } from 'vue';

/**
 * Singleton state that lets other parts of the app (e.g. GameplayLayout)
 * signal to FeedbackWidget that the bottom position should be lifted so the
 * button doesn't overlap a bottom sheet.
 */
const audioSheetOpen = ref(false);

export function useFeedbackWidget() {
    return { audioSheetOpen };
}
