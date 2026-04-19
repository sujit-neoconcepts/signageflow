<script setup>
import { Head, Link, useForm, usePage, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import { mdiFormatListBulleted, mdiPlusCircle, mdiRefreshCircle } from "@mdi/js";
import { computed, onBeforeMount, ref, reactive } from "vue";
import Multiselect from "vue-multiselect";
import "../../../css/vue-multiselect.css";
import { getTodayString } from "@/helpers/helpers";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import CardBoxModal from "@/components/CardBoxModal.vue";
import IconRounded from "@/components/IconRounded.vue";
import axios from "axios";

const quickClientModal = ref(false);
const quickClientForm = reactive({ cl_name: '', contact_person: '', cl_addr: '', cl_addr2: '', pincode: '', cl_phn: '', cl_email: '', cl_gst: '', active: {id: 1, label: 'Yes'} });
const quickClientErrors = reactive({ cl_name: '', cl_email: '' });
const quickClientLoading = ref(false);

const addClient = () => {
    quickClientForm.cl_name = '';
    quickClientForm.contact_person = '';
    quickClientForm.cl_addr = '';
    quickClientForm.cl_addr2 = '';
    quickClientForm.pincode = '';
    quickClientForm.cl_phn = '';
    quickClientForm.cl_email = '';
    quickClientForm.cl_gst = '';
    quickClientForm.active = {id: 1, label: 'Yes'};
    quickClientErrors.cl_name = '';
    quickClientErrors.cl_email = '';
    quickClientModal.value = true;
};

const refreshClients = () => {
    router.reload({ only: ['clients'], preserveState: true, preserveScroll: true });
};

const submitClientModal = async () => {
    quickClientErrors.cl_name = '';
    quickClientErrors.cl_email = '';
    if (!quickClientForm.cl_name.trim()) { quickClientErrors.cl_name = 'Client Name is required.'; return; }
    
    quickClientLoading.value = true;
    try {
        const response = await axios.post(route('client.store'), quickClientForm, {headers: {'Accept': 'application/json'}});
        const newClient = response.data.data;
        selectedClient.value = { id: newClient.id, label: newClient.cl_name };
        form.client_id = newClient.id;
        refreshClients();
        quickClientModal.value = false;
    } catch (e) {
        const errs = e.response?.data?.errors;
        if (errs?.cl_name) quickClientErrors.cl_name = errs.cl_name[0];
        if (errs?.cl_email) quickClientErrors.cl_email = errs.cl_email[0];
        if (!errs) alert("Error: " + (e.response?.data?.message || e.message));
    } finally {
        quickClientLoading.value = false;
    }
};

const props = defineProps({
    formdata: {
        type: Object,
        default: () => ({}),
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
    clients: {
        type: Array,
        default: () => [],
    },
    costSheetOptions: {
        type: Array,
        default: () => [],
    },
    munits: {
        type: Array,
        default: () => [],
    },
});

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");
const initializing = ref(true);
const previousProductType = ref("");
const selectedClient = ref(null);

const form = useForm({
    order_date: "",
    client_id: "",
    product_type: "",
    remark: "",
    transport_charge: "0",
    gst_percent: "18",
    roundoff: "0",
    items: [],
    enquiry_id: "",
});

const productTypeOptions = [
    { id: "signage", label: "Signage" },
    { id: "cabinet", label: "Cabinet" },
    { id: "letters", label: "Letters" },
];

const clientOptions = computed(() => {
    return (props.clients || []).map((client) => ({
        id: client.id,
        label: client.label || client.cl_name || client.name || "",
    }));
});

// Make costSheetOptions reactive so newly added items appear immediately
const localCostSheetOptions = ref([...props.costSheetOptions]);

const costSheetMap = computed(() => {
    const map = {};
    localCostSheetOptions.value.forEach((item) => {
        map[item.id] = item;
    });
    return map;
});

const filteredCostSheets = computed(() => {
    if (!form.product_type) return [];
    return localCostSheetOptions.value.filter((item) => item.prod_type === form.product_type);
});

// ─── Quick-Add Item Modal ───
const quickAddModal = ref(false);
const quickAddTargetLine = ref(null);
const quickAddSearchText = ref("");
const quickAddForm = reactive({ name: "", qty_unit: "", alt_units: "", rate: "0" });
const quickAddErrors = reactive({ name: "", qty_unit: "", alt_units: "" });
const quickAddLoading = ref(false);

const openQuickAdd = (line, searchText = "") => {
    quickAddTargetLine.value = line;
    quickAddForm.name = searchText;
    quickAddForm.qty_unit = "";
    quickAddForm.alt_units = "";
    quickAddForm.rate = "0";
    quickAddErrors.name = "";
    quickAddErrors.qty_unit = "";
    quickAddErrors.alt_units = "";
    quickAddModal.value = true;
};

const routeForProductType = (type) => {
    const map = { signage: "signageCostSheet", cabinet: "cabinetCostSheet", letters: "lettersCostSheet" };
    return map[type] ?? "signageCostSheet";
};

const submitQuickAdd = async () => {
    quickAddErrors.name = "";
    quickAddErrors.qty_unit = "";
    if (!quickAddForm.name.trim()) { quickAddErrors.name = "Name is required."; return; }
    if (!quickAddForm.qty_unit) { quickAddErrors.qty_unit = "Unit is required."; return; }

    quickAddLoading.value = true;
    try {
        const routeName = routeForProductType(form.product_type);
        const response = await axios.post(
            route(routeName + ".quickStore"),
            { name: quickAddForm.name.trim(), qty_unit: quickAddForm.qty_unit, alt_units: quickAddForm.alt_units, rate: quickAddForm.rate || 0 },
            { headers: { Accept: "application/json" } }
        );
        const newItem = response.data;
        localCostSheetOptions.value.push(newItem);
        // Auto-select in the row that triggered the modal
        if (quickAddTargetLine.value) {
            quickAddTargetLine.value.selected_cost_sheet = newItem;
            onItemChange(quickAddTargetLine.value);
        }
        quickAddModal.value = false;
    } catch (e) {
        const errs = e.response?.data?.errors;
        if (errs?.name) quickAddErrors.name = errs.name[0];
        if (errs?.qty_unit) quickAddErrors.qty_unit = errs.qty_unit[0];
        if (errs?.alt_units) quickAddErrors.alt_units = errs.alt_units[0];
        if (!errs) alert("Error: " + (e.response?.data?.message || e.message));
    } finally {
        quickAddLoading.value = false;
    }
};

const isDimensionUnit = (unit) => {
    const u = String(unit || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]/g, "");
    return u === "sqft" || u === "sqf" || u === "sqm";
};

const addItemLine = () => {
    form.items.push({
        cost_sheet_id: "",
        selected_cost_sheet: null,
        item_name: "",
        qty_unit: "",
        qty_mode: "direct",
        length: "",
        width: "",
        pieces: "",
        qty: "",
        rate: "",
        gst_percent: String(form.gst_percent || "18"),
    });
};

const removeItemLine = (index) => {
    form.items.splice(index, 1);
};

const recalculateQty = (line) => {
    if (line.qty_mode !== "dimension") return;
    const l = parseFloat(line.length || 0);
    const w = parseFloat(line.width || 0);
    const q = parseFloat(line.pieces || 0);
    if (l > 0 && w > 0 && q > 0) {
        let val = l * w * q;
        const u = String(line.qty_unit || "").trim().toLowerCase().replace(/[^a-z0-9]/g, "");
        if (u === "sqft" || u === "sqf") {
            val = val / 144;
        }
        line.qty = val.toFixed(4);
    } else {
        line.qty = "";
    }
};

const onItemChange = (line) => {
    const selected = line.selected_cost_sheet || null;
    line.cost_sheet_id = selected?.id ?? "";

    if (!selected) {
        line.item_name = "";
        line.qty_unit = "";
        line.qty_mode = "direct";
        line.length = "";
        line.width = "";
        line.pieces = "";
        line.qty = "";
        line.rate = "";
        return;
    }

    line.item_name = selected.name;
    line.qty_unit = selected.qty_unit;
    line.rate = Number(selected.rate || 0).toFixed(4);
    line.gst_percent = String(form.gst_percent || "18");
    line.qty_mode = isDimensionUnit(selected.qty_unit) ? "dimension" : "direct";

    if (line.qty_mode === "dimension") {
        line.length = line.length || "";
        line.width = line.width || "";
        line.pieces = line.pieces || "";
        recalculateQty(line);
    } else {
        line.length = "";
        line.width = "";
        line.pieces = "";
        line.qty = line.qty || "";
    }
};

const roundHalfUp = (num) => {
    return Number(Math.round(parseFloat(num || 0) + "e+2") + "e-2");
};

const lineTaxable = (line) => {
    const qty = parseFloat(line.qty || 0);
    const rate = parseFloat(line.rate || 0);
    return roundHalfUp(qty * rate);
};

const lineGstAmount = (line) => {
    const taxable = lineTaxable(line);
    const gst = parseFloat(line.gst_percent || 0);
    return roundHalfUp((taxable * gst) / 100);
};

const lineTotal = (line) => {
    return roundHalfUp(lineTaxable(line) + lineGstAmount(line));
};

const itemsTaxableTotal = computed(() => {
    return form.items.reduce((sum, line) => sum + lineTaxable(line), 0).toFixed(2);
});

const itemsGstTotal = computed(() => {
    return form.items.reduce((sum, line) => sum + lineGstAmount(line), 0).toFixed(2);
});

const transportGst = computed(() => {
    const transport = parseFloat(form.transport_charge || 0);
    const gst = parseFloat(form.gst_percent || 0);
    return roundHalfUp((transport * gst) / 100);
});

const totalAmount = computed(() => {
    return (
        parseFloat(itemsTaxableTotal.value || 0) +
        parseFloat(itemsGstTotal.value || 0) +
        parseFloat(form.transport_charge || 0) +
        parseFloat(transportGst.value || 0) +
        parseFloat(form.roundoff || 0)
    ).toFixed(2);
});

const onProductTypeChange = () => {
    if (initializing.value) return;
    if (!form.product_type || form.product_type === previousProductType.value) return;
    form.items = [];
    addItemLine();
    previousProductType.value = form.product_type;
};

const itemFieldError = (idx, field) => {
    return form.errors[`items.${idx}.${field}`] || "";
};

const itemRowHasError = (idx) => {
    return Object.keys(form.errors).some((key) => key.startsWith(`items.${idx}.`));
};


onBeforeMount(() => {
    form.order_date = props.formdata.order_date ?? getTodayString();
    form.client_id = props.formdata.client_id ?? "";
    selectedClient.value = clientOptions.value.find((client) => client.id == form.client_id) ?? null;
    form.product_type = props.formdata.product_type ?? "";
    previousProductType.value = form.product_type;
    form.remark = props.formdata.remark ?? "";
    form.transport_charge = String(props.formdata.transport_charge ?? "0");
    form.gst_percent = String(props.formdata.gst_percent ?? "18");
    form.roundoff = String(props.formdata.roundoff ?? "0");
    form.enquiry_id = props.formdata.enquiry_id ?? "";

    if (props.formdata.items && props.formdata.items.length > 0) {
        props.formdata.items.forEach((item) => {
            const selected = costSheetMap.value[item.cost_sheet_id];
            const qtyUnit = selected?.qty_unit ?? "";
            const mode = item.qty_mode ?? (isDimensionUnit(qtyUnit) ? "dimension" : "direct");

            form.items.push({
                cost_sheet_id: item.cost_sheet_id,
                selected_cost_sheet: selected ?? null,
                item_name: item.item_name ?? selected?.name ?? "",
                qty_unit: qtyUnit,
                qty_mode: mode,
                length: item.length ?? "",
                width: item.width ?? "",
                pieces: item.pieces ?? "",
                qty: item.qty ?? "",
                rate: Number(item.rate || selected?.rate || 0).toFixed(4),
                gst_percent: String(item.gst_percent ?? form.gst_percent ?? "18"),
            });
        });
    } else {
        addItemLine();
    }

    initializing.value = false;
});

const submitform = () => {
    const payload = {
        order_date: form.order_date,
        client_id: form.client_id,
        product_type: form.product_type,
        remark: form.remark,
        transport_charge: form.transport_charge,
        gst_percent: form.gst_percent,
        roundoff: form.roundoff,
        enquiry_id: form.enquiry_id || null,
        items: form.items.map((line) => ({
            cost_sheet_id: line.cost_sheet_id,
            qty: line.qty,
            rate: line.rate,
            gst_percent: line.gst_percent,
            length: line.qty_mode === "dimension" ? line.length : null,
            width: line.qty_mode === "dimension" ? line.width : null,
            pieces: line.qty_mode === "dimension" ? line.pieces : null,
        })),
    };

    form.transform(() => payload);

    if (props.formdata.id) {
        form.put(route("salesOrder.update", props.formdata.id));
    } else {
        form.post(route("salesOrder.store"));
    }
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
                    <Link :href="route('salesOrder.index')">
                        <BaseButton
                            class="m-2"
                            :icon="mdiFormatListBulleted"
                            color="success"
                            rounded-full
                            small
                            label="List Sales Order"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>

            <div
                v-if="props.formdata.enquiry_no"
                class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800 flex items-center gap-2"
            >
                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M13,9H11V7H13M13,17H11V11H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"/></svg>
                Creating Sales Order from Enquiry <strong>{{ props.formdata.enquiry_no }}</strong>. Items and client have been pre-filled.
            </div>

            <form @submit.prevent="submitform">
                <CardBox class="sticky top-10 z-20 shadow-md mb-4">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-sm font-medium">Order No</label>
                            <div class="w-full mt-1 border rounded px-3 py-2 bg-gray-100 dark:bg-slate-800">
                                {{ props.formdata.order_no ?? "Auto generated on save" }}
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Order Date</label>
                            <VueDatePicker
                                v-model="form.order_date"
                                input-class-name="text-gray-500 dark:!text-white shadow-sm text-sm !bg-white dark:!bg-slate-800 !border-gray-700"
                                :month-change-on-scroll="false"
                                :range="false"
                                :enable-time-picker="false"
                                arrow-navigation
                                format="dd-MM-yyyy"
                                model-type="yyyy-MM-dd"
                                auto-apply
                                class="mt-1"
                            />
                            <div v-if="form.errors.order_date" class="text-red-500 text-xs mt-1">{{ form.errors.order_date }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium flex justify-between items-center mb-1">
                                <span>Client</span>
                                <div class="flex items-center gap-1">
                                    <IconRounded
                                        :icon="mdiPlusCircle"
                                        color="light"
                                        class="cursor-pointer"
                                        bg
                                        title="Add New Client"
                                        @click="addClient" 
                                    />
                                    <IconRounded
                                        :icon="mdiRefreshCircle"
                                        color="light"
                                        class="cursor-pointer"
                                        bg
                                        title="Refresh Clients"
                                        @click="refreshClients" 
                                    />
                                </div>
                            </label>
                            <Multiselect
                                v-model="selectedClient"
                                class="mt-1"
                                placeholder="Select Client"
                                track-by="id"
                                label="label"
                                select-label=""
                                :options="clientOptions"
                                @select="form.client_id = selectedClient?.id ?? ''"
                                @remove="form.client_id = ''"
                            />
                            <div v-if="form.errors.client_id" class="text-red-500 text-xs mt-1">{{ form.errors.client_id }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Product Type</label>
                            <select v-model="form.product_type" class="w-full mt-1 border rounded px-3 py-2" @change="onProductTypeChange">
                                <option value="">Select Product Type</option>
                                <option v-for="item in productTypeOptions" :key="item.id" :value="item.id">
                                    {{ item.label }}
                                </option>
                            </select>
                            <div v-if="form.errors.product_type" class="text-red-500 text-xs mt-1">{{ form.errors.product_type }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Transport Charge</label>
                            <input
                                v-model="form.transport_charge"
                                class="w-full mt-1 border rounded px-3 py-2 text-right"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <div v-if="form.errors.transport_charge" class="text-red-500 text-xs mt-1">{{ form.errors.transport_charge }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Order GST (%)</label>
                            <input
                                v-model="form.gst_percent"
                                class="w-full mt-1 border rounded px-3 py-2 text-right"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <div v-if="form.errors.gst_percent" class="text-red-500 text-xs mt-1">{{ form.errors.gst_percent }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Roundoff (+/-)</label>
                            <input
                                v-model="form.roundoff"
                                class="w-full mt-1 border rounded px-3 py-2 text-right"
                                type="number"
                                step="0.01"
                            />
                            <div v-if="form.errors.roundoff" class="text-red-500 text-xs mt-1">{{ form.errors.roundoff }}</div>
                        </div>

                        <div class="lg:col-span-1">
                            <label class="text-sm font-medium">Remark</label>
                            <input v-model="form.remark" class="w-full mt-1 border rounded px-3 py-2" type="text" />
                        </div>
                    </div>
                </CardBox>

                <CardBox class="mt-4">
                    <div class="mb-2">
                        <h3 class="font-semibold">Items</h3>
                    </div>

                    <div v-if="form.errors.items" class="text-red-500 text-xs mb-2">{{ form.errors.items }}</div>
                    <div>
                        <table class="w-full text-sm border">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border p-2 text-left">Item</th>
                                    <th class="border p-2 text-left">Unit</th>
                                    <th class="border p-2 text-left">L</th>
                                    <th class="border p-2 text-left">W</th>
                                    <th class="border p-2 text-left">Q</th>
                                    <th class="border p-2 text-left">Qty</th>
                                    <th class="border p-2 text-left">Rate</th>
                                    <th class="border p-2 text-left">GST %</th>
                                    <th class="border p-2 text-right">Taxable</th>
                                    <th class="border p-2 text-right">GST Amt</th>
                                    <th class="border p-2 text-right">Line Total</th>
                                    <th class="border p-2 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, idx) in form.items" :key="idx" :class="{ 'bg-red-50': itemRowHasError(idx) }">
                                    <td class="border p-2 min-w-52">
                                        <div class="flex items-center gap-1">
                                            <Multiselect
                                                v-model="line.selected_cost_sheet"
                                                placeholder="Select Item"
                                                track-by="id"
                                                label="label"
                                                select-label=""
                                                :disabled="!form.product_type"
                                                :options="filteredCostSheets"
                                                @select="onItemChange(line)"
                                                @remove="onItemChange(line)"
                                                class="flex-1"
                                            />
                                            <button
                                                type="button"
                                                :disabled="!form.product_type"
                                                class="flex-shrink-0 w-7 h-7 rounded bg-green-600 text-white font-bold text-lg leading-none flex items-center justify-center hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                                title="Add new item"
                                                @click="openQuickAdd(line, line.selected_cost_sheet?.label ?? '')"
                                            >+</button>
                                        </div>
                                        <div v-if="itemFieldError(idx, 'cost_sheet_id')" class="text-red-500 text-xs mt-1">
                                            {{ itemFieldError(idx, "cost_sheet_id") }}
                                        </div>
                                    </td>
                                    <td class="border p-2">{{ line.qty_unit }}</td>
                                    <td class="border p-2">
                                        <input
                                            v-model="line.length"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="w-20 border rounded px-2 py-1 text-right"
                                            :disabled="line.qty_mode !== 'dimension'"
                                            @input="recalculateQty(line)"
                                        />
                                        <div v-if="itemFieldError(idx, 'length')" class="text-red-500 text-xs mt-1">
                                            {{ itemFieldError(idx, "length") }}
                                        </div>
                                    </td>
                                    <td class="border p-2">
                                        <input
                                            v-model="line.width"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="w-20 border rounded px-2 py-1 text-right"
                                            :disabled="line.qty_mode !== 'dimension'"
                                            @input="recalculateQty(line)"
                                        />
                                        <div v-if="itemFieldError(idx, 'width')" class="text-red-500 text-xs mt-1">
                                            {{ itemFieldError(idx, "width") }}
                                        </div>
                                    </td>
                                    <td class="border p-2">
                                        <input
                                            v-model="line.pieces"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="w-20 border rounded px-2 py-1 text-right"
                                            :disabled="line.qty_mode !== 'dimension'"
                                            @input="recalculateQty(line)"
                                        />
                                        <div v-if="itemFieldError(idx, 'pieces')" class="text-red-500 text-xs mt-1">
                                            {{ itemFieldError(idx, "pieces") }}
                                        </div>
                                    </td>
                                    <td class="border p-2">
                                        <input
                                            v-model="line.qty"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="w-24 border rounded px-2 py-1 text-right"
                                            :disabled="line.qty_mode === 'dimension'"
                                        />
                                        <div v-if="itemFieldError(idx, 'qty')" class="text-red-500 text-xs mt-1">
                                            {{ itemFieldError(idx, "qty") }}
                                        </div>
                                    </td>
                                    <td class="border p-2">
                                        <input
                                            v-model="line.rate"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="w-24 border rounded px-2 py-1 text-right"
                                        />
                                        <div v-if="itemFieldError(idx, 'rate')" class="text-red-500 text-xs mt-1">
                                            {{ itemFieldError(idx, "rate") }}
                                        </div>
                                    </td>
                                    <td class="border p-2">
                                        <input
                                            v-model="line.gst_percent"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-20 border rounded px-2 py-1 text-right"
                                        />
                                        <div v-if="itemFieldError(idx, 'gst_percent')" class="text-red-500 text-xs mt-1">
                                            {{ itemFieldError(idx, "gst_percent") }}
                                        </div>
                                    </td>
                                    <td class="border p-2 text-right">{{ lineTaxable(line).toFixed(2) }}</td>
                                    <td class="border p-2 text-right">{{ lineGstAmount(line).toFixed(2) }}</td>
                                    <td class="border p-2 text-right">{{ lineTotal(line).toFixed(2) }}</td>
                                    <td class="border p-2 text-center">
                                        <button type="button" class="text-red-600" @click="removeItemLine(idx)">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <BaseButton label="+ Add Item" type="button" color="success" small @click="addItemLine" />
                    </div>
                </CardBox>

                <CardBox class="mt-4">
                    <div class="grid grid-cols-1 lg:grid-cols-6 gap-3 text-sm font-semibold">
                        <div>Items Taxable Total: {{ itemsTaxableTotal }}</div>
                        <div>Items GST Total: {{ itemsGstTotal }}</div>
                        <div>Transport: {{ Number(form.transport_charge || 0).toFixed(2) }}</div>
                        <div>Transport GST: {{ Number(transportGst || 0).toFixed(2) }}</div>
                        <div>Roundoff: {{ Number(form.roundoff || 0).toFixed(2) }}</div>
                        <div>Total Amount: {{ totalAmount }}</div>
                    </div>
                </CardBox>

                <div class="mt-4 flex">
                    <BaseButton class="mr-2" type="submit" small :disabled="form.processing" color="info" :label="props.formdata.id ? 'Update' : 'Save'" />
                    <Link :href="route('salesOrder.index')">
                        <BaseButton type="reset" small color="info" outline label="Cancel" />
                    </Link>
                </div>
            </form>
        </SectionMain>

        <!-- Quick-Add Item Modal -->
        <CardBoxModal
            v-model="quickAddModal"
            title="Add New Item"
            hasCancel
            @confirm="submitQuickAdd"
        >
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium block mb-1">Item Name <span class="text-red-500">*</span></label>
                    <input
                        v-model="quickAddForm.name"
                        type="text"
                        class="w-full border rounded px-3 py-2 text-sm"
                        placeholder="Enter item name"
                    />
                    <div v-if="quickAddErrors.name" class="text-red-500 text-xs mt-1">{{ quickAddErrors.name }}</div>
                </div>
                <div>
                    <label class="text-sm font-medium block mb-1">Unit <span class="text-red-500">*</span></label>
                    <select v-model="quickAddForm.qty_unit" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">-- Select Unit --</option>
                        <option v-for="unit in props.munits" :key="unit" :value="unit">{{ unit }}</option>
                    </select>
                    <div v-if="quickAddErrors.qty_unit" class="text-red-500 text-xs mt-1">{{ quickAddErrors.qty_unit }}</div>
                </div>
                <div>
                    <label class="text-sm font-medium block mb-1">Alt Units</label>
                    <select v-model="quickAddForm.alt_units" class="w-full border rounded px-3 py-2 text-sm">
                        <option value="">-- Select Alt Unit --</option>
                        <option v-for="unit in props.munits" :key="unit" :value="unit">{{ unit }}</option>
                    </select>
                    <div v-if="quickAddErrors.alt_units" class="text-red-500 text-xs mt-1">{{ quickAddErrors.alt_units }}</div>
                </div>
                <div>
                    <label class="text-sm font-medium block mb-1">Default Rate</label>
                    <input
                        v-model="quickAddForm.rate"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full border rounded px-3 py-2 text-sm"
                        placeholder="0.00"
                    />
                </div>
                <div v-if="quickAddLoading" class="text-sm text-blue-600">Saving...</div>
            </div>
        </CardBoxModal>

        <!-- Client Quick Add Modal -->
        <CardBoxModal v-model="quickClientModal" title="Add New Client" hasCancel @confirm="submitClientModal">
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium">Client Name *</label>
                    <input v-model="quickClientForm.cl_name" class="w-full mt-1 border rounded px-3 py-2 text-sm" placeholder="Enter Client Name" />
                    <div v-if="quickClientErrors.cl_name" class="text-red-500 text-xs mt-1">{{ quickClientErrors.cl_name }}</div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Contact Person</label>
                        <input v-model="quickClientForm.contact_person" class="w-full mt-1 border rounded px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Phone</label>
                        <input v-model="quickClientForm.cl_phn" class="w-full mt-1 border rounded px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Email</label>
                        <input v-model="quickClientForm.cl_email" type="email" class="w-full mt-1 border rounded px-3 py-2 text-sm" />
                        <div v-if="quickClientErrors.cl_email" class="text-red-500 text-xs mt-1">{{ quickClientErrors.cl_email }}</div>
                    </div>
                    <div>
                        <label class="text-sm font-medium">GST</label>
                        <input v-model="quickClientForm.cl_gst" class="w-full mt-1 border rounded px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">Address</label>
                        <input v-model="quickClientForm.cl_addr" class="w-full mt-1 border rounded px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">Address Line 2</label>
                        <input v-model="quickClientForm.cl_addr2" class="w-full mt-1 border rounded px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Pincode</label>
                        <input v-model="quickClientForm.pincode" class="w-full mt-1 border rounded px-3 py-2 text-sm" />
                    </div>
                </div>
            </div>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
