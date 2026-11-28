<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiReceiptTextCheck,
    mdiPlus,
    mdiCheckDecagram,
    mdiCloseCircle,
    mdiDownload,
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
import FormCheckRadio from "@/components/FormCheckRadio.vue";
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const props = defineProps({
    claims: Object,
    employees: Array,
    resourceNeo: Object,
});

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "info");

const isCreateModalActive = ref(false);
const isActModalActive = ref(false);
const activeClaim = ref(null);
const actionType = ref("approve");

const categories = [
    { id: "Travel", label: "Travel & Fuel" },
    { id: "Food & Meal", label: "Food & Meals" },
    { id: "Office Supplies", label: "Office Supplies" },
    { id: "Client Entertainment", label: "Client Entertainment" },
    { id: "Medical", label: "Medical / Health" },
    { id: "Others", label: "Others" },
];

const today = new Date().toISOString().substring(0, 10);

const form = useForm({
    employee_id: null,
    category: categories[0].id,
    claim_date: today,
    amount: 0,
    description: "",
    receipt: null,
    bridge_to_expense: false,
});

const actForm = useForm({
    action: "approve",
    remarks: "",
});

const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const submitCreate = () => {
    form.transform((data) => ({
        ...data,
        employee_id: unwrap(data.employee_id),
        category: unwrap(data.category),
    })).post(route("hrms.payroll.claims.store"), {
        onSuccess: () => (isCreateModalActive.value = false),
    });
};

const openActModal = (claim, type) => {
    activeClaim.value = claim;
    actionType.value = type;
    actForm.action = type;
    actForm.remarks = "";
    isActModalActive.value = true;
};

const submitAct = () => {
    actForm.post(route("hrms.payroll.claims.act", activeClaim.value.id), {
        onSuccess: () => (isActModalActive.value = false),
    });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS - E-Claims" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiReceiptTextCheck" title="E-Claims & Reimbursements" main>
                <BaseButton
                    v-if="can('hrmsClaim_create')"
                    :icon="mdiPlus"
                    label="Submit E-Claim"
                    color="contrast"
                    rounded-full
                    small
                    @click="isCreateModalActive = true"
                />
            </SectionTitleLineWithButton>

            <NotificationBar v-if="message" :color="msgType" class="mb-4">
                {{ message }}
            </NotificationBar>

            <CardBox class="mb-6" has-table>
                <Table :resource="props.claims">
                    <template #cell(approval_status)="{ item }">
                        <span
                            class="px-2 py-0.5 text-xs font-semibold rounded-full"
                            :class="{
                                'bg-yellow-100 text-yellow-800': item.approval_status.startsWith('pending_'),
                                'bg-blue-100 text-blue-800': item.approval_status === 'approved',
                                'bg-green-100 text-green-800': item.approval_status === 'paid',
                                'bg-red-100 text-red-800': item.approval_status === 'rejected',
                            }"
                        >
                            {{ item.approval_status.toUpperCase() }}
                        </span>
                    </template>
                    <template #cell(host_expense_id)="{ item }">
                        <span v-if="item.host_expense_id" class="text-xs text-indigo-600 font-semibold">
                            EXP #{{ item.host_expense_id }}
                        </span>
                        <span v-else class="text-xs text-gray-400">-</span>
                    </template>
                    <template #cell(Actions)="{ item }">
                        <BaseButtons type="justify-start" no-wrap>
                            <BaseButton
                                v-if="item.receipt_path"
                                :icon="mdiDownload"
                                color="info"
                                small
                                rounded-full
                                title="Download Receipt"
                                :href="route('hrms.payroll.claims.receipt', item.id)"
                            />
                            <BaseButton
                                v-if="can('hrmsClaim_approve') && item.approval_status.startsWith('pending_')"
                                :icon="mdiCheckDecagram"
                                color="success"
                                small
                                rounded-full
                                title="Approve"
                                @click="openActModal(item, 'approve')"
                            />
                            <BaseButton
                                v-if="can('hrmsClaim_approve') && item.approval_status.startsWith('pending_')"
                                :icon="mdiCloseCircle"
                                color="danger"
                                small
                                rounded-full
                                title="Reject"
                                @click="openActModal(item, 'reject')"
                            />
                        </BaseButtons>
                    </template>
                </Table>
            </CardBox>

            <!-- Submit Claim Modal -->
            <CardBoxModal
                v-model="isCreateModalActive"
                title="Submit Reimbursement Claim"
                button-label="Submit Claim"
                has-cancel
                @confirm="submitCreate"
            >
                <div class="space-y-4">
                    <FormField label="Employee">
                        <FormControl
                            v-model="form.employee_id"
                            :options="props.employees.map(e => ({ id: e.id, label: `${e.emp_code} - ${e.full_name}` }))"
                            type="select"
                            required
                        />
                    </FormField>
                    <FormField label="Category">
                        <FormControl v-model="form.category" :options="categories" type="select" required />
                    </FormField>
                    <div class="grid grid-cols-2 gap-4">
                        <FormField label="Expense Date">
                            <FormControl v-model="form.claim_date" type="date" required />
                        </FormField>
                        <FormField label="Amount (₹)">
                            <FormControl v-model="form.amount" type="number" step="0.01" min="1" required />
                        </FormField>
                    </div>
                    <FormField label="Description">
                        <FormControl v-model="form.description" placeholder="Travel to client site, fuel, etc." />
                    </FormField>
                    <FormField label="Receipt / Invoice Upload (Max 5MB)">
                        <input
                            type="file"
                            accept="image/*,.pdf"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            @change="(e) => (form.receipt = e.target.files[0])"
                        />
                    </FormField>
                    <FormCheckRadio
                        v-model="form.bridge_to_expense"
                        label="Bridge into SignageFlow Host Expenses table"
                        type="checkbox"
                    />
                </div>
            </CardBoxModal>

            <!-- Approve/Reject Modal -->
            <CardBoxModal
                v-model="isActModalActive"
                :title="`${actionType === 'approve' ? 'Approve' : 'Reject'} Claim - ${activeClaim?.claim_number}`"
                :button-label="actionType === 'approve' ? 'Approve Claim' : 'Reject Claim'"
                :button="actionType === 'approve' ? 'success' : 'danger'"
                has-cancel
                @confirm="submitAct"
            >
                <div class="space-y-4">
                    <p class="text-sm">
                        Confirm {{ actionType }} for <b>₹{{ activeClaim?.amount }}</b> ({{ activeClaim?.category }})?
                    </p>
                    <FormField label="Remarks">
                        <FormControl v-model="actForm.remarks" placeholder="Optional approval or rejection remarks" />
                    </FormField>
                </div>
            </CardBoxModal>
        </SectionMain>
    </LayoutAuthenticated>
</template>
