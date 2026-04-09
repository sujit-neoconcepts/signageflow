<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    startNumber: Number,
});
const emit = defineEmits(['stopped'])
let cowntdown = ref(props.startNumber);
let timeout = ref(0);

onMounted(() => {
    timeout.value = setInterval(() => cowntdown.value--, 1000)
})
watch(cowntdown, (newValue) => {
    if (newValue <= 0) {
        clearTimeout(timeout.value);
        emit('stopped', true)
    }
})
</script>
<template>
    <span class="inline-block w-10 text-center"><b>{{ cowntdown }} </b></span>
</template>
