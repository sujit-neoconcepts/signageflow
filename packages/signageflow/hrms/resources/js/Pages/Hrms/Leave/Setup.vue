<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiCalendarCheck,
    mdiPackageVariantPlus,
    mdiFileEdit,
    mdiTrashCan,
    mdiAlert,
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
import FormCheckRadio from "@/components/FormCheckRadio.vue";
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    tab: String,
    currentFy: String,
    records: Object,
    companies: Array,
    departments: Array,
    workLocations: Array,
    resourceNeo: Object,
});

const tabs = [
    { key: "leave_types", label: "Leave Types", singular: "Leave Type" },
    { key: "fwa_policies", label: "FWA Policies", singular: "FWA Policy" },
    { key: "calendar_controls", label: "Calendar Controls", singular: "Calendar Entry" },
];
const currentTabMeta = computed(() => tabs.find((t) => t.key === props.tab) ?? tabs[0]);

const accrualOpts = [
    { id: "monthly", label: "Monthly Accrual" },
    { id: "quarterly", label: "Quarterly Accrual" },
    { id: "annual", label: "Annual (Apr 1)" },
    { id: "none", label: "Manual / None" },
];
const genderOpts = [
    { id: "all", label: "All" },
    { id: "female", label: "Female" },
    { id: "male", label: "Male" },
];
const fwaModeOpts = [
    { id: "wfh", label: "Work From Home" },
    { id: "flexi_hours", label: "Flexi Hours" },
    { id: "remote_site", label: "Remote Site" },
];
const holidayTypeOpts = [
    { id: "public_holiday", label: "Public Holiday" },
    { id: "restricted_day", label: "Restricted Leave Day" },
    { id: "blocked_annual_leave", label: "Block Annual Leave" },
    { id: "rest_day", label: "Rest Day / Off Day" },
];
const toOpt = (items) => [
    { id: "", label: "-- All --" },
    ...(items || []).map((x) => ({ id: x.id, label: x.name })),
];
const deptOpts = computed(() => toOpt(props.departments));
const locOpts = computed(() => toOpt(props.workLocations));
const findOpt = (opts, id) => opts.find((o) => o.id === id) || opts[0];
const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const isFormModalActive = ref(false);
const isDeleteModalActive = ref(false);
const editingId = ref(null);
const deletingId = ref(null);

const ltForm = useForm({
    code: "", name: "", is_paid: true, allow_half_day: true, requires_attachment: false,
    attachment_after_days: 0, accrual_frequency: accrualOpts[0], annual_quota: 12,
    max_carry_forward: 0, allow_encashment: false, gender_restriction: genderOpts[0],
    min_tenure_days: 0, is_statutory: false, is_active: true,
});

const fwaForm = useForm({
    code: "", name: "", mode: fwaModeOpts[0], max_days_per_month: 4, requires_approval: true, is_active: true,
});

const holidayForm = useForm({
    name: "", start_date: "", end_date: "", holiday_type: holidayTypeOpts[0],
    department_id: deptOpts.value[0], work_location_id: locOpts.value[0],
});

const openCreateModal = () => {
    editingId.value = null;
    ltForm.reset(); ltForm.clearErrors(); ltForm.accrual_frequency = accrualOpts[0]; ltForm.gender_restriction = genderOpts[0];
    fwaForm.reset(); fwaForm.clearErrors(); fwaForm.mode = fwaModeOpts[0];
    holidayForm.reset(); holidayForm.clearErrors(); holidayForm.holiday_type = holidayTypeOpts[0];
    holidayForm.department_id = deptOpts.value[0]; holidayForm.work_location_id = locOpts.value[0];
    isFormModalActive.value = true;
};

const openEditModal = (row) => {
    editingId.value = row.id;
    if (props.tab === "leave_types") {
        Object.assign(ltForm, {
            code: row.code, name: row.name,
            is_paid: !!row.is_paid, allow_half_day: !!row.allow_half_day,
            requires_attachment: !!row.requires_attachment,
            attachment_after_days: row.attachment_after_days ?? 0,
            accrual_frequency: findOpt(accrualOpts, row.accrual_frequency ?? "monthly"),
            annual_quota: row.annual_quota ?? 12,
            max_carry_forward: row.max_carry_forward ?? 0,
            allow_encashment: !!row.allow_encashment,
            gender_restriction: findOpt(genderOpts, row.gender_restriction ?? "all"),
            min_tenure_days: row.min_tenure_days ?? 0,
            is_active: !!row.is_active,
        });
    } else if (props.tab === "fwa_policies") {
        Object.assign(fwaForm, {
            code: row.code, name: row.name,
            mode: findOpt(fwaModeOpts, row.mode ?? "wfh"),
            max_days_per_month: row.max_days_per_month ?? 4,
            requires_approval: !!row.requires_approval,
            is_active: !!row.is_active,
        });
    } else if (props.tab === "calendar_controls") {
        Object.assign(holidayForm, {
            name: row.name,
            start_date: row.start_date?.slice(0, 10) ?? "",
            end_date: row.end_date?.slice(0, 10) ?? "",
            holiday_type: findOpt(holidayTypeOpts, row.holiday_type ?? "public_holiday"),
            department_id: findOpt(deptOpts.value, row.department_id ?? ""),
            work_location_id: findOpt(locOpts.value, row.work_location_id ?? ""),
        });
    }
    isFormModalActive.value = true;
};

const submitForm = () => {
    const opts = { preserveScroll: true, onSuccess: () => { isFormModalActive.value = false; editingId.value = null; } };
    if (props.tab === "leave_types") {
        const tf = ltForm.transform((d) => ({
            ...d,
            accrual_frequency: unwrap(d.accrual_frequency),
            gender_restriction: unwrap(d.gender_restriction),
        }));
        editingId.value
            ? tf.put(route("hrms.leave.types.update", editingId.value), opts)
            : tf.post(route("hrms.leave.types.store"), opts);
    } else if (props.tab === "fwa_policies") {
        const tf = fwaForm.transform((d) => ({ ...d, mode: unwrap(d.mode) }));
        editingId.value
            ? tf.put(route("hrms.leave.fwaPolicies.update", editingId.value), opts)
            : tf.post(route("hrms.leave.fwaPolicies.store"), opts);
    } else if (props.tab === "calendar_controls") {
        const tf = holidayForm.transform((d) => ({
            ...d,
            holiday_type: unwrap(d.holiday_type),
            department_id: unwrap(d.department_id),
            work_location_id: unwrap(d.work_location_id),
        }));
        editingId.value
            ? tf.put(route("hrms.leave.holidays.update", editingId.value), opts)
            : tf.post(route("hrms.leave.holidays.store"), opts);
    }
};

const confirmDelete = () => {
    if (!deletingId.value) return;
    const routeName = {
        leave_types: "hrms.leave.types.destroy",
        fwa_policies: "hrms.leave.fwaPolicies.destroy",
        calendar_controls: "hrms.leave.holidays.destroy",
    }[props.tab];
    router.delete(route(routeName, deletingId.value), {
        preserveScroll: true,
        onFinish: () => { deletingId.value = null; },
    });
};

const switchTab = (key) => router.get(route("hrms.leave.setup"), { tab: key }, { preserveState: false });
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Leave & Calendar Setup" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCalendarCheck" :title="`Leave Policy & Calendar Setup (FY ${currentFy})`" main>
                <div class="flex">
                    <BaseButton
                        v-if="can('hrmsLeaveSetup_create')"
                        class="m-2"
                        :icon="mdiPackageVariantPlus"
                        color="success"
                        rounded-full
                        small
                        label="Add New"
                        @click="openCreateModal"
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

            <div class="flex flex-wrap gap-2 mb-4">
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

            <CardBox has-table>
                <Table :resource="records" :resourceNeo="resourceNeo">
                    <template #cell(accrual_frequency)="{ item: r }">
                        <span class="uppercase text-xs font-semibold">{{ r.accrual_frequency }}</span>
                    </template>
                    <template #cell(annual_quota)="{ item: r }">
                        <span>{{ r.annual_quota }} / CF {{ r.max_carry_forward }}</span>
                    </template>
                    <template #cell(is_paid)="{ item: r }">
                        <span>{{ r.is_paid ? "Paid" : "Unpaid" }}</span>
                    </template>
                    <template #cell(holiday_type)="{ item: r }">
                        <span class="uppercase text-xs font-bold">{{ r.holiday_type }}</span>
                    </template>
                    <template #cell(start_date)="{ item: r }">
                        <span>{{ r.start_date?.slice(0, 10) }}</span>
                    </template>
                    <template #cell(end_date)="{ item: r }">
                        <span>{{ r.end_date?.slice(0, 10) }}</span>
                    </template>
                    <template #cell(is_active)="{ item: r }">
                        <span
                            class="px-2 py-0.5 rounded text-xs font-bold uppercase"
                            :class="r.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-200 text-slate-600'"
                        >
                            {{ r.is_active ? "Active" : "Inactive" }}
                        </span>
                    </template>
                    <template #cell(actions)="{ item: r }">
                        <BaseButton
                            v-if="can('hrmsLeaveSetup_edit')"
                            class="mr-2"
                            color="info"
                            :icon="mdiFileEdit"
                            small
                            @click="openEditModal(r)"
                        />
                        <BaseButton
                            v-if="can('hrmsLeaveSetup_delete')"
                            color="danger"
                            :icon="mdiTrashCan"
                            small
                            @click="deletingId = r.id; isDeleteModalActive = true;"
                        />
                    </template>
                </Table>
            </CardBox>
        </SectionMain>

        <CardBoxModal
            v-model="isFormModalActive"
            :title="`${editingId ? 'Edit' : 'Add'} ${currentTabMeta.singular}`"
            :buttonLabel="editingId ? 'Update' : 'Save'"
            button="info"
            has-cancel
            @confirm="submitForm"
        >
            <form v-if="tab === 'leave_types'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Code" :error="ltForm.errors.code"><FormControl v-model="ltForm.code" required /></FormField>
                <FormField label="Name" :error="ltForm.errors.name"><FormControl v-model="ltForm.name" required /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Annual Quota"><FormControl v-model="ltForm.annual_quota" type="number" step="0.5" /></FormField>
                    <FormField label="Max Carry Forward"><FormControl v-model="ltForm.max_carry_forward" type="number" step="0.5" /></FormField>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Accrual"><FormControl v-model="ltForm.accrual_frequency" :options="accrualOpts" /></FormField>
                    <FormField label="Gender Restriction"><FormControl v-model="ltForm.gender_restriction" :options="genderOpts" /></FormField>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Paid Leave"><FormCheckRadio type="switch" v-model="ltForm.is_paid" name="is_paid" label="Paid" prelabel="Unpaid" /></FormField>
                    <FormField label="Allow Half Day"><FormCheckRadio type="switch" v-model="ltForm.allow_half_day" name="allow_half" label="Yes" prelabel="No" /></FormField>
                </div>
                <FormField label="Active"><FormCheckRadio type="switch" v-model="ltForm.is_active" name="lt_active" label="ON" prelabel="OFF" /></FormField>
            </form>

            <form v-else-if="tab === 'fwa_policies'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Code" :error="fwaForm.errors.code"><FormControl v-model="fwaForm.code" required /></FormField>
                <FormField label="Policy Name" :error="fwaForm.errors.name"><FormControl v-model="fwaForm.name" required /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Mode"><FormControl v-model="fwaForm.mode" :options="fwaModeOpts" /></FormField>
                    <FormField label="Max Days / Month"><FormControl v-model="fwaForm.max_days_per_month" type="number" /></FormField>
                </div>
                <FormField label="Active"><FormCheckRadio type="switch" v-model="fwaForm.is_active" name="fwa_active" label="ON" prelabel="OFF" /></FormField>
            </form>

            <form v-else-if="tab === 'calendar_controls'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Label" :error="holidayForm.errors.name"><FormControl v-model="holidayForm.name" required /></FormField>
                <FormField label="Type"><FormControl v-model="holidayForm.holiday_type" :options="holidayTypeOpts" /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Start Date"><FormControl v-model="holidayForm.start_date" type="date" required /></FormField>
                    <FormField label="End Date"><FormControl v-model="holidayForm.end_date" type="date" required /></FormField>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Department"><FormControl v-model="holidayForm.department_id" :options="deptOpts" /></FormField>
                    <FormField label="Work Location"><FormControl v-model="holidayForm.work_location_id" :options="locOpts" /></FormField>
                </div>
            </form>
        </CardBoxModal>

        <CardBoxModal
            v-model="isDeleteModalActive"
            buttonLabel="Confirm"
            title="Please confirm"
            button="danger"
            has-cancel
            @confirm="confirmDelete"
        >
            <p>Are you sure you want to delete this {{ currentTabMeta.singular.toLowerCase() }}?</p>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
