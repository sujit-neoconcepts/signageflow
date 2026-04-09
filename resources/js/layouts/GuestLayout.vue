<script setup>
import { useStyleStore } from "@/stores/style.js";
import { onMounted } from "vue";
const styleStore = useStyleStore();
const props = defineProps({
    title: String,
    sitekey: String,
    captcha_en: Boolean,
});
onMounted(() => {
    if (props.captcha_en) {
        const recaptchaScript = document.createElement("script");
        recaptchaScript.setAttribute(
            "src",
            "https://www.google.com/recaptcha/api.js?render=" + props.sitekey
        );
        document.head.appendChild(recaptchaScript);
    }
});
</script>
<template>
    <div class="flex flex-wrap" :class="{ dark: styleStore.darkMode }">
        <div
            class="w-full sm:w-full md:w-full lg:w-3/5 xl:w-3/5 lg:bg-white"
        >
            <div
                class="flex items-center justify-center text-2xl uppercase text-white dark:text-black lg:text-9xl lg:h-screen lg:bg-[url('/images/banner.png')] bg-no-repeat lg:bg-center lg:-indent-[500%]"
            >
                SignageFlow
            </div>
        </div>

        <div
            class="w-full sm:w-full md:w-full lg:w-2/5 xl:w-2/5 lg:bg-gray-500"
        >
            <div
                class="h-screen flex flex-col sm:justify-center items-center md:px-4 px-4 pt-6 sm:pt-0 bg-gray-50 dark:bg-slate-800 dark:text-slate-100"
            >
                <div class="w-full sm:max-w-md">
                    <h1 class="font-bold">Welcome To SignageFlow ERP</h1>
                    <p class="mt-2 text-xs">{{ title }}</p>
                </div>

                <div
                    class="w-full sm:max-w-md mt-1 px-6 py-4 shadow-md overflow-hidden sm:rounded-lg dark:bg-slate-900/70 border dark:border-none bg-white"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
