<script setup lang="ts">
import mainLogo from '@/assets/logo/main-logo.png';
import { index } from '@/wayfinder/routes';
import { index as storiesIndex } from '@/wayfinder/routes/stories';
import { index as dashboardIndex } from '@/wayfinder/routes/user/dashboard';
import { Link } from '@inertiajs/vue3';

interface FooterLink {
    label: string;
    href: string;
    accent?: boolean;
}

interface FooterColumn {
    title: string;
    links: FooterLink[];
}

const community: FooterColumn = {
    title: 'Community',
    links: [
        { label: 'Discord', href: 'https://discord.com' },
        { label: 'Wattpad', href: 'https://www.wattpad.com' },
        { label: 'Reddit', href: 'https://www.reddit.com' },
    ],
};

const navigate: FooterColumn = {
    title: 'Navigate',
    links: [
        { label: 'Home', href: index().url, accent: true },
        { label: 'Library', href: storiesIndex().url },
        { label: 'Featured Worlds', href: storiesIndex().url },
        { label: 'Profile', href: dashboardIndex().url },
    ],
};

const moods: FooterColumn = {
    title: 'Moods',
    links: [
        { label: 'Heartfelt', href: index().url },
        { label: 'Adventurous', href: index().url },
        { label: 'Mysterious', href: index().url },
        { label: 'Epic', href: index().url },
        { label: 'Whimsical', href: index().url },
    ],
};

const support: FooterColumn = {
    title: 'Support',
    links: [
        { label: 'Help Center', href: '#' },
        { label: 'Terms of Service', href: '#' },
        { label: 'Privacy Policy', href: '#' },
    ],
};

const columns: FooterColumn[] = [community, navigate, moods, support];

const contactEmail = 'Contact@lorespinner.com';

function isNativeAnchor(href: string): boolean {
    return (
        href.startsWith('http://') ||
        href.startsWith('https://') ||
        href.startsWith('mailto:') ||
        href === '#'
    );
}

const linkRowClass =
    'flex flex-col gap-[10px] font-light text-[12px] leading-[15px] tracking-[-0.48px] text-white';
const headingClass =
    'text-[16px] font-semibold leading-normal tracking-[-0.64px] text-white';
</script>

<template>
    <footer class="relative bg-[#0f0f0f] text-white">
        <div class="container">
            <div
                class="container-content pt-[56px] pb-[64px] sm:pt-[64px] sm:pb-[72px] lg:pt-[74px] lg:pb-[93px]"
            >
                <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-6">
                    <div class="w-full max-w-[284px] shrink-0">
                        <img
                            :src="mainLogo"
                            alt="LoreSpinner"
                            class="block h-[44px] w-auto max-w-[min(100%,229.5px)] object-contain object-left md:h-12 lg:h-[52.5px] lg:max-w-[229.5px]"
                            width="512"
                            height="144"
                            decoding="async"
                        />
                        <p
                            class="mt-2.5 text-[13px] font-light leading-[100%] tracking-[-0.52px] text-white lg:mt-[11px]"
                        >
                            For dreamers, creators, and wanderers ; Lorespinner brings your stories to life.
                        </p>
                    </div>

                    <nav
                        aria-label="Footer"
                        class="flex flex-1 flex-wrap gap-x-6 gap-y-10 md:gap-y-8 lg:min-w-0 lg:flex-nowrap lg:justify-start lg:gap-x-6 lg:gap-y-0"
                    >
                        <div
                            v-for="col in columns"
                            :key="col.title"
                            class="flex w-[calc(50%-12px)] flex-col gap-[17px] min-[480px]:w-[110px] min-[480px]:shrink-0 min-[480px]:grow-0 lg:w-[110px]"
                        >
                            <p :class="headingClass">
                                {{ col.title }}
                            </p>
                            <ul :class="linkRowClass">
                                <li v-for="link in col.links" :key="link.label">
                                    <a
                                        v-if="isNativeAnchor(link.href)"
                                        :href="link.href"
                                        class="block transition-colors hover:text-primary"
                                        :class="link.accent ? 'text-primary' : 'text-white'"
                                        :rel="
                                            link.href.startsWith('http') ? 'noopener noreferrer' : undefined
                                        "
                                        :target="link.href.startsWith('http') ? '_blank' : undefined"
                                    >
                                        {{ link.label }}
                                    </a>
                                    <Link
                                        v-else
                                        :href="link.href"
                                        class="block transition-colors hover:text-primary"
                                        :class="link.accent ? 'text-primary' : 'text-white'"
                                    >
                                        {{ link.label }}
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <div
                            class="flex w-full flex-col gap-[17px] min-[480px]:w-[160px] min-[480px]:shrink-0 min-[480px]:grow-0 lg:w-[160px]"
                        >
                            <p :class="headingClass">Get in touch</p>
                            <a
                                :href="`mailto:${contactEmail}`"
                                class="text-[12px] font-light leading-[15px] tracking-[-0.48px] text-white transition-colors hover:text-primary"
                            >
                                {{ contactEmail }}
                            </a>
                        </div>
                    </nav>
                </div>

                <p
                    class="mt-16 text-center text-[14px] font-normal leading-normal tracking-[-0.56px] text-primary sm:mt-20 lg:mt-[128px]"
                >
                    © Copyright 2026. Lorespinner Inc. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</template>
