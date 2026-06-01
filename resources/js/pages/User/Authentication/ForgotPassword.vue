<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import StickyFooterLayout from '@/layouts/StickyFooterLayout.vue';
import login from '@/wayfinder/routes/user/authentication/login';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/ForgotPasswordController';
import { Form } from '@inertiajs/vue3';
</script>

<template>
    <BaseBackgroundGradient />
    <StickyFooterLayout class="mx-auto max-w-124">
        <template #body>
            <div class="flex w-full flex-col items-center gap-8">
                <BaseLogo class="w-68" />
                <div class="flex flex-col gap-2 text-center">
                    <p class="text-lg text-white">Reset your password</p>
                    <p class="text-sm text-gray-400">Enter your email and choose a new password.</p>
                </div>
            </div>
        </template>
        <template #footer>
            <Form :action="store()" #default="{ errors, processing }">
                <div class="flex w-full flex-col gap-6">
                    <BaseInputFormat label="Email" :error="errors.email">
                        <PrimeInputText name="email" placeholder="Enter your email address" />
                    </BaseInputFormat>
                    <BaseInputFormat label="New Password" :error="errors.password">
                        <PrimePassword name="password" placeholder="Enter your new password" :feedback="false" toggle-mask />
                    </BaseInputFormat>
                    <BaseInputFormat label="Confirm Password" :error="errors.password_confirmation">
                        <PrimePassword name="password_confirmation" placeholder="Confirm your new password" :feedback="false" toggle-mask />
                    </BaseInputFormat>
                    <div class="flex flex-col gap-4">
                        <BaseButton :processing severity="primary" class="text-lg">Reset Password</BaseButton>
                        <div class="flex justify-center">
                            <BaseButton type="internal-link" :href="login.create.url()" severity="transparent" class="text-sm">
                                Back to Login
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </Form>
        </template>
    </StickyFooterLayout>
</template>

<style scoped></style>
