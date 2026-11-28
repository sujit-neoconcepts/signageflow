<script setup>
import { ref, computed } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { mdiCalendarCheck } from "@mdi/js";
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend } from "chart.js";
import { Bar } from "vue-chartjs";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps({
    month: Number,
    year: Number,
    trendRange: String,
    filters: Object,
    pendingApprovals: Number,
    dailyDistribution: Array,
    employeesByLeaveType: Array,
    trends: Object,
    orgOptions: Object,
});

const state = ref({
    month: props.month,
    year: props.year,
    trend_range: props.trendRange,
    department_id: props.filters?.department_id ?? "",
});

const apply = () => router.get(route("hrms.leave.dashboard"), state.value, { preserveState: true });

const chartData = computed(() => ({
    labels: props.dailyDistribution.map((d) => d.day),
    datasets: [{ label: "Employees on Leave", data: props.dailyDistribution.map((d) => d.count), backgroundColor: "#0EA5E9" }],
}));
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Leave Dashboard" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCalendarCheck" title="Leave Dashboard & Analytics" main />

            <CardBox class="mb-4">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <select v-model="state.month" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option v-for="m in 12" :key="m" :value="m">Month {{ m }}</option>
                    </select>
                    <input v-model="state.year" type="number" class="rounded border-slate-300 dark:bg-slate-800 text-sm" />
                    <select v-model="state.department_id" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option value="">All Departments</option>
                        <option v-for="d in orgOptions.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <select v-model="state.trend_range" class="rounded border-slate-300 dark:bg-slate-800 text-sm">
                        <option value="weekly">Weekly Trends</option>
                        <option value="monthly">Monthly Trends</option>
                        <option value="yearly">Yearly (FY) Trends</option>
                    </select>
                    <BaseButton label="Apply" color="info" @click="apply" />
                </div>
            </CardBox>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <CardBox><div class="text-xs text-slate-500 uppercase">Pending Approvals</div><div class="text-2xl font-bold text-amber-600">{{ pendingApprovals }}</div></CardBox>
                <CardBox><div class="text-xs text-slate-500 uppercase">Total Leaves Taken</div><div class="text-2xl font-bold text-sky-600">{{ trends.total_leaves_taken }}</div></CardBox>
                <CardBox><div class="text-xs text-slate-500 uppercase">Peak Day</div><div class="text-lg font-bold">{{ trends.peak_day }}</div></CardBox>
                <CardBox><div class="text-xs text-slate-500 uppercase">Full Day</div><div class="text-2xl font-bold text-emerald-600">{{ trends.full_day_count }}</div></CardBox>
                <CardBox><div class="text-xs text-slate-500 uppercase">Half Day</div><div class="text-2xl font-bold text-indigo-600">{{ trends.half_day_count }}</div></CardBox>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <CardBox class="lg:col-span-2">
                    <h3 class="font-bold mb-3">Leaves for the Month</h3>
                    <div class="h-64"><Bar :data="chartData" :options="{ responsive: true, maintainAspectRatio: false }" /></div>
                </CardBox>
                <CardBox>
                    <h3 class="font-bold mb-3">Employees On Leave Today by Type</h3>
                    <div v-for="grp in employeesByLeaveType" :key="grp.leave_type" class="mb-3 p-2 rounded bg-slate-50 dark:bg-slate-800">
                        <div class="font-bold text-sm">{{ grp.leave_type }} ({{ grp.count }})</div>
                        <div class="text-xs text-slate-500">{{ grp.employees.join(', ') }}</div>
                    </div>
                    <div v-if="employeesByLeaveType.length === 0" class="text-sm text-slate-500">No employees on leave today.</div>
                </CardBox>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
