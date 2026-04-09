<script setup>
import { Head, Link } from "@inertiajs/vue3";
import {
    mdiViewDashboard,
    mdiCashMultiple,
    mdiArrowLeft,
    mdiCart,
    mdiTruckDelivery,
} from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseIcon from "@/components/BaseIcon.vue";

const props = defineProps({
    purchaseExpenses: Array,
    outwardExpenses: Array,
    expensesByCategory: Array,
    depositsByCategory: Array,
    startDate: String,
    endDate: String,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat("en-IN", {
        style: "currency",
        currency: "INR",
        minimumFractionDigits: 2,
    }).format(value || 0);
};

const openPurchaseList = (sgroup) => {
    const url = route("purchase.index", {
        filter: {
            groupinfo_sname: sgroup,
        },
        perPage: 10000,
    });
    window.location.href = url;
};

const openExpenseList = (expCate) => {
    const url = route("expense.index", {
        filter: {
            exp_date_start: props.startDate,
            exp_date_end: props.endDate,
            amt_type: 'Expense',
            exp_cate: expCate,
        },
        perPage: 10000,
    });
    window.location.href = url;
};

const openDepositList = (expCate) => {
    const url = route("expense.index", {
        filter: {
            exp_date_start: props.startDate,
            exp_date_end: props.endDate,
            amt_type: 'Deposit',
            exp_cate: expCate,
        },
        perPage: 10000,
    });
    window.location.href = url;
};

const openOutwardList = (sgroup) => {
    const url = route("outward.index", {
        filter: {
            out_date_start: props.startDate,
            out_date_end: props.endDate,
            groupinfo_sname: sgroup,
        },
        perPage: 10000,
    });
    window.location.href = url;
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Expenses Details" />
        <SectionMain>
            <!-- Breadcrumb -->
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-6">
                <Link
                    :href="route('dashboard')"
                    class="hover:text-blue-600 transition-colors flex items-center"
                >
                    <BaseIcon :path="mdiViewDashboard" size="16" class="mr-1" />
                    Dashboard
                </Link>
                <span class="text-gray-400">/</span>
                <span class="text-gray-800 dark:text-gray-200 font-medium"
                    >Expenses Details</span
                >
            </div>

            <div class="flex items-center justify-between mb-6">
                <SectionTitleLineWithButton
                    :icon="mdiCashMultiple"
                    title="Expenses Details (Sub Group Wise)"
                    main
                    class="mb-0"
                >
                </SectionTitleLineWithButton>
                <Link
                    :href="route('dashboard')"
                    class="bg-gray-100 hover:bg-gray-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg transition-colors flex items-center text-sm font-medium"
                >
                    <BaseIcon :path="mdiArrowLeft" size="18" class="mr-1" />
                    Back
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                <!-- Purchase Expenses -->
                <div
                    class="bg-white dark:bg-slate-900 rounded-2xl shadow-lg overflow-hidden border border-gray-100 dark:border-slate-800 hover:shadow-xl transition-shadow duration-300"
                >
                    <div
                        class="bg-gradient-to-r from-red-500 to-rose-600 p-4 flex justify-between items-center"
                    >
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <div
                                class="bg-white/20 p-2 rounded-lg mr-3 backdrop-blur-sm"
                            >
                                <BaseIcon :path="mdiCart" class="text-white" />
                            </div>
                            Purchase Expenses
                        </h3>
                        <div
                            class="bg-white/20 backdrop-blur-md text-white px-3 py-1 rounded-full text-sm font-semibold shadow-sm"
                        >
                            Total:
                            {{
                                formatCurrency(
                                    purchaseExpenses.reduce(
                                        (a, b) => a + Number(b.total_value || 0),
                                        0
                                    )
                                )
                            }}
                        </div>
                    </div>
                    <div class="p-6">
                        <div
                            v-if="purchaseExpenses.length === 0"
                            class="text-center text-gray-400 py-8 flex flex-col items-center"
                        >
                            <BaseIcon
                                :path="mdiCart"
                                size="48"
                                class="mb-2 opacity-20"
                            />
                            <span>No purchase data available</span>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(item, index) in purchaseExpenses"
                                :key="index"
                                class="flex justify-between items-center p-3 bg-gray-50 dark:bg-slate-800/50 rounded-xl hover:bg-red-50 dark:hover:bg-slate-800 transition-colors group border border-transparent hover:border-red-100 dark:hover:border-slate-700 cursor-pointer"
                                @click="openPurchaseList(item.sgroup)"
                            >
                                <div class="flex items-center">
                                    <span
                                        class="w-2 h-2 rounded-full bg-red-500 mr-3"
                                    ></span>
                                    <span
                                        class="font-semibold text-gray-700 dark:text-gray-300"
                                        >{{ item.sgroup }}</span
                                    >
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900 dark:text-white font-mono text-lg group-hover:text-red-600 dark:group-hover:text-red-400 transition-colors">
                                        {{ formatCurrency(item.total_value) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outward Expenses -->
                <div
                    class="bg-white dark:bg-slate-900 rounded-2xl shadow-lg overflow-hidden border border-gray-100 dark:border-slate-800 hover:shadow-xl transition-shadow duration-300"
                >
                    <div
                        class="bg-gradient-to-r from-orange-500 to-amber-600 p-4 flex justify-between items-center"
                    >
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <div
                                class="bg-white/20 p-2 rounded-lg mr-3 backdrop-blur-sm"
                            >
                                <BaseIcon :path="mdiTruckDelivery" class="text-white" />
                            </div>
                            Outward Expenses
                        </h3>
                        <div
                            class="bg-white/20 backdrop-blur-md text-white px-3 py-1 rounded-full text-sm font-semibold shadow-sm"
                        >
                            Total:
                            {{
                                formatCurrency(
                                    outwardExpenses.reduce(
                                        (a, b) => a + Number(b.total_value || 0),
                                        0
                                    )
                                )
                            }}
                        </div>
                    </div>
                    <div class="p-6">
                        <div
                            v-if="outwardExpenses.length === 0"
                            class="text-center text-gray-400 py-8 flex flex-col items-center"
                        >
                            <BaseIcon
                                :path="mdiTruckDelivery"
                                size="48"
                                class="mb-2 opacity-20"
                            />
                            <span>No outward data available</span>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(item, index) in outwardExpenses"
                                :key="index"
                                @click="openOutwardList(item.sgroup)"
                                class="flex justify-between items-center p-3 bg-gray-50 dark:bg-slate-800/50 rounded-xl hover:bg-orange-50 dark:hover:bg-slate-800 transition-colors group border border-transparent hover:border-orange-100 dark:hover:border-slate-700 cursor-pointer"
                            >
                                <div class="flex items-center">
                                    <span
                                        class="w-2 h-2 rounded-full bg-orange-500 mr-3"
                                    ></span>
                                    <span
                                        class="font-semibold text-gray-700 dark:text-gray-300"
                                        >{{ item.sgroup }}</span
                                    >
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900 dark:text-white font-mono text-lg group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                                        {{ formatCurrency(item.total_value) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expenses and Deposits by Category -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Expenses by Category -->
                <div
                    class="bg-white dark:bg-slate-900 rounded-2xl shadow-lg overflow-hidden border border-gray-100 dark:border-slate-800 hover:shadow-xl transition-shadow duration-300"
                >
                    <div
                        class="bg-gradient-to-r from-rose-500 to-pink-600 p-4 flex justify-between items-center"
                    >
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <div
                                class="bg-white/20 p-2 rounded-lg mr-3 backdrop-blur-sm"
                            >
                                <BaseIcon :path="mdiCashMultiple" class="text-white" />
                            </div>
                            Expenses by Category
                        </h3>
                        <div
                            class="bg-white/20 backdrop-blur-md text-white px-3 py-1 rounded-full text-sm font-semibold shadow-sm"
                        >
                            Total:
                            {{
                                formatCurrency(
                                    expensesByCategory.reduce(
                                        (a, b) => a + Number(b.total_value || 0),
                                        0
                                    )
                                )
                            }}
                        </div>
                    </div>
                    <div class="p-6">
                        <div
                            v-if="expensesByCategory.length === 0"
                            class="text-center text-gray-400 py-8 flex flex-col items-center"
                        >
                            <BaseIcon
                                :path="mdiCashMultiple"
                                size="48"
                                class="mb-2 opacity-20"
                            />
                            <span>No expense data available</span>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(item, index) in expensesByCategory"
                                :key="index"
                                @click="openExpenseList(item.exp_cate)"
                                class="flex justify-between items-center p-3 bg-gray-50 dark:bg-slate-800/50 rounded-xl hover:bg-rose-50 dark:hover:bg-slate-800 transition-colors group border border-transparent hover:border-rose-100 dark:hover:border-slate-700 cursor-pointer"
                            >
                                <div class="flex items-center">
                                    <span
                                        class="w-2 h-2 rounded-full bg-rose-500 mr-3"
                                    ></span>
                                    <span
                                        class="font-semibold text-gray-700 dark:text-gray-300"
                                        >{{ item.exp_cate }}</span
                                    >
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900 dark:text-white font-mono text-lg group-hover:text-rose-600 dark:group-hover:text-rose-400 transition-colors">
                                        {{ formatCurrency(item.total_value) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deposits by Category -->
                <div
                    class="bg-white dark:bg-slate-900 rounded-2xl shadow-lg overflow-hidden border border-gray-100 dark:border-slate-800 hover:shadow-xl transition-shadow duration-300"
                >
                    <div
                        class="bg-gradient-to-r from-emerald-500 to-teal-600 p-4 flex justify-between items-center"
                    >
                        <h3 class="text-xl font-bold text-white flex items-center">
                            <div
                                class="bg-white/20 p-2 rounded-lg mr-3 backdrop-blur-sm"
                            >
                                <BaseIcon :path="mdiCashMultiple" class="text-white" />
                            </div>
                            Deposits by Category
                        </h3>
                        <div
                            class="bg-white/20 backdrop-blur-md text-white px-3 py-1 rounded-full text-sm font-semibold shadow-sm"
                        >
                            Total:
                            {{
                                formatCurrency(
                                    depositsByCategory.reduce(
                                        (a, b) => a + Number(b.total_value || 0),
                                        0
                                    )
                                )
                            }}
                        </div>
                    </div>
                    <div class="p-6">
                        <div
                            v-if="depositsByCategory.length === 0"
                            class="text-center text-gray-400 py-8 flex flex-col items-center"
                        >
                            <BaseIcon
                                :path="mdiCashMultiple"
                                size="48"
                                class="mb-2 opacity-20"
                            />
                            <span>No deposit data available</span>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(item, index) in depositsByCategory"
                                :key="index"
                                @click="openDepositList(item.exp_cate)"
                                class="flex justify-between items-center p-3 bg-gray-50 dark:bg-slate-800/50 rounded-xl hover:bg-emerald-50 dark:hover:bg-slate-800 transition-colors group border border-transparent hover:border-emerald-100 dark:hover:border-slate-700 cursor-pointer"
                            >
                                <div class="flex items-center">
                                    <span
                                        class="w-2 h-2 rounded-full bg-emerald-500 mr-3"
                                    ></span>
                                    <span
                                        class="font-semibold text-gray-700 dark:text-gray-300"
                                        >{{ item.exp_cate }}</span
                                    >
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-gray-900 dark:text-white font-mono text-lg group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                                        {{ formatCurrency(item.total_value) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
