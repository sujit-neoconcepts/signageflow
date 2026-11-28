<script setup>
import { computed, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import {
    mdiChartBox,
    mdiFileExcel,
    mdiFilterVariant,
} from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import { can } from "@/utils/permissions";

const props = defineProps({
    report: String,
    reports: Object,
    reportsList: Object,
    filters: Object,
    departments: Array,
    year: Number,
    month: Number,
    columns: Array,
    rows: Array,
    resourceNeo: Object,
});

const allReports = computed(() => props.reports || props.reportsList || {});
const currentReportTitle = computed(() => allReports.value?.[props.report] || props.report || "Payroll Report");

const selectedYear = ref(props.year || props.filters?.year || new Date().getFullYear());
const selectedMonth = ref(props.month || props.filters?.month || (new Date().getMonth() + 1));
const selectedDept = ref(props.filters?.department_id || "");

const yearOptions = [2024, 2025, 2026, 2027, 2028].map(y => ({ id: y, label: String(y) }));
const monthOptions = [
    { id: 1, label: "Jan" }, { id: 2, label: "Feb" }, { id: 3, label: "Mar" },
    { id: 4, label: "Apr" }, { id: 5, label: "May" }, { id: 6, label: "Jun" },
    { id: 7, label: "Jul" }, { id: 8, label: "Aug" }, { id: 9, label: "Sep" },
    { id: 10, label: "Oct" }, { id: 11, label: "Nov" }, { id: 12, label: "Dec" },
];
const deptOptions = computed(() => [
    { id: "", label: "-- All Departments --" },
    ...(props.departments || []).map(d => ({ id: d.id, label: d.name })),
]);

const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || "";

const switchReport = (repKey) => {
    router.get(route("hrms.payroll.reports"), {
        report: repKey,
        year: unwrap(selectedYear.value),
        month: unwrap(selectedMonth.value),
        department_id: unwrap(selectedDept.value) || undefined,
    }, { preserveState: true, replace: true });
};

const applyFilters = () => {
    router.get(route("hrms.payroll.reports"), {
        report: props.report,
        year: unwrap(selectedYear.value),
        month: unwrap(selectedMonth.value),
        department_id: unwrap(selectedDept.value) || undefined,
    }, { preserveState: true, replace: true });
};

const exportUrl = computed(() =>
    route("hrms.payroll.reports.export", {
        report: props.report,
        year: unwrap(selectedYear.value),
        month: unwrap(selectedMonth.value),
        department_id: unwrap(selectedDept.value) || undefined,
    })
);

const safeColumns = computed(() => props.columns || []);
const safeRows = computed(() => props.rows || []);
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="`HRMS - ${currentReportTitle}`" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiChartBox" :title="`Payroll Reports: ${currentReportTitle}`" main>
                <BaseButton
                    v-if="can('hrmsPayrollReport_export')"
                    :icon="mdiFileExcel"
                    label="Export Excel"
                    color="success"
                    rounded-full
                    small
                    :href="exportUrl"
                />
            </SectionTitleLineWithButton>

            <!-- Report Pills Navigation -->
            <div class="flex flex-wrap gap-2 mb-4">
                <BaseButton
                    v-for="(label, key) in allReports"
                    :key="key"
                    :label="label"
                    :color="report === key ? 'contrast' : 'white'"
                    :outline="report !== key"
                    small
                    rounded-full
                    @click="switchReport(key)"
                />
            </div>

            <!-- Filter Bar -->
            <CardBox class="mb-4 py-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-32">
                        <FormControl
                            v-model="selectedYear"
                            :options="yearOptions"
                            type="select"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div class="w-32">
                        <FormControl
                            v-model="selectedMonth"
                            :options="monthOptions"
                            type="select"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <div class="w-48">
                        <FormControl
                            v-model="selectedDept"
                            :options="deptOptions"
                            type="select"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <BaseButton
                        :icon="mdiFilterVariant"
                        label="Apply"
                        color="info"
                        rounded-full
                        small
                        @click="applyFilters"
                    />
                </div>
            </CardBox>

            <!-- Report Table -->
            <CardBox class="mb-6 overflow-x-auto">
                <table class="w-full text-sm text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <th
                                v-for="(col, i) in safeColumns"
                                :key="i"
                                class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap"
                            >
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="(row, rIdx) in safeRows"
                            :key="rIdx"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td
                                v-for="(val, cIdx) in row"
                                :key="cIdx"
                                class="px-4 py-2.5 text-gray-800 dark:text-gray-300 whitespace-nowrap"
                            >
                                {{ typeof val === 'number' ? Number(val).toLocaleString('en-IN') : (val ?? '-') }}
                            </td>
                        </tr>
                        <tr v-if="safeRows.length === 0">
                            <td :colspan="safeColumns.length || 1" class="text-center py-8 text-gray-400">
                                No records found for the selected period.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
