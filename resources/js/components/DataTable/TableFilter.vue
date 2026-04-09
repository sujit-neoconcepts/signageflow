<template>
    <ButtonWithDropdown
        placement="bottom-end"
        dusk="filters-dropdown"
        :active="hasEnabledFilters"
        title="Filters"
    >
        <template #button>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                :class="{
                    'text-gray-400': !hasEnabledFilters,
                    'text-green-400': hasEnabledFilters,
                }"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"
                    clip-rule="evenodd"
                />
            </svg>
        </template>

        <div
            role="menu"
            aria-orientation="horizontal"
            aria-labelledby="filter-menu"
            class="min-w-max"
        >
            <div
                v-for="(filter, key) in filters"
                :key="key"
                class="flex flex-row py-1"
            >
                <div
                    class="w-2/5 text-xs uppercase tracking-wide text-gray-900 dark:text-gray-100 text-opacity py-3 pr-3"
                >
                    {{ filter.label }}
                </div>
                <div class="w-3/5 p-0">
                    <select
                        v-if="filter.type === 'select'"
                        :name="filter.key"
                        :value="filter.value"
                        class="block focus:border-transparent focus:ring-0 text-gray-500 dark:text-gray-100 w-full shadow-sm text-sm bg-gray-100 dark:bg-slate-600 rounded-md"
                        @change="
                            handleFilterChange(filter.key, $event.target.value)
                        "
                    >
                        <option
                            v-for="(option, optionKey) in getFilteredOptions(filter)"
                            :key="optionKey"
                            :value="optionKey"
                        >
                            {{ option }}
                        </option>
                    </select>
                    <VueDatePicker
                        input-class-name="text-gray-500 dark:text-gray-100 shadow-sm text-sm bg-gray-100 dark:bg-slate-600"
                        :month-change-on-scroll="false"
                        :range="false"
                        :enable-time-picker="false"
                        format="dd-MM-yyyy"
                        auto-apply
                        v-if="filter.type === 'datePicker'"
                        :model-value="filter.value"
                        @update:model-value="setFilterValue(filter.key, $event)"
                        :name="filter.key"
                    >
                    </VueDatePicker>
                </div>
            </div>
        </div>
    </ButtonWithDropdown>
</template>

<script setup>
import { computed } from "vue";
import ButtonWithDropdown from "./ButtonWithDropdown.vue";

import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";

const props = defineProps({
    hasEnabledFilters: {
        type: Boolean,
        required: true,
    },

    filters: {
        type: Object,
        required: true,
    },

    onFilterChange: {
        type: Function,
        required: true,
    },

    // Cascading filter data (optional - any array of objects for cascading logic)
    // Example: PipeVariation::all(), SheetVariation::all(), or any custom dataset
    cascadeData: {
        type: Array,
        default: () => [],
    },

    cascadingFilterMap: {
        type: Object,
        default: () => ({}), // e.g. { grade: 'grade', thickness: 'thickness', tube_size: 'tube_size', tube_length: 'tlength' }
    },
});

// Check if cascading is enabled (only when cascadeData and cascadingFilterMap are provided)
const hasCascading = computed(() => {
    return props.cascadeData?.length > 0 && Object.keys(props.cascadingFilterMap).length > 0;
});

// Get current filter values as a reactive object
const filterValues = computed(() => {
    const vals = {};
    if (Array.isArray(props.filters)) {
        props.filters.forEach((f) => {
            vals[f.key] = f.value;
        });
    }
    return vals;
});

// Get the ordered list of cascading filter keys
const cascadeOrder = computed(() => Object.keys(props.cascadingFilterMap));

// Get filtered options for a filter based on parent selections in the cascade chain
function getFilteredOptions(filter) {
    // If cascading is not enabled or this filter is not in the cascade map, return original options
    if (!hasCascading.value || !props.cascadingFilterMap[filter.key]) {
        return filter.options;
    }

    const currentIndex = cascadeOrder.value.indexOf(filter.key);

    // First filter in the chain has no parent dependencies
    if (currentIndex <= 0) {
        return filter.options;
    }

    // Filter cascadeData based on all parent filter values in the cascade chain
    let filteredData = [...props.cascadeData];
    for (let i = 0; i < currentIndex; i++) {
        const parentFilterKey = cascadeOrder.value[i];
        const parentFieldName = props.cascadingFilterMap[parentFilterKey];
        const parentValue = filterValues.value[parentFilterKey];

        if (parentValue) {
            filteredData = filteredData.filter(
                (item) => String(item[parentFieldName]) === String(parentValue)
            );
        }
    }

    // Get unique values for the current field from filtered data
    const currentFieldName = props.cascadingFilterMap[filter.key];
    const validValues = new Set(
        filteredData.map((item) => String(item[currentFieldName]))
    );

    // Filter original options to only include valid values (keep empty/"All" option)
    const filteredOptions = {};
    for (const [key, label] of Object.entries(filter.options)) {
        if (key === "" || validValues.has(key)) {
            filteredOptions[key] = label;
        }
    }
    return filteredOptions;
}

// Handle filter change with automatic reset of dependent child filters
function handleFilterChange(key, value) {
    props.onFilterChange(key, value);

    // If cascading is enabled and this filter is in the cascade chain, reset child filters
    if (hasCascading.value && props.cascadingFilterMap[key]) {
        const currentIndex = cascadeOrder.value.indexOf(key);

        // Reset all filters after this one in the cascade chain
        for (let i = currentIndex + 1; i < cascadeOrder.value.length; i++) {
            props.onFilterChange(cascadeOrder.value[i], "");
        }
    }
}

function setFilterValue(key, val) {
    var d = new Date(val),
        month = d.getMonth() + 1,
        day = d.getDate(),
        year = d.getFullYear();
    props.onFilterChange(key, val ? year + "-" + month + "-" + day : "");
}
</script>
