<script setup>
import { ref, watch, onMounted, computed, nextTick } from "vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import BaseButton from "@/components/BaseButton.vue";
import { mdiViewList, mdiTrashCan, mdiPlus } from "@mdi/js";
import VueMultiselect from "vue-multiselect";
import "vue-multiselect/dist/vue-multiselect.css";
import axios from "axios";
import { useToast } from "vue-toast-notification";

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    costSheetId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const isOpen = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const grandTotal = computed(() => {
    return compositions.value.reduce((acc, comp) => {
        const total = (Number(comp.unitPrice) || 0) * (Number(comp.quantity) || 0);
        const marginTotal = total + (total * (Number(comp.margin) || 0) / 100);
        return acc + marginTotal;
    }, 0);
});

const compositions = ref([]);
const consumableOptions = ref([]);

const fetchOptions = async () => {
    try {
        const response = await axios.get(route("consumableInternalName.options"));
        consumableOptions.value = response.data;
    } catch (error) {
        useToast().error("Failed to load consumable options");
    }
};

const fetchCompositions = async () => {
    if (!props.costSheetId) return;
    try {
        const response = await axios.get(route("costSheetCompositions.index", props.costSheetId));
        compositions.value = response.data.map((c) => ({
            id: c.id,
            selectedConsumable: c.consumable,
            consumable_internal_name_id: c.consumable_internal_name_id,
            unit: c.unit,
            unitPrice: c.consumable?.unitPrice || 0,
            quantity: c.quantity,
            margin: c.margin || 0,
        }));
        if (compositions.value.length === 0) {
            addCompositionRow();
        }
    } catch (error) {
        useToast().error("Failed to load compositions");
    }
};

onMounted(() => {
    fetchOptions();
});

watch(isOpen, (newVal) => {
    if (newVal) {
        fetchCompositions();
    } else {
        compositions.value = [];
    }
});

const addCompositionRow = () => {
    compositions.value.push({
        id: null,
        selectedConsumable: null,
        consumable_internal_name_id: null,
        unit: "",
        unitPrice: 0,
        quantity: 0,
        margin: 0,
    });
};

const removeCompositionRow = (index) => {
    compositions.value.splice(index, 1);
};

const handleConsumableChange = (composition, selected) => {
    if (selected) {
        composition.consumable_internal_name_id = selected.id;
        composition.unit = selected.unitName;
        composition.unitPrice = selected.unitPrice || 0;
    } else {
        composition.consumable_internal_name_id = null;
        composition.unit = "";
        composition.unitPrice = 0;
    }
};

const saveCompositions = async () => {
    // Basic validation
    for (let c of compositions.value) {
        if (!c.consumable_internal_name_id) {
            useToast().error("Please select a consumable for all rows or remove empty rows.");
            return;
        }
    }

    try {
        const response = await axios.post(route("costSheetCompositions.store", props.costSheetId), {
            compositions: compositions.value.map((c) => ({
                id: c.id,
                consumable_internal_name_id: c.consumable_internal_name_id,
                unit: c.unit,
                quantity: c.quantity,
                margin: c.margin,
            })),
        });
        useToast().success(response.data.message);
        emit("saved");
        isOpen.value = false;
    } catch (error) {
        useToast().error("Failed to save compositions");
    }
};
</script>

<template>
    <CardBoxModal
        v-model="isOpen"
        title="Manage Compositions"
        button="success"
        buttonLabel="Save"
        :full-width="true"
        has-cancel
        @confirm="saveCompositions"
    >
        <div class="space-y-4 min-h-[50vh]">
            <div class="overflow-visible">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2">Consumable Internal Name</th>
                            <th class="px-4 py-2 w-24">Unit</th>
                            <th class="px-4 py-2 w-24 text-right">Unit Price</th>
                            <th class="px-4 py-2 w-24">Quantity</th>
                            <th class="px-4 py-2 w-24 text-right">Total</th>
                            <th class="px-4 py-2 w-24">Margin (%)</th>
                            <th class="px-4 py-2 w-24 text-right">Margin Total</th>
                            <th class="px-4 py-2 w-16"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(comp, index) in compositions" :key="index" class="border-b dark:border-gray-600">
                            <td class="px-4 py-2 min-w-[250px]">
                                <VueMultiselect
                                    v-model="comp.selectedConsumable"
                                    :options="consumableOptions"
                                    label="name"
                                    track-by="id"
                                    placeholder="Search Consumable..."
                                    :searchable="true"
                                    :close-on-select="true"
                                    @update:modelValue="handleConsumableChange(comp, $event)"
                                >
                                    <template #option="{ option }">
                                        <div class="text-xs font-semibold">{{ option.name }}</div>
                                    </template>
                                </VueMultiselect>
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    type="text"
                                    class="w-full bg-gray-100 border-gray-300 rounded text-center dark:bg-gray-800 dark:border-gray-600"
                                    v-model="comp.unit"
                                    readonly
                                />
                            </td>
                            <td class="px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-300">
                                {{ Number(comp.unitPrice || 0).toFixed(2) }}
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-right"
                                    v-model="comp.quantity"
                                />
                            </td>
                            <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-gray-100">
                                {{ (Number(comp.unitPrice || 0) * Number(comp.quantity || 0)).toFixed(2) }}
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-right"
                                    v-model="comp.margin"
                                />
                            </td>
                            <td class="px-4 py-2 text-right font-bold text-gray-900 dark:text-gray-100">
                                {{ (((Number(comp.unitPrice || 0) * Number(comp.quantity || 0))) * (1 + (Number(comp.margin || 0) / 100))).toFixed(2) }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                <BaseButton
                                    color="danger"
                                    :icon="mdiTrashCan"
                                    small
                                    @click="removeCompositionRow(index)"
                                />
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="compositions.length > 0">
                        <tr class="bg-gray-100 dark:bg-gray-800 font-bold border-t border-gray-300 dark:border-gray-600">
                            <td colspan="6" class="px-4 py-3 text-right">Grand Total:</td>
                            <td class="px-4 py-3 text-right text-lg">{{ grandTotal.toFixed(2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div>
                <BaseButton
                    color="info"
                    :icon="mdiPlus"
                    label="Add Row"
                    small
                    @click="addCompositionRow"
                />
            </div>
        </div>
    </CardBoxModal>
</template>
