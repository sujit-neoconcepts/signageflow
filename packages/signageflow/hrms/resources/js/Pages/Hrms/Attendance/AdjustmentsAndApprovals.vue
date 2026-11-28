<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiClipboardCheckOutline,
    mdiAlert,
    mdiPackageVariantPlus,
    mdiCheck,
    mdiClose,
    mdiCoffee,
    mdiCalculator,
} from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import BaseButton from "@/components/BaseButton.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    tab: String,
    date: String,
    month: Number,
    year: Number,
    departments: Array,
    employees: Array,
    records: Object,
    resourceNeo: Object,
});

const tabs = [
    { key: "adjust", label: "Manual Adjustments" },
    { key: "regularization", label: "Regularization Approvals" },
    { key: "ot", label: "OT Approvals" },
    { key: "allowances", label: "Attendance Allowances" },
];

const selectedDate = ref(props.date);
const isAdjustModalActive = ref(false);
const isBreakModalActive = ref(false);
const breakTargetDayId = ref(null);

const toOpt = (items, labelFn = (x) => x.name, emptyLabel = "-- Select --") => [
    { id: "", label: emptyLabel },
    ...(items || []).map((x) => ({ id: x.id, label: labelFn(x) })),
];
const empOpts = computed(() => toOpt(props.employees, (e) => `${e.emp_code} - ${e.full_name}`));
const deptOpts = computed(() => toOpt(props.departments, (d) => d.name, "All Departments"));
const modeOpts = [
    { id: "individual", label: "By Individual" },
    { id: "department", label: "By Department" },
    { id: "filters", label: "Update By Filters" },
    { id: "bulk", label: "Staff Bulk Attendance" },
];
const statusOpts = [
    { id: "", label: "Auto-calculate Status" },
    { id: "present", label: "Present" },
    { id: "half_day", label: "Half Day" },
    { id: "absent", label: "Absent" },
];
const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const adjForm = useForm({
    mode: modeOpts[0],
    attendance_date: props.date,
    employee_id: empOpts.value[0],
    department_id: deptOpts.value[0],
    in_time: "",
    out_time: "",
    status_override: statusOpts[0],
    notes: "",
});

const breakForm = useForm({
    adjusted_break_minutes: 60,
    reason: "",
});

const switchTab = (key) => {
    router.get(route("hrms.attendance.adjustments"), {
        tab: key,
        date: selectedDate.value,
        month: props.month,
        year: props.year,
    }, { preserveState: false });
};

const changeDate = () => {
    router.get(route("hrms.attendance.adjustments"), {
        tab: props.tab,
        date: selectedDate.value,
        month: props.month,
        year: props.year,
    }, { preserveState: false });
};

const submitAdjust = () => {
    adjForm.transform((d) => ({
        ...d,
        mode: unwrap(d.mode),
        employee_id: unwrap(d.employee_id),
        department_id: unwrap(d.department_id),
        status_override: unwrap(d.status_override),
    })).post(route("hrms.attendance.adjustments.apply"), {
        preserveScroll: true,
        onSuccess: () => {
            isAdjustModalActive.value = false;
        },
    });
};

const openBreakModal = (day) => {
    breakTargetDayId.value = day.id;
    breakForm.adjusted_break_minutes = day.break_minutes ?? 60;
    breakForm.reason = "";
    isBreakModalActive.value = true;
};

const submitBreak = () => {
    if (!breakTargetDayId.value) return;
    breakForm.post(route("hrms.attendance.days.break", breakTargetDayId.value), {
        preserveScroll: true,
        onSuccess: () => {
            isBreakModalActive.value = false;
            breakTargetDayId.value = null;
        },
    });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Attendance Adjustments & Approvals" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiClipboardCheckOutline" title="Attendance Adjustments, OT & Approvals" main>
                <div class="flex flex-wrap items-center">
                    <BaseButton
                        v-if="tab === 'adjust' && can('hrmsAttendance_edit')"
                        class="m-1"
                        :icon="mdiPackageVariantPlus"
                        color="success"
                        rounded-full
                        small
                        label="Manual Adjustment"
                        @click="isAdjustModalActive = true"
                    />
                    <BaseButton
                        v-if="tab === 'allowances' && can('hrmsAttendance_approve')"
                        class="m-1"
                        :icon="mdiCalculator"
                        color="info"
                        rounded-full
                        small
                        label="Compute Monthly Eligibility"
                        @click="router.post(route('hrms.attendance.allowances.compute'), { year, month })"
                    />
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msgType"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>

            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex flex-wrap gap-2">
                    <BaseButton
                        v-for="t in tabs"
                        :key="t.key"
                        :label="t.label"
                        :color="tab === t.key ? 'info' : 'white'"
                        :outline="tab !== t.key"
                        small
                        rounded-full
                        @click="switchTab(t.key)"
                    />
                </div>
                <div v-if="tab === 'adjust'" class="flex items-center gap-2 text-sm">
                    <label class="font-semibold">Date:</label>
                    <input v-model="selectedDate" type="date" @change="changeDate" class="rounded border-slate-300 dark:bg-slate-800 text-sm" />
                </div>
            </div>

            <CardBox has-table>
                <Table :resource="records" :resourceNeo="resourceNeo">
                    <template #cell(attendance_date)="{ item: r }">
                        <span>{{ r.attendance_date?.slice(0, 10) }}</span>
                    </template>
                    <template #cell(status)="{ item: r }">
                        <span class="uppercase text-xs font-bold">{{ r.status }}</span>
                    </template>
                    <template #cell(approval_status)="{ item: r }">
                        <span class="uppercase text-xs font-bold">{{ r.approval_status }}</span>
                    </template>
                    <template #cell(first_in_at)="{ item: r }">
                        <span>{{ r.first_in_at?.slice(11, 16) ?? "-" }}</span>
                    </template>
                    <template #cell(last_out_at)="{ item: r }">
                        <span>{{ r.last_out_at?.slice(11, 16) ?? "-" }}</span>
                    </template>
                    <template #cell(is_eligible)="{ item: r }">
                        <span>{{ r.is_eligible ? "Yes" : "No" }}</span>
                    </template>
                    <template #cell(computed_amount)="{ item: r }">
                        <span>₹{{ r.computed_amount }}</span>
                    </template>
                    <template #cell(actions)="{ item: r }">
                        <template v-if="tab === 'adjust' && can('hrmsAttendance_edit')">
                            <BaseButton color="info" :icon="mdiCoffee" small label="Break" @click="openBreakModal(r)" />
                        </template>
                        <template v-else-if="tab === 'regularization' && r.approval_status?.startsWith('pending') && can('hrmsAttendance_approve')">
                            <div class="flex gap-2">
                                <BaseButton color="success" :icon="mdiCheck" small @click="router.post(route('hrms.attendance.requests.act', r.id), { action: 'approve' })" />
                                <BaseButton color="danger" :icon="mdiClose" small @click="router.post(route('hrms.attendance.requests.act', r.id), { action: 'reject' })" />
                            </div>
                        </template>
                        <template v-else-if="tab === 'ot' && r.approval_status === 'pending' && can('hrmsAttendance_approve')">
                            <div class="flex gap-2">
                                <BaseButton color="success" :icon="mdiCheck" small @click="router.post(route('hrms.attendance.ot.act', r.id), { action: 'approve' })" />
                                <BaseButton color="danger" :icon="mdiClose" small @click="router.post(route('hrms.attendance.ot.act', r.id), { action: 'reject' })" />
                            </div>
                        </template>
                        <template v-else-if="tab === 'allowances' && r.status === 'pending' && can('hrmsAttendance_approve')">
                            <div class="flex gap-2">
                                <BaseButton color="success" :icon="mdiCheck" small @click="router.post(route('hrms.attendance.allowances.approve', r.id), { status: 'approved' })" />
                                <BaseButton color="danger" :icon="mdiClose" small @click="router.post(route('hrms.attendance.allowances.approve', r.id), { status: 'rejected' })" />
                            </div>
                        </template>
                    </template>
                </Table>
            </CardBox>
        </SectionMain>

        <CardBoxModal
            v-model="isAdjustModalActive"
            title="Manual Attendance Adjustment"
            buttonLabel="Apply Adjustment"
            button="info"
            has-cancel
            @confirm="submitAdjust"
        >
            <form @submit.prevent="submitAdjust" class="space-y-3">
                <FormField label="Adjustment Mode"><FormControl v-model="adjForm.mode" :options="modeOpts" /></FormField>
                <FormField label="Date"><FormControl v-model="adjForm.attendance_date" type="date" required /></FormField>
                <FormField v-if="unwrap(adjForm.mode) === 'individual'" label="Employee">
                    <FormControl v-model="adjForm.employee_id" :options="empOpts" />
                </FormField>
                <FormField v-else label="Department">
                    <FormControl v-model="adjForm.department_id" :options="deptOpts" />
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="In Time"><FormControl v-model="adjForm.in_time" type="time" /></FormField>
                    <FormField label="Out Time"><FormControl v-model="adjForm.out_time" type="time" /></FormField>
                </div>
                <FormField label="Status Override"><FormControl v-model="adjForm.status_override" :options="statusOpts" /></FormField>
                <FormField label="Notes"><FormControl v-model="adjForm.notes" /></FormField>
            </form>
        </CardBoxModal>

        <CardBoxModal
            v-model="isBreakModalActive"
            title="Adjust Break Minutes"
            buttonLabel="Save Break"
            button="info"
            has-cancel
            @confirm="submitBreak"
        >
            <form @submit.prevent="submitBreak" class="space-y-3">
                <FormField label="Adjusted Break (Minutes)" :error="breakForm.errors.adjusted_break_minutes">
                    <FormControl v-model="breakForm.adjusted_break_minutes" type="number" required />
                </FormField>
                <FormField label="Reason" :error="breakForm.errors.reason">
                    <FormControl v-model="breakForm.reason" required />
                </FormField>
            </form>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
