<script setup>
import { Head, usePage, Link, router } from "@inertiajs/vue3";
import {
    mdiViewDashboard,
    mdiAlert,
    mdiPackageVariant,
    mdiFileDocumentOutline,
    mdiCashMultiple,
    mdiCurrencyUsd,
    mdiPipe,
    mdiCandle,
    mdiChartPie,
    mdiChartBar,
    mdiTrendingUp,
    mdiFactory,
    mdiAccountGroup,
    mdiTruckDelivery,
    mdiRefresh
} from "@mdi/js";

import { computed, ref, watch } from "vue";
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


import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import BaseIcon from "@/components/BaseIcon.vue";

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
    financialYear: Object,
    pipePpmData: Object,
    candelData: Object,
    salesData: Object,
    expensesData: Object,
    enquiryData: Object,
    monthlySales: Array,
    topClients: Array,
    topSuppliers: Array,
    startDate: String,
    endDate: String,
});

// Date range state - initialize from props (which come from URL or defaults)
const dateFrom = ref(props.startDate || props.financialYear?.start || '');
const dateTo = ref(props.endDate || props.financialYear?.end || '');

// Function to apply date filter
const applyDateFilter = () => {
    router.get(route('dashboard'), {
        start_date: dateFrom.value,
        end_date: dateTo.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Function to reset date filter to financial year defaults
const resetDateFilter = () => {
    dateFrom.value = props.financialYear?.start || '';
    dateTo.value = props.financialYear?.end || '';
    router.get(route('dashboard'), {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Computed property to build query params for links
const dateQueryParams = computed(() => ({
    start_date: dateFrom.value,
    end_date: dateTo.value,
}));

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");
const user_name = usePage().props.auth.user.name;

// Helper functions
const formatNumber = (num) => {
    return new Intl.NumberFormat("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num || 0);
};

const formatWeight = (weight) => {
    return new Intl.NumberFormat("en-IN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(weight || 0) + " kg";
};

// Chart configurations
const salesTrendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: "top",
            labels: {
                usePointStyle: true,
                boxWidth: 8,
                font: {
                    family: "'Inter', sans-serif",
                    size: 11
                }
            }
        },
        title: {
            display: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: false,
            },
            ticks: {
                font: {
                    family: "'Inter', sans-serif",
                    size: 11
                }
            }
        },
        x: {
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    family: "'Inter', sans-serif",
                    size: 11
                }
            }
        }
    },
    interaction: {
        mode: 'index',
        intersect: false,
    },
};

const salesTrendData = computed(() => ({
    labels: props.monthlySales?.map((item) => item.month) || [],
    datasets: [
        {
            label: "Tubes",
            data: props.monthlySales?.map((item) => item.tubes) || [],
            borderColor: "#3B82F6",
            backgroundColor: "rgba(59, 130, 246, 0.1)",
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
        },
        {
            label: "Sheets",
            data: props.monthlySales?.map((item) => item.sheets) || [],
            borderColor: "#10B981",
            backgroundColor: "rgba(16, 185, 129, 0.1)",
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
        },
        {
            label: "Coils",
            data: props.monthlySales?.map((item) => item.coils) || [],
            borderColor: "#F59E0B",
            backgroundColor: "rgba(245, 158, 11, 0.1)",
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
        },
        {
            label: "Strips",
            data: props.monthlySales?.map((item) => item.strips) || [],
            borderColor: "#EF4444",
            backgroundColor: "rgba(239, 68, 68, 0.1)",
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
        },
        {
            label: "Blusters",
            data: props.monthlySales?.map((item) => item.blusters) || [],
            borderColor: "#8B5CF6",
            backgroundColor: "rgba(139, 92, 246, 0.1)",
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
        },
        {
            label: "Black Tubes",
            data: props.monthlySales?.map((item) => item.blackTubes) || [],
            borderColor: "#6B7280",
            backgroundColor: "rgba(107, 114, 128, 0.1)",
            tension: 0.4,
            pointRadius: 2,
            pointHoverRadius: 4,
        },
    ],
}));

const topClientsData = computed(() => ({
    labels: props.topClients?.map((client) => client.name) || [],
    datasets: [
        {
            label: "Total Order Value",
            data: props.topClients?.map((client) => client.total_value) || [],
            backgroundColor: [
                "rgba(59, 130, 246, 0.8)",
                "rgba(16, 185, 129, 0.8)",
                "rgba(245, 158, 11, 0.8)",
                "rgba(239, 68, 68, 0.8)",
                "rgba(139, 92, 246, 0.8)",
                "rgba(236, 72, 153, 0.8)",
                "rgba(20, 184, 166, 0.8)",
                "rgba(249, 115, 22, 0.8)",
                "rgba(132, 204, 22, 0.8)",
                "rgba(99, 102, 241, 0.8)",
            ],
            borderRadius: 4,
            barThickness: 20,
        },
    ],
}));

const topSuppliersData = computed(() => ({
    labels: props.topSuppliers?.map((supplier) => supplier.name) || [],
    datasets: [
        {
            label: "Total Purchase Value",
            data: props.topSuppliers?.map((supplier) => supplier.total_value) || [],
            backgroundColor: [
                "rgba(239, 68, 68, 0.8)",
                "rgba(245, 158, 11, 0.8)",
                "rgba(16, 185, 129, 0.8)",
                "rgba(59, 130, 246, 0.8)",
                "rgba(139, 92, 246, 0.8)",
                "rgba(236, 72, 153, 0.8)",
                "rgba(20, 184, 166, 0.8)",
                "rgba(249, 115, 22, 0.8)",
                "rgba(132, 204, 22, 0.8)",
                "rgba(99, 102, 241, 0.8)",
            ],
            borderRadius: 4,
            barThickness: 20,
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
        title: {
            display: false,
        },
    },
    scales: {
        y: {
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    family: "'Inter', sans-serif",
                    size: 11
                }
            }
        },
        x: {
            grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: false,
            },
            ticks: {
                font: {
                    family: "'Inter', sans-serif",
                    size: 11
                }
            }
        }
    }
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Dashboard" />
        
        <div class="min-h-screen bg-gray-50/50 dark:bg-slate-900 transition-colors duration-300 p-6 lg:p-10">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 animate-fade-in-down">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight flex items-center gap-3">
                        <span class="bg-gradient-to-br from-blue-600 to-indigo-600 text-white p-2.5 rounded-xl shadow-lg shadow-blue-500/30">
                            <BaseIcon :path="mdiViewDashboard" size="28" />
                        </span>
                        Dashboard
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm font-medium ml-1">
                        Welcome back, <span class="text-indigo-600 dark:text-indigo-400 font-bold">{{ user_name }}</span>
                    </p>
                </div>
                
                <div class="mt-4 md:mt-0 flex items-center gap-4 bg-white dark:bg-slate-800 p-3 pr-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700/50 backdrop-blur-sm">
                    <div class="flex items-center gap-4">
                        <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400">
                            <BaseIcon :path="mdiTrendingUp" size="20" />
                        </div>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-4">
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-gray-400 uppercase font-bold tracking-wider whitespace-nowrap">From</label>
                                <input 
                                    type="date" 
                                    v-model="dateFrom"
                                    @change="applyDateFilter"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                />
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-gray-400 uppercase font-bold tracking-wider whitespace-nowrap">To</label>
                                <input 
                                    type="date" 
                                    v-model="dateTo"
                                    @change="applyDateFilter"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                />
                            </div>
                            <button
                                @click="resetDateFilter"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 text-gray-600 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg border border-gray-200 dark:border-slate-600 hover:border-indigo-300 dark:hover:border-indigo-700 transition-all duration-200 text-sm font-medium"
                                title="Reset to Financial Year"
                            >
                                <BaseIcon :path="mdiRefresh" size="16" />
                                <span class="hidden sm:inline">Reset</span>
                            </button>
                        </div>
                    </div>
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

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Stock Card -->
                <Link :href="route('dashboard.stock', dateQueryParams)" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-600 to-indigo-600 dark:bg-none dark:bg-slate-800 p-6 shadow-lg shadow-blue-500/30 dark:shadow-none border border-blue-500/30 dark:border-slate-700/50 hover:scale-[1.02] transition-all duration-300">
                    <div class="absolute top-0 right-0 -mt-6 -mr-6 w-32 h-32 bg-white/20 rounded-full blur-3xl group-hover:bg-white/30 transition-all duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex p-3 bg-white/20 dark:bg-blue-900/20 rounded-2xl text-white dark:text-blue-400 backdrop-blur-sm shadow-sm">
                                <BaseIcon :path="mdiPackageVariant" size="28" class="m-2 mt-2"/>
                                <h3 class="text-xl font-bold text-white ">Stock Overview</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white/20 dark:bg-slate-700 text-xs font-bold uppercase tracking-wider text-white dark:text-gray-400 backdrop-blur-sm">Inventory</span>
                        </div>
                        
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-blue-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-blue-200 dark:bg-blue-500 shadow-[0_0_10px_rgba(191,219,254,0.5)]"></div>
                                    <span class="text-sm font-medium text-blue-50 dark:text-gray-300">Pipe</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(stockOverview?.tubes?.available_weight) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-blue-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-cyan-200 dark:bg-cyan-500 shadow-[0_0_10px_rgba(165,243,252,0.5)]"></div>
                                    <span class="text-sm font-medium text-blue-50 dark:text-gray-300">Sheet</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(stockOverview?.sheets?.available_weight) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-blue-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-indigo-200 dark:bg-indigo-500 shadow-[0_0_10px_rgba(199,210,254,0.5)]"></div>
                                    <span class="text-sm font-medium text-blue-50 dark:text-gray-300">Coil</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(stockOverview?.coils?.available_weight) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-blue-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-purple-200 dark:bg-purple-500 shadow-[0_0_10px_rgba(233,213,255,0.5)]"></div>
                                    <span class="text-sm font-medium text-blue-50 dark:text-gray-300">Candel</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatNumber(stockOverview?.blusters?.available_qty) }} pcs</span>
                            </div>
                        </div>
                    </div>
                </Link>

                <!-- Expenses Card -->
                <Link :href="route('dashboard.expenses', dateQueryParams)" v-if="expensesData" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-red-500 to-rose-600 dark:bg-none dark:bg-slate-800 p-6 shadow-lg shadow-red-500/30 dark:shadow-none border border-red-500/30 dark:border-slate-700/50 hover:scale-[1.02] transition-all duration-300">
                    <div class="absolute top-0 right-0 -mt-6 -mr-6 w-32 h-32 bg-white/20 rounded-full blur-3xl group-hover:bg-white/30 transition-all duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex p-3 bg-white/20 dark:bg-red-900/20 rounded-2xl text-white dark:text-red-400 backdrop-blur-sm shadow-sm">
                                <BaseIcon :path="mdiCashMultiple" size="28" class="m-2 mt-2"/>
                                <h3 class="text-xl font-bold text-white">Expense and Deposit</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white/20 dark:bg-slate-700 text-xs font-bold uppercase tracking-wider text-white dark:text-gray-400 backdrop-blur-sm">Finance</span>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-red-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-purple-200 dark:bg-purple-500 shadow-[0_0_10px_rgba(233,213,255,0.5)]"></div>
                                    <span class="text-sm font-medium text-red-50 dark:text-gray-300">Purchase Value</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">₹ {{ formatNumber(expensesData.total_purchase_value || 0) }}</span>
                            </div>
                            <Link :href="route('dashboard.expenses', dateQueryParams)" class="flex justify-between items-center p-3 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-red-900/20 transition-colors border border-white/10 dark:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-purple-200 dark:bg-purple-500 shadow-[0_0_10px_rgba(233,213,255,0.5)]"></div>
                                <span class="text-sm font-medium text-red-50 dark:text-gray-300">Outward Value</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">₹ {{ formatNumber(expensesData.total_outward_value || 0) }}</span>
                            </Link>
                            <Link :href="route('dashboard.expenses', dateQueryParams)" class="flex justify-between items-center p-3 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-red-900/20 transition-colors border border-white/10 dark:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-rose-200 dark:bg-rose-500 shadow-[0_0_10px_rgba(254,205,211,0.5)]"></div>
                                    <span class="text-sm font-medium text-red-50 dark:text-gray-300">Expense</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">₹ {{ formatNumber(expensesData.total_expense || 0) }}</span>
                            </Link>
                            <Link :href="route('dashboard.expenses', dateQueryParams)" class="flex justify-between items-center p-3 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-red-900/20 transition-colors border border-white/10 dark:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-emerald-200 dark:bg-emerald-500 shadow-[0_0_10px_rgba(167,243,208,0.5)]"></div>
                                    <span class="text-sm font-medium text-red-50 dark:text-gray-300">Deposit</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">₹ {{ formatNumber(expensesData.total_deposit || 0) }}</span>
                            </Link>
                        </div>
                    </div>
                </Link>

                <!-- Sale Enquiry Card -->
                <div v-if="enquiryData" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-teal-500 to-emerald-500 dark:bg-none dark:bg-slate-800 p-6 shadow-lg shadow-teal-500/30 dark:shadow-none border border-teal-500/30 dark:border-slate-700/50 transition-all duration-300">
                    <div class="absolute top-0 right-0 -mt-6 -mr-6 w-32 h-32 bg-white/20 rounded-full blur-3xl group-hover:bg-white/30 transition-all duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex p-3 bg-white/20 dark:bg-teal-900/20 rounded-2xl text-white dark:text-teal-400 backdrop-blur-sm shadow-sm">
                                <BaseIcon :path="mdiFileDocumentOutline" size="28" class="m-2 mt-2"/>
                                <h3 class="text-xl font-bold text-white">Sale Enquiry</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white/20 dark:bg-slate-700 text-xs font-bold uppercase tracking-wider text-white dark:text-gray-400 backdrop-blur-sm">CRM</span>
                        </div>
                        
                        <div class="space-y-3">
                            <Link :href="route('dashboard.enquiry', dateQueryParams)" class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-teal-900/20 transition-colors border border-white/10 dark:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-teal-200 dark:bg-teal-500 shadow-[0_0_10px_rgba(153,246,228,0.5)]"></div>
                                    <span class="text-sm font-medium text-teal-50 dark:text-gray-300">Total</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatNumber(enquiryData.total || 0) }}</span>
                            </Link>
                            <Link :href="route('dashboard.enquiry', { ...dateQueryParams, status: 0 })" class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-teal-900/20 transition-colors border border-white/10 dark:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-amber-200 dark:bg-amber-500 shadow-[0_0_10px_rgba(253,230,138,0.5)]"></div>
                                    <span class="text-sm font-medium text-teal-50 dark:text-gray-300">Pending</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatNumber(enquiryData.pending || 0) }}</span>
                            </Link>
                            <Link :href="route('dashboard.enquiry', { ...dateQueryParams, status: 2 })" class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-teal-900/20 transition-colors border border-white/10 dark:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-green-200 dark:bg-green-500 shadow-[0_0_10px_rgba(187,247,208,0.5)]"></div>
                                    <span class="text-sm font-medium text-teal-50 dark:text-gray-300">Sold</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatNumber(enquiryData.sold || 0) }}</span>
                            </Link>
                            <Link :href="route('dashboard.enquiry', { ...dateQueryParams, status: 3 })" class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-teal-900/20 transition-colors border border-white/10 dark:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-red-200 dark:bg-red-500 shadow-[0_0_10px_rgba(254,202,202,0.5)]"></div>
                                    <span class="text-sm font-medium text-teal-50 dark:text-gray-300">Closed</span>
                                </div>
                                <span class="font-bold text-white font-mono text-sm">{{ formatNumber(enquiryData.closed || 0) }}</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Pipe PPM Card -->
                <Link :href="route('dashboard.pipePpm', dateQueryParams)" v-if="pipePpmData" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-orange-500 to-amber-500 dark:bg-none dark:bg-slate-800 p-6 shadow-lg shadow-orange-500/30 dark:shadow-none border border-orange-500/30 dark:border-slate-700/50 hover:scale-[1.02] transition-all duration-300">
                    <div class="absolute top-0 right-0 -mt-6 -mr-6 w-32 h-32 bg-white/20 rounded-full blur-3xl group-hover:bg-white/30 transition-all duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex p-3 bg-white/20 dark:bg-orange-900/20 rounded-2xl text-white dark:text-orange-400 backdrop-blur-sm shadow-sm">
                                <BaseIcon :path="mdiFactory" size="28" class="m-2 mt-2"/>
                                <h3 class="text-xl font-bold text-white">Pipe PPM</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white/20 dark:bg-slate-700 text-xs font-bold uppercase tracking-wider text-white dark:text-gray-400 backdrop-blur-sm">Production</span>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-orange-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <span class="text-sm font-medium text-orange-50 dark:text-gray-300">Coil Purchase</span>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(pipePpmData.coil_purchase?.total || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-orange-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <span class="text-sm font-medium text-orange-50 dark:text-gray-300">Slitting</span>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(pipePpmData.slitting?.total || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-orange-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <span class="text-sm font-medium text-orange-50 dark:text-gray-300">Tube Making</span>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(pipePpmData.tube_making?.total || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-orange-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <span class="text-sm font-medium text-orange-50 dark:text-gray-300">Tube Polishing</span>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(pipePpmData.tube_polishing?.total || 0) }}</span>
                            </div>
                        </div>
                    </div>
                </Link>

                <!-- Candel Card -->
                <Link :href="route('dashboard.candel', dateQueryParams)" v-if="candelData" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-600 to-fuchsia-600 dark:bg-none dark:bg-slate-800 p-6 shadow-lg shadow-purple-500/30 dark:shadow-none border border-purple-500/30 dark:border-slate-700/50 hover:scale-[1.02] transition-all duration-300">
                    <div class="absolute top-0 right-0 -mt-6 -mr-6 w-32 h-32 bg-white/20 rounded-full blur-3xl group-hover:bg-white/30 transition-all duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex p-3 bg-white/20 dark:bg-purple-900/20 rounded-2xl text-white dark:text-purple-400 backdrop-blur-sm shadow-sm">
                                <BaseIcon :path="mdiCandle" size="28" class="m-2 mt-2"/>
                                <h3 class="text-xl font-bold text-white">Candel</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white/20 dark:bg-slate-700 text-xs font-bold uppercase tracking-wider text-white dark:text-gray-400 backdrop-blur-sm">Production</span>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-purple-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <span class="text-sm font-medium text-purple-50 dark:text-gray-300">Tube Transferred</span>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(candelData.total_weight || 0) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-purple-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <span class="text-sm font-medium text-purple-50 dark:text-gray-300">Bluster Produced</span>
                                <span class="font-bold text-white font-mono text-sm">{{ formatNumber(candelData.total_qty || 0) }} pcs</span>
                            </div>
                        </div>
                    </div>
                </Link>

                <!-- Sales Card -->
                <Link :href="route('dashboard.sales', dateQueryParams)" v-if="salesData" class="group relative overflow-hidden rounded-3xl bg-gradient-to-br from-green-600 to-emerald-600 dark:bg-none dark:bg-slate-800 p-6 shadow-lg shadow-green-500/30 dark:shadow-none border border-green-500/30 dark:border-slate-700/50 hover:scale-[1.02] transition-all duration-300">
                    <div class="absolute top-0 right-0 -mt-6 -mr-6 w-32 h-32 bg-white/20 rounded-full blur-3xl group-hover:bg-white/30 transition-all duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex p-3 bg-white/20 dark:bg-green-900/20 rounded-2xl text-white dark:text-green-400 backdrop-blur-sm shadow-sm">
                                <BaseIcon :path="mdiCurrencyUsd" size="28" class="m-2 mt-2"/>
                                <h3 class="text-xl font-bold text-white">Sales</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white/20 dark:bg-slate-700 text-xs font-bold uppercase tracking-wider text-white dark:text-gray-400 backdrop-blur-sm">Revenue</span>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-2.5 rounded-xl bg-white/10 dark:bg-slate-700/30 hover:bg-white/20 dark:hover:bg-green-900/20 transition-colors border border-white/10 dark:border-slate-700">
                                <span class="text-sm font-medium text-green-50 dark:text-gray-300">HO Sales</span>
                                <span class="font-bold text-white font-mono text-sm">{{ formatWeight(salesData.ho_sales_weight || 0) }}</span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Charts Section -->
            <div v-if="monthlySales && topClients">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Sales Trend Chart -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-none border border-gray-100 dark:border-slate-700/50">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400">
                                    <BaseIcon :path="mdiChartBar" size="20" />
                                </div>
                                Sales Trend
                            </h3>
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">Last 12 Months</span>
                        </div>
                        <div class="h-80 w-full">
                            <Line :data="salesTrendData" :options="salesTrendOptions" />
                        </div>
                    </div>

                    <!-- Top Clients Chart -->
                    <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-none border border-gray-100 dark:border-slate-700/50">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                <div class="p-2 bg-purple-50 dark:bg-purple-900/30 rounded-lg text-purple-600 dark:text-purple-400">
                                    <BaseIcon :path="mdiAccountGroup" size="20" />
                                </div>
                                Top Clients
                            </h3>
                            <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">By Order Value</span>
                        </div>
                        <div class="h-80 w-full">
                            <Bar :data="topClientsData" :options="{ ...chartOptions, indexAxis: 'y' }" />
                        </div>
                    </div>
                </div>

                <!-- Top Suppliers Chart -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-none border border-gray-100 dark:border-slate-700/50 mb-6" v-if="topSuppliers && topSuppliers.length > 0">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg text-emerald-600 dark:text-emerald-400">
                                <BaseIcon :path="mdiTruckDelivery" size="20" />
                            </div>
                            Top Suppliers
                        </h3>
                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wider">By Purchase Value</span>
                    </div>
                    <div class="h-80 w-full">
                        <Bar
                            :data="topSuppliersData"
                            :options="{ 
                                ...chartOptions, 
                                indexAxis: 'y',
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </LayoutAuthenticated>
</template>

<style scoped>
.animate-fade-in-down {
    animation: fadeInDown 0.5s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
