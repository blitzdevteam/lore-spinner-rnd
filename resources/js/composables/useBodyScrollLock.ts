let lockCount = 0;
let savedScrollY = 0;

function applyLock() {
    savedScrollY = window.scrollY;
    document.body.style.position = 'fixed';
    document.body.style.top = `-${savedScrollY}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    document.body.style.overflow = 'hidden';
}

function releaseLock() {
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    document.body.style.overflow = '';
    window.scrollTo(0, savedScrollY);
}

export function lockBodyScroll() {
    if (typeof document === 'undefined') return;
    lockCount++;
    if (lockCount === 1) {
        applyLock();
    }
}

export function unlockBodyScroll() {
    if (typeof document === 'undefined') return;
    lockCount = Math.max(0, lockCount - 1);
    if (lockCount === 0) {
        releaseLock();
    }
}

export function resetBodyScrollLock() {
    if (typeof document === 'undefined') return;
    lockCount = 0;
    releaseLock();
}
