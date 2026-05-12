/**
 * Writer Lab client-side trace.
 *
 * Silent by default. Turn on in DevTools with:
 *   localStorage.setItem('wl_debug', '1')
 *
 * Then every lab UI event prints to console with a [wl] prefix so a writer's
 * bug report can be reconstructed without server access.
 */

const KEY = 'wl_debug';

const debugOn = (): boolean => {
    if (typeof window === 'undefined') return false;
    try {
        return window.localStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
};

export const wlTrace = (event: string, payload: Record<string, unknown> = {}): void => {
    if (!debugOn()) return;
    // eslint-disable-next-line no-console
    console.debug('[wl]', event, payload);
};

export const wlError = (event: string, payload: Record<string, unknown> = {}): void => {
    // Errors always surface
    // eslint-disable-next-line no-console
    console.error('[wl]', event, payload);
};
