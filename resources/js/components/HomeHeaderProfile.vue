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
        <Link
            :href="login.create().url"
            class="flex h-10 items-center justify-center rounded-full border border-[#00C6DE]/70 bg-transparent px-4 text-sm font-medium text-[#c8ced1] transition-colors hover:border-[#00C6DE] hover:text-white"
        >
            Account
        </Link>
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
                class="size-[45px] cursor-pointer rounded-full border-2 border-[#00C6DE] outline-2 outline-transparent transition hover:brightness-110"
            />
        </button>
    </template>
</template>

<style scoped></style>
