<!-- resources/js/Components/FinancialYearSelector.vue -->
<template>
    <div class="financial-year-selector">
        <select
            v-model="selectedYear"
            @change="changeFinancialYear"
            class="px-3 py-2 max-w-full min-w-[150px] focus:ring focus:outline-none rounded w-full dark:placeholder-gray-400 border-0 bg-transparent"
        >
            <option v-for="year in financialYears" :key="year" :value="year">
                FY {{ year }}
            </option>
        </select>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { router, usePage } from "@inertiajs/vue3";

const selectedYear = ref("");
const cdate = new Date();
const currentYear =
    cdate.getMonth() < 3 ? cdate.getFullYear() - 1 : cdate.getFullYear();

// Generate last 5 financial years
const financialYears = Array.from({ length: 10 }, (_, i) => {
    const year = currentYear + 1 - i;
    return `${year}-${year + 1}`;
});

const changeFinancialYear = () => {
    router.post(route("settings.change-financial-year"), {
        year: selectedYear.value,
    });
};

onMounted(() => {
    selectedYear.value =
        usePage().props.flash.financial_year ||
        `${currentYear}-${currentYear + 1}`;
});
</script>
