<script setup>
import { ref, computed } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { mdiViewDashboard, mdiDownload } from "@mdi/js";
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
} from "chart.js";
import { Bar, Doughnut } from "vue-chartjs";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Title, Tooltip, Legend);

const props = defineProps({
    date: String,
    filters: Object,
    kpis: Object,
    absentRoster: Array,
    shiftChart: Array,
    lateComers: Array,
    genderBreakdown: Object,
    orgOptions: Object,
});

const filterState = ref({
    date: props.date,
    department_id: props.filters?.department_id ?? "",
    section_id: props.filters?.section_id ?? "",
    designation_id: props.filters?.designation_id ?? "",
    category_id: props.filters?.category_id ?? "",
    extra_classification_id: props.filters?.extra_classification_id ?? "",
});

const applyFilters = () => {
    router.get(route("hrms.attendance.dashboard"), filterState.value, { preserveState: true, preserveScroll: true });
};

const shiftBarData = computed(() => ({
    labels: props.shiftChart.map((s) => s.label),
    datasets: [
        { label: "Present", data: props.shiftChart.map((s) => s.present), backgroundColor: "#10B981" },
        { label: "Absent", data: props.shiftChart.map((s) => s.absent), backgroundColor: "#EF4444" },
        { label: "Late", data: props.shiftChart.map((s) => s.late), backgroundColor: "#F59E0B" },
        { label: "Early Out", data: props.shiftChart.map((s) => s.early_out), backgroundColor: "#6366F1" },
    ],
}));

const shiftBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
};

const donutData = computed(() => ({
    labels: ["Present", "Absent", "On Leave"],
    datasets: [
        {
            data: [props.kpis.present, props.kpis.absent, props.kpis.on_leave],
            backgroundColor: ["#10B981", "#EF4444", "#0EA5E9"],
        },
    ],
}));
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Attendance Dashboard" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiViewDashboard" title="Attendance Dashboard" main>
                <BaseButton :href="route('hrms.attendance.dashboard.export', filterState)" :icon="mdiDownload" label="Export KPI" color="success" small />
            </SectionTitleLineWithButton>

            <CardBox class="mb-4">
                <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                    <input v-model="filterState.date" type="date" class="rounded border-slate-300 dark:bg-slate-800 text-sm" />
                    <select v-model="filterState.department_id" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option value="">All Departments</option>
                        <option v-for="d in orgOptions.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <select v-model="filterState.section_id" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option value="">All Sections</option>
                        <option v-for="s in orgOptions.sections" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <select v-model="filterState.designation_id" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option value="">All Designations</option>
                        <option v-for="d in orgOptions.designations" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <select v-model="filterState.category_id" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option value="">All Categories</option>
                        <option v-for="c in orgOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <BaseButton label="Apply" color="info" @click="applyFilters" />
                </div>
            </CardBox>

            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <CardBox>
                    <div class="text-xs text-slate-500 uppercase">Present</div>
                    <div class="text-2xl font-bold text-emerald-600">{{ kpis.present }} / {{ kpis.headcount }}</div>
                    <div class="text-xs text-slate-500">{{ kpis.attendance_rate }}% | On Time: {{ kpis.on_time }}</div>
                </CardBox>
                <CardBox>
                    <div class="text-xs text-slate-500 uppercase">Absent</div>
                    <div class="text-2xl font-bold text-rose-600">{{ kpis.absent }}</div>
                </CardBox>
                <CardBox>
                    <div class="text-xs text-slate-500 uppercase">On Leave</div>
                    <div class="text-2xl font-bold text-sky-600">{{ kpis.on_leave }}</div>
                </CardBox>
                <CardBox>
                    <div class="text-xs text-slate-500 uppercase">Late Arrivals</div>
                    <div class="text-2xl font-bold text-amber-600">{{ kpis.late }}</div>
                </CardBox>
                <CardBox>
                    <div class="text-xs text-slate-500 uppercase">Early Out</div>
                    <div class="text-2xl font-bold text-indigo-600">{{ kpis.early_out }}</div>
                </CardBox>
                <CardBox>
                    <div class="text-xs text-slate-500 uppercase">Pending Approvals</div>
                    <div class="text-2xl font-bold">{{ kpis.pending_approvals }}</div>
                </CardBox>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <CardBox class="lg:col-span-2">
                    <h3 class="font-bold mb-3">Daily Shifts / Schedules</h3>
                    <div class="h-64">
                        <Bar :data="shiftBarData" :options="shiftBarOptions" />
                    </div>
                </CardBox>
                <CardBox>
                    <h3 class="font-bold mb-3">Employees Attendance & Gender</h3>
                    <div class="h-48">
                        <Doughnut :data="donutData" :options="{ responsive: true, maintainAspectRatio: false }" />
                    </div>
                    <div class="mt-3 text-xs text-slate-500 flex justify-around">
                        <span>Male: {{ genderBreakdown.male }}</span>
                        <span>Female: {{ genderBreakdown.female }}</span>
                        <span>Other: {{ genderBreakdown.other }}</span>
                    </div>
                </CardBox>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <CardBox has-table>
                    <div class="p-3 font-bold border-b dark:border-slate-700">Absent Today Roster ({{ absentRoster.length }})</div>
                    <table class="w-full text-sm">
                        <thead><tr class="border-b dark:border-slate-700 text-left"><th class="p-2">Code</th><th class="p-2">Name</th><th class="p-2">Dept</th></tr></thead>
                        <tbody>
                            <tr v-for="a in absentRoster.slice(0, 20)" :key="a.id" class="border-b dark:border-slate-800">
                                <td class="p-2 font-mono">{{ a.emp_code }}</td>
                                <td class="p-2">{{ a.full_name }}</td>
                                <td class="p-2">{{ a.department ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardBox>
                <CardBox has-table>
                    <div class="p-3 font-bold border-b dark:border-slate-700">Late Comers (Rolling 30 Days)</div>
                    <table class="w-full text-sm">
                        <thead><tr class="border-b dark:border-slate-700 text-left"><th class="p-2">Name</th><th class="p-2">Dept</th><th class="p-2">30d Lates</th><th class="p-2">Trend</th></tr></thead>
                        <tbody>
                            <tr v-for="l in lateComers" :key="l.emp_code" class="border-b dark:border-slate-800">
                                <td class="p-2">{{ l.full_name }}</td>
                                <td class="p-2">{{ l.department ?? '-' }}</td>
                                <td class="p-2 font-bold">{{ l.count_30d }}</td>
                                <td class="p-2 font-bold" :class="l.trend === 'up' ? 'text-rose-600' : l.trend === 'down' ? 'text-emerald-600' : 'text-slate-400'">
                                    {{ l.trend === 'up' ? '▲' : l.trend === 'down' ? '▼' : '—' }} ({{ l.prev_30d }})
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardBox>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
