<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiCashMultiple,
    mdiPlus,
    mdiFileEdit,
    mdiTrashCan,
    mdiUpload,
    mdiFileExcel,
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
    tab: String,
    records: Object,
    users: Array,
    payGroups: Array,
    bankTemplates: Array,
    employees: Array,
    components: Array,
    resourceNeo: Object,
});

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "info");

const tabs = [
    { key: "components", label: "Salary Components", singular: "Component" },
    { key: "pay_groups", label: "Pay Groups", singular: "Pay Group" },
    { key: "statutory", label: "Statutory Rules", singular: "Statutory Rule" },
    { key: "structures", label: "Salary Structures", singular: "Structure" },
    { key: "revisions", label: "Salary Revisions", singular: "Revision" },
];
const currentTabMeta = computed(() => tabs.find((t) => t.key === props.tab) ?? tabs[0]);

const isFormModalActive = ref(false);
const isRevisionModalActive = ref(false);
const isImportModalActive = ref(false);
const editingId = ref(null);

const compTypes = [
    { id: "earning", label: "Earning" },
    { id: "deduction", label: "Deduction" },
    { id: "employer_contribution", label: "Employer Contribution" },
];
const calcTypes = [
    { id: "fixed", label: "Fixed Amount" },
    { id: "percent_of_basic", label: "% of Basic" },
    { id: "percent_of_gross", label: "% of Gross" },
    { id: "formula", label: "Custom Formula" },
];
const freqOpts = [
    { id: "monthly", label: "Monthly" },
    { id: "weekly", label: "Weekly" },
];

const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const compForm = useForm({
    code: "", name: "", type: compTypes[0], calc_type: calcTypes[0],
    formula_or_value: "0", is_pf_wage: false, is_esic_wage: false, is_pt_wage: false, sort_order: 10,
});

const payGroupForm = useForm({
    code: "", name: "", pay_frequency: freqOpts[0],
    l1_approver_user_id: null, l2_approver_user_id: null, default_bank_template_id: null,
});

const statForm = useForm({
    financial_year: "2026-2027",
    epf_employee_rate: 12.0, epf_employer_rate: 12.0, epf_wage_ceiling: 15000,
    esic_employee_rate: 0.75, esic_employer_rate: 3.25, esic_wage_ceiling: 21000,
});

const structForm = useForm({
    employee_id: null, pay_group_id: null, effective_from: new Date().toISOString().substring(0, 10),
    monthly_gross: 0, basic_salary: 0, monthly_ctc: 0,
});

const revForm = useForm({
    employee_id: null, new_gross: 0, effective_from: new Date().toISOString().substring(0, 10),
    arrear_applicable: false, remarks: "",
});

const importForm = useForm({ file: null });

const switchTab = (tabKey) => {
    router.get(route("hrms.payroll.setup"), { tab: tabKey }, { preserveState: true, replace: true });
};

const openCreateModal = () => {
    editingId.value = null;
    if (props.tab === "components") {
        compForm.reset();
        compForm.type = compTypes[0];
        compForm.calc_type = calcTypes[0];
    } else if (props.tab === "pay_groups") {
        payGroupForm.reset();
        payGroupForm.pay_frequency = freqOpts[0];
    } else if (props.tab === "statutory") {
        statForm.reset();
    } else if (props.tab === "structures") {
        structForm.reset();
        structForm.pay_group_id = props.payGroups?.[0]?.id ?? null;
    } else if (props.tab === "revisions") {
        revForm.reset();
    }
    isFormModalActive.value = true;
};

const openEditModal = (row) => {
    editingId.value = row.id;
    if (props.tab === "components") {
        compForm.code = row.code;
        compForm.name = row.name;
        compForm.type = compTypes.find((t) => t.id === row.type) ?? compTypes[0];
        compForm.calc_type = calcTypes.find((t) => t.id === row.calc_type) ?? calcTypes[0];
        compForm.formula_or_value = row.formula_or_value;
        compForm.is_pf_wage = Boolean(row.is_pf_wage);
        compForm.is_esic_wage = Boolean(row.is_esic_wage);
        compForm.is_pt_wage = Boolean(row.is_pt_wage);
        compForm.sort_order = row.sort_order;
    } else if (props.tab === "pay_groups") {
        payGroupForm.code = row.code;
        payGroupForm.name = row.name;
        payGroupForm.pay_frequency = freqOpts.find((f) => f.id === row.pay_frequency) ?? freqOpts[0];
        payGroupForm.l1_approver_user_id = row.l1_approver_user_id;
        payGroupForm.l2_approver_user_id = row.l2_approver_user_id;
        payGroupForm.default_bank_template_id = row.default_bank_template_id;
    }
    isFormModalActive.value = true;
};

const submitForm = () => {
    if (props.tab === "components") {
        compForm.transform((data) => ({
            ...data,
            type: unwrap(data.type),
            calc_type: unwrap(data.calc_type),
        }));
        if (editingId.value) {
            compForm.put(route("hrms.payroll.setup.components.update", editingId.value), {
                onSuccess: () => (isFormModalActive.value = false),
            });
        } else {
            compForm.post(route("hrms.payroll.setup.components.store"), {
                onSuccess: () => (isFormModalActive.value = false),
            });
        }
    } else if (props.tab === "pay_groups") {
        payGroupForm.transform((data) => ({
            ...data,
            pay_frequency: unwrap(data.pay_frequency),
            l1_approver_user_id: unwrap(data.l1_approver_user_id),
            l2_approver_user_id: unwrap(data.l2_approver_user_id),
            default_bank_template_id: unwrap(data.default_bank_template_id),
        }));
        if (editingId.value) {
            payGroupForm.put(route("hrms.payroll.setup.payGroups.update", editingId.value), {
                onSuccess: () => (isFormModalActive.value = false),
            });
        } else {
            payGroupForm.post(route("hrms.payroll.setup.payGroups.store"), {
                onSuccess: () => (isFormModalActive.value = false),
            });
        }
    } else if (props.tab === "statutory") {
        statForm.post(route("hrms.payroll.setup.statutory.save"), {
            onSuccess: () => (isFormModalActive.value = false),
        });
    } else if (props.tab === "structures") {
        structForm.transform((data) => ({
            ...data,
            employee_id: unwrap(data.employee_id),
            pay_group_id: unwrap(data.pay_group_id),
        }));
        structForm.post(route("hrms.payroll.setup.structures.store"), {
            onSuccess: () => (isFormModalActive.value = false),
        });
    } else if (props.tab === "revisions") {
        revForm.transform((data) => ({
            ...data,
            employee_id: unwrap(data.employee_id),
        }));
        revForm.post(route("hrms.payroll.setup.revisions.store"), {
            onSuccess: () => (isFormModalActive.value = false),
        });
    }
};

const deleteRecord = (row) => {
    if (!confirm(`Are you sure you want to delete ${row.name || row.code}?`)) return;
    if (props.tab === "components") {
        router.delete(route("hrms.payroll.setup.components.destroy", row.id));
    } else if (props.tab === "pay_groups") {
        router.delete(route("hrms.payroll.setup.payGroups.destroy", row.id));
    }
};

const submitImport = () => {
    importForm.post(route("hrms.payroll.setup.revisions.import"), {
        onSuccess: () => (isImportModalActive.value = false),
    });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="HRMS - Payroll Setup" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCashMultiple" title="Payroll Setup & Salary Administration" main>
                <div class="flex items-center gap-2">
                    <BaseButton
                        v-if="props.tab === 'revisions' && can('hrmsPayroll_create')"
                        :icon="mdiUpload"
                        label="Excel Import"
                        color="info"
                        rounded-full
                        small
                        @click="isImportModalActive = true"
                    />
                    <BaseButton
                        v-if="can('hrmsPayroll_create')"
                        :icon="mdiPlus"
                        :label="`Add ${currentTabMeta.singular}`"
                        color="contrast"
                        rounded-full
                        small
                        @click="openCreateModal"
                    />
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar v-if="message" :color="msgType" class="mb-4">
                {{ message }}
            </NotificationBar>

            <!-- Navigation Tabs -->
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
                <Table :resource="props.records">
                    <!-- Actions slot -->
                    <template #cell(Actions)="{ item }">
                        <BaseButtons type="justify-start" no-wrap>
                            <BaseButton
                                v-if="can('hrmsPayroll_edit') && (props.tab === 'components' || props.tab === 'pay_groups')"
                                :icon="mdiFileEdit"
                                color="info"
                                small
                                rounded-full
                                @click="openEditModal(item)"
                            />
                            <BaseButton
                                v-if="can('hrmsPayroll_delete') && (props.tab === 'components' || props.tab === 'pay_groups')"
                                :icon="mdiTrashCan"
                                color="danger"
                                small
                                rounded-full
                                @click="deleteRecord(item)"
                            />
                        </BaseButtons>
                    </template>
                </Table>
            </CardBox>

            <!-- Add/Edit Modal -->
            <CardBoxModal
                v-model="isFormModalActive"
                :title="editingId ? `Edit ${currentTabMeta.singular}` : `Add New ${currentTabMeta.singular}`"
                button-label="Save"
                has-cancel
                @confirm="submitForm"
            >
                <!-- Component Form -->
                <div v-if="props.tab === 'components'" class="space-y-3">
                    <FormField label="Code & Name">
                        <FormControl v-model="compForm.code" placeholder="BASIC, HRA, etc." required />
                        <FormControl v-model="compForm.name" placeholder="Full Component Name" required />
                    </FormField>
                    <FormField label="Type & Calculation">
                        <FormControl v-model="compForm.type" :options="compTypes" type="select" />
                        <FormControl v-model="compForm.calc_type" :options="calcTypes" type="select" />
                    </FormField>
                    <FormField label="Value or Formula" help="Fixed amount, percentage (e.g. 50), or formula (e.g. BASIC * 0.5)">
                        <FormControl v-model="compForm.formula_or_value" required />
                    </FormField>
                    <div class="flex flex-wrap gap-4 pt-2">
                        <FormCheckRadio v-model="compForm.is_pf_wage" label="Consider for EPF" type="checkbox" />
                        <FormCheckRadio v-model="compForm.is_esic_wage" label="Consider for ESIC" type="checkbox" />
                        <FormCheckRadio v-model="compForm.is_pt_wage" label="Consider for PT" type="checkbox" />
                    </div>
                </div>

                <!-- Pay Group Form -->
                <div v-else-if="props.tab === 'pay_groups'" class="space-y-3">
                    <FormField label="Code & Name">
                        <FormControl v-model="payGroupForm.code" placeholder="M-STAFF" required />
                        <FormControl v-model="payGroupForm.name" placeholder="Monthly Staff Pay Group" required />
                    </FormField>
                    <FormField label="Pay Frequency">
                        <FormControl v-model="payGroupForm.pay_frequency" :options="freqOpts" type="select" />
                    </FormField>
                    <FormField label="L1 & L2 Approvers">
                        <FormControl v-model="payGroupForm.l1_approver_user_id" :options="[{id: '', label: '-- None --'}, ...props.users]" type="select" />
                        <FormControl v-model="payGroupForm.l2_approver_user_id" :options="[{id: '', label: '-- None --'}, ...props.users]" type="select" />
                    </FormField>
                    <FormField label="Default Bank Submission Template">
                        <FormControl v-model="payGroupForm.default_bank_template_id" :options="[{id: '', label: '-- Standard NEFT --'}, ...props.bankTemplates]" type="select" />
                    </FormField>
                </div>

                <!-- Statutory Rules Form -->
                <div v-else-if="props.tab === 'statutory'" class="space-y-3">
                    <FormField label="Financial Year">
                        <FormControl v-model="statForm.financial_year" placeholder="2026-2027" required />
                    </FormField>
                    <FormField label="EPF Employee & Employer Rate (%)">
                        <FormControl v-model="statForm.epf_employee_rate" type="number" step="0.01" />
                        <FormControl v-model="statForm.epf_employer_rate" type="number" step="0.01" />
                    </FormField>
                    <FormField label="EPF Wage Ceiling (₹)">
                        <FormControl v-model="statForm.epf_wage_ceiling" type="number" />
                    </FormField>
                    <FormField label="ESIC Employee & Employer Rate (%)">
                        <FormControl v-model="statForm.esic_employee_rate" type="number" step="0.01" />
                        <FormControl v-model="statForm.esic_employer_rate" type="number" step="0.01" />
                    </FormField>
                    <FormField label="ESIC Wage Ceiling (₹)">
                        <FormControl v-model="statForm.esic_wage_ceiling" type="number" />
                    </FormField>
                </div>

                <!-- Structure Form -->
                <div v-else-if="props.tab === 'structures'" class="space-y-3">
                    <FormField label="Employee & Pay Group">
                        <FormControl v-model="structForm.employee_id" :options="props.employees.map(e => ({id: e.id, label: `${e.emp_code} - ${e.full_name}`}))" type="select" required />
                        <FormControl v-model="structForm.pay_group_id" :options="props.payGroups.map(p => ({id: p.id, label: p.name}))" type="select" required />
                    </FormField>
                    <FormField label="Effective Date & Monthly Gross">
                        <FormControl v-model="structForm.effective_from" type="date" required />
                        <FormControl v-model="structForm.monthly_gross" type="number" step="0.01" required />
                    </FormField>
                    <FormField label="Basic Salary & Monthly CTC" help="Balance earnings will automatically adjust into Special Allowance (SPEC)">
                        <FormControl v-model="structForm.basic_salary" type="number" step="0.01" required />
                        <FormControl v-model="structForm.monthly_ctc" type="number" step="0.01" />
                    </FormField>
                </div>

                <!-- Revision Form -->
                <div v-else-if="props.tab === 'revisions'" class="space-y-3">
                    <FormField label="Employee">
                        <FormControl v-model="revForm.employee_id" :options="props.employees.map(e => ({id: e.id, label: `${e.emp_code} - ${e.full_name}`}))" type="select" required />
                    </FormField>
                    <FormField label="New Monthly Gross & Effective Date">
                        <FormControl v-model="revForm.new_gross" type="number" step="0.01" required />
                        <FormControl v-model="revForm.effective_from" type="date" required />
                    </FormField>
                    <FormField label="Remarks">
                        <FormControl v-model="revForm.remarks" placeholder="Annual Appraisal / Promotion" />
                    </FormField>
                    <FormCheckRadio v-model="revForm.arrear_applicable" label="Calculate Arrears in next PayRun" type="checkbox" />
                </div>
            </CardBoxModal>

            <!-- Excel Import Modal -->
            <CardBoxModal
                v-model="isImportModalActive"
                title="Bulk Salary Revisions (Excel Upload)"
                button-label="Upload & Apply"
                has-cancel
                @confirm="submitImport"
            >
                <div class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Upload an Excel file (.xlsx, .xls, .csv) with columns:<br/>
                        <b>A: Emp Code</b> | <b>B: New Monthly Gross</b> | <b>C: Effective Date (YYYY-MM-DD)</b> | <b>D: Arrears (yes/no)</b>
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
