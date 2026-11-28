<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { mdiViewList, mdiAccountBoxMultiple } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import FormCheckRadio from "@/components/FormCheckRadio.vue";
import Multiselect from "vue-multiselect";
import "@/../css/vue-multiselect.css";

const props = defineProps({
    employee: {
        type: Object,
        default: null,
    },
    orgOptions: {
        type: Object,
        default: () => ({}),
    },
    hostUsers: {
        type: Array,
        default: () => [],
    },
});

const isEdit = !!props.employee;

const toOpt = (items, labelFn = (x) => x.name) => [
    { id: "", label: "-- Select --" },
    ...(items || []).map((x) => ({ id: x.id, label: labelFn(x) })),
];

const findOpt = (opts, id) => opts.find((o) => o.id === id) || opts[0];

const userOpts = toOpt(props.hostUsers, (u) => `${u.name} (${u.email})`);
const deptOpts = toOpt(props.orgOptions.departments);
const secOpts = toOpt(props.orgOptions.sections);
const desigOpts = toOpt(props.orgOptions.designations);
const catOpts = toOpt(props.orgOptions.categories);
const shiftOpts = toOpt(props.orgOptions.shifts);
const locOpts = toOpt(props.orgOptions.workLocations);
const approverOpts = toOpt(props.orgOptions.approvers, (a) => `${a.emp_code} - ${a.full_name}`);
const genderOpts = [
    { id: "male", label: "Male" },
    { id: "female", label: "Female" },
    { id: "other", label: "Other" },
];
const statusOpts = [
    { id: "active", label: "Active" },
    { id: "probation", label: "Probation" },
    { id: "notice", label: "Notice" },
    { id: "resigned", label: "Resigned" },
    { id: "terminated", label: "Terminated" },
];
const typeOpts = [
    { id: "permanent", label: "Permanent" },
    { id: "contract", label: "Contract" },
    { id: "intern", label: "Intern" },
    { id: "daily_wage", label: "Daily Wage" },
];
const geofenceOpts = [
    { id: "inherit", label: "Inherit Location" },
    { id: "strict", label: "Strict Block" },
    { id: "flag", label: "Allow & Flag" },
    { id: "bypass", label: "Bypass" },
];

const form = useForm({
    emp_code: props.employee?.emp_code ?? "",
    user_id: findOpt(userOpts, props.employee?.user_id ?? ""),
    first_name: props.employee?.first_name ?? "",
    last_name: props.employee?.last_name ?? "",
    gender: findOpt(genderOpts, props.employee?.gender ?? "male"),
    date_of_joining: props.employee?.date_of_joining?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
    employment_status: findOpt(statusOpts, props.employee?.employment_status ?? "active"),
    employment_type: findOpt(typeOpts, props.employee?.employment_type ?? "permanent"),
    department_id: findOpt(deptOpts, props.employee?.department_id ?? ""),
    section_id: findOpt(secOpts, props.employee?.section_id ?? ""),
    designation_id: findOpt(desigOpts, props.employee?.designation_id ?? ""),
    category_id: findOpt(catOpts, props.employee?.category_id ?? ""),
    default_shift_id: findOpt(shiftOpts, props.employee?.default_shift_id ?? ""),
    work_location_id: findOpt(locOpts, props.employee?.work_location_id ?? ""),
    assigned_locations: (props.employee?.assigned_work_locations || []).map((l) => ({ id: l.id, name: l.name })),
    l1_approver_id: findOpt(approverOpts, props.employee?.l1_approver_id ?? ""),
    l2_approver_id: findOpt(approverOpts, props.employee?.l2_approver_id ?? ""),
    backup_approver_id: findOpt(approverOpts, props.employee?.backup_approver_id ?? ""),
    geofence_mode: findOpt(geofenceOpts, props.employee?.geofence_mode ?? "inherit"),
    mobile_attendance_enabled: props.employee?.mobile_attendance_enabled ?? true,
    kiosk_pin: "",
    official_email: props.employee?.official_email ?? "",
    phone: props.employee?.phone ?? "",
    pan_number: props.employee?.pan_number ?? "",
    uan_number: props.employee?.uan_number ?? "",
    esic_number: props.employee?.esic_number ?? "",
    bank_name: props.employee?.bank_name ?? "",
    bank_account_no: props.employee?.bank_account_no ?? "",
    bank_ifsc: props.employee?.bank_ifsc ?? "",
    epf_enabled: props.employee?.epf_enabled ?? true,
    esic_enabled: props.employee?.esic_enabled ?? false,
    pt_enabled: props.employee?.pt_enabled ?? true,
    lwf_enabled: props.employee?.lwf_enabled ?? false,
    tax_regime: props.employee?.tax_regime ?? "new",
});

const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const submitForm = () => {
    const transformed = form.transform((data) => ({
        ...data,
        user_id: unwrap(data.user_id),
        gender: unwrap(data.gender),
        employment_status: unwrap(data.employment_status),
        employment_type: unwrap(data.employment_type),
        department_id: unwrap(data.department_id),
        section_id: unwrap(data.section_id),
        designation_id: unwrap(data.designation_id),
        category_id: unwrap(data.category_id),
        default_shift_id: unwrap(data.default_shift_id),
        work_location_id: unwrap(data.work_location_id),
        assigned_location_ids: (data.assigned_locations || []).map((l) => l.id),
        l1_approver_id: unwrap(data.l1_approver_id),
        l2_approver_id: unwrap(data.l2_approver_id),
        backup_approver_id: unwrap(data.backup_approver_id),
        geofence_mode: unwrap(data.geofence_mode),
    }));

    if (isEdit) {
        transformed.put(route("hrms.employees.update", props.employee.id));
    } else {
        transformed.post(route("hrms.employees.store"));
    }
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Employees" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="mdiAccountBoxMultiple"
                :title="isEdit ? `Edit Employee: ${employee.emp_code}` : 'Employees'"
                main
            >
                <div class="flex">
                    <Link :href="route('hrms.employees.index')">
                        <BaseButton class="m-2" :icon="mdiViewList" color="success" rounded-full small label="List Employees" />
                    </Link>
                </div>
            </SectionTitleLineWithButton>

            <form @submit.prevent="submitForm" autocomplete="off">
                <CardBox>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <FormField label="Emp Code" :error="form.errors.emp_code">
                            <FormControl v-model="form.emp_code" required />
                        </FormField>
                        <FormField label="Linked SignageFlow User" :error="form.errors.user_id">
                            <FormControl v-model="form.user_id" :options="userOpts" />
                        </FormField>
                        <FormField label="First Name" :error="form.errors.first_name">
                            <FormControl v-model="form.first_name" required />
                        </FormField>
                        <FormField label="Last Name" :error="form.errors.last_name">
                            <FormControl v-model="form.last_name" />
                        </FormField>
                        <FormField label="Gender" :error="form.errors.gender">
                            <FormControl v-model="form.gender" :options="genderOpts" />
                        </FormField>
                        <FormField label="Date of Joining" :error="form.errors.date_of_joining">
                            <FormControl v-model="form.date_of_joining" type="date" required />
                        </FormField>
                        <FormField label="Employment Status" :error="form.errors.employment_status">
                            <FormControl v-model="form.employment_status" :options="statusOpts" />
                        </FormField>
                        <FormField label="Employment Type" :error="form.errors.employment_type">
                            <FormControl v-model="form.employment_type" :options="typeOpts" />
                        </FormField>
                        <FormField label="Department" :error="form.errors.department_id">
                            <FormControl v-model="form.department_id" :options="deptOpts" />
                        </FormField>
                        <FormField label="Designation" :error="form.errors.designation_id">
                            <FormControl v-model="form.designation_id" :options="desigOpts" />
                        </FormField>
                        <FormField label="Default Shift" :error="form.errors.default_shift_id">
                            <FormControl v-model="form.default_shift_id" :options="shiftOpts" />
                        </FormField>
                        <FormField label="Primary Work Location" :error="form.errors.work_location_id">
                            <FormControl v-model="form.work_location_id" :options="locOpts" />
                        </FormField>
                        <FormField label="Assigned Work Locations">
                            <Multiselect
                                v-model="form.assigned_locations"
                                :options="orgOptions.workLocations || []"
                                :multiple="true"
                                track-by="id"
                                label="name"
                                placeholder="Select Work Locations"
                                select-label=""
                            />
                        </FormField>
                        <FormField label="Geofence Mode" :error="form.errors.geofence_mode">
                            <FormControl v-model="form.geofence_mode" :options="geofenceOpts" />
                        </FormField>
                        <FormField label="L1 Approver" :error="form.errors.l1_approver_id">
                            <FormControl v-model="form.l1_approver_id" :options="approverOpts" />
                        </FormField>
                        <FormField label="L2 Approver" :error="form.errors.l2_approver_id">
                            <FormControl v-model="form.l2_approver_id" :options="approverOpts" />
                        </FormField>
                        <FormField label="Backup Officer" :error="form.errors.backup_approver_id">
                            <FormControl v-model="form.backup_approver_id" :options="approverOpts" />
                        </FormField>
                        <FormField label="Kiosk PIN" help="Leave blank to keep existing PIN" :error="form.errors.kiosk_pin">
                            <FormControl v-model="form.kiosk_pin" type="password" autocomplete="new-password" />
                        </FormField>
                        <FormField label="Official Email" :error="form.errors.official_email">
                            <FormControl v-model="form.official_email" type="email" />
                        </FormField>
                        <FormField label="Phone" :error="form.errors.phone">
                            <FormControl v-model="form.phone" />
                        </FormField>
                        <FormField label="PAN Number" :error="form.errors.pan_number">
                            <FormControl v-model="form.pan_number" />
                        </FormField>
                        <FormField label="UAN Number" :error="form.errors.uan_number">
                            <FormControl v-model="form.uan_number" />
                        </FormField>
                        <FormField label="Bank Account No" :error="form.errors.bank_account_no">
                            <FormControl v-model="form.bank_account_no" />
                        </FormField>
                        <FormField label="Bank IFSC" :error="form.errors.bank_ifsc">
                            <FormControl v-model="form.bank_ifsc" />
                        </FormField>
                        <FormField label="Mobile Attendance">
                            <FormCheckRadio type="switch" v-model="form.mobile_attendance_enabled" name="mobile_att" label="ON" prelabel="OFF" />
                        </FormField>
                        <FormField label="EPF Enabled">
                            <FormCheckRadio type="switch" v-model="form.epf_enabled" name="epf_enabled" label="ON" prelabel="OFF" />
                        </FormField>
                    </div>
                </CardBox>

                <div class="mt-4 flex">
                    <BaseButton class="mr-2" type="submit" small color="info" :label="isEdit ? 'Update' : 'Save'" :disabled="form.processing" />
                    <Link :href="route('hrms.employees.index')">
                        <BaseButton type="reset" small color="info" outline label="Cancel" />
                    </Link>
                </div>
            </form>
        </SectionMain>
    </LayoutAuthenticated>
</template>
