<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiCalendarCheck,
    mdiPackageVariantPlus,
    mdiFileEdit,
    mdiTrashCan,
    mdiAlert,
    mdiOpenInNew,
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
    records: Object,
    hostLocations: Array,
    workLocations: Array,
    shifts: Array,
    employees: Array,
    resourceNeo: Object,
});

const tabs = [
    { key: "shifts", label: "Shifts", singular: "Shift" },
    { key: "locations", label: "Work Locations", singular: "Work Location" },
    { key: "kiosks", label: "Tablet Kiosks", singular: "Kiosk" },
    { key: "rosters", label: "Shift Rosters", singular: "Roster" },
    { key: "allowances", label: "Attendance Allowances", singular: "Allowance Rule" },
];
const currentTabMeta = computed(() => tabs.find((t) => t.key === props.tab) ?? tabs[0]);

const toOpt = (items, labelFn = (x) => x.name) => [
    { id: "", label: "-- Select --" },
    ...(items || []).map((x) => ({ id: x.id, label: labelFn(x) })),
];
const hostLocOpts = computed(() => toOpt(props.hostLocations));
const workLocOpts = computed(() => toOpt(props.workLocations));
const shiftOpts = computed(() => toOpt(props.shifts));
const empOpts = computed(() => toOpt(props.employees, (e) => `${e.emp_code} - ${e.full_name}`));
const geofenceOpts = [
    { id: "flag", label: "Allow & Flag Out-of-Range" },
    { id: "strict", label: "Strict Geofence Block" },
    { id: "disabled", label: "Disabled" },
];
const findOpt = (opts, id) => opts.find((o) => o.id === id) || opts[0];
const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const isFormModalActive = ref(false);
const isDeleteModalActive = ref(false);
const editingId = ref(null);
const deletingId = ref(null);

const shiftForm = useForm({
    code: "", name: "", start_time: "09:30", end_time: "18:30",
    is_overnight: false, grace_in_minutes: 15, grace_out_minutes: 10,
    break_minutes: 60, half_day_minutes: 240, full_day_minutes: 480,
    ot_eligible: true, ot_min_threshold_minutes: 30, ot_multiplier: 1.5, is_active: true,
});

const locForm = useForm({
    code: "", name: "", host_location_id: findOpt(hostLocOpts.value, ""),
    latitude: "", longitude: "", radius_meters: 150,
    geofence_mode: geofenceOpts[0], require_selfie: false, address: "", is_active: true,
});

const kioskForm = useForm({
    code: "", name: "", work_location_id: findOpt(workLocOpts.value, ""),
    require_snapshot: false, allow_pin: true, allow_qr: true, is_active: true,
});

const rosterForm = useForm({
    employee_id: findOpt(empOpts.value, ""), shift_id: findOpt(shiftOpts.value, ""),
    effective_from: new Date().toISOString().slice(0, 10), effective_to: "",
});

const allowanceForm = useForm({
    code: "", name: "", amount: 1000, max_absences_allowed: 0,
    max_lates_allowed: 2, min_present_days: 22, is_active: true,
});

const openCreateModal = () => {
    editingId.value = null;
    shiftForm.reset(); shiftForm.clearErrors();
    locForm.reset(); locForm.clearErrors(); locForm.host_location_id = findOpt(hostLocOpts.value, ""); locForm.geofence_mode = geofenceOpts[0];
    kioskForm.reset(); kioskForm.clearErrors(); kioskForm.work_location_id = findOpt(workLocOpts.value, "");
    rosterForm.reset(); rosterForm.clearErrors(); rosterForm.employee_id = findOpt(empOpts.value, ""); rosterForm.shift_id = findOpt(shiftOpts.value, "");
    allowanceForm.reset(); allowanceForm.clearErrors();
    isFormModalActive.value = true;
};

const openEditModal = (row) => {
    editingId.value = row.id;
    if (props.tab === "shifts") {
        Object.assign(shiftForm, {
            code: row.code, name: row.name,
            start_time: row.start_time?.slice(0, 5) ?? "09:30",
            end_time: row.end_time?.slice(0, 5) ?? "18:30",
            is_overnight: !!row.is_overnight,
            grace_in_minutes: row.grace_in_minutes ?? 15,
            grace_out_minutes: row.grace_out_minutes ?? 10,
            break_minutes: row.break_minutes ?? 60,
            ot_eligible: !!row.ot_eligible,
            ot_multiplier: row.ot_multiplier ?? 1.5,
            is_active: !!row.is_active,
        });
    } else if (props.tab === "locations") {
        Object.assign(locForm, {
            code: row.code, name: row.name,
            host_location_id: findOpt(hostLocOpts.value, row.host_location_id ?? ""),
            latitude: row.latitude ?? "", longitude: row.longitude ?? "",
            radius_meters: row.radius_meters ?? 150,
            geofence_mode: findOpt(geofenceOpts, row.geofence_mode ?? "flag"),
            require_selfie: !!row.require_selfie,
            address: row.address ?? "",
            is_active: !!row.is_active,
        });
    } else if (props.tab === "kiosks") {
        Object.assign(kioskForm, {
            code: row.code, name: row.name,
            work_location_id: findOpt(workLocOpts.value, row.work_location_id ?? ""),
            require_snapshot: !!row.require_snapshot,
            allow_pin: !!row.allow_pin,
            allow_qr: !!row.allow_qr,
            is_active: !!row.is_active,
        });
    } else if (props.tab === "rosters") {
        Object.assign(rosterForm, {
            employee_id: findOpt(empOpts.value, row.employee_id ?? ""),
            shift_id: findOpt(shiftOpts.value, row.shift_id ?? ""),
            effective_from: row.effective_from?.slice(0, 10) ?? "",
            effective_to: row.effective_to?.slice(0, 10) ?? "",
        });
    } else if (props.tab === "allowances") {
        Object.assign(allowanceForm, {
            code: row.code, name: row.name, amount: row.amount,
            max_absences_allowed: row.max_absences_allowed,
            max_lates_allowed: row.max_lates_allowed,
            min_present_days: row.min_present_days,
            is_active: !!row.is_active,
        });
    }
    isFormModalActive.value = true;
};

const submitForm = () => {
    const opts = { preserveScroll: true, onSuccess: () => { isFormModalActive.value = false; editingId.value = null; } };
    if (props.tab === "shifts") {
        editingId.value
            ? shiftForm.put(route("hrms.attendance.shifts.update", editingId.value), opts)
            : shiftForm.post(route("hrms.attendance.shifts.store"), opts);
    } else if (props.tab === "locations") {
        const tf = locForm.transform((d) => ({ ...d, host_location_id: unwrap(d.host_location_id), geofence_mode: unwrap(d.geofence_mode) }));
        editingId.value
            ? tf.put(route("hrms.attendance.workLocations.update", editingId.value), opts)
            : tf.post(route("hrms.attendance.workLocations.store"), opts);
    } else if (props.tab === "kiosks") {
        const tf = kioskForm.transform((d) => ({ ...d, work_location_id: unwrap(d.work_location_id) }));
        editingId.value
            ? tf.put(route("hrms.attendance.kiosks.update", editingId.value), opts)
            : tf.post(route("hrms.attendance.kiosks.store"), opts);
    } else if (props.tab === "rosters") {
        const tf = rosterForm.transform((d) => ({ ...d, employee_id: unwrap(d.employee_id), shift_id: unwrap(d.shift_id) }));
        editingId.value
            ? tf.put(route("hrms.attendance.rosters.update", editingId.value), opts)
            : tf.post(route("hrms.attendance.rosters.store"), opts);
    } else if (props.tab === "allowances") {
        editingId.value
            ? allowanceForm.put(route("hrms.attendance.allowances.update", editingId.value), opts)
            : allowanceForm.post(route("hrms.attendance.allowances.store"), opts);
    }
};

const confirmDelete = () => {
    if (!deletingId.value) return;
    const routeName = {
        shifts: "hrms.attendance.shifts.destroy",
        locations: "hrms.attendance.workLocations.destroy",
        kiosks: "hrms.attendance.kiosks.destroy",
        rosters: "hrms.attendance.rosters.destroy",
        allowances: "hrms.attendance.allowances.destroy",
    }[props.tab];
    router.delete(route(routeName, deletingId.value), {
        preserveScroll: true,
        onFinish: () => { deletingId.value = null; },
    });
};

const switchTab = (key) => router.get(route("hrms.attendance.setup"), { tab: key }, { preserveState: false });
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Attendance Setup" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCalendarCheck" title="Attendance Setup & Clocking Channels" main>
                <div class="flex flex-wrap items-center">
                    <a
                        v-if="tab === 'kiosks'"
                        :href="route('hrms.kiosk.terminal')"
                        target="_blank"
                    >
                        <BaseButton class="m-1" :icon="mdiOpenInNew" color="info" rounded-full small label="Open Kiosk" />
                    </a>
                    <BaseButton
                        v-if="can('hrmsAttendanceSetup_create')"
                        class="m-1"
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
                    <template #cell(start_time)="{ item: r }">
                        <span>{{ r.start_time?.slice(0, 5) }} - {{ r.end_time?.slice(0, 5) }}</span>
                    </template>
                    <template #cell(grace_in_minutes)="{ item: r }">
                        <span>{{ r.grace_in_minutes }}m / {{ r.grace_out_minutes }}m</span>
                    </template>
                    <template #cell(ot_multiplier)="{ item: r }">
                        <span>{{ r.ot_eligible ? `${r.ot_multiplier}x` : "No" }}</span>
                    </template>
                    <template #cell(latitude)="{ item: r }">
                        <span class="text-xs">{{ r.latitude ?? "-" }}, {{ r.longitude ?? "-" }}</span>
                    </template>
                    <template #cell(geofence_mode)="{ item: r }">
                        <span class="uppercase text-xs font-bold">{{ r.geofence_mode }}</span>
                    </template>
                    <template #cell(effective_from)="{ item: r }">
                        <span>{{ r.effective_from?.slice(0, 10) }}</span>
                    </template>
                    <template #cell(effective_to)="{ item: r }">
                        <span>{{ r.effective_to?.slice(0, 10) ?? "Open" }}</span>
                    </template>
                    <template #cell(amount)="{ item: r }">
                        <span>₹{{ r.amount }}</span>
                    </template>
                    <template #cell(min_present_days)="{ item: r }">
                        <span class="text-xs">Abs ≤ {{ r.max_absences_allowed }}, Late ≤ {{ r.max_lates_allowed }}, Present ≥ {{ r.min_present_days }}</span>
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
                            v-if="can('hrmsAttendanceSetup_edit')"
                            class="mr-2"
                            color="info"
                            :icon="mdiFileEdit"
                            small
                            @click="openEditModal(r)"
                        />
                        <BaseButton
                            v-if="can('hrmsAttendanceSetup_delete')"
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
            <form v-if="tab === 'shifts'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Code" :error="shiftForm.errors.code"><FormControl v-model="shiftForm.code" required /></FormField>
                <FormField label="Name" :error="shiftForm.errors.name"><FormControl v-model="shiftForm.name" required /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Start Time"><FormControl v-model="shiftForm.start_time" type="time" required /></FormField>
                    <FormField label="End Time"><FormControl v-model="shiftForm.end_time" type="time" required /></FormField>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <FormField label="Grace In (m)"><FormControl v-model="shiftForm.grace_in_minutes" type="number" /></FormField>
                    <FormField label="Grace Out (m)"><FormControl v-model="shiftForm.grace_out_minutes" type="number" /></FormField>
                    <FormField label="OT Mult"><FormControl v-model="shiftForm.ot_multiplier" type="number" step="0.1" /></FormField>
                </div>
                <FormField label="Overnight Shift"><FormCheckRadio type="switch" v-model="shiftForm.is_overnight" name="is_overnight" label="Yes" prelabel="No" /></FormField>
                <FormField label="Active"><FormCheckRadio type="switch" v-model="shiftForm.is_active" name="shift_active" label="ON" prelabel="OFF" /></FormField>
            </form>

            <form v-else-if="tab === 'locations'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Code" :error="locForm.errors.code"><FormControl v-model="locForm.code" required /></FormField>
                <FormField label="Name" :error="locForm.errors.name"><FormControl v-model="locForm.name" required /></FormField>
                <FormField label="SignageFlow Location"><FormControl v-model="locForm.host_location_id" :options="hostLocOpts" /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Latitude"><FormControl v-model="locForm.latitude" type="number" step="0.0000001" /></FormField>
                    <FormField label="Longitude"><FormControl v-model="locForm.longitude" type="number" step="0.0000001" /></FormField>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Radius (m)"><FormControl v-model="locForm.radius_meters" type="number" /></FormField>
                    <FormField label="Geofence Mode"><FormControl v-model="locForm.geofence_mode" :options="geofenceOpts" /></FormField>
                </div>
                <FormField label="Require Selfie"><FormCheckRadio type="switch" v-model="locForm.require_selfie" name="require_selfie" label="Yes" prelabel="No" /></FormField>
            </form>

            <form v-else-if="tab === 'kiosks'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Code" :error="kioskForm.errors.code"><FormControl v-model="kioskForm.code" required /></FormField>
                <FormField label="Name" :error="kioskForm.errors.name"><FormControl v-model="kioskForm.name" required /></FormField>
                <FormField label="Work Location"><FormControl v-model="kioskForm.work_location_id" :options="workLocOpts" /></FormField>
                <FormField label="Allow PIN"><FormCheckRadio type="switch" v-model="kioskForm.allow_pin" name="allow_pin" label="Yes" prelabel="No" /></FormField>
                <FormField label="Allow QR"><FormCheckRadio type="switch" v-model="kioskForm.allow_qr" name="allow_qr" label="Yes" prelabel="No" /></FormField>
            </form>

            <form v-else-if="tab === 'rosters'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Employee" :error="rosterForm.errors.employee_id"><FormControl v-model="rosterForm.employee_id" :options="empOpts" /></FormField>
                <FormField label="Shift" :error="rosterForm.errors.shift_id"><FormControl v-model="rosterForm.shift_id" :options="shiftOpts" /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Effective From"><FormControl v-model="rosterForm.effective_from" type="date" required /></FormField>
                    <FormField label="Effective To"><FormControl v-model="rosterForm.effective_to" type="date" /></FormField>
                </div>
            </form>

            <form v-else-if="tab === 'allowances'" @submit.prevent="submitForm" class="space-y-3">
                <FormField label="Code" :error="allowanceForm.errors.code"><FormControl v-model="allowanceForm.code" required /></FormField>
                <FormField label="Name" :error="allowanceForm.errors.name"><FormControl v-model="allowanceForm.name" required /></FormField>
                <FormField label="Amount (INR)" :error="allowanceForm.errors.amount"><FormControl v-model="allowanceForm.amount" type="number" required /></FormField>
                <div class="grid grid-cols-3 gap-3">
                    <FormField label="Max Absences"><FormControl v-model="allowanceForm.max_absences_allowed" type="number" /></FormField>
                    <FormField label="Max Lates"><FormControl v-model="allowanceForm.max_lates_allowed" type="number" /></FormField>
                    <FormField label="Min Present"><FormControl v-model="allowanceForm.min_present_days" type="number" /></FormField>
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
