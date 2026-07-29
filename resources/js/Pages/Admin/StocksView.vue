<script setup>
import { Head, Link, usePage, useForm, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiFileEdit,
    mdiTrashCan,
    mdiTransitTransfer,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import BaseIcon from "@/components/BaseIcon.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Table from "@/components/DataTable/Table.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onMounted, ref } from "vue";
import { can } from '@/utils/permissions';
import axios from "axios";
import { formatDisplayDate } from "@/helpers/helpers";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");
const props = defineProps({
    resourceData: {
        type: Object,
        default: () => ({}),
    },
    can: {
        type: Object,
        default: () => ({}),
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});
const filterList = computed(
    () => usePage().props.queryBuilderProps.default.filters
);
const filterValue = (list) => (list && list["value"]) ?? "";
const getFilterValue = (key) => {
    const filter = filterList.value?.find(f => f.key === key);
    return filterValue(filter);
};
const filteroOption = (list) => {
    const temp = [];
    for (var key in list) {
        if (key != "") {
            temp.push(list[key]);
        }
    }
    return temp;
};

const selectedRowsele = ref([]);
const selectedRows = (event) => {
    selectedRowsele.value = event;
};
const maxitemforpartial = 2;
const form = useForm({
    superviser: "",
    superviser_from: "",
    location: "",
    location_from: "",
    stocks: [],
    quantities: {}, // Add this line for storing quantities
});

const isModalTransferActive = ref(false);
const tansferStock = () => {
    form.stocks = selectedRowsele.value;
    form.superviser_from = getFilterValue("pur_incharge");
    form.location_from = getFilterValue("pur_loc");

    // Only validate quantities if doing partial transfer (5 or fewer items)
    if (selectedRowsele.value.length <= maxitemforpartial) {
        const invalidQuantities = form.stocks.some((stockId) => {
            const qty = parseFloat(form.quantities[stockId]);
            const maxQty = getMaxQuantity(stockId);
            return !qty || qty <= 0 || qty > maxQty;
        });

        if (invalidQuantities) {
            useToast().error("Please check all quantities", { duration: 7000 });
            return;
        }
    }

    if (
        form.superviser == "" ||
        form.location == "" ||
        form.stocks.length == 0
    ) {
        useToast().error("Please fill all required fields", { duration: 7000 });
        return;
    }

    form.post(route(props.resourceNeo.resourceName + ".transfer_stock"), {
        onFinish: () => {
            selectedRowsele.value = [];
            form.quantities = {};
        },
    });
};
const delselect = ref(0);
const isModalDangerActive = ref(false);

const deleteRecord = () => {
    if (delselect.value != 0) {
        router.delete(
            route(props.resourceNeo.resourceName + ".destroy", delselect.value),
            {
                preserveScroll: true,
                resetOnSuccess: false,
                onFinish: () => {
                    delselect.value = 0;
                },
            }
        );
    }
};
onMounted(() => {
    if (message.value) {
        if (msg_type.value == "info") {
            useToast().info(message.value, { duration: 7000 });
        } else if (msg_type.value == "success") {
            useToast().success(message.value, { duration: 7000 });
        } else if (msg_type.value == "danger") {
            useToast().error(message.value, { duration: 7000 });
        } else {
            useToast().warning(message.value, { duration: 7000 });
        }
    }
});
const isModalTranActive = ref(false);
const transaDetailsLive = ref([]);
const selectedProductName = ref("");
const selectedStockItem = ref(null);

const showditail = async (pname, item = null) => {
    selectedProductName.value = pname;
    selectedStockItem.value = item;
    isModalTranActive.value = true;
    transaDetailsLive.value = [];
    await axios
        .post(route("stocks.detail"), {
            name: pname,
        })
        .then((response) => {
            transaDetailsLive.value = response.data;
        });
};

const transactionSummary = computed(() => {
    let openingQty = 0;
    let openingQtyAlt = 0;
    let purchaseQty = 0;
    let purchaseQtyAlt = 0;
    let outwardQty = 0;
    let outwardQtyAlt = 0;
    let mainUnit = "";
    let altUnit = "";

    (transaDetailsLive.value || []).forEach((row) => {
        if (row.pur_pr_detail_int) {
            if (!mainUnit && row.pur_unint_int) mainUnit = row.pur_unint_int;
            if (!altUnit && row.pur_unint_int_alt) altUnit = row.pur_unint_int_alt;

            if (row.entry_type == 1) {
                openingQty += Number(row.pur_qty_int || 0);
                openingQtyAlt += Number(row.pur_qty_int_alt || 0);
            } else {
                purchaseQty += Number(row.pur_qty_int || 0);
                purchaseQtyAlt += Number(row.pur_qty_int_alt || 0);
            }
        } else {
            if (!mainUnit && row.out_qty_unit) mainUnit = row.out_qty_unit;
            if (!altUnit && row.out_qty_unit_alt) altUnit = row.out_qty_unit_alt;

            outwardQty += Number(row.out_qty || 0);
            outwardQtyAlt += Number(row.out_qty_alt || 0);
        }
    });

    const balanceQty = openingQty + purchaseQty - outwardQty;
    const balanceQtyAlt = openingQtyAlt + purchaseQtyAlt - outwardQtyAlt;

    const formatNum = (num) => {
        return Number.isInteger(num) ? num : Number(num.toFixed(3));
    };

    return {
        openingQty: formatNum(openingQty),
        openingQtyAlt: formatNum(openingQtyAlt),
        purchaseQty: formatNum(purchaseQty),
        purchaseQtyAlt: formatNum(purchaseQtyAlt),
        outwardQty: formatNum(outwardQty),
        outwardQtyAlt: formatNum(outwardQtyAlt),
        balanceQty: formatNum(balanceQty),
        balanceQtyAlt: formatNum(balanceQtyAlt),
        mainUnit: mainUnit || selectedStockItem.value?.pr_int_unit || "",
        altUnit: altUnit || selectedStockItem.value?.pr_int_unit_alt || "",
        totalCount: transaDetailsLive.value.length,
    };
});

const getStockDetails = (stockId) => {
    const stock = props.resourceData.data.find((item) => item.id === stockId);
    return stock ? `${stock.pr_detail_int} (${stock.pr_int_unit})` : "";
};

const getMaxQuantity = (stockId) => {
    const stock = props.resourceData.data.find((item) => item.id === stockId);
    return stock ? stock.balsum : 0;
};
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="props.resourceNeo.resourceTitle" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="props.resourceNeo.iconPath"
                :title="props.resourceNeo.resourceTitle"
                main
            >
                <div class="flex">
                    <span v-for="exLink in props.resourceNeo.extraMainLinks">
                        <Link
                            :title="exLink.label"
                            :href="route(exLink.link)"
                            class="-mb-3 mr-2"
                        >
                            <BaseButton
                                class="m-2"
                                :icon="mdiPackageVariantPlus"
                                color="success"
                                rounded-full
                                small
                                :label="exLink.label"
                            /> </Link
                    ></span>
                    <Link
                        :href="
                            route(props.resourceNeo.resourceName + '.create')
                        "
                        v-if="
                            props.resourceNeo.actions.includes('c') &&
                            can(props.resourceNeo.resourceName + '_create')
                        "
                    >
                        <BaseButton
                            class="m-2"
                            :icon="mdiPackageVariantPlus"
                            color="success"
                            rounded-full
                            small
                            label="Add New"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>
            <CardBox has-table>
                <Table
                    :resource="resourceData"
                    :resourceNeo="resourceNeo"
                    :stickyHeader="!0"
                    @selectedRows="selectedRows($event)"
                >
                    <template #cell(pr_detail_int)="{ item: moduledata }">
                        <span
                            class="cursor-pointer hover:text-blue-600"
                            @click="showditail(moduledata.pr_detail_int, moduledata)"
                            >{{ moduledata.pr_detail_int }}
                        </span>
                    </template>

                    <template #customButtons>
                        <div
                            class="order-8 sm:order-2 mx-2 pt-1"
                            v-if="
                                selectedRowsele.length &&
                                (can('all') || can('stocks_list_for_all') ? getFilterValue('pur_incharge') != '' : true) &&
                                getFilterValue('pur_loc') != '' &&
                                getFilterValue('stock_date') == '' &&
                                can(props.resourceNeo.resourceName + '_transfer')
                            "
                        >
                            <button
                                type="button"
                                @click="isModalTransferActive = true"
                                :disabled="!selectedRowsele.length"
                                class="w-full border border-gray-200 dark:border-slate-700 text-opacity rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-50 hover:text-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                :class="{
                                    'bg-gray-200 dark:bg-slate-700 text-gray-400':
                                        !selectedRowsele.length,
                                    'bg-red-600 dark:bg-red-500 text-gray-100':
                                        selectedRowsele.length,
                                }"
                            >
                                <BaseIcon
                                    :path="mdiTransitTransfer"
                                    title="Reject"
                                    :size="20"
                                    h="h-5"
                                />
                            </button>
                        </div>
                    </template>
                    <template #cell(actions)="{ item: dItem }">
                        <span v-for="exLink in props.resourceNeo.extraLinks">
                            <Link
                                :title="exLink.label"
                                :href="route(exLink.link, dItem.id)"
                                class="-mb-3 mr-2"
                                v-if="dItem[exLink.key] == exLink.compvl"
                            >
                                <BaseButton
                                    color="info"
                                    :icon="exLink.icon"
                                    small
                                /> </Link
                        ></span>
                        <Link
                            :href="
                                route(
                                    props.resourceNeo.resourceName + '.edit',
                                    dItem.id
                                )
                            "
                            class="-mb-3 mr-2"
                            v-if="
                                props.resourceNeo.actions.indexOf('u') !== -1 && can(props.resourceNeo.resourceName + '_edit')
                            "
                        >
                            <BaseButton
                                color="info"
                                :icon="mdiFileEdit"
                                small
                            />
                        </Link>
                        <BaseButton
                            color="danger"
                            :icon="mdiTrashCan"
                            small
                            @click="
                                delselect = dItem.id;
                                isModalDangerActive = true;
                            "
                            v-if="
                                props.resourceNeo.actions.indexOf('d') !== -1 &&
                                can(props.resourceNeo.resourceName + '_delete')
                            "
                        />
                    </template>
                </Table>
            </CardBox>
        </SectionMain>
        <CardBoxModal
            v-model="isModalDangerActive"
            buttonLabel="Confirm"
            title="Please confirm"
            button="danger"
            has-cancel
            @confirm="deleteRecord"
        >
            <p>Are you sure to delete?</p>
        </CardBoxModal>
        <CardBoxModal
            v-model="isModalTranActive"
            buttonLabel="Ok"
            title="Item Transactions"
            full-width
            has-cancel
        >
            <div
                class="lg:max-h-[calc(100vh-280px)] overflow-y-auto relative"
            >
                <div class="mb-4 bg-gray-50 dark:bg-slate-800/80 p-4 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm space-y-4">
                    <div class="flex flex-wrap justify-between items-center gap-2 pb-3 border-b border-gray-200 dark:border-slate-700">
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-blue-600 dark:text-blue-400">
                                {{ selectedProductName }}
                            </h2>
                            <span v-if="selectedStockItem?.groupinfo_name" class="text-xs px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300 font-medium">
                                {{ selectedStockItem.groupinfo_name }}
                                <template v-if="selectedStockItem.groupinfo_sname"> &rsaquo; {{ selectedStockItem.groupinfo_sname }}</template>
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold px-3 py-1 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-full">
                                Total Transactions: {{ transactionSummary.totalCount }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 text-center">
                        <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-gray-100 dark:border-slate-800 shadow-xs">
                            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">Opening Qty</div>
                            <div class="text-base font-bold text-gray-800 dark:text-gray-100 mt-1">
                                {{ transactionSummary.openingQty }} <span v-if="transactionSummary.mainUnit" class="text-xs font-normal text-gray-500">{{ transactionSummary.mainUnit }}</span>
                            </div>
                            <div v-if="transactionSummary.openingQtyAlt" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                Alt: {{ transactionSummary.openingQtyAlt }} {{ transactionSummary.altUnit }}
                            </div>
                        </div>

                        <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-gray-100 dark:border-slate-800 shadow-xs">
                            <div class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Purchases (In)</div>
                            <div class="text-base font-bold text-emerald-700 dark:text-emerald-300 mt-1">
                                {{ transactionSummary.purchaseQty }} <span v-if="transactionSummary.mainUnit" class="text-xs font-normal text-gray-500">{{ transactionSummary.mainUnit }}</span>
                            </div>
                            <div v-if="transactionSummary.purchaseQtyAlt" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                Alt: {{ transactionSummary.purchaseQtyAlt }} {{ transactionSummary.altUnit }}
                            </div>
                        </div>

                        <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-gray-100 dark:border-slate-800 shadow-xs">
                            <div class="text-xs text-amber-600 dark:text-amber-400 font-medium">Outward (Out)</div>
                            <div class="text-base font-bold text-amber-700 dark:text-amber-300 mt-1">
                                {{ transactionSummary.outwardQty }} <span v-if="transactionSummary.mainUnit" class="text-xs font-normal text-gray-500">{{ transactionSummary.mainUnit }}</span>
                            </div>
                            <div v-if="transactionSummary.outwardQtyAlt" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                Alt: {{ transactionSummary.outwardQtyAlt }} {{ transactionSummary.altUnit }}
                            </div>
                        </div>

                        <div class="p-3 bg-blue-50/70 dark:bg-blue-950/40 rounded-lg border border-blue-200 dark:border-blue-800/50 shadow-xs">
                            <div class="text-xs text-blue-600 dark:text-blue-400 font-semibold">Net Balance Qty</div>
                            <div class="text-base font-bold text-blue-700 dark:text-blue-300 mt-1">
                                {{ transactionSummary.balanceQty }} <span v-if="transactionSummary.mainUnit" class="text-xs font-normal text-blue-500">{{ transactionSummary.mainUnit }}</span>
                            </div>
                            <div v-if="transactionSummary.balanceQtyAlt" class="text-xs text-blue-500/80 dark:text-blue-400/70 mt-0.5">
                                Alt: {{ transactionSummary.balanceQtyAlt }} {{ transactionSummary.altUnit }}
                            </div>
                        </div>

                        <div v-if="selectedStockItem?.stock_value !== undefined" class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-gray-100 dark:border-slate-800 shadow-xs">
                            <div class="text-xs text-purple-600 dark:text-purple-400 font-medium">Stock Value</div>
                            <div class="text-base font-bold text-purple-700 dark:text-purple-300 mt-1">
                                ₹{{ selectedStockItem.stock_value ?? '0.00' }}
                            </div>
                            <div v-if="selectedStockItem.stock_unit_price" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                Rate: ₹{{ selectedStockItem.stock_unit_price }}
                            </div>
                        </div>
                    </div>
                </div>
            <table>
                <thead>
                    <tr>
                        <td>Date</td>
                        <td>Type</td>
                        <td>Incharge</td>
                        <td>Location</td>
                    <td>Internal Qty</td>
                    <td>Internal Unit</td>
                    <td>Internal Qty Alt</td>
                    <td>Internal Unit Alt</td>
                    <td>Billed Qty</td>
                    <td>Qty Unit</td>
                    <td>Billed Qty Alt</td>
                    <td>Qty Unit Alt</td>
                    <td>Remark</td>
                </tr>
            </thead>
                <tbody>
                <tr v-for="(data, index) in transaDetailsLive">
                    <td>
                        {{
                            formatDisplayDate(data.pur_pr_detail_int
                                ? data.pur_date
                                : data.out_date)
                        }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int
                                ? data.entry_type
                                    ? "Opening"
                                    : "Purchase"
                                : "Out"
                        }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int
                                ? data.pur_incharge
                                : data.out_incharge
                        }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int ? data.pur_loc : data.out_loc
                        }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int
                                ? data.pur_qty_int
                                : data.out_qty
                        }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int
                                ? data.pur_unint_int
                                : data.out_qty_unit
                        }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int
                                ? data.pur_qty_int_alt
                                : data.out_qty_alt
                        }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int
                                ? data.pur_unint_int_alt
                                : data.out_qty_unit_alt
                        }}
                    </td>
                    <td>
                        {{ data.pur_pr_detail_int ? data.pur_qty : "" }}
                    </td>
                    <td>
                        {{ data.pur_pr_detail_int ? data.pur_unit : "" }}
                    </td>
                    <td>
                        {{ data.pur_pr_detail_int ? data.pur_qty_alt : "" }}
                    </td>
                    <td>
                        {{ data.pur_pr_detail_int ? data.pur_unit_alt : "" }}
                    </td>
                    <td>
                        {{
                            data.pur_pr_detail_int
                                ? data.remark
                                : data.out_remark
                        }}
                    </td>
                </tr>
                </tbody>
            </table>
            </div>
        </CardBoxModal>
        <CardBoxModal
            v-model="isModalTransferActive"
            buttonLabel="Confirm"
            title="Please confirm"
            button="info"
            has-cancel
            @confirm="tansferStock"
        >
            <p>
                {{
                    selectedRowsele.length <= maxitemforpartial
                        ? "Please specify quantities to transfer:"
                        : "Full quantity will be transferred (partial transfer available for " +
                          maxitemforpartial +
                          " or fewer items):"
                }}
            </p>
            <form @submit.prevent="tansferStock">
                <FormField label="To:superviser" help="">
                    <FormControl
                        v-model="form.superviser"
                        :options="
                            filteroOption(
                                (filterList.value?.find(f => f.key === 'pur_incharge')?.options) ?? []
                            )
                        "
                        placeholder=""
                    />
                </FormField>
                <FormField label="To:Location" help="">
                    <FormControl
                        v-model="form.location"
                        :options="
                            filteroOption(
                                (filterList.value?.find(f => f.key === 'pur_loc')?.options) ?? []
                            )
                        "
                        placeholder=""
                    />
                </FormField>

                <div
                    v-if="selectedRowsele.length <= maxitemforpartial"
                    v-for="stockId in selectedRowsele"
                    :key="stockId"
                >
                    <div class="border-b py-2">
                        <FormField :label="getStockDetails(stockId)">
                            <input
                                type="number"
                                v-model="form.quantities[stockId]"
                                class="w-full border rounded px-2 py-1"
                                :max="getMaxQuantity(stockId)"
                                min="0"
                                step="0.01"
                                required
                            />
                            <span class="text-sm text-gray-500">
                                Available: {{ getMaxQuantity(stockId) }}
                            </span>
                        </FormField>
                    </div>
                </div>
            </form>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
