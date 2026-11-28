<script setup>
import { ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { mdiFileExcel, mdiDownload } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";

const props = defineProps({
    reportType: String,
    financialYear: String,
    fromDate: String,
    toDate: String,
    filters: Object,
    columns: Array,
    rows: Array,
    departments: Array,
});

const reportOptions = [
    { key: "daily_leave", label: "Daily Leave" },
    { key: "monthly_summary", label: "Monthly Leave Summary" },
    { key: "leave_status", label: "Leave Status" },
    { key: "leave_attachment", label: "Leave Attachment Report" },
    { key: "leave_history", label: "Leave History (Transactions)" },
    { key: "leave_taken_history", label: "Leave Taken History" },
    { key: "leave_consolidate", label: "Leave Consolidate (FY)" },
    { key: "comp_off", label: "Comp Off Report" },
    { key: "permission", label: "Permission Report" },
    { key: "fwa_status", label: "FWA Status Report" },
];

const state = ref({
    report: props.reportType,
    financial_year: props.financialYear,
    from_date: props.fromDate,
    to_date: props.toDate,
    department_id: props.filters?.department_id ?? "",
});

const run = () => router.get(route("hrms.leave.reports"), state.value, { preserveState: true });
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Leave Reports" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiFileExcel" title="Leave Reports" main>
                <BaseButton :href="route('hrms.leave.reports.export', state)" :icon="mdiDownload" label="Export Excel" color="success" small />
            </SectionTitleLineWithButton>

            <CardBox class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-2 text-sm">
                    <select v-model="state.report" class="rounded border-slate-300 dark:bg-slate-800">
                        <option v-for="o in reportOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                    </select>
                    <input v-model="state.financial_year" placeholder="2026-2027" class="rounded border-slate-300 dark:bg-slate-800" />
                    <input v-model="state.from_date" type="date" class="rounded border-slate-300 dark:bg-slate-800" />
                    <input v-model="state.to_date" type="date" class="rounded border-slate-300 dark:bg-slate-800" />
                    <select v-model="state.department_id" class="rounded border-slate-300 dark:bg-slate-800">
                        <option value="">All Departments</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <BaseButton label="Generate" color="info" @click="run" />
                </div>
            </CardBox>

            <CardBox has-table>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="border-b dark:border-slate-700 text-left"><th v-for="col in columns" :key="col" class="p-3">{{ col }}</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, idx) in rows" :key="idx" class="border-b dark:border-slate-800">
                                <td v-for="(cell, cidx) in r" :key="cidx" class="p-3">{{ cell }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
