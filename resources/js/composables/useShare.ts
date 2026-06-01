export type SharePayload = {
    title: string;
    text?: string;
    url: string;
};

export type ShareResult = 'shared' | 'copied' | 'cancelled' | 'failed';

function canUseNativeShare(data: SharePayload): boolean {
    if (typeof navigator === 'undefined' || typeof navigator.share !== 'function') {
        return false;
    }
    if (typeof navigator.canShare === 'function') {
        try {
            return navigator.canShare(data);
        } catch {
            return true;
        }
    }
    return true;
}

async function copyToClipboard(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        // fall through to legacy copy
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(textarea);
    }
}

export function useShare() {
    async function share(payload: SharePayload): Promise<ShareResult> {
        const data: SharePayload = {
            title: payload.title,
            text: payload.text?.trim() || payload.title,
            url: payload.url,
        };

        if (canUseNativeShare(data)) {
            try {
                await navigator.share(data);
                return 'shared';
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return 'cancelled';
                }
            }
        }

        const copied = await copyToClipboard(data.url);
        return copied ? 'copied' : 'failed';
    }

    return { share };
}
