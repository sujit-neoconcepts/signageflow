<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import {
    mdiSitemap,
    mdiPackageVariantPlus,
    mdiTrashCan,
    mdiFileEdit,
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
    records: Object,
    companies: Array,
    departments: Array,
    resourceNeo: Object,
});

const tabs = [
    { key: "company", label: "Companies", singular: "Company" },
    { key: "department", label: "Departments", singular: "Department" },
    { key: "section", label: "Sections", singular: "Section" },
    { key: "designation", label: "Designations", singular: "Designation" },
    { key: "category", label: "Categories", singular: "Category" },
    { key: "extra_classification", label: "Extra Classifications", singular: "Extra Classification" },
];

const currentTabMeta = computed(() => tabs.find((t) => t.key === props.tab) ?? tabs[1]);

const toOpt = (items) => [
    { id: "", label: "-- Optional --" },
    ...(items || []).map((x) => ({ id: x.id, label: x.name })),
];
const companyOpts = computed(() => toOpt(props.companies));
const deptOpts = computed(() => toOpt(props.departments));
const findOpt = (opts, id) => opts.find((o) => o.id === id) || opts[0];

const isFormModalActive = ref(false);
const isDeleteModalActive = ref(false);
const editingId = ref(null);
const deletingId = ref(null);

const form = useForm({
    code: "",
    name: "",
    company_id: findOpt(companyOpts.value, ""),
    department_id: findOpt(deptOpts.value, ""),
    level: 1,
    legal_name: "",
    gstin: "",
    is_active: true,
});

const openCreateModal = () => {
    editingId.value = null;
    form.clearErrors();
    form.reset("code", "name", "legal_name", "gstin");
    form.company_id = findOpt(companyOpts.value, "");
    form.department_id = findOpt(deptOpts.value, "");
    form.level = 1;
    form.is_active = true;
    isFormModalActive.value = true;
};

const openEditModal = (row) => {
    editingId.value = row.id;
    form.clearErrors();
    form.code = row.code ?? "";
    form.name = row.name ?? "";
    form.company_id = findOpt(companyOpts.value, row.company_id ?? "");
    form.department_id = findOpt(deptOpts.value, row.department_id ?? "");
    form.level = row.level ?? 1;
    form.legal_name = row.legal_name ?? "";
    form.gstin = row.gstin ?? "";
    form.is_active = !!row.is_active;
    isFormModalActive.value = true;
};

const unwrap = (val) => (val && typeof val === "object" ? val.id : val) || null;

const submitForm = () => {
    const transformed = form.transform((data) => ({
        ...data,
        company_id: unwrap(data.company_id),
        department_id: unwrap(data.department_id),
    }));

    if (editingId.value) {
        transformed.put(route("hrms.org.update", { type: props.tab, id: editingId.value }), {
            preserveScroll: true,
            onSuccess: () => {
                isFormModalActive.value = false;
                editingId.value = null;
            },
        });
    } else {
        transformed.post(route("hrms.org.store", { type: props.tab }), {
            preserveScroll: true,
            onSuccess: () => {
                isFormModalActive.value = false;
            },
        });
    }
};

const confirmDelete = () => {
    if (deletingId.value) {
        router.delete(route("hrms.org.destroy", { type: props.tab, id: deletingId.value }), {
            preserveScroll: true,
            onFinish: () => {
                deletingId.value = null;
            },
        });
    }
};

const switchTab = (key) => {
    router.get(route("hrms.org.index"), { tab: key }, { preserveState: false });
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Organization Hierarchy" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiSitemap" title="Organization Hierarchy" main>
                <div class="flex">
                    <BaseButton
                        v-if="can('hrmsOrg_create')"
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
                    <template #cell(parent_name)="{ item: row }">
                        <span>{{ row.parent_name ?? "-" }}</span>
                    </template>
                    <template #cell(is_active)="{ item: row }">
                        <span
                            class="px-2 py-0.5 rounded text-xs font-bold uppercase"
                            :class="row.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-200 text-slate-600'"
                        >
                            {{ row.is_active ? "Active" : "Inactive" }}
                        </span>
                    </template>
                    <template #cell(actions)="{ item: row }">
                        <BaseButton
                            v-if="can('hrmsOrg_edit')"
                            class="mr-2"
                            color="info"
                            :icon="mdiFileEdit"
                            small
                            @click="openEditModal(row)"
                        />
                        <BaseButton
                            v-if="can('hrmsOrg_delete')"
                            color="danger"
                            :icon="mdiTrashCan"
                            small
                            @click="deletingId = row.id; isDeleteModalActive = true;"
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
            <form @submit.prevent="submitForm" class="space-y-4">
                <FormField label="Code" :error="form.errors.code">
                    <FormControl v-model="form.code" required />
                </FormField>
                <FormField label="Name" :error="form.errors.name">
                    <FormControl v-model="form.name" required />
                </FormField>
                <FormField v-if="tab === 'department'" label="Company" :error="form.errors.company_id">
                    <FormControl v-model="form.company_id" :options="companyOpts" />
                </FormField>
                <FormField v-if="tab === 'section'" label="Department" :error="form.errors.department_id">
                    <FormControl v-model="form.department_id" :options="deptOpts" />
                </FormField>
                <FormField v-if="tab === 'designation'" label="Hierarchy Level" :error="form.errors.level">
                    <FormControl v-model="form.level" type="number" />
                </FormField>
                <template v-if="tab === 'company'">
                    <FormField label="Legal Name" :error="form.errors.legal_name">
                        <FormControl v-model="form.legal_name" />
                    </FormField>
                    <FormField label="GSTIN" :error="form.errors.gstin">
                        <FormControl v-model="form.gstin" />
                    </FormField>
                </template>
                <FormField label="Active">
                    <FormCheckRadio type="switch" v-model="form.is_active" name="is_active" label="ON" prelabel="OFF" />
                </FormField>
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
