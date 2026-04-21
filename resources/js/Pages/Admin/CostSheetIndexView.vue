<script setup>
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiFileEdit,
    mdiTrashCan,
    mdiViewList,
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
import { computed, onMounted, ref, onUnmounted, watch } from "vue";
import ActionMenu from "@/components/ActionMenu.vue";
import { can } from '@/utils/permissions';
import CostSheetCompositionModal from "@/components/CostSheetCompositionModal.vue";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");
const props = defineProps({
    resourceData: {
        type: Object,
        default: () => ({}),
    },
    can: {
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

const isCompositionModalActive = ref(false);
const selectedCostSheetId = ref(null);
const selectedCostSheet = ref(null);

const openCompositionModal = (item) => {
    selectedCostSheetId.value = item.id;
    selectedCostSheet.value = item;
    isCompositionModalActive.value = true;
};

const deleteRecord = () => {
    if (delselect.value != 0) {
        router.delete(
            route(props.resourceNeo.resourceName + ".destroy", delselect.value),
            {
                preserveScroll: true,
                resetOnSuccess: false,
                onFinish: () => {
                    delselect.value = 0;
                },
            }
        );
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
        openMenuIds.value.clear(); // Close other menus
        openMenuIds.value.add(id);
    }
};

const closeAllMenus = () => {
    openMenuIds.value.clear();
};

const handleWindowClick = (event) => {
    // Check if click target is inside any menu or menu button
    const isMenuClick = event.target.closest(".menu-container");
    if (!isMenuClick) {
        closeAllMenus();
    }
};

const actionClasses = {
    menuItem: "block hover:bg-gray-100 dark:hover:bg-gray-700",
    button: "w-full text-left",
    dropdown:
        "absolute right-0 z-50 mt-2 bg-white border rounded-md shadow-lg dark:bg-gray-800 dark:border-gray-700",
};

const checkConditions = (item, conditions) => {
    if (!conditions) return true;
    return conditions.every((rule) => {
        if (rule.cond === "==") return item[rule.key] == rule.compvl;
        if (rule.cond === "!=") return item[rule.key] != rule.compvl;
        if (rule.cond === ">") return item[rule.key] > rule.compvl;
        if (rule.cond === "<") return item[rule.key] < rule.compvl;
        if (rule.cond === "*") return true;
        return false;
    });
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
                    <template v-if="props.resourceNeo.extraMainLinks">
                        <Link
                            v-for="link in props.resourceNeo.extraMainLinks"
                            :key="link.link"
                            :href="route(link.link)"
                            class="text-gray-600"
                        >
                            <BaseButton
                                class="m-2"
                                color="success"
                                rounded-full
                                :icon="link.icon"
                                small
                                :label="link.label"
                                :title="link.label"
                        /></Link>
                    </template>
                    <Link
                        v-if="
                            resourceNeo.actions.includes('c') &&
                            can(props.resourceNeo.resourceName + '_create')
                        "
                        :href="route(resourceNeo.resourceName + '.create')"
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
                    /></Link>
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                class="mb-4"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>
            <CardBox has-table>
                <Table
                    :resource="resourceData"
                    :resourceNeo="resourceNeo"
                    :stickyHeader="!0"
                >
                    <template #cell(actions)="{ item: dItem }">
                        <div class="relative menu-container">
                            <ActionMenu
                                :item="dItem"
                                :extra-links="props.resourceNeo.extraLinks"
                                :action-expand="props.resourceNeo.actionExpand"
                                :is-open="openMenuIds.has(dItem.id)"
                                :menu-classes="actionClasses"
                                @toggle="toggleMenu(dItem.id)"
                            >
                                <button
                                    :class="[
                                        actionClasses.button,
                                        {
                                            'p-2': props.resourceNeo
                                                .actionExpand,
                                        },
                                    ]"
                                    @click="openCompositionModal(dItem)"
                                >
                                    <BaseButton
                                        :class="[
                                            actionClasses.button,
                                            'w-auto',
                                        ]"
                                        color="warning"
                                        :icon="mdiViewList"
                                        small
                                        :label="
                                            props.resourceNeo.actionExpand
                                                ? 'Composition'
                                                : ''
                                        "
                                        title="Composition"
                                    />
                                </button>
                                <Link
                                    v-if="
                                        checkConditions(
                                            dItem,
                                            props.resourceNeo.editRule
                                        ) &&
                                        props.resourceNeo.actions.includes(
                                            'u'
                                        ) &&
                                        (can(props.resourceNeo.resourceName +
                                                    '_edit'
                                            ))
                                    "
                                    :href="
                                        route(
                                            props.resourceNeo.resourceName +
                                                '.edit',
                                            dItem.id
                                        )
                                    "
                                    :class="[
                                        actionClasses.menuItem,
                                        {
                                            'p-2': props.resourceNeo
                                                .actionExpand,
                                        },
                                    ]"
                                >
                                    <BaseButton
                                        :class="[
                                            actionClasses.button,
                                            'w-auto',
                                        ]"
                                        color="info"
                                        :icon="mdiFileEdit"
                                        small
                                        :label="
                                            props.resourceNeo.actionExpand
                                                ? 'Edit'
                                                : ''
                                        "
                                        title="Edit"
                                    />
                                </Link>
                                <button
                                    v-if="
                                        checkConditions(
                                            dItem,
                                            props.resourceNeo.deleteRule
                                        ) &&
                                        props.resourceNeo.actions.includes(
                                            'd'
                                        ) &&
                                        (can(props.resourceNeo.resourceName +
                                                    '_delete'
                                            ))
                                    "
                                    :class="[
                                        actionClasses.button,
                                        {
                                            'p-2': props.resourceNeo
                                                .actionExpand,
                                        },
                                    ]"
                                    @click="
                                        delselect = dItem.id;
                                        isModalDangerActive = true;
                                    "
                                >
                                    <BaseButton
                                        :class="[
                                            actionClasses.button,
                                            'w-auto',
                                        ]"
                                        color="danger"
                                        :icon="mdiTrashCan"
                                        small
                                        :label="
                                            props.resourceNeo.actionExpand
                                                ? 'Delete'
                                                : ''
                                        "
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
            <p>Are you sure to delete?</p>
        </CardBoxModal>
        
        <CostSheetCompositionModal 
            v-model="isCompositionModalActive" 
            :costSheetId="selectedCostSheetId" 
            :costSheet="selectedCostSheet"
            @saved="isCompositionModalActive = false"
        />
    </LayoutAuthenticated>
</template>

<style scoped>
/* Add click-away listener to close dropdown when clicking outside */
:deep(body) {
    @apply cursor-pointer;
}
</style>
