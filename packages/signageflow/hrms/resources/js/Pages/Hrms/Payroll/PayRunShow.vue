<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiCashCheck,
    mdiSync,
    mdiSendCheck,
    mdiCheckDecagram,
    mdiFilePdfBox,
    mdiFileExcel,
    mdiBank,
    mdiTrashCan,
    mdiFileEdit,
    mdiArrowLeft,
} from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import BaseButton from "@/components/BaseButton.vue";
import BaseButtons from "@/components/BaseButtons.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const props = defineProps({
    tab: String,
    payRun: Object,
    items: Object,
    bankTemplates: Array,
    glBatch: Object,
    resourceNeo: Object,
});

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "info");

const tabs = [
    { key: "initialized", label: "Initialized" },
    { key: "non_initialized", label: "Non-Initialized" },
    { key: "arrear", label: "Arrears" },
    { key: "fnf", label: "FnF Settlements" },
];

const isEditItemModalActive = ref(false);
const isImportModalActive = ref(false);
const editingItemId = ref(null);

const editItemForm = useForm({
    payable_days: 0,
    arrear_amount: 0,
    fnf_settlement_amount: 0,
    tds_amount: 0,
    notes: "",
});

const importForm = useForm({ file: null });

const switchTab = (tabKey) => {
    router.get(route("hrms.payroll.payruns.show", props.payRun.id), { tab: tabKey }, { preserveState: true, replace: true });
};

const triggerSync = () => {
    if (!confirm("Re-sync PayRun with live attendance, leaves, claims, and loan schedules?")) return;
    router.post(route("hrms.payroll.payruns.initialize", props.payRun.id));
};

const submitForApproval = () => {
    if (!confirm("Submit this PayRun to Stage 2 (Approver)?")) return;
    router.post(route("hrms.payroll.payruns.submit", props.payRun.id));
};

const approvePayRun = () => {
    if (!confirm("Approve this PayRun? This will lock attendance and deduct loan EMIs.")) return;
    router.post(route("hrms.payroll.payruns.approve", props.payRun.id));
};

const publishPayRun = () => {
    if (!confirm("Publish payslips to employees (DomPDF generation + Web/Mobile release)?")) return;
    router.post(route("hrms.payroll.payruns.publish", props.payRun.id));
};

const openEditItemModal = (item) => {
    editingItemId.value = item.id;
    editItemForm.payable_days = item.payable_days;
    editItemForm.arrear_amount = item.arrear_amount;
    editItemForm.fnf_settlement_amount = item.fnf_settlement_amount;
    editItemForm.tds_amount = item.tds_amount;
    editItemForm.notes = item.notes ?? "";
    isEditItemModalActive.value = true;
};

const submitItemUpdate = () => {
    editItemForm.put(route("hrms.payroll.payruns.items.update", editingItemId.value), {
        onSuccess: () => (isEditItemModalActive.value = false),
    });
};

const deleteItem = (item) => {
    if (!confirm(`Remove ${item.employee_name} from this PayRun?`)) return;
    router.delete(route("hrms.payroll.payruns.items.destroy", item.id));
};

const submitImportOverrides = () => {
    importForm.post(route("hrms.payroll.payruns.importOverrides", props.payRun.id), {
        onSuccess: () => (isImportModalActive.value = false),
    });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="`PayRun - ${props.payRun.run_code}`" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCashCheck" :title="`PayRun: ${props.payRun.run_code}`" main>
                <div class="flex items-center gap-2">
                    <BaseButton
                        :icon="mdiArrowLeft"
                        label="Back"
                        color="white"
                        rounded-full
                        small
                        :href="route('hrms.payroll.payruns')"
                    />
                    <!-- Stage 1 Actions -->
                    <template v-if="props.payRun.stage === 'initialized'">
                        <BaseButton
                            :icon="mdiSync"
                            label="Re-Sync Live Data"
                            color="warning"
                            rounded-full
                            small
                            @click="triggerSync"
                        />
                        <BaseButton
                            :icon="mdiFileExcel"
                            label="Import Overrides"
                            color="info"
                            rounded-full
                            small
                            @click="isImportModalActive = true"
                        />
                        <BaseButton
                            v-if="can('hrmsPayroll_edit')"
                            :icon="mdiSendCheck"
                            label="Submit to Approver"
                            color="contrast"
                            rounded-full
                            small
                            @click="submitForApproval"
                        />
                    </template>

                    <!-- Stage 2 Actions -->
                    <template v-else-if="props.payRun.stage.startsWith('pending_')">
                        <BaseButton
                            v-if="can('hrmsPayroll_approveL1')"
                            :icon="mdiCheckDecagram"
                            label="Approve PayRun"
                            color="success"
                            rounded-full
                            small
                            @click="approvePayRun"
                        />
                    </template>

                    <!-- Stage 3 Actions -->
                    <template v-else-if="props.payRun.stage === 'approved'">
                        <BaseButton
                            v-if="can('hrmsPayroll_publish')"
                            :icon="mdiFilePdfBox"
                            label="Publish Payslips"
                            color="success"
                            rounded-full
                            small
                            @click="publishPayRun"
                        />
                    </template>

                    <template v-if="['approved', 'published'].includes(props.payRun.stage)">
                        <BaseButton
                            :icon="mdiBank"
                            label="Bank NEFT"
                            color="info"
                            rounded-full
                            small
                            :href="route('hrms.payroll.payruns.exportBank', props.payRun.id)"
                        />
                    </template>
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar v-if="message" :color="msgType" class="mb-4">
                {{ message }}
            </NotificationBar>

            <!-- PayRun Summary Header Card -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <CardBox class="text-center py-2">
                    <div class="text-xs text-gray-500 uppercase">Pay Group & Cycle</div>
                    <div class="font-bold text-base">{{ props.payRun.pay_group?.name ?? '-' }} ({{ props.payRun.pay_frequency }})</div>
                    <div class="text-xs text-gray-400">{{ props.payRun.period_start }} to {{ props.payRun.period_end }}</div>
                </CardBox>
                <CardBox class="text-center py-2">
                    <div class="text-xs text-gray-500 uppercase">Total Gross</div>
                    <div class="font-bold text-lg text-blue-600">₹{{ Number(props.payRun.total_gross).toLocaleString('en-IN') }}</div>
                </CardBox>
                <CardBox class="text-center py-2">
                    <div class="text-xs text-gray-500 uppercase">Total Deductions</div>
                    <div class="font-bold text-lg text-red-600">₹{{ Number(props.payRun.total_deductions).toLocaleString('en-IN') }}</div>
                </CardBox>
                <CardBox class="text-center py-2">
                    <div class="text-xs text-gray-500 uppercase">Net Disbursement</div>
                    <div class="font-bold text-xl text-green-600">₹{{ Number(props.payRun.total_net).toLocaleString('en-IN') }}</div>
                    <div class="text-xs font-semibold uppercase text-indigo-500 mt-0.5">Stage: {{ props.payRun.stage }}</div>
                </CardBox>
            </div>

            <!-- Tab Buttons -->
            <div class="flex gap-2 mb-4 overflow-x-auto pb-1">
                <BaseButton
                    v-for="t in tabs"
                    :key="t.key"
                    :label="t.label"
                    :color="tab === t.key ? 'contrast' : 'white'"
                    :outline="tab !== t.key"
                    small
                    rounded-full
                    @click="switchTab(t.key)"
                />
            </div>

            <CardBox class="mb-6" has-table>
                <Table :resource="props.items">
                    <template #cell(Actions)="{ item }">
                        <BaseButtons type="justify-start" no-wrap>
                            <BaseButton
                                v-if="props.payRun.stage === 'initialized' && can('hrmsPayroll_edit')"
                                :icon="mdiFileEdit"
                                color="info"
                                small
                                rounded-full
                                @click="openEditItemModal(item)"
                            />
                            <BaseButton
                                v-if="item.is_published && can('hrmsPayroll_list')"
                                :icon="mdiFilePdfBox"
                                color="success"
                                small
                                rounded-full
                                :href="route('hrms.payroll.payruns.items.payslip', item.id)"
                            />
                            <BaseButton
                                v-if="props.payRun.stage === 'initialized' && can('hrmsPayroll_delete')"
                                :icon="mdiTrashCan"
                                color="danger"
                                small
                                rounded-full
                                @click="deleteItem(item)"
                            />
                        </BaseButtons>
                    </template>
                </Table>
            </CardBox>

            <!-- Inline Item Edit Modal -->
            <CardBoxModal
                v-model="isEditItemModalActive"
                title="Adjust PayRun Item"
                button-label="Save Adjustments"
                has-cancel
                @confirm="submitItemUpdate"
            >
                <div class="space-y-4">
                    <FormField label="Payable Days (0-31)">
                        <FormControl v-model="editItemForm.payable_days" type="number" step="0.5" required />
                    </FormField>
                    <FormField label="Arrear Amount (₹)">
                        <FormControl v-model="editItemForm.arrear_amount" type="number" step="0.01" />
                    </FormField>
                    <FormField label="FnF Settlement Amount (₹)">
                        <FormControl v-model="editItemForm.fnf_settlement_amount" type="number" step="0.01" />
                    </FormField>
                    <FormField label="TDS Deduction (₹)">
                        <FormControl v-model="editItemForm.tds_amount" type="number" step="0.01" />
                    </FormField>
                    <FormField label="Adjustment Notes">
                        <FormControl v-model="editItemForm.notes" placeholder="Approved special adjustment" />
                    </FormField>
                </div>
            </CardBoxModal>

            <!-- Import Overrides Modal -->
            <CardBoxModal
                v-model="isImportModalActive"
                title="Bulk Overrides Import (Excel)"
                button-label="Import"
                has-cancel
                @confirm="submitImportOverrides"
            >
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Upload Excel (.xlsx, .xls, .csv) with columns:<br/>
                        <b>A: Emp Code</b> | <b>B: Arrear Amount</b> | <b>C: TDS Amount</b>
                    </p>
                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        @change="(e) => (importForm.file = e.target.files[0])"
                    />
                </div>
            </CardBoxModal>
        </SectionMain>
    </LayoutAuthenticated>
</template>
