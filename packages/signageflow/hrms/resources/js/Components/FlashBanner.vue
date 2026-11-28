<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import NotificationBar from "@/components/NotificationBar.vue";

const page = usePage();
const message = computed(() => page.props.flash?.message);
const msgType = computed(() => page.props.flash?.msg_type ?? "info");
</script>

<template>
    <NotificationBar v-if="message" :color="msgType" class="mb-4">
        {{ message }}
    </NotificationBar>
</template>
