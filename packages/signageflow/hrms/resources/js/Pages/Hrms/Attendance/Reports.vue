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
    fromDate: String,
    toDate: String,
    filters: Object,
    columns: Array,
    rows: Array,
    departments: Array,
});

const reportOptions = [
    { key: "daily_attendance", label: "Daily Attendance" },
    { key: "muster", label: "Attendance Muster" },
    { key: "consolidated_monthly", label: "Consolidated Monthly" },
    { key: "department_attendance", label: "Department Attendance" },
    { key: "individual", label: "Individual Attendance" },
    { key: "working_hours_summary", label: "Working Hours Summary" },
    { key: "day_wise_ot", label: "Day Wise OT" },
    { key: "monthly_ot", label: "Monthly Attendance OT" },
    { key: "break_reports", label: "Break Reports" },
    { key: "clocking_data", label: "Clocking Data" },
    { key: "mobile_daily", label: "Mobile Daily Attendance" },
    { key: "mobile_reports", label: "Mobile Attendance Reports" },
    { key: "work_location", label: "Work Location" },
    { key: "assigned_work_location", label: "Assigned Work Location" },
    { key: "attendance_problem", label: "Attendance Problem" },
    { key: "regularization", label: "Regularization Report" },
];

const state = ref({
    report: props.reportType,
    from_date: props.fromDate,
    to_date: props.toDate,
    department_id: props.filters?.department_id ?? "",
});

const run = () => router.get(route("hrms.attendance.reports"), state.value, { preserveState: true });
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Attendance Reports" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiFileExcel" title="Attendance Reports" main>
                <BaseButton :href="route('hrms.attendance.reports.export', state)" :icon="mdiDownload" label="Export Excel" color="success" small />
            </SectionTitleLineWithButton>

            <CardBox class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <select v-model="state.report" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option v-for="o in reportOptions" :key="o.key" :value="o.key">{{ o.label }}</option>
                    </select>
                    <input v-model="state.from_date" type="date" class="rounded border-slate-300 dark:bg-slate-800 text-sm" />
                    <input v-model="state.to_date" type="date" class="rounded border-slate-300 dark:bg-slate-800 text-sm" />
                    <select v-model="state.department_id" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option value="">All Departments</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <BaseButton label="Generate" color="info" @click="run" />
                </div>
            </CardBox>

            <CardBox has-table>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b dark:border-slate-700 text-left">
                                <th v-for="col in columns" :key="col" class="p-3">{{ col }}</th>
                            </tr>
                        </thead>
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
