<script setup>
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiAccountBoxMultiple,
    mdiFileEdit,
    mdiTrashCan,
    mdiUpload,
    mdiDownload,
    mdiAlertCircleOutline,
} from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Table from "@/components/DataTable/Table.vue";
import { can } from "@/utils/permissions";

const message = computed(() => usePage().props.flash.message);
const msgType = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    employees: {
        type: Object,
        default: () => ({}),
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});

const delselect = ref(0);
const isModalDangerActive = ref(false);

const deleteEmployee = () => {
    if (delselect.value !== 0) {
        router.delete(route("hrms.employees.destroy", delselect.value), {
            preserveScroll: true,
            resetOnSuccess: false,
            onFinish: () => {
                delselect.value = 0;
            },
        });
    }
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Employees" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiAccountBoxMultiple" title="Employees" main>
                <div class="flex flex-wrap items-center">
                    <Link :href="route('hrms.employees.validation')" v-if="can('hrmsEmployee_list')">
                        <BaseButton class="m-1" :icon="mdiAlertCircleOutline" color="warning" rounded-full small label="Validation" />
                    </Link>
                    <Link :href="route('hrms.employees.import')" v-if="can('hrmsEmployee_import')">
                        <BaseButton class="m-1" :icon="mdiUpload" color="info" rounded-full small label="Import" />
                    </Link>
                    <a :href="route('hrms.employees.export')" v-if="can('hrmsEmployee_export')">
                        <BaseButton class="m-1" :icon="mdiDownload" color="success" rounded-full small label="Export" />
                    </a>
                    <Link :href="route('hrms.employees.create')" v-if="can('hrmsEmployee_create')">
                        <BaseButton class="m-1" :icon="mdiPackageVariantPlus" color="success" rounded-full small label="Add New" />
                    </Link>
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

            <CardBox has-table>
                <Table :resource="employees" :resourceNeo="resourceNeo">
                    <template #cell(user_email)="{ item: emp }">
                        <span>{{ emp.user_email ?? "Kiosk Only" }}</span>
                    </template>
                    <template #cell(employment_status)="{ item: emp }">
                        <span class="uppercase text-xs font-semibold">{{ emp.employment_status }}</span>
                    </template>
                    <template #cell(actions)="{ item: emp }">
                        <Link
                            :href="route('hrms.employees.edit', emp.id)"
                            class="-mb-3 mr-2"
                            v-if="can('hrmsEmployee_edit')"
                        >
                            <BaseButton color="info" :icon="mdiFileEdit" small />
                        </Link>
                        <BaseButton
                            color="danger"
                            :icon="mdiTrashCan"
                            small
                            @click="delselect = emp.id; isModalDangerActive = true;"
                            v-if="can('hrmsEmployee_delete')"
                        />
                    </template>
                </Table>
            </CardBox>
        </SectionMain>

        <CardBoxModal
            v-model="isModalDangerActive"
            buttonLabel="Confirm"
            title="Please confirm"
            button="danger"
            has-cancel
            @confirm="deleteEmployee"
        >
            <p>Are you sure you want to delete this employee?</p>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
