<script setup>
import { onMounted, ref } from 'vue';

defineProps(['modelValue', 'icon']);

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});
</script>

<template>
    <input
        class="border-gray-700 focus:ring focus:outline-none rounded-md shadow-sm dark:placeholder-gray-400  border bg-white dark:bg-slate-800"
        :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" ref="input" />
</template>
