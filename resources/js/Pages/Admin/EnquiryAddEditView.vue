<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import { mdiFormatListBulleted } from "@mdi/js";
import { computed, onBeforeMount, ref } from "vue";
import Multiselect from "vue-multiselect";
import "../../../css/vue-multiselect.css";
import { getTodayString } from "@/helpers/helpers";
import axios from "axios";
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

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
});

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");
const initializing = ref(true);
const previousProductType = ref("");
const selectedClient = ref(null);

const form = useForm({
    enquiry_date: "",
    client_id: "",
    product_type: "",
    remark: "",
    transport_charge: "0",
    gst_percent: "0",
    items: [],
    custom_items: [],
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

const costSheetMap = computed(() => {
    const map = {};
    props.costSheetOptions.forEach((item) => {
        map[item.id] = item;
    });
    return map;
});

const filteredCostSheets = computed(() => {
    if (!form.product_type) return [];
    return props.costSheetOptions.filter((item) => item.prod_type === form.product_type);
});

const isDimensionUnit = (unit) => {
    const u = String(unit || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]/g, "");
    return u === "sqft" ||  u === "sqf" || u === "sqm";
};

// ─── Cost Sheet Items ───
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
        rate: "0",
        gst_percent: "0",
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
        line.rate = "0";
        return;
    }
    
    line.item_name = selected.name;
    line.qty_unit = selected.qty_unit;
    line.rate = "0";
    line.gst_percent = "0";
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

// ─── Custom Items ───
const addCustomItemLine = () => {
    form.custom_items.push({
        item_name: "",
        qty: "",
    });
};

const removeCustomItemLine = (index) => {
    form.custom_items.splice(index, 1);
};

const customItemFieldError = (idx, field) => {
    return form.errors[`custom_items.${idx}.${field}`] || "";
};

// ─── Unused but kept for reactivity ───
const lineTaxable = (line) => {
    const qty = parseFloat(line.qty || 0);
    const rate = parseFloat(line.rate || 0);
    return +(qty * rate).toFixed(2);
};

const lineGstAmount = (line) => {
    const taxable = lineTaxable(line);
    const gst = parseFloat(line.gst_percent || 0);
    return +((taxable * gst) / 100).toFixed(2);
};

const lineTotal = (line) => {
    return +(lineTaxable(line) + lineGstAmount(line)).toFixed(2);
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
    return +((transport * gst) / 100).toFixed(2);
});

const totalAmount = computed(() => {
    return (
        parseFloat(itemsTaxableTotal.value || 0) +
        parseFloat(itemsGstTotal.value || 0) +
        parseFloat(form.transport_charge || 0) +
        parseFloat(transportGst.value || 0)
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
    form.enquiry_date = props.formdata.enquiry_date ?? getTodayString();
    form.client_id = props.formdata.client_id ?? "";
    selectedClient.value = clientOptions.value.find((client) => client.id == form.client_id) ?? null;
    form.product_type = props.formdata.product_type ?? "";
    previousProductType.value = form.product_type;
    form.remark = props.formdata.remark ?? "";
    form.transport_charge = "0";
    form.gst_percent = "0";

    // Load cost sheet items
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
                rate: "0",
                gst_percent: "0",
            });
        });
    } else {
        addItemLine();
    }

    // Load custom items
    if (props.formdata.custom_items && props.formdata.custom_items.length > 0) {
        props.formdata.custom_items.forEach((ci) => {
            form.custom_items.push({
                item_name: ci.item_name ?? "",
                qty: ci.qty ?? "",
            });
        });
    }

    // Load existing files (edit mode)
    if (props.formdata.existing_files && props.formdata.existing_files.length > 0) {
        existingFiles.value = props.formdata.existing_files;
    }

    initializing.value = false;
});

// ─── File Upload ───
const existingFiles = ref([]);
const newFiles = ref([]);
const fileInputRef = ref(null);
const dragOver = ref(false);
const uploadingFiles = ref(false);
const uploadProgress = ref(0);    // 0-100
const uploadSpeed = ref('');       // e.g. "2.3 MB/s"
const uploadEta = ref('');         // e.g. "~12s remaining"
const fileUploadError = ref("");
const uploadSuccess = ref(false);

const formatBytes = (bytes) => {
    if (!bytes) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
};

const onFileInput = (e) => {
    const files = Array.from(e.target.files || []);
    files.forEach((f) => newFiles.value.push(f));
    e.target.value = "";
    uploadNewFiles();
};

const onDrop = (e) => {
    dragOver.value = false;
    const files = Array.from(e.dataTransfer.files || []);
    files.forEach((f) => newFiles.value.push(f));
    uploadNewFiles();
};

const removeNewFile = (idx) => {
    newFiles.value.splice(idx, 1);
};

const tempFiles = ref([]);

const deleteExistingFile = async (file, idx) => {
    if (!confirm(`Delete "${file.original_name}"?`)) return;
    try {
        if (file.is_temp) {
            existingFiles.value.splice(idx, 1);
            tempFiles.value = tempFiles.value.filter(tf => tf.stored_name !== file.stored_name);
        } else {
            await axios.delete(`/admin/enquiry-file/${file.id}`);
            existingFiles.value.splice(idx, 1);
        }
    } catch {
        alert("Failed to delete file.");
    }
};

const uploadNewFiles = async () => {
    if (newFiles.value.length === 0) return;

    uploadingFiles.value = true;
    uploadProgress.value = 0;
    uploadSpeed.value = '';
    uploadEta.value = '';
    fileUploadError.value = '';
    uploadSuccess.value = false;

    const fd = new FormData();
    newFiles.value.forEach((f) => fd.append('files[]', f));

    const startTime = Date.now();

    try {
        const url = props.formdata.id ? `/admin/enquiry/${props.formdata.id}/files` : '/admin/enquiry-temp-files';
        const res = await axios.post(url, fd, {
            onUploadProgress: (e) => {
                if (!e.total) return;
                const pct = Math.round((e.loaded / e.total) * 100);
                uploadProgress.value = pct;

                const elapsed = (Date.now() - startTime) / 1000;
                const bytesPerSec = e.loaded / elapsed;
                const remaining = (e.total - e.loaded) / bytesPerSec;

                uploadSpeed.value = formatBytes(Math.round(bytesPerSec)) + '/s';
                uploadEta.value = remaining > 0 ? `~${Math.ceil(remaining)}s remaining` : 'finalising...';
            },
        });

        if (props.formdata.id) {
            res.data.files.forEach((f) => existingFiles.value.push({ ...f }));
        } else {
            res.data.files.forEach((f) => tempFiles.value.push({ ...f }));
            res.data.files.forEach((f) => existingFiles.value.push({ ...f, is_temp: true }));
        }

        newFiles.value = [];
        uploadProgress.value = 100;
        uploadSuccess.value = true;
        uploadSpeed.value = '';
        uploadEta.value = '';
    } catch (err) {
        const errData = err?.response?.data;
        if (errData?.errors) {
            fileUploadError.value = Object.values(errData.errors).flat().join(' ');
        } else {
            fileUploadError.value = errData?.message || `Upload failed (HTTP ${err?.response?.status ?? 'network error'}).`;
        }
    } finally {
        uploadingFiles.value = false;
    }
};

const submitform = () => {
    const payload = {
        enquiry_date: form.enquiry_date,
        client_id: form.client_id,
        product_type: form.product_type,
        remark: form.remark,
        transport_charge: form.transport_charge,
        gst_percent: form.gst_percent,
        items: form.items.map((line) => ({
            cost_sheet_id: line.cost_sheet_id,
            qty: line.qty,
            rate: line.rate,
            gst_percent: line.gst_percent,
            length: line.qty_mode === "dimension" ? line.length : null,
            width: line.qty_mode === "dimension" ? line.width : null,
            pieces: line.qty_mode === "dimension" ? line.pieces : null,
        })),
        custom_items: form.custom_items
            .filter((ci) => ci.item_name.trim() !== "")
            .map((ci) => ({
                item_name: ci.item_name,
                qty: ci.qty,
            })),
        temp_files: tempFiles.value, // Send temporary files IDs explicitly
    };

    form.transform(() => payload);

    if (props.formdata.id) {
        form.put(route("enquiry.update", props.formdata.id));
    } else {
        form.post(route("enquiry.store"));
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
                    <Link :href="route('enquiry.index')">
                        <BaseButton
                            class="m-2"
                            :icon="mdiFormatListBulleted"
                            color="success"
                            rounded-full
                            small
                            label="List Enquiry"
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

            <form @submit.prevent="submitform">
                <CardBox>
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-sm font-medium">Order No</label>
                            <div class="w-full mt-1 border rounded px-3 py-2 bg-gray-100 dark:bg-slate-800">
                                {{ props.formdata.enquiry_no ?? "Auto generated on save" }}
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Order Date</label>
                            <input v-model="form.enquiry_date" class="w-full mt-1 border rounded px-3 py-2" type="date" />
                            <div v-if="form.errors.enquiry_date" class="text-red-500 text-xs mt-1">{{ form.errors.enquiry_date }}</div>
                        </div>

                        <div>
                            <label class="text-sm font-medium">Client</label>
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

                        <div class="lg:col-span-2">
                            <label class="text-sm font-medium">Remark</label>
                            <input v-model="form.remark" class="w-full mt-1 border rounded px-3 py-2" type="text" />
                        </div>
                    </div>
                </CardBox>

                <!-- Cost Sheet Items -->
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
                                    <th class="border p-2 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, idx) in form.items" :key="idx" :class="{ 'bg-red-50': itemRowHasError(idx) }">
                                    <td class="border p-2 min-w-52">
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
                                        />
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

                <!-- Custom Items Section -->
                <CardBox class="mt-4">
                    <div class="mb-2">
                        <h3 class="font-semibold">Custom Items</h3>
                    </div>

                    <div v-if="form.errors.custom_items" class="text-red-500 text-xs mb-2">{{ form.errors.custom_items }}</div>
                    <div>
                        <table class="w-full text-sm border">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border p-2 text-left">Item Name</th>
                                    <th class="border p-2 text-left" style="width: 120px;">Qty</th>
                                    <th class="border p-2 text-center" style="width: 80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(ci, cidx) in form.custom_items" :key="'ci-' + cidx">
                                    <td class="border p-2">
                                        <input
                                            v-model="ci.item_name"
                                            type="text"
                                            class="w-full border rounded px-2 py-1"
                                            placeholder="Type item name"
                                        />
                                        <div v-if="customItemFieldError(cidx, 'item_name')" class="text-red-500 text-xs mt-1">
                                            {{ customItemFieldError(cidx, "item_name") }}
                                        </div>
                                    </td>
                                    <td class="border p-2">
                                        <input
                                            v-model="ci.qty"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="w-full border rounded px-2 py-1 text-right"
                                            placeholder="Qty"
                                        />
                                        <div v-if="customItemFieldError(cidx, 'qty')" class="text-red-500 text-xs mt-1">
                                            {{ customItemFieldError(cidx, "qty") }}
                                        </div>
                                    </td>
                                    <td class="border p-2 text-center">
                                        <button type="button" class="text-red-600" @click="removeCustomItemLine(cidx)">Remove</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <BaseButton label="+ Add Custom Item" type="button" color="success" small @click="addCustomItemLine" />
                    </div>
                </CardBox>

                <!-- File Attachments -->
                <CardBox class="mt-4">
                    <div class="mb-3">
                        <h3 class="font-semibold">Attachments</h3>
                        <p class="text-xs text-gray-500 mt-1">Upload any files — PDF, PSD, JPG, PNG, CDR, AI, CorelDraw, etc. No size limit.</p>
                    </div>

                    <!-- Drop zone -->
                    <div
                        class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors"
                        :class="dragOver ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-blue-400'"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="onDrop"
                        @click="fileInputRef.click()"
                    >
                        <svg class="mx-auto mb-2 text-gray-400" style="width:36px;height:36px" viewBox="0 0 24 24"><path fill="currentColor" d="M14,2H6C4.89,2 4,2.89 4,4V20C4,21.11 4.89,22 6,22H18C19.11,22 20,21.11 20,20V8L14,2M18,20H6V4H13V9H18V20M12,19L8,15H10.5V12H13.5V15H16L12,19Z"/></svg>
                        <p class="text-sm text-gray-500">Drag &amp; drop files here, or <span class="text-blue-600 underline font-medium">click to browse</span></p>
                        <p class="text-xs text-gray-400 mt-1">Any file type accepted</p>
                        <input ref="fileInputRef" type="file" multiple class="hidden" @change="onFileInput" />
                    </div>

                    <!-- Queued new files -->
                    <div v-if="newFiles.length > 0" class="mt-3">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Queued for upload ({{ newFiles.length }} file{{ newFiles.length > 1 ? 's' : '' }}):</p>
                        <div class="space-y-1">
                            <div
                                v-for="(f, idx) in newFiles"
                                :key="'new-' + idx"
                                class="flex items-center justify-between bg-amber-50 dark:bg-amber-900/20 border border-amber-200 rounded px-3 py-1.5 text-sm"
                            >
                                <span class="truncate flex-1 font-medium">{{ f.name }}</span>
                                <span class="text-gray-400 text-xs mx-3 flex-shrink-0">{{ formatBytes(f.size) }}</span>
                                <button type="button" class="text-red-500 hover:text-red-700 text-xs flex-shrink-0" :disabled="uploadingFiles" @click="removeNewFile(idx)">✕</button>
                            </div>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="uploadingFiles" class="mt-4">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span>Uploading... {{ uploadProgress }}%</span>
                            <span>{{ uploadSpeed }}&nbsp;&nbsp;{{ uploadEta }}</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-3 rounded-full transition-all duration-300"
                                :class="uploadProgress < 100 ? 'bg-blue-500' : 'bg-green-500'"
                                :style="{ width: uploadProgress + '%' }"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Do not close or navigate away during upload.</p>
                    </div>

                    <!-- Upload success message -->
                    <div v-if="uploadSuccess && !uploadingFiles" class="mt-3 text-xs text-green-600 font-medium">✅ Files uploaded successfully!</div>

                    <!-- Error -->
                    <div v-if="fileUploadError" class="mt-2 text-xs text-red-600">⚠ {{ fileUploadError }}</div>

                    <!-- Existing uploaded files -->
                    <div v-if="existingFiles.length > 0" class="mt-4">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Uploaded files ({{ existingFiles.length }}):</p>
                        <div class="space-y-1">
                            <div
                                v-for="(f, idx) in existingFiles"
                                :key="'ef-' + (f.id || idx)"
                                class="flex items-center justify-between bg-green-50 dark:bg-green-900/20 border border-green-200 rounded px-3 py-1.5 text-sm"
                            >
                                <a v-if="!f.is_temp" :href="f.download_url" target="_blank" class="truncate flex-1 text-blue-600 hover:underline font-medium">{{ f.original_name }}</a>
                                <span v-else class="truncate flex-1 text-gray-600 font-medium">{{ f.original_name }}</span>
                                <span class="text-gray-400 text-xs mx-3 flex-shrink-0">{{ formatBytes(f.file_size) }}</span>
                                <button type="button" class="text-red-500 hover:text-red-700 text-xs flex-shrink-0" @click="deleteExistingFile(f, idx)">Delete</button>
                            </div>
                        </div>
                    </div>

                </CardBox>

                <div class="mt-4 flex">
                    <BaseButton class="mr-2" type="submit" small :disabled="form.processing" color="info" :label="props.formdata.id ? 'Update' : 'Save'" />
                    <Link :href="route('enquiry.index')">
                        <BaseButton type="reset" small color="info" outline label="Cancel" />
                    </Link>
                </div>
            </form>
        </SectionMain>
    </LayoutAuthenticated>
</template>
