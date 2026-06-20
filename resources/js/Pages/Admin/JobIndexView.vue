<script setup>
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiFileEdit,
    mdiTrashCan,
    mdiEye,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Table from "@/components/DataTable/Table.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onMounted, ref } from "vue";
import { can } from '@/utils/permissions';

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    jobs: {
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

const deleteRecord = () => {
    if (delselect.value != 0) {
        router.delete(route("job.destroy", delselect.value), {
            preserveScroll: true,
            resetOnSuccess: false,
            onFinish: () => {
                delselect.value = 0;
            },
        });
    }
};

const getStatusColor = (status) => {
    switch (status) {
        case 'not_started': return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700';
        case 'in_progress': return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800';
        case 'completed': return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800';
        case 'closed': return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700/50 dark:text-slate-400 dark:border-slate-600';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getStatusLabel = (status) => {
    return (status || '').replace(/_/g, ' ');
};

const getProgress = (job) => {
    if (!job.tasks || job.tasks.length === 0) return 0;
    const completed = job.tasks.filter(t => ['completed', 'verified', 'closed'].includes(t.status)).length;
    return Math.round((completed / job.tasks.length) * 100);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}-${month}-${year}`;
};

onMounted(() => {
    if (message.value) {
        if (msg_type.value == "success") {
            useToast().success(message.value, { duration: 7000 });
        } else if (msg_type.value == "danger") {
            useToast().error(message.value, { duration: 7000 });
        } else {
            useToast().warning(message.value, { duration: 7000 });
        }
    }
});
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Jobs" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="props.resourceNeo.iconPath"
                title="Jobs"
                main
            >
                <div class="flex">
                    <Link
                        v-if="can('job_create')"
                        :href="route('job.create')"
                    >
                        <BaseButton
                            class="m-2"
                            color="success"
                            rounded-full
                            :icon="mdiPackageVariantPlus"
                            small
                            label="Create Job"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar
                v-if="message"
                class="mb-4"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>

            <CardBox has-table>
                <Table
                    :resource="jobs"
                    :resourceNeo="resourceNeo"
                    :stickyHeader="true"
                >
                    <template #cell(title)="{ item: dItem }">
                        <Link :href="route('job.show', dItem.id)" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                            {{ dItem.title }}
                        </Link>
                    </template>

                    <template v-slot:cell(client.cl_name)="{ item: dItem }">
                        <span>{{ dItem.client?.cl_name || 'N/A' }}</span>
                    </template>

                    <template v-slot:cell(workflow.name)="{ item: dItem }">
                        <span>{{ dItem.workflow?.name || 'N/A' }}</span>
                    </template>

                    <template #cell(due_date)="{ item: dItem }">
                        <span class="text-sm">{{ formatDate(dItem.due_date) }}</span>
                    </template>

                    <template #cell(status)="{ item: dItem }">
                        <span
                            :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border capitalize', getStatusColor(dItem.status)]"
                        >
                            {{ getStatusLabel(dItem.status) }}
                        </span>
                    </template>

                    <template #cell(progress)="{ item: dItem }">
                        <div class="flex items-center space-x-2 min-w-[100px]">
                            <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-2 rounded-full transition-all"
                                    :class="getProgress(dItem) >= 100 ? 'bg-green-500' : 'bg-blue-500'"
                                    :style="{ width: getProgress(dItem) + '%' }"
                                ></div>
                            </div>
                            <span class="text-xs font-mono text-gray-500 w-8 text-right">{{ getProgress(dItem) }}%</span>
                        </div>
                    </template>

                    <template #cell(actions)="{ item: dItem }">
                        <div class="flex items-center space-x-2">
                            <Link v-if="can('job_view')" :href="route('job.show', dItem.id)">
                                <BaseButton
                                    color="success"
                                    :icon="mdiEye"
                                    small
                                    title="View"
                                />
                            </Link>
                            <Link
                                v-if="can('job_edit') && dItem.status !== 'closed'"
                                :href="route('job.edit', dItem.id)"
                            >
                                <BaseButton
                                    color="info"
                                    :icon="mdiFileEdit"
                                    small
                                    title="Edit"
                                />
                            </Link>
                            <BaseButton
                                v-if="can('job_delete')"
                                color="danger"
                                :icon="mdiTrashCan"
                                small
                                title="Delete"
                                @click="delselect = dItem.id; isModalDangerActive = true;"
                            />
                        </div>
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
            @confirm="deleteRecord"
        >
            <p>Are you sure to delete this job? Tasks linked to this job will remain but will be unlinked.</p>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
