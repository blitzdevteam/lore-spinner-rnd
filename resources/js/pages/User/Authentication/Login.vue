<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import AuthenticationLayout from '@/layouts/AuthenticationLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/LoginController';
import forgotPassword from '@/wayfinder/routes/user/authentication/forgot-password';
import { Form, Link } from '@inertiajs/vue3';
</script>

<template>
    <Form :action="store()" #default="{ errors, processing }">
        <AuthenticationLayout>
            <template #body>
                <div class="flex w-full flex-col gap-4">
                    <BaseInputFormat label="Email" :error="errors.email">
                        <PrimeInputText name="email" placeholder="Enter your email address" />
                    </BaseInputFormat>
                    <BaseInputFormat label="Password" :error="errors.password">
                        <PrimePassword name="password" placeholder="Enter your password" :feedback="false" toggle-mask />
                    </BaseInputFormat>
                </div>
            </template>
            <template #footer>
                <div class="flex w-full flex-col gap-8">
                    <div class="flex flex-col gap-4">
                        <BaseButton :processing severity="primary" class="text-lg">Login</BaseButton>
                        <div class="flex justify-center">
                            <Link :href="forgotPassword.create.url()" class="text-sm text-gray-400 transition-colors hover:text-primary-300">Forgot password?</Link>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div
                            class="grid h-22 cursor-pointer place-items-center rounded-xl border border-transparent bg-gray-950 outline-0 transition-all hover:border-primary-500/35 hover:bg-gray-900/80"
                        >
                            <div class="flex flex-col items-center justify-center gap-1">
                                <img src="@/assets/brands/google.svg" class="w-6 brightness-0 invert" alt="" />
                                <p class="font-light text-white">Google</p>
                            </div>
                        </div>
                        <div
                            class="grid h-22 cursor-pointer place-items-center rounded-xl border border-transparent bg-gray-950 outline-0 transition-all hover:border-primary-500/35 hover:bg-gray-900/80"
                        >
                            <div class="flex flex-col items-center justify-center gap-1">
                                <img src="@/assets/brands/facebook.svg" class="w-6" alt="" />
                                <p class="font-light text-white">Facebook</p>
                            </div>
                        </div>
                        <div
                            class="grid h-22 cursor-pointer place-items-center rounded-xl border border-transparent bg-gray-950 outline-0 transition-all hover:border-primary-500/35 hover:bg-gray-900/80"
                        >
                            <div class="flex flex-col items-center justify-center gap-1">
                                <img src="@/assets/brands/apple.svg" class="w-6 brightness-0 invert" alt="" />
                                <p class="font-light text-white">Apple</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </AuthenticationLayout>
    </Form>
</template>

<style scoped></style>
