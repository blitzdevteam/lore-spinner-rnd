<template>
    <div class="aurora-notes flex flex-col gap-5 pb-8 text-base leading-relaxed text-gray-300">
        <p class="text-lg font-medium text-gray-50">
            Welcome — this page is a playground for the gameplay background. Take your time, try odd combinations, and trust your eye.
        </p>

        <section class="aurora-notes__block">
            <h4 class="aurora-notes__heading">How to explore</h4>
            <ol class="aurora-notes__list">
                <li>Open <strong class="text-amber-200">Settings</strong> and go to the <strong class="text-amber-200">Aurora</strong> tab.</li>
                <li>Pick a story with the chips at the bottom, or turn on <strong class="text-amber-200">Custom story colours</strong> and paint your own palette.</li>
                <li>Move the sliders — numbers update live next to each one. The background responds immediately.</li>
                <li>Switch stories to see how the same settings feel on crimson, teal, violet, earthy tones, and the rest.</li>
            </ol>
        </section>

        <section class="aurora-notes__block">
            <h4 class="aurora-notes__heading">When something feels right</h4>
            <p>
                Scroll to <strong class="text-amber-200">Share with Daniel</strong> at the bottom of the Aurora tab. Use <strong class="text-amber-200">Copy</strong> on the JSON block (or the big Copy JSON button), then paste in Telegram.
            </p>
            <p>
                Or tap <strong class="text-amber-200">Email Daniel</strong> — your mail app opens with the JSON already in the message. A short note about the mood you wanted is always welcome.
            </p>
        </section>

        <section class="aurora-notes__block">
            <h4 class="aurora-notes__heading">Tips</h4>
            <ul class="aurora-notes__list aurora-notes__list--bullets">
                <li>There is no wrong answer here — playful experiments are the point.</li>
                <li>If you lose your way, use <strong class="text-amber-200">Reset</strong> or pick another story chip to start fresh.</li>
                <li>Custom colours stay off until you enable them; otherwise the story chip drives the palette.</li>
            </ul>
        </section>

        <p class="text-sm font-medium text-amber-200/90">Thank you for lending your taste to this — it genuinely helps.</p>
    </div>
</template>

<style scoped>
.aurora-notes__block {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.aurora-notes__heading {
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #e5ad53;
}

.aurora-notes__list {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    padding-left: 1.25rem;
    font-size: 0.9375rem;
    color: rgb(209, 213, 219);
}

.aurora-notes__list--bullets {
    list-style: disc;
}
</style>
