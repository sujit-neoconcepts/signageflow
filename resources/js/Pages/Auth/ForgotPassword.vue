<script setup>
import GuestLayout from '@/layouts/GuestLayout.vue';
import InputError from '@/components/InputError.vue';
import InputLabel from '@/components/InputLabel.vue';
import PrimaryButton from '@/components/PrimaryButton.vue';
import TextInput from '@/components/TextInput.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    status: String,
    sitekey: String,
});

const form = useForm({
    email: '',
    captcha_token: "",
});

const submit = () => {

    let submitForm = (token) => {
        form.captcha_token = token;
        form.post(route('password.email'));
    };

    grecaptcha
        .execute(props.sitekey, { action: "submit" })
        .then(function (token) {
            submitForm(token);
        });


};
</script>

<template>
    <GuestLayout title="Please enter your email, We will send you password re-set link." :sitekey="sitekey">

        <Head title="Forgot Password" />

        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>
        <div v-if="form.errors.captcha_token" class="mb-4 font-medium text-sm text-red-600">
            {{ form.errors.captcha_token }}
        </div>
        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required autofocus
                    autocomplete="username" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link :href="route('login')"
                    class="underline text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                Back to Login?
                </Link>
                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Email Password Reset Link
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
