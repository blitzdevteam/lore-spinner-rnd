<script setup lang="ts">
import heartImg from '@/assets/mood/Heart.svg';
import mountainsImg from '@/assets/mood/Mountains.svg';
import eyeImg from '@/assets/mood/Eye.svg';
import swordImg from '@/assets/mood/Sword.svg';
import spiralImg from '@/assets/mood/Spiral.svg';
</script>

<template>
    <section class="home-section-y">
        <div class="container">
            <div class="container-content home-section-gap">
                <div class="not-italic">
                    <h2 class="flex h-10 items-center text-[26px] font-bold uppercase leading-[33px] text-white">Explore by mood</h2>
                    <p class="text-base font-normal leading-[26px] text-[#b6b6b6]">
                        Choose the feeling you want to step into.
                    </p>
                </div>

                <!-- Figma 5412:908: flex row, gap exactly 10px (not stretched 1fr columns) -->
                <div
                    class="mood-scroll flex w-full max-w-full flex-nowrap items-center gap-[10px] overflow-x-auto pb-1 md:w-max md:overflow-visible"
                >
                    <!-- Heartfelt -->
                    <div class="relative h-[148px] w-[195px] shrink-0 overflow-hidden rounded-lg shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]">
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_125.7px_0px_#792020,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(121,13,13,0.16)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[22px] size-8">
                                <img :src="heartImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute left-[65.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#c93434]"
                        >
                            Heartfelt
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </div>

                    <!-- Adventurous -->
                    <div class="relative h-[148px] w-[195px] shrink-0 overflow-hidden rounded-lg shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]">
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="absolute left-[60px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_111.9px_0px_#7c5e0b,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(247,179,8,0.07)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[22px] size-8">
                                <img
                                    :src="mountainsImg"
                                    alt=""
                                    width="32"
                                    height="32"
                                    class="block size-full max-w-none"
                                />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute left-[51.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#ecc863]"
                        >
                            Adventurous
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </div>

                    <!-- Mysterious -->
                    <div class="relative h-[148px] w-[195px] shrink-0 overflow-hidden rounded-lg shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]">
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <div
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_142.1px_0px_#248077,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(98,232,219,0.09)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[25px] size-8">
                                <img :src="eyeImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <p
                            class="absolute left-[57.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#62e8db]"
                        >
                            Mysterious
                        </p>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </div>

                    <!-- Epic -->
                    <div class="relative h-[148px] w-[195px] shrink-0 overflow-hidden rounded-lg shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]">
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <p
                            class="absolute left-[81.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#58d9a1]"
                        >
                            Epic
                        </p>
                        <div
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_95.8px_0px_#257351,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(88,217,161,0.15)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div
                                class="absolute left-[15px] top-[15px] flex size-[45.255px] items-center justify-center"
                            >
                                <div class="flex-none -rotate-45">
                                    <div class="relative size-8">
                                        <img :src="swordImg" alt="" width="32" height="32" class="block size-full max-w-none" />
                                    </div>
                                </div>
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </div>

                    <!-- Whimsical -->
                    <div class="relative h-[148px] w-[195px] shrink-0 overflow-hidden rounded-lg shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]">
                        <div class="pointer-events-none absolute inset-0 rounded-lg" aria-hidden="true">
                            <div class="absolute inset-0 rounded-lg bg-[rgba(255,255,255,0.1)]" />
                            <div
                                class="absolute inset-0 rounded-lg bg-[rgba(30,30,30,0.1)] backdrop-blur-[3px] mix-blend-plus-lighter"
                            />
                        </div>
                        <p
                            class="absolute left-[60.5px] top-[113.5px] -translate-y-1/2 whitespace-nowrap text-[15px] font-medium capitalize leading-[33px] not-italic text-[#a979c2]"
                        >
                            Whimsical
                        </p>
                        <div
                            class="absolute left-[59.5px] top-[17px] size-[76px] overflow-hidden rounded-[150px] shadow-[0px_0px_117.8px_0px_#7e5296,0px_3px_60px_0px_rgba(0,0,0,0.2)]"
                        >
                            <div class="pointer-events-none absolute inset-0 rounded-[150px]" aria-hidden="true">
                                <div class="absolute inset-0 rounded-[150px] bg-[rgba(255,175,175,0.03)]" />
                                <div
                                    class="absolute inset-0 rounded-[150px] bg-[rgba(169,121,194,0.07)] backdrop-blur-[2.25px] mix-blend-plus-lighter"
                                />
                            </div>
                            <div class="absolute left-[22px] top-[24px] size-8">
                                <img
                                    :src="spiralImg"
                                    alt=""
                                    width="32"
                                    height="32"
                                    class="block size-full max-w-none"
                                />
                            </div>
                            <div
                                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.188px_0.375px_0.375px_0.188px_rgba(255,255,255,0.22),inset_-0.15px_-0.375px_0.113px_0.375px_rgba(255,255,255,0.05)]"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.mood-scroll::-webkit-scrollbar {
    display: none;
}

.mood-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
/* Figma mood tiles: default = calm tile + faint icon ring; hover = border + glow (6320-267) */
.mood-card {
    background-color: #000;
    border: 1px solid transparent;
    box-shadow: none;
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease;
}

.mood-icon-orbit {
    transition: box-shadow 0.3s ease;
}

.mood-icon-orbit-bg {
    transition: opacity 0.3s ease;
}

.mood-card:not(:hover) .mood-icon-orbit-bg {
    opacity: 0.42;
}

.mood-card:hover .mood-icon-orbit-bg {
    opacity: 1;
}

/* Idle: thin accent ring around icon; hover: restore Figma orbit glow */
.mood-card--heartfelt .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(201, 52, 52, 0.38);
}

.mood-card--heartfelt:hover .mood-icon-orbit {
    box-shadow:
        0px 0px 125.7px 0px #792020,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--adventurous .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(236, 200, 99, 0.4);
}

.mood-card--adventurous:hover .mood-icon-orbit {
    box-shadow:
        0px 0px 111.9px 0px #7c5e0b,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--mysterious .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(98, 232, 219, 0.38);
}

.mood-card--mysterious:hover .mood-icon-orbit {
    box-shadow:
        0px 0px 142.1px 0px #248077,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--epic .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(88, 217, 161, 0.4);
}

.mood-card--epic:hover .mood-icon-orbit {
    box-shadow:
        0px 0px 95.8px 0px #257351,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card--whimsical .mood-icon-orbit {
    box-shadow: 0 0 0 1px rgba(169, 121, 194, 0.4);
}

.mood-card--whimsical:hover .mood-icon-orbit {
    box-shadow:
        0px 0px 117.8px 0px #7e5296,
        0px 3px 60px 0px rgba(0, 0, 0, 0.2);
}

.mood-card:hover {
    box-shadow:
        0px 4px 80px 0px rgba(0, 0, 0, 0.2),
        inset 0 0 48px var(--mood-glow-inset),
        0 0 20px var(--mood-glow-outer);
}

.mood-card-glow {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.mood-card:hover .mood-card-glow {
    opacity: 1;
}

.mood-card--heartfelt {
    --mood-glow-inset: rgba(201, 52, 52, 0.14);
    --mood-glow-outer: rgba(201, 52, 52, 0.1);
}

.mood-card--heartfelt:hover {
    border-color: #c93434;
}

.mood-card-glow--heartfelt {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(201, 52, 52, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--adventurous {
    --mood-glow-inset: rgba(236, 200, 99, 0.14);
    --mood-glow-outer: rgba(236, 200, 99, 0.1);
}

.mood-card--adventurous:hover {
    border-color: #ecc863;
}

.mood-card-glow--adventurous {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(236, 200, 99, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--mysterious {
    --mood-glow-inset: rgba(98, 232, 219, 0.14);
    --mood-glow-outer: rgba(98, 232, 219, 0.1);
}

.mood-card--mysterious:hover {
    border-color: #62e8db;
}

.mood-card-glow--mysterious {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(98, 232, 219, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--epic {
    --mood-glow-inset: rgba(88, 217, 161, 0.14);
    --mood-glow-outer: rgba(88, 217, 161, 0.1);
}

.mood-card--epic:hover {
    border-color: #58d9a1;
}

.mood-card-glow--epic {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(88, 217, 161, 0.22) 0%,
        transparent 62%
    );
}

.mood-card--whimsical {
    --mood-glow-inset: rgba(169, 121, 194, 0.14);
    --mood-glow-outer: rgba(169, 121, 194, 0.1);
}

.mood-card--whimsical:hover {
    border-color: #a979c2;
}

.mood-card-glow--whimsical {
    background: radial-gradient(
        ellipse 100% 72% at 50% 30%,
        rgba(169, 121, 194, 0.22) 0%,
        transparent 62%
    );
}
</style>
