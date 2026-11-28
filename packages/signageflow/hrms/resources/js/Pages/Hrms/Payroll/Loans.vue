<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiHandCoin,
    mdiPlus,
    mdiPauseCircle,
    mdiPlayCircle,
    mdiCheckCircle,
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
    loans: Object,
    employees: Array,
    resourceNeo: Object,
});

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "info");

const isCreateModalActive = ref(false);

const loanTypes = [
    { id: "salary_advance", label: "Salary Advance" },
    { id: "personal_loan", label: "Personal Loan" },
    { id: "emergency_loan", label: "Emergency Loan" },
];

const today = new Date().toISOString().substring(0, 10);

const form = useForm({
    employee_id: null,
    loan_type: loanTypes[0],
    principal_amount: 10000,
    tenure_months: 6,
    disbursed_date: today,
    start_deduction_date: today,
    remarks: "",
});

const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const submitCreate = () => {
    form.transform((data) => ({
        ...data,
        employee_id: unwrap(data.employee_id),
        loan_type: unwrap(data.loan_type),
    })).post(route("hrms.payroll.loans.store"), {
        onSuccess: () => (isCreateModalActive.value = false),
    });
};

const toggleLoan = (loan) => {
    const action = loan.status === "active" ? "pause" : "resume";
    if (!confirm(`Are you sure you want to ${action} Loan ${loan.loan_number}?`)) return;
    router.post(route("hrms.payroll.loans.toggleStatus", loan.id));
};

const precloseLoan = (loan) => {
    if (!confirm(`Pre-close Loan ${loan.loan_number}? All remaining pending EMIs will be marked skipped.`)) return;
    router.post(route("hrms.payroll.loans.preclose", loan.id));
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS - Loans & Advances" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiHandCoin" title="Loan Master & Salary Advances" main>
                <BaseButton
                    v-if="can('hrmsPayroll_create')"
                    :icon="mdiPlus"
                    label="Issue Loan / Advance"
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
                <Table :resource="props.loans">
                    <template #cell(status)="{ item }">
                        <span
                            class="px-2 py-0.5 text-xs font-semibold rounded-full"
                            :class="{
                                'bg-green-100 text-green-800': item.status === 'active',
                                'bg-yellow-100 text-yellow-800': item.status === 'paused',
                                'bg-gray-100 text-gray-800': item.status === 'closed',
                            }"
                        >
                            {{ item.status.toUpperCase() }}
                        </span>
                    </template>
                    <template #cell(Actions)="{ item }">
                        <BaseButtons type="justify-start" no-wrap>
                            <BaseButton
                                v-if="can('hrmsPayroll_edit') && item.status !== 'closed'"
                                :icon="item.status === 'active' ? mdiPauseCircle : mdiPlayCircle"
                                :color="item.status === 'active' ? 'warning' : 'info'"
                                small
                                rounded-full
                                :title="item.status === 'active' ? 'Pause EMIs' : 'Resume EMIs'"
                                @click="toggleLoan(item)"
                            />
                            <BaseButton
                                v-if="can('hrmsPayroll_edit') && item.status !== 'closed'"
                                :icon="mdiCheckCircle"
                                color="danger"
                                small
                                rounded-full
                                title="Pre-close Loan"
                                @click="precloseLoan(item)"
                            />
                        </BaseButtons>
                    </template>
                </Table>
            </CardBox>

            <!-- Issue Loan Modal -->
            <CardBoxModal
                v-model="isCreateModalActive"
                title="Issue Loan / Salary Advance"
                button-label="Create Schedule"
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
                    <FormField label="Loan Type">
                        <FormControl v-model="form.loan_type" :options="loanTypes" type="select" required />
                    </FormField>
                    <div class="grid grid-cols-2 gap-4">
                        <FormField label="Principal Amount (₹)">
                            <FormControl v-model="form.principal_amount" type="number" step="100" min="100" required />
                        </FormField>
                        <FormField label="Tenure (Months)">
                            <FormControl v-model="form.tenure_months" type="number" min="1" max="60" required />
                        </FormField>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <FormField label="Disbursed Date">
                            <FormControl v-model="form.disbursed_date" type="date" required />
                        </FormField>
                        <FormField label="Start Deduction Date">
                            <FormControl v-model="form.start_deduction_date" type="date" required />
                        </FormField>
                    </div>
                    <FormField label="Remarks">
                        <FormControl v-model="form.remarks" placeholder="Reason for loan request" />
                    </FormField>
                </div>
            </CardBoxModal>
        </SectionMain>
    </LayoutAuthenticated>
</template>
