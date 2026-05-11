<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import WriterLabAuthLayout from '@/layouts/WriterLabAuthLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/writer/authentication/register');
};
</script>

<template>
    <WriterLabAuthLayout>
        <template #body>
            <form class="flex w-full flex-col gap-4" @submit.prevent="submit">
                <BaseInputFormat label="Name" :error="form.errors.name">
                    <PrimeInputText
                        v-model="form.name"
                        name="name"
                        placeholder="Your name"
                        class="w-full"
                    />
                </BaseInputFormat>
                <BaseInputFormat label="Email" :error="form.errors.email">
                    <PrimeInputText
                        v-model="form.email"
                        name="email"
                        placeholder="Enter your email address"
                        class="w-full"
                    />
                </BaseInputFormat>
                <BaseInputFormat label="Password" :error="form.errors.password">
                    <PrimePassword
                        v-model="form.password"
                        name="password"
                        placeholder="Create a password"
                        :feedback="false"
                        toggle-mask
                        class="w-full"
                    />
                </BaseInputFormat>
                <BaseInputFormat label="Confirm Password" :error="form.errors.password_confirmation">
                    <PrimePassword
                        v-model="form.password_confirmation"
                        name="password_confirmation"
                        placeholder="Confirm your password"
                        :feedback="false"
                        toggle-mask
                        class="w-full"
                    />
                </BaseInputFormat>
            </form>
        </template>
        <template #footer>
            <div class="flex w-full flex-col gap-4">
                <BaseButton
                    severity="primary"
                    class="text-lg"
                    :processing="form.processing"
                    @click="submit"
                >
                    Create Writer Account
                </BaseButton>
            </div>
        </template>
    </WriterLabAuthLayout>
</template>

<style scoped></style>
