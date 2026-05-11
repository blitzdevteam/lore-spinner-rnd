<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { logout } from '@/wayfinder/routes/user/authentication';
import login from '@/wayfinder/routes/user/authentication/login';
import register from '@/wayfinder/routes/user/authentication/register';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, LucideUpload, PenLine, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();

const auth = computed(() => page.props.auth);

const profileDrawerVisibility = ref(false);

// Account dropdown (shown when not logged in)
const accountMenuOpen = ref(false);
const accountMenuRef  = ref<HTMLDivElement | null>(null);

const closeOnOutsideClick = (e: MouseEvent) => {
    if (accountMenuRef.value && !accountMenuRef.value.contains(e.target as Node)) {
        accountMenuOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', closeOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', closeOnOutsideClick));
</script>

<template>
    <template v-if="auth === null">
        <div ref="accountMenuRef" class="relative">
            <BaseButton class="!h-10" @click="accountMenuOpen = !accountMenuOpen">
                Account
            </BaseButton>

            <!-- Dropdown -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
            >
                <div
                    v-if="accountMenuOpen"
                    class="absolute right-0 top-[calc(100%+8px)] z-50 w-56 origin-top-right rounded-2xl border border-gray-800 bg-gray-950 py-2 shadow-2xl"
                >
                    <!-- Player section -->
                    <p class="px-4 pb-1 pt-2 text-[10px] uppercase tracking-widest text-gray-600">Player</p>
                    <Link
                        :href="login.create().url"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-200 transition-colors hover:bg-gray-900 hover:text-white"
                        @click="accountMenuOpen = false"
                    >
                        Log In
                    </Link>
                    <Link
                        :href="register.create().url"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-200 transition-colors hover:bg-gray-900 hover:text-white"
                        @click="accountMenuOpen = false"
                    >
                        Sign Up
                    </Link>

                    <div class="my-2 border-t border-gray-800/80"></div>

                    <!-- Writer Lab section -->
                    <p class="px-4 pb-1 text-[10px] uppercase tracking-widest text-gray-600">Writer Lab</p>
                    <Link
                        href="/writer/authentication/login"
                        class="flex items-center justify-between px-4 py-2.5 text-sm transition-colors hover:bg-gray-900"
                        @click="accountMenuOpen = false"
                    >
                        <span class="text-primary-400">Log In as Writer</span>
                        <PenLine class="size-3.5 text-primary-500/60" />
                    </Link>
                    <Link
                        href="/writer/authentication/register"
                        class="flex items-center justify-between px-4 py-2.5 text-sm transition-colors hover:bg-gray-900"
                        @click="accountMenuOpen = false"
                    >
                        <span class="text-primary-400">Apply as Writer</span>
                        <PenLine class="size-3.5 text-primary-500/60" />
                    </Link>
                </div>
            </Transition>
        </div>
    </template>
    <template v-else>
        <Teleport to="body">
            <PrimeDrawer v-model:visible="profileDrawerVisibility" position="right" class="!w-full max-w-108" :show-close-icon="false">
                <template #container="{ closeCallback }">
                    <div class="h-full overflow-y-auto">
                        <div class="flex flex-col gap-8 py-8">
                            <div class="flex items-center gap-4 px-8">
                                <div class="size-10"></div>
                                <div class="flex flex-1 items-center justify-center">
                                    <h3 class="text-xl font-normal text-white">Profile</h3>
                                </div>
                                <button
                                    @click="closeCallback"
                                    class="grid size-10 cursor-pointer place-items-center rounded-full transition hover:bg-gray-950"
                                >
                                    <X class="size-6 text-white" />
                                </button>
                            </div>
                            <div class="flex flex-1 flex-col gap-6 px-8">
                                <div class="flex flex-col gap-4 rounded-xl border border-gray-700/75 bg-white/5 p-4">
                                    <div class="flex items-center gap-4">
                                        <div class="flex flex-1 items-center gap-3">
                                            <img :src="auth.avatar" alt="" class="size-13 rounded-full" />
                                            <div class="flex flex-col">
                                                <p class="text-lg font-medium">{{ auth.full_name }}</p>
                                                <span class="text-sm text-gray-300">@{{ auth.username }}</span>
                                            </div>
                                        </div>
                                        <BaseButton severity="muted">
                                            <div class="flex items-center gap-2 text-primary-400">
                                                <LucideUpload :stroke-width="2" class="size-4" />
                                                <p class="text-sm font-normal">Upload Image</p>
                                            </div>
                                        </BaseButton>
                                    </div>
                                </div>
                                <h6 class="text-lg text-white">Settings</h6>
                                <div class="flex flex-col rounded-xl border border-gray-700/75 bg-white/5">
                                    <ul class="divide divide-y divide-gray-700/75">
                                        <li>
                                            <Link href="#" class="flex items-center justify-between p-4 text-white transition-all hover:px-6">
                                                <p class="text-sm font-normal">Achievement</p>
                                                <ChevronRight class="size-5" :stroke-width="2" />
                                            </Link>
                                        </li>
                                        <li>
                                            <Link href="#" class="flex items-center justify-between p-4 text-white transition-all hover:px-6">
                                                <p class="text-sm font-normal">Activity</p>
                                                <ChevronRight class="size-5" :stroke-width="2" />
                                            </Link>
                                        </li>
                                        <li>
                                            <Link href="#" class="flex items-center justify-between p-4 text-white transition-all hover:px-6">
                                                <p class="text-sm font-normal">Account</p>
                                                <ChevronRight class="size-5" :stroke-width="2" />
                                            </Link>
                                        </li>
                                    </ul>
                                </div>
                                <h6 class="text-lg text-white">Other</h6>
                                <div class="flex flex-col rounded-xl border border-gray-700/75 bg-white/5">
                                    <ul class="divide divide-y divide-gray-700/75">
                                        <li>
                                            <Link href="#" class="flex items-center justify-between p-4 text-white transition-all hover:px-6">
                                                <p class="text-sm font-normal">Invite Friends</p>
                                                <ChevronRight class="size-5" :stroke-width="2" />
                                            </Link>
                                        </li>
                                        <li>
                                            <Link href="#" class="flex items-center justify-between p-4 text-white transition-all hover:px-6">
                                                <p class="text-sm font-normal">Help & Support</p>
                                                <ChevronRight class="size-5" :stroke-width="2" />
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                href="/writer/authentication/login"
                                                class="flex items-center justify-between p-4 transition-all hover:px-6"
                                            >
                                                <p class="text-sm font-normal text-primary-400">Writer Lab</p>
                                                <PenLine class="size-4 text-primary-500/70" :stroke-width="2" />
                                            </Link>
                                        </li>
                                        <li>
                                            <Link href="#" class="flex items-center justify-between p-4 text-white transition-all hover:px-6">
                                                <p class="text-sm font-normal">Version 1.1.1</p>
                                                <ChevronRight class="size-5" :stroke-width="2" />
                                            </Link>
                                        </li>
                                    </ul>
                                </div>
                                <Link :method="logout().method" :href="logout().url">
                                    <BaseButton severity="muted" class="w-full font-normal text-red-500 outline-none hover:bg-red-700/10">
                                        Logout
                                    </BaseButton>
                                </Link>
                            </div>
                        </div>
                    </div>
                </template>
            </PrimeDrawer>
        </Teleport>

        <button @click="() => (profileDrawerVisibility = !profileDrawerVisibility)">
            <img
                :src="auth.avatar"
                alt=""
                class="size-12 cursor-pointer rounded-full border border-primary-600 outline-3 outline-transparent transition hover:outline-primary-400/30"
            />
        </button>
    </template>
</template>

<style scoped></style>
