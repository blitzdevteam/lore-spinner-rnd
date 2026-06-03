<template>
    <div class="aurora-notes flex flex-col gap-4 pb-8 text-sm leading-relaxed text-gray-400">
        <p class="text-gray-200">
            Welcome — this page is a playground for the gameplay background. Take your time, try odd combinations, and trust your eye.
        </p>

        <section class="aurora-notes__block">
            <h4 class="aurora-notes__heading">How to explore</h4>
            <ol class="aurora-notes__list">
                <li>Open <strong class="text-gray-300">Settings</strong> and go to the <strong class="text-gray-300">Aurora</strong> tab.</li>
                <li>Pick a story with the chips at the bottom, or turn on <strong class="text-gray-300">Custom story colours</strong> and paint your own palette.</li>
                <li>Move the sliders — numbers update live next to each one. The background responds immediately.</li>
                <li>Switch stories to see how the same settings feel on crimson, teal, violet, earthy tones, and the rest.</li>
            </ol>
        </section>

        <section class="aurora-notes__block">
            <h4 class="aurora-notes__heading">When something feels right</h4>
            <p>
                Tap the <strong class="text-gray-300">copy</strong> icon at the top of the Aurora tab. That copies a JSON snapshot of everything you tuned — colours, mixes, intensity, timing.
            </p>
            <p>
                Send that JSON to <strong class="text-primary">Daniel</strong> by email or Telegram. A short note about what you were going for (moody, brighter, more contrast, etc.) is always welcome.
            </p>
        </section>

        <section class="aurora-notes__block">
            <h4 class="aurora-notes__heading">Tips</h4>
            <ul class="aurora-notes__list aurora-notes__list--bullets">
                <li>There is no wrong answer here — playful experiments are the point.</li>
                <li>If you lose your way, use <strong class="text-gray-300">Reset</strong> or pick another story chip to start fresh.</li>
                <li>Custom colours stay off until you enable them; otherwise the story chip drives the palette.</li>
            </ul>
        </section>

        <p class="text-xs text-gray-500">Thank you for lending your taste to this — it genuinely helps.</p>
    </div>
</template>

<style scoped>
.aurora-notes__block {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.aurora-notes__heading {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgba(229, 173, 83, 0.9);
}

.aurora-notes__list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-left: 1.125rem;
    font-size: 0.8125rem;
}

.aurora-notes__list--bullets {
    list-style: disc;
}
</style>
