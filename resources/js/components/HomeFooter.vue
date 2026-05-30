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
</script>

<template>
    <footer class="bg-[#0f0f0f]">
        <div class="container">
            <div class="container-content flex w-full flex-col gap-12 py-16 md:py-20">
                <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between lg:gap-12">
                    <!-- Brand -->
                    <div class="max-w-[min(100%,420px)] shrink-0">
                        <img
                            :src="mainLogo"
                            alt="LoreSpinner"
                            class="h-[88px] w-auto max-w-full object-contain object-left md:h-[112px] lg:h-[128px]"
                            width="512"
                            height="144"
                        />
                        <p class="mt-5 text-[13px] font-light leading-[1.35] tracking-[-0.52px] text-white md:mt-6">
                            For dreamers, creators, and wanderers ; Lorespinner brings your stories to life.
                        </p>
                    </div>

                    <!-- Link columns -->
                    <div class="flex flex-wrap gap-x-6 gap-y-8 lg:flex-nowrap lg:justify-end">
                        <div v-for="col in columns" :key="col.title" class="flex w-[110px] flex-col gap-[17px]">
                            <p class="text-[16px] font-semibold tracking-[-0.64px] text-white">
                                {{ col.title }}
                            </p>
                            <ul class="flex flex-col gap-[10px]">
                                <li v-for="link in col.links" :key="link.label">
                                    <a
                                        v-if="isNativeAnchor(link.href)"
                                        :href="link.href"
                                        class="block text-[12px] font-light tracking-[-0.48px] transition-colors hover:text-primary"
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
                                        class="block text-[12px] font-light tracking-[-0.48px] transition-colors hover:text-primary"
                                        :class="link.accent ? 'text-primary' : 'text-white'"
                                    >
                                        {{ link.label }}
                                    </Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Get in touch -->
                        <div class="flex w-[160px] flex-col gap-[17px]">
                            <p class="text-[16px] font-semibold tracking-[-0.64px] text-white">Get in touch</p>
                            <a
                                :href="`mailto:${contactEmail}`"
                                class="text-[12px] font-light tracking-[-0.48px] text-white transition-colors hover:text-primary"
                            >
                                {{ contactEmail }}
                            </a>
                        </div>
                    </div>
                </div>

                <p class="text-center text-[14px] font-normal tracking-[-0.56px] text-primary">
                    © Copyright 2026. Lorespinner Inc. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</template>
