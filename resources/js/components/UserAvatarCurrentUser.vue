<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import UserAvatar from "@/components/UserAvatar.vue";

const userName = computed(() => usePage().props.auth.user.name);
const userId = computed(() => usePage().props.auth.user.id);
</script>

<template>
  <UserAvatar :username="userName" :userid="userId" api="initials">
    <slot />
  </UserAvatar>
</template>