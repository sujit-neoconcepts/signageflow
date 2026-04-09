<script setup>
import NavBarItem from "@/components/NavBarItem.vue";

import { computed } from 'vue';

import { can } from '@/utils/permissions';

const props = defineProps({
  menu: {
    type: Array,
    default: () => [],
  },
});

const filteredMenu = computed(() => props.menu.filter(i => !i.resource || can(i.resource + '_list')))

const emit = defineEmits(["menu-click"]);

const menuClick = (event, item) => {
  emit("menu-click", event, item);
};
</script>

<template>
  <NavBarItem v-for="(item, index) in filteredMenu" :key="index" :item="item" @menu-click="menuClick" />
</template>
