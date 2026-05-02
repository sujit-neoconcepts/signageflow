<script setup>
import { Head, usePage, router } from "@inertiajs/vue3";
import {
    mdiViewDashboard,
    mdiAlert,
    mdiPackageVariant,
    mdiCashMultiple,
    mdiChartLine,
    mdiChartBar,
    mdiCalendar,
} from "@mdi/js";

import { computed, ref } from "vue";
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    BarElement,
} from "chart.js";
import { Line, Bar } from "vue-chartjs";

import SectionMain from "@/components/SectionMain.vue";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import CardBox from "@/components/CardBox.vue";
import BaseIcon from "@/components/BaseIcon.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import BaseButton from "@/components/BaseButton.vue";

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    BarElement
);

const props = defineProps({
    stockOverview: Object,
    expensesData: Object,
    monthlyTrend: Array,
    topSuppliers: Array,
    expensesByCategory: Array,
    financialYear: Object,
    startDate: String,
    endDate: String,
});

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");
const user_name = usePage().props.auth.user.name;

// Date filter state
const filterStartDate = ref(props.startDate);
const filterEndDate = ref(props.endDate);

// Auto-apply date filter on change
const applyDateFilter = () => {
    router.get(route('dashboard'), {
        start_date: filterStartDate.value,
        end_date: filterEndDate.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Reset to current month
const resetDateFilter = () => {
    router.get(route('dashboard'), {}, {
        preserveState: false,
        preserveScroll: false,
    });
};


// Chart configurations
const trendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: "top",
        },
        title: {
            display: true,
            text: "Monthly Purchase, Outward Trend and Sales (Last 12 Months)",
        },
        tooltip: {
            callbacks: {
                label: function (context) {
                    const value = context.raw ?? 0;
                    return `${context.dataset.label}: ₹${new Intl.NumberFormat('en-IN').format(Math.round(value))}`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function (value) {
                    if (value >= 100000) return '₹' + (value / 100000).toFixed(1) + 'L';
                    if (value >= 1000) return '₹' + (value / 1000).toFixed(0) + 'K';
                    return '₹' + value;
                },
            },
        },
    },
};

const trendData = computed(() => ({
    labels: props.monthlyTrend.map((item) => item.month),
    datasets: [
        {
            label: "Purchases",
            data: props.monthlyTrend.map((item) => item.purchases),
            backgroundColor: "rgba(59, 130, 246, 0.85)",
            borderColor: "#3B82F6",
            borderWidth: 1,
            borderRadius: 4,
        },
        {
            label: "Outwards",
            data: props.monthlyTrend.map((item) => item.outwards),
            backgroundColor: "rgba(16, 185, 129, 0.85)",
            borderColor: "#10B981",
            borderWidth: 1,
            borderRadius: 4,
        },
        {
            label: "Sales (Cabinet)",
            data: props.monthlyTrend.map((item) => item.sales_cabinet),
            backgroundColor: "rgba(245, 158, 11, 0.85)",
            borderColor: "#F59E0B",
            borderWidth: 1,
            borderRadius: 4,
        },
        {
            label: "Sales (Letters)",
            data: props.monthlyTrend.map((item) => item.sales_letters),
            backgroundColor: "rgba(139, 92, 246, 0.85)",
            borderColor: "#8B5CF6",
            borderWidth: 1,
            borderRadius: 4,
        },
        {
            label: "Sales (Signage)",
            data: props.monthlyTrend.map((item) => item.sales_signage),
            backgroundColor: "rgba(239, 68, 68, 0.85)",
            borderColor: "#EF4444",
            borderWidth: 1,
            borderRadius: 4,
        },
    ],
}));

const topSuppliersData = computed(() => ({
    labels: props.topSuppliers.map((supplier) => supplier.name),
    datasets: [
        {
            label: "Total Purchase Value",
            data: props.topSuppliers.map((supplier) => supplier.total_value),
            backgroundColor: [
                "#3B82F6",
                "#10B981",
                "#F59E0B",
                "#EF4444",
                "#8B5CF6",
                "#EC4899",
                "#14B8A6",
                "#F97316",
                "#84CC16",
                "#6366F1",
            ],
            borderWidth: 1,
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
    },
    indexAxis: "y",
};

const expenseChartHeight = computed(() => {
    // 384px is equivalent to Tailwind's h-96
    const minHeight = 384; 
    // Allocate ~45px per bar so they remain thick and clickable, plus 60px for axes/padding
    const dynamicHeight = props.expensesByCategory.length * 45 + 60; 
    return Math.max(minHeight, dynamicHeight) + 'px';
});

const expenseCategoryData = computed(() => ({
    labels: props.expensesByCategory.map((exp) => exp.exp_cate),
    datasets: [
        {
            label: "Total Expense",
            data: props.expensesByCategory.map((exp) => exp.total_value),
            backgroundColor: [
                "#EF4444", "#F59E0B", "#10B981", "#3B82F6", "#8B5CF6",
                "#EC4899", "#14B8A6", "#F97316", "#84CC16", "#6366F1",
            ],
            borderWidth: 1,
        },
    ],
}));

const expenseCategoryOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: "y",
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: function(context) {
                    return `₹${new Intl.NumberFormat("en-IN").format(Math.round(context.raw))} (Click to view)`;
                }
            }
        }
    },
    onClick: (event, elements, chart) => {
        if (elements && elements.length > 0) {
            const index = elements[0].index;
            const category = chart.data.labels[index];
            router.get(route('expense.index'), {
                'filter[amt_type]': 'Expense',
                'filter[exp_cate]': category
            });
        }
    },
    onHover: (event, chartElement) => {
        event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
    }
};

// Helper functions
const formatNumber = (num) => {
    return new Intl.NumberFormat("en-IN").format(num);
};

const formatCurrency = (amount) => {
    return `₹${formatNumber(Math.round(amount))}`;
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Dashboard" />
        <SectionMain>
            <!-- Header & Date Filter Section -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                <!-- Left: Dashboard Title & Welcome -->
                <div class="flex items-center gap-4">
                    <div class="bg-blue-600 p-3 rounded-xl shadow-lg shadow-blue-500/30 text-white">
                        <BaseIcon :path="mdiViewDashboard" size="32" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800 dark:text-white leading-tight">Dashboard</h1>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            Welcome back, <span class="text-blue-600 dark:text-blue-400">{{ user_name }}</span>
                        </p>
                    </div>
                </div>

                <!-- Right: Date Filter Card -->
                <div class="bg-white dark:bg-slate-800 p-1.5 rounded-lg shadow-sm border border-slate-100 dark:border-slate-700 flex flex-wrap items-center gap-3">
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-md text-blue-600 dark:text-blue-400">
                        <BaseIcon :path="mdiChartLine" size="20" />
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">From</label>
                        <input
                            v-model="filterStartDate"
                            type="date"
                            @change="applyDateFilter"
                            class="px-2 py-1.5 text-sm border border-slate-200 dark:border-slate-600 rounded bg-transparent focus:outline-none focus:border-blue-500 text-slate-700 dark:text-slate-200"
                        />
                    </div>
                    
                    <span class="text-slate-300">|</span>

                    <div class="flex items-center gap-2">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">To</label>
                        <input
                            v-model="filterEndDate"
                            type="date"
                            @change="applyDateFilter"
                            class="px-2 py-1.5 text-sm border border-slate-200 dark:border-slate-600 rounded bg-transparent focus:outline-none focus:border-blue-500 text-slate-700 dark:text-slate-200"
                        />
                    </div>

                    <button 
                        @click="resetDateFilter"
                        class="ml-2 flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-blue-600 hover:bg-slate-50 rounded transition-colors"
                    >
                        <BaseIcon :path="mdiCalendar" size="14" />
                        Reset
                    </button>
                </div>
            </div>
            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
                class="mb-6"
            >
                {{ message }}
            </NotificationBar>

            <!-- Key Metrics Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <CardBox 
                    class="bg-gradient-to-r from-blue-500 to-blue-600 text-white cursor-pointer"
                    is-hoverable
                    @click="router.get(route('product.index'), { perPage: 10000 })"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Products</p>
                            <p class="text-2xl font-bold">
                                {{ formatNumber(stockOverview.total_products) }}
                            </p>
                        </div>
                        <BaseIcon
                            :path="mdiPackageVariant"
                            size="48"
                            class="text-blue-200"
                        />
                    </div>
                </CardBox>

                <CardBox 
                    class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white cursor-pointer"
                    is-hoverable
                    @click="router.get(route('stocks.level'), { 'filter[status]': 'Below Threshold' })"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm">Low Stock Items</p>
                            <p class="text-2xl font-bold">
                                {{ formatNumber(stockOverview.low_stock_items) }}
                            </p>
                        </div>
                        <BaseIcon
                            :path="mdiAlert"
                            size="48"
                            class="text-yellow-200"
                        />
                    </div>
                </CardBox>

                <CardBox 
                    class="bg-gradient-to-r from-green-500 to-green-600 text-white cursor-pointer"
                    is-hoverable
                    @click="router.get(route('purchase.index'), { 'filter[pur_date_start]': filterStartDate, 'filter[pur_date_end]': filterEndDate, perPage: 10000 })"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Purchases</p>
                            <p class="text-2xl font-bold">
                                {{ formatCurrency(expensesData.total_purchase_value) }}
                            </p>
                        </div>
                        <BaseIcon
                            :path="mdiCashMultiple"
                            size="48"
                            class="text-green-200"
                        />
                    </div>
                </CardBox>

                <CardBox 
                    class="bg-gradient-to-r from-purple-500 to-purple-600 text-white cursor-pointer"
                    is-hoverable
                    @click="router.get(route('expense.index'), { 'filter[amt_type]': 'Expense', 'filter[exp_date_start]': filterStartDate, 'filter[exp_date_end]': filterEndDate, perPage: 10000 })"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Total Expenses</p>
                            <p class="text-2xl font-bold">
                                {{ formatCurrency(expensesData.total_expense) }}
                            </p>
                        </div>
                        <BaseIcon
                            :path="mdiCashMultiple"
                            size="48"
                            class="text-purple-200"
                        />
                    </div>
                </CardBox>
            </div>

            <!-- Expenses Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <CardBox>
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <BaseIcon :path="mdiCashMultiple" class="mr-2 text-blue-600" />
                        Expenses Summary
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                            <span class="text-gray-700">Purchase Value</span>
                            <span class="font-bold text-blue-600">
                                {{ formatCurrency(expensesData.total_purchase_value) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                            <span class="text-gray-700">Outward Value</span>
                            <span class="font-bold text-green-600">
                                {{ formatCurrency(expensesData.total_outward_value) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                            <span class="text-gray-700">Total Expenses</span>
                            <span class="font-bold text-purple-600">
                                {{ formatCurrency(expensesData.total_expense) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg">
                            <span class="text-gray-700">Total Deposits</span>
                            <span class="font-bold text-yellow-600">
                                {{ formatCurrency(expensesData.total_deposit) }}
                            </span>
                        </div>
                    </div>
                </CardBox>

                <CardBox>
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <BaseIcon :path="mdiChartLine" class="mr-2 text-green-600" />
                        Quick Links
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <a
                            href="/admin/consumableInternalNameReport"
                            class="p-4 bg-blue-50 hover:bg-blue-100 rounded-lg text-center transition"
                        >
                            <p class="font-semibold text-blue-700">Product Price</p>
                            <p class="text-sm text-gray-600">View Price</p>
                        </a>
                        <a
                            href="/admin/purchase"
                            class="p-4 bg-green-50 hover:bg-green-100 rounded-lg text-center transition"
                        >
                            <p class="font-semibold text-green-700">Purchases</p>
                            <p class="text-sm text-gray-600">View Purchases</p>
                        </a>
                        <a
                            href="/admin/outward"
                            class="p-4 bg-purple-50 hover:bg-purple-100 rounded-lg text-center transition"
                        >
                            <p class="font-semibold text-purple-700">Outwards</p>
                            <p class="text-sm text-gray-600">View Outwards</p>
                        </a>
                        <a
                            href="/admin/stocks"
                            class="p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg text-center transition"
                        >
                            <p class="font-semibold text-yellow-700">Stocks</p>
                            <p class="text-sm text-gray-600">Check Stock Levels</p>
                        </a>
                        <a
                            href="/admin/expense"
                            class="p-4 bg-red-50 hover:bg-red-100 rounded-lg text-center transition"
                        >
                            <p class="font-semibold text-red-700">Expenses</p>
                            <p class="text-sm text-gray-600">Track Expenses</p>
                        </a>
                        <a
                            href="/admin/dashboard/expenses"
                            class="p-4 bg-indigo-50 hover:bg-indigo-100 rounded-lg text-center transition"
                        >
                            <p class="font-semibold text-indigo-700">Reports</p>
                            <p class="text-sm text-gray-600">View Details</p>
                        </a>
                    </div>
                </CardBox>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Monthly Trend Chart -->
                <CardBox>
                    <div class="h-80">
                        <Bar :data="trendData" :options="trendOptions" />
                    </div>
                </CardBox>

                <!-- Top Suppliers Chart -->
                <CardBox>
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <BaseIcon :path="mdiChartBar" class="mr-2 text-indigo-600" />
                        Top 10 Suppliers by Purchase Value
                    </h3>
                    <div class="h-64">
                        <Bar :data="topSuppliersData" :options="chartOptions" />
                    </div>
                </CardBox>
            </div>

            <!-- Charts Row 2 -->
            <div class="grid grid-cols-1 gap-6 mb-6">
                <!-- Expenses by Category Chart -->
                <CardBox>
                    <h3 class="text-lg font-semibold mb-4 flex items-center">
                        <BaseIcon :path="mdiChartBar" class="mr-2 text-red-600" />
                        Expenses by Category (Click to View)
                    </h3>
                    <div class="text-sm text-gray-500 mb-2">Click on any bar to open the filtered expense report for that category.</div>
                    <div :style="{ height: expenseChartHeight }">
                        <Bar :data="expenseCategoryData" :options="expenseCategoryOptions" />
                    </div>
                </CardBox>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
