<script setup>
import { Head, Link, usePage, router } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiFileEdit,
    mdiTrashCan,
    mdiViewSplitVertical,
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
});
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="props.resourceNeo.resourceTitle" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="props.resourceNeo.iconPath"
                :title="props.resourceNeo.resourceTitle"
                main
            >
                <div class="flex">
                    <span v-for="exLink in props.resourceNeo.extraMainLinks">
                        <Link
                            :title="exLink.label"
                            :href="route(exLink.link)"
                            class="-mb-3 mr-2"
                        >
                            <BaseButton
                                class="m-2"
                                :icon="mdiPackageVariantPlus"
                                color="success"
                                rounded-full
                                small
                                :label="exLink.label"
                            /> </Link
                    ></span>
                    <Link
                        :href="
                            route(props.resourceNeo.resourceName + '.create')
                        "
                        v-if="
                            props.resourceNeo.actions.includes('c') && (can(props.resourceNeo.resourceName + '_create'))
                        "
                    >
                        <BaseButton
                            class="m-2"
                            :icon="mdiPackageVariantPlus"
                            color="success"
                            rounded-full
                            small
                            label="Add New"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>
            <CardBox class="mb-3">
                <div class="grid grid-cols-4 gap-4">
                    <div>Opening {{props.resourceNeo.date_staring}}: {{props.resourceNeo.opening}}</div>
                    <div  class="text-center">Deposit {{props.resourceNeo.date_dur}}: {{props.resourceNeo.depo_dur}}</div>
                    <div  class="text-center">Expense {{props.resourceNeo.date_dur}}: {{props.resourceNeo.exp_dur}}</div>
                    <div class="text-right">Closing {{props.resourceNeo.date_closing}}: {{props.resourceNeo.closing}}</div>
                </div>
            </CardBox>
            <CardBox has-table>
                <Table
                    :resource="resourceData"
                    :resourceNeo="resourceNeo"
                    :stickyHeader="!0"
                >
                   
                    <template #cell(actions)="{ item: dItem }">
                        <span v-for="exLink in props.resourceNeo.extraLinks">
                            <Link
                                :title="exLink.label"
                                :href="route(exLink.link, dItem.id)"
                                class="-mb-3 mr-2"
                                v-if="dItem[exLink.key] == exLink.compvl"
                            >
                                <BaseButton
                                    color="info"
                                    :icon="exLink.icon"
                                    small
                                /> </Link
                        ></span>
                        <Link
                            :href="
                                route(
                                    props.resourceNeo.resourceName + '.edit',
                                    dItem.id
                                )
                            "
                            class="-mb-3 mr-2"
                            v-if="
                                props.resourceNeo.actions.indexOf('u') !== -1 && (can(props.resourceNeo.resourceName + '_edit'))
                            "
                        >
                            <BaseButton
                                color="info"
                                :icon="mdiFileEdit"
                                small
                            />
                        </Link>
                        <BaseButton
                            color="danger"
                            :icon="mdiTrashCan"
                            small
                            @click="
                                delselect = dItem.id;
                                isModalDangerActive = true;
                            "
                            v-if="
                                props.resourceNeo.actions.indexOf('d') !== -1 && (can(props.resourceNeo.resourceName + '_delete'))
                            "
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
            @confirm="deleteRecord"
        >
            <p>Are you sure to delete?</p>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
