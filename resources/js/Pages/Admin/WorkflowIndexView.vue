<script setup>
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiFileEdit,
    mdiTrashCan,
    mdiRefresh,
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
import { computed, onMounted, ref, onUnmounted } from "vue";
import ActionMenu from "@/components/ActionMenu.vue";
import { can } from '@/utils/permissions';

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    workflows: {
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
        router.delete(route("workflow.destroy", delselect.value), {
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
        if (msg_type.value == "success") {
            useToast().success(message.value, { duration: 7000 });
        } else if (msg_type.value == "danger") {
            useToast().error(message.value, { duration: 7000 });
        } else {
            useToast().warning(message.value, { duration: 7000 });
        }
    }
    window.addEventListener("click", handleWindowClick);
});

onUnmounted(() => {
    window.removeEventListener("click", handleWindowClick);
});

const openMenuIds = ref(new Set());
const toggleMenu = (id) => {
    if (openMenuIds.value.has(id)) {
        openMenuIds.value.delete(id);
    } else {
        openMenuIds.value.clear();
        openMenuIds.value.add(id);
    }
};

const handleWindowClick = (event) => {
    const isMenuClick = event.target.closest(".menu-container");
    if (!isMenuClick) {
        openMenuIds.value.clear();
    }
};

const actionClasses = {
    menuItem: "block hover:bg-gray-100 dark:hover:bg-gray-700",
    button: "w-full text-left",
    dropdown: "absolute right-0 z-50 mt-2 bg-white border rounded-md shadow-lg dark:bg-gray-800 dark:border-gray-700",
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Workflows" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="props.resourceNeo.iconPath"
                title="Workflows"
                main
            >
                <div class="flex">
                    <Link
                        v-if="can('workflow_create')"
                        :href="route('workflow.create')"
                    >
                        <BaseButton
                            class="m-2"
                            color="success"
                            rounded-full
                            :icon="mdiPackageVariantPlus"
                            small
                            label="Add Workflow"
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
                    :resource="workflows"
                    :resourceNeo="resourceNeo"
                    :stickyHeader="true"
                >
                    <template #cell(stages_count)="{ item: dItem }">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                            {{ dItem.stages ? dItem.stages.length : 0 }} stages
                        </span>
                    </template>

                    <template #cell(is_active)="{ item: dItem }">
                        <span
                            :class="[
                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border',
                                dItem.is_active
                                    ? 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800'
                                    : 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
                            ]"
                        >
                            {{ dItem.is_active ? "Active" : "Inactive" }}
                        </span>
                    </template>

                    <template v-slot:cell(creator.name)="{ item: dItem }">
                        <span>{{ dItem.creator?.name }}</span>
                    </template>

                    <template #cell(actions)="{ item: dItem }">
                        <div class="relative menu-container">
                            <ActionMenu
                                :item="dItem"
                                :is-open="openMenuIds.has(dItem.id)"
                                :menu-classes="actionClasses"
                                @toggle="toggleMenu(dItem.id)"
                            >
                                <Link
                                    v-if="can('workflow_edit')"
                                    :href="route('workflow.edit', dItem.id)"
                                    :class="[actionClasses.menuItem, 'p-2']"
                                >
                                    <BaseButton
                                        :class="[actionClasses.button, 'w-auto']"
                                        color="info"
                                        :icon="mdiFileEdit"
                                        small
                                        label="Edit"
                                        title="Edit"
                                    />
                                </Link>
                                <button
                                    v-if="can('workflow_delete')"
                                    :class="[actionClasses.button, 'p-2']"
                                    @click="delselect = dItem.id; isModalDangerActive = true"
                                >
                                    <BaseButton
                                        :class="[actionClasses.button, 'w-auto']"
                                        color="danger"
                                        :icon="mdiTrashCan"
                                        small
                                        label="Delete"
                                        title="Delete"
                                    />
                                </button>
                            </ActionMenu>
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
            <p>Are you sure to delete this workflow? This will also remove all its stages.</p>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
