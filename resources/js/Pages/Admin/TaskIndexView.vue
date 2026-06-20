<script setup>
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiEye,
    mdiFileEdit,
    mdiTrashCan,
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
import { can } from "@/utils/permissions";
import { formatDisplayDate } from "@/helpers/helpers";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    tasks: {
        type: Object,
        default: () => ({}),
    },
    executives: {
        type: Array,
        default: () => [],
    },
    creators: {
        type: Array,
        default: () => [],
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
        router.delete(route("task.destroy", delselect.value), {
            preserveScroll: true,
            resetOnSuccess: false,
            onFinish: () => {
                delselect.value = 0;
            },
        });
    }
};

onMounted(() => {
    if (message.value) {
        if (msg_type.value == "info") {
            useToast().info(message.value, { duration: 7000 });
        } else if (msg_type.value == "success") {
            useToast().success(message.value, { duration: 7000 });
        } else if (msg_type.value == "danger") {
            useToast().error(message.value, { duration: 7000 });
        } else {
            useToast().warning(message.value, { duration: 7000 });
        }
    }
});

const getStatusClass = (status) => {
    switch (status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'accepted': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'in_progress': return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400';
        case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'verified': return 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400';
        case 'closed': return 'bg-gray-100 text-gray-800 dark:bg-gray-700/50 dark:text-gray-400';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getPriorityClass = (priority) => {
    switch (priority) {
        case 'low': return 'bg-gray-100 text-gray-600 dark:bg-gray-850 dark:text-gray-400';
        case 'medium': return 'bg-blue-50 text-blue-600 dark:bg-blue-900/10 dark:text-blue-400';
        case 'high': return 'bg-orange-100 text-orange-700 dark:bg-orange-950/20 dark:text-orange-400';
        case 'urgent': return 'bg-red-100 text-red-800 dark:bg-red-950/30 dark:text-red-400 font-bold';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getAssigneeStatusColor = (status) => {
    switch (status) {
        case 'pending': return 'text-yellow-600 dark:text-yellow-400';
        case 'accepted': return 'text-blue-600 dark:text-blue-400';
        case 'in_progress': return 'text-purple-600 dark:text-purple-400';
        case 'completed': return 'text-green-600 dark:text-green-400 font-semibold';
        case 'verified': return 'text-teal-600 dark:text-teal-400';
        case 'closed': return 'text-gray-500 dark:text-gray-400';
        default: return 'text-gray-600';
    }
};
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="resourceNeo.resourceTitle" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="resourceNeo.iconPath"
                :title="resourceNeo.resourceTitle"
                main
            >
                <div class="flex">
                    <Link
                        v-if="can('task_create')"
                        :href="route('task.create')"
                        class="text-gray-600"
                    >
                        <BaseButton
                            class="m-2"
                            color="success"
                            rounded-full
                            :icon="mdiPackageVariantPlus"
                            small
                            label="Add New"
                            title="Add New"
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
                    :resource="tasks"
                    :resourceNeo="resourceNeo"
                    :stickyHeader="true"
                >
                    <template #cell(title)="{ item }">
                        <Link :href="route('task.show', item.id)" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                            {{ item.title }}
                        </Link>
                    </template>

                    <template #cell(due_date)="{ item }">
                        <span>{{ formatDisplayDate(item.due_date) }}</span>
                    </template>

                    <template #cell(status)="{ item }">
                        <span
                            class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize"
                            :class="getStatusClass(item.status)"
                        >
                            {{ item.status.replace('_', ' ') }}
                        </span>
                    </template>

                    <template #cell(priority)="{ item }">
                        <span
                            class="inline-block px-2 py-0.5 rounded text-xs uppercase font-medium"
                            :class="getPriorityClass(item.priority)"
                        >
                            {{ item.priority }}
                        </span>
                    </template>

                    <template #cell(assignees)="{ item }">
                        <div class="flex flex-wrap gap-1.5 max-w-xs">
                            <span
                                v-for="assignee in item.assignees"
                                :key="assignee.id"
                                class="text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-slate-700/60 dark:text-slate-300"
                            >
                                {{ assignee.name }}
                                <span :class="['ml-1 font-bold', getAssigneeStatusColor(assignee.pivot.status)]">
                                    ({{ assignee.pivot.status === 'completed' ? '✓' : assignee.pivot.status[0].toUpperCase() }})
                                </span>
                            </span>
                        </div>
                    </template>

                    <template v-slot:cell(creator.name)="{ item }">
                        <span>{{ item.creator?.name }}</span>
                    </template>

                    <template v-slot:cell(job.title)="{ item }">
                        <Link v-if="item.job" :href="route('job.show', item.job.id)" class="text-blue-600 dark:text-blue-400 hover:underline">
                            {{ item.job.title }}
                        </Link>
                        <span v-else class="text-gray-400">—</span>
                    </template>

                    <template #cell(actions)="{ item }">
                        <div class="flex items-center space-x-2">
                            <Link :href="route('task.show', item.id)">
                                <BaseButton
                                    color="success"
                                    :icon="mdiEye"
                                    small
                                    title="View"
                                />
                            </Link>
                            <Link
                                v-if="can('task_edit') && item.status !== 'closed'"
                                :href="route('task.edit', item.id)"
                            >
                                <BaseButton
                                    color="info"
                                    :icon="mdiFileEdit"
                                    small
                                    title="Edit"
                                />
                            </Link>
                            <BaseButton
                                v-if="can('task_delete')"
                                color="danger"
                                :icon="mdiTrashCan"
                                small
                                title="Delete"
                                @click="delselect = item.id; isModalDangerActive = true;"
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
            <p>Are you sure to delete this task?</p>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
