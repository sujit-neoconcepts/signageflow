<template>
    <select
        name="per_page"
        :dusk="dusk"
        :value="perPageValue"
        class="block focus:border-transparent focus:ring-0 min-w-max shadow-sm text-sm border border-gray-200 dark:border-slate-700 bg-gray-200 dark:bg-slate-700 text-opacity rounded-md px-5 py-1.5 transition ease-in-out m-0"
        @change="onChange($event.target.value)"
    >
        <option v-for="option in perPageOptions" :key="option" :value="option">
            {{ option == allDataValue ? "All" : option }}
            {{ option == allDataValue ? " Data" : translations.per_page }}
        </option>
    </select>
</template>

<script setup>
import { computed } from "vue";
import uniq from "lodash-es/uniq";
import { getTranslations } from "../translations.js";

const translations = getTranslations();
const allDataValue = 10000;

const props = defineProps({
    dusk: {
        type: String,
        default: null,
        required: false,
    },

    value: {
        type: Number,
        default: 15,
        required: false,
    },

    options: {
        type: Array,
        default() {
            return [10, 15, 30, 50, 100];
        },
        required: false,
    },

    onChange: {
        type: Function,
        required: true,
    },
    showAll: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const perPageValue = computed(() => {
    return isNaN(props.value) ? allDataValue : props.value;
});
const perPageOptions = computed(() => {
    let options = [...props.options];
    if (props.showAll) {
        options.push(parseInt(allDataValue));
    }

    return uniq(options).sort((a, b) => a - b);
});
</script>
