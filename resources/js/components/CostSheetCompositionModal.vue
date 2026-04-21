<script setup>
import { ref, watch, onMounted, computed } from "vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import BaseButton from "@/components/BaseButton.vue";
import { mdiTrashCan, mdiPlus, mdiChevronUp, mdiChevronDown } from "@mdi/js";
import BaseIcon from "@/components/BaseIcon.vue";
import VueMultiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import axios from "axios";
import { useToast } from "vue-toast-notification";

// ─── Props / Emits ────────────────────────────────────────────────────────────
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    costSheetId: { type: [Number, String], default: null },
    costSheet: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

// ─── Section definitions ────────────────────────────────────────────────────
const SECTIONS = [
    { key: "raw_material", label: "Raw Materials",   color: "blue"   },
    { key: "signage",      label: "Signage",          color: "purple" },
    { key: "cabinet",      label: "Cabinet",          color: "orange" },
    { key: "letters",      label: "Letters",          color: "green"  },
];

// ─── State ──────────────────────────────────────────────────────────────────
const rows = ref({
    raw_material: [],
    signage:      [],
    cabinet:      [],
    letters:      [],
});

const consumableOptions = ref([]);   // for raw_material
const costSheetOptions  = ref({      // for signage / cabinet / letters
    signage: [],
    cabinet: [],
    letters: [],
});

const expandedSections = ref({
    raw_material: true,
    signage:      false,
    cabinet:      false,
    letters:      false,
});

const toggleSection = (section) => {
    expandedSections.value[section] = !expandedSections.value[section];
};

// ─── Computed: per-section totals & grand total ─────────────────────────────
const sectionTotal = (section) =>
    rows.value[section].reduce((acc, row) => {
        const sub      = (Number(row.unitPrice) || 0) * (Number(row.quantity) || 0);
        const withMarg = sub * (1 + (Number(row.margin) || 0) / 100);
        return acc + withMarg;
    }, 0);

const grandTotal = computed(() =>
    SECTIONS.reduce((acc, s) => acc + sectionTotal(s.key), 0)
);

const noOfUnit = computed(() => Number(props.costSheet?.no_of_unit) || 1);

const perUnitCost = computed(() => grandTotal.value / noOfUnit.value);

const perUnitSaleRate = computed(() => Number(props.costSheet?.rate) || 0);

const calculatedMarginPct = computed(() => {
    if (perUnitSaleRate.value <= 0) return 0;
    return ((perUnitSaleRate.value - perUnitCost.value) / perUnitSaleRate.value) * 100;
});

// ─── Load options ────────────────────────────────────────────────────────────
const fetchConsumableOptions = async () => {
    try {
        const { data } = await axios.get(route("consumableInternalName.options"));
        consumableOptions.value = data;
    } catch {
        useToast().error("Failed to load consumable options");
    }
};

const fetchCostSheetOptions = async () => {
    try {
        const types = ["signage", "cabinet", "letters"];
        for (const type of types) {
            const { data } = await axios.get(route("costSheetCompositions.options"), {
                params: { prod_type: type },
            });
            costSheetOptions.value[type] = data;
        }
    } catch (error) {
        console.error("Error fetching cost sheet options:", error);
    }
};

// ─── Load compositions ───────────────────────────────────────────────────────
const fetchCompositions = async () => {
    if (!props.costSheetId) return;
    try {
        const { data } = await axios.get(
            route("costSheetCompositions.index", props.costSheetId)
        );

        // Reset
        SECTIONS.forEach((s) => (rows.value[s.key] = []));

        data.forEach((c) => {
            const section = c.section || "raw_material";
            const consumable = c.consumable;
            const childCostSheet = c.child_cost_sheet || c.childCostSheet;

            if (section === "raw_material") {
                const basePrice = Number(consumable?.unitPrice || 0);
                const margin    = Number(consumable?.openStockMarginPercent || 0);
                rows.value.raw_material.push({
                    id:                          c.id,
                    selectedItem:                consumable,
                    consumable_internal_name_id: c.consumable_internal_name_id,
                    child_cost_sheet_id:         null,
                    unit:                        c.unit,
                    unitPrice:                   basePrice * (1 + margin / 100),
                    quantity:                    c.quantity,
                    margin:                      c.margin || 0,
                });
            } else {
                const child = childCostSheet;
                rows.value[section]?.push({
                    id:                          c.id,
                    selectedItem:                child,
                    consumable_internal_name_id: null,
                    child_cost_sheet_id:         c.child_cost_sheet_id,
                    unit:                        c.unit,
                    unitPrice:                   getCostSheetUnitPrice(child),
                    quantity:                    c.quantity,
                    margin:                      c.margin || 0,
                });
            }
        });

        // Ensure at least one empty row per section
        SECTIONS.forEach((s) => {
            if (rows.value[s.key].length === 0) addRow(s.key);
        });
    } catch {
        useToast().error("Failed to load compositions");
    }
};

// ─── Lifecycle & watchers ────────────────────────────────────────────────────
onMounted(() => {
    fetchConsumableOptions();
    ["signage", "cabinet", "letter"].forEach(fetchCostSheetOptions);
});

watch(isOpen, (newVal) => {
    if (newVal) {
        fetchCompositions();
    } else {
        SECTIONS.forEach((s) => (rows.value[s.key] = []));
    }
});

// ─── Row mutations ────────────────────────────────────────────────────────────
const addRow = (section) => {
    rows.value[section].push({
        id:                          null,
        selectedItem:                null,
        consumable_internal_name_id: null,
        child_cost_sheet_id:         null,
        unit:                        "",
        unitPrice:                   0,
        quantity:                    0,
        margin:                      0,
    });
};

const removeRow = (section, index) => {
    rows.value[section].splice(index, 1);
};

// ─── Selection handlers ──────────────────────────────────────────────────────
const handleRawMaterialChange = (row, selected) => {
    if (selected) {
        row.consumable_internal_name_id = selected.id;
        row.child_cost_sheet_id         = null;
        row.unit                        = selected.unitName;
        const base   = Number(selected.unitPrice || 0);
        const marg   = Number(selected.openStockMarginPercent || 0);
        row.unitPrice = base * (1 + marg / 100);
    } else {
        row.consumable_internal_name_id = null;
        row.unit      = "";
        row.unitPrice = 0;
    }
};

const handleCostSheetChange = (row, selected) => {
    if (selected) {
        row.child_cost_sheet_id         = selected.id;
        row.consumable_internal_name_id = null;
        row.unit                        = selected.qty_unit;
        row.unitPrice                   = getCostSheetUnitPrice(selected);
    } else {
        row.child_cost_sheet_id = null;
        row.unit                = "";
        row.unitPrice           = 0;
    }
};

// ─── Save ─────────────────────────────────────────────────────────────────────
const saveCompositions = async () => {
    const allRows = [];

    for (const s of SECTIONS) {
        for (const row of rows.value[s.key]) {
            if (s.key === "raw_material" && !row.consumable_internal_name_id) continue;
            if (s.key !== "raw_material" && !row.child_cost_sheet_id) continue;
            allRows.push({
                id:                          row.id,
                section:                     s.key,
                consumable_internal_name_id: row.consumable_internal_name_id ?? null,
                child_cost_sheet_id:         row.child_cost_sheet_id ?? null,
                unit:                        row.unit,
                quantity:                    row.quantity,
                margin:                      row.margin,
            });
        }
    }

    try {
        const { data } = await axios.post(
            route("costSheetCompositions.store", props.costSheetId),
            { 
                compositions: allRows,
                total_cost: grandTotal.value
            }
        );
        useToast().success(data.message);
        emit("saved");
        isOpen.value = false;
    } catch {
        useToast().error("Failed to save compositions");
    }
};

// ─── Helpers ──────────────────────────────────────────────────────────────────
const fmt = (n) => Number(n || 0).toFixed(2);
const rowTotal    = (row) => (Number(row.unitPrice) || 0) * (Number(row.quantity) || 0);
const rowWithMarg = (row) => rowTotal(row) * (1 + (Number(row.margin) || 0) / 100);

const sectionColorClasses = {
    blue:   { header: "bg-blue-600 text-white",   badge: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"   },
    purple: { header: "bg-purple-600 text-white",  badge: "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200" },
    orange: { header: "bg-orange-500 text-white",  badge: "bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200" },
    green:  { header: "bg-emerald-600 text-white", badge: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200" },
};

const getCostSheetUnitPrice = (item) => {
    const cost = Number(item?.total_cost || 0);
    const units = Number(item?.no_of_unit || 1);
    const perUnitCost = cost / units;
    // Fallback to sale rate if cost is 0
    return perUnitCost > 0 ? perUnitCost : Number(item?.rate || 0);
};
</script>

<template>
    <CardBoxModal
        v-model="isOpen"
        title="Manage Compositions"
        button="success"
        buttonLabel="Save All"
        :full-width="true"
        has-cancel
        @confirm="saveCompositions"
    >
        <div class="max-h-[70vh] overflow-y-auto pr-2 custom-scrollbar">
            <!-- ── Sticky Header & Summary Area ────────────────────────── -->
            <div class="sticky top-0 z-20 bg-white dark:bg-slate-900 pb-4 space-y-4">
                <!-- Target Product Header -->
                <div v-if="costSheet?.name"
                    class="p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg shadow-sm">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <div class="text-[10px] text-blue-600 dark:text-blue-400 uppercase font-extrabold tracking-wider mb-1">Target Product</div>
                            <div class="text-xl font-black text-gray-900 dark:text-white">{{ costSheet.name }}</div>
                        </div>
                        <div class="flex flex-wrap gap-6 text-right">
                            <div>
                                <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-extrabold tracking-wider mb-1">No of Units</div>
                                <div class="font-bold text-gray-700 dark:text-gray-300">{{ costSheet.no_of_unit }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-extrabold tracking-wider mb-1">Sale Unit</div>
                                <div class="font-bold text-gray-700 dark:text-gray-300">{{ costSheet.qty_unit }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-extrabold tracking-wider mb-1">Per Unit Sale Rate</div>
                                <div class="font-black text-2xl text-green-600 dark:text-green-400">₹ {{ Number(costSheet.rate || 0).toLocaleString() }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Section (Moved from bottom) -->
                <div class="rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 p-4 shadow-sm">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div>
                            <div class="text-[10px] text-gray-500 uppercase font-extrabold tracking-wider mb-1">Grand Total</div>
                            <div class="text-xl font-black text-gray-900 dark:text-white">₹ {{ fmt(grandTotal) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-500 uppercase font-extrabold tracking-wider mb-1">No of Units</div>
                            <div class="text-xl font-black text-gray-900 dark:text-white">{{ noOfUnit }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-500 uppercase font-extrabold tracking-wider mb-1">Per Unit Cost</div>
                            <div class="text-xl font-black text-orange-600 dark:text-orange-400">₹ {{ fmt(perUnitCost) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] text-gray-500 uppercase font-extrabold tracking-wider mb-1">
                                Margin (vs ₹{{ fmt(perUnitSaleRate) }})
                            </div>
                            <div :class="[
                                'text-xl font-black',
                                calculatedMarginPct >= 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'
                            ]">
                                {{ fmt(calculatedMarginPct) }}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 4 Sections ────────────────────────────────────────── -->
            <div class="space-y-6 mt-2">
                <div v-for="sec in SECTIONS" :key="sec.key" class="rounded-lg border border-gray-200 dark:border-gray-700">
                <!-- Section Header -->
                <div 
                    :class="['flex items-center justify-between px-4 py-2 cursor-pointer transition-colors', sectionColorClasses[sec.color].header]"
                    @click="toggleSection(sec.key)"
                >
                    <div class="flex items-center gap-2">
                        <BaseIcon :path="expandedSections[sec.key] ? mdiChevronUp : mdiChevronDown" />
                        <span class="font-bold text-sm tracking-wide">{{ sec.label }}</span>
                    </div>
                    <span :class="['text-xs font-bold px-2 py-0.5 rounded-full', sectionColorClasses[sec.color].badge]">
                        Total: ₹ {{ fmt(sectionTotal(sec.key)) }}
                    </span>
                </div>

                <!-- Section Content (Accordion Body) -->
                <div v-show="expandedSections[sec.key]">
                    <!-- Section Table -->
                    <div class="overflow-visible">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 min-w-[220px]">
                                    {{ sec.key === 'raw_material' ? 'Consumable Internal Name' : sec.label + ' Item' }}
                                </th>
                                <th class="px-3 py-2 w-20">Unit</th>
                                <th class="px-3 py-2 w-24 text-right">Unit Price</th>
                                <th class="px-3 py-2 w-24">Qty</th>
                                <th class="px-3 py-2 w-24 text-right">Sub Total</th>
                                <th class="px-3 py-2 w-24">Margin %</th>
                                <th class="px-3 py-2 w-28 text-right">With Margin</th>
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in rows[sec.key]" :key="idx"
                                class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">

                                <!-- ── Raw Material selector ── -->
                                <td v-if="sec.key === 'raw_material'" class="px-3 py-1.5">
                                    <VueMultiselect
                                        v-model="row.selectedItem"
                                        :options="consumableOptions"
                                        label="name"
                                        track-by="id"
                                        placeholder="Search consumable…"
                                        :searchable="true"
                                        :close-on-select="true"
                                        @update:modelValue="handleRawMaterialChange(row, $event)"
                                    >
                                        <template #option="{ option }">
                                            <div class="flex flex-col text-xs">
                                                <div class="font-bold">{{ option.name }}</div>
                                                <div class="flex justify-between text-gray-500 mt-0.5">
                                                    <span>{{ option.unitName }} / {{ option.unitAltName || '-' }}</span>
                                                    <span class="text-blue-600 font-semibold">
                                                        ₹{{ option.unitPrice }} (+{{ option.openStockMarginPercent }}%)
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </VueMultiselect>
                                </td>

                                <!-- ── Cost-Sheet selector ── -->
                                <td v-else class="px-3 py-1.5">
                                    <VueMultiselect
                                        v-model="row.selectedItem"
                                        :options="costSheetOptions[sec.key]"
                                        label="name"
                                        track-by="id"
                                        :placeholder="`Search ${sec.label} item…`"
                                        :searchable="true"
                                        :close-on-select="true"
                                        @update:modelValue="handleCostSheetChange(row, $event)"
                                    >
                                        <template #option="{ option }">
                                            <div class="flex flex-col text-xs">
                                                <div class="font-bold">{{ option.name }}</div>
                                                <div class="flex justify-between text-gray-500 mt-0.5">
                                                    <span>{{ option.qty_unit }}</span>
                                                    <span class="text-blue-600 font-semibold">
                                                        Rate: ₹{{ fmt(getCostSheetUnitPrice(option)) }} 
                                                        <span v-if="!(Number(option.total_cost) > 0)" class="text-[10px] opacity-70">(Sale Rate)</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </template>
                                    </VueMultiselect>
                                </td>

                                <!-- Unit -->
                                <td class="px-3 py-1.5">
                                    <input type="text" readonly
                                        class="w-full bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded text-center text-xs"
                                        v-model="row.unit" />
                                </td>

                                <!-- Unit Price -->
                                <td class="px-3 py-1.5 text-right font-semibold text-gray-700 dark:text-gray-300 text-xs">
                                    {{ fmt(row.unitPrice) }}
                                </td>

                                <!-- Quantity -->
                                <td class="px-3 py-1.5">
                                    <input type="number" min="0" step="0.001"
                                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-right text-xs"
                                        v-model="row.quantity" />
                                </td>

                                <!-- Sub Total -->
                                <td class="px-3 py-1.5 text-right text-xs text-gray-700 dark:text-gray-300">
                                    {{ fmt(rowTotal(row)) }}
                                </td>

                                <!-- Margin % -->
                                <td class="px-3 py-1.5">
                                    <input type="number" min="0" step="0.01"
                                        class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-right text-xs"
                                        v-model="row.margin" />
                                </td>

                                <!-- With Margin -->
                                <td class="px-3 py-1.5 text-right font-bold text-xs text-gray-900 dark:text-gray-100">
                                    {{ fmt(rowWithMarg(row)) }}
                                </td>

                                <!-- Remove -->
                                <td class="px-3 py-1.5 text-center">
                                    <BaseButton color="danger" :icon="mdiTrashCan" small
                                        @click="removeRow(sec.key, idx)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                    <!-- Add Row button -->
                    <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40">
                        <BaseButton color="info" :icon="mdiPlus" :label="`Add ${sec.label} Row`" small
                            @click="addRow(sec.key)" />
                    </div>
                </div>
            </div>

        </div>
    </div>
</CardBoxModal>
</template>

<style scoped>
/* Ensure VueMultiselect dropdown always renders above the modal overlay */
:deep(.multiselect__content-wrapper) {
    z-index: 9999 !important;
    position: absolute;
}
:deep(.multiselect) {
    overflow: visible !important;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #f3f4f6; /* bg-gray-100 */
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db; /* bg-gray-300 */
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #9ca3af; /* bg-gray-400 */
}

/* Dark mode support */
:where(.dark) .custom-scrollbar::-webkit-scrollbar-track {
    background: #1f2937; /* bg-gray-800 */
}
:where(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #4b5563; /* bg-gray-600 */
}
:where(.dark) .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #6b7280; /* bg-gray-500 */
}
</style>
