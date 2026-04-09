<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiImage,
    mdiAlert,
    mdiPackage,
    mdiFileEdit,
    mdiTrashCan,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";

import CardBoxModal from "@/components/CardBoxModal.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";

import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import { ref } from 'vue';
import Table from "@/components/DataTable/Table.vue";

import { computed } from 'vue';
import { can } from '@/utils/permissions';
const message = computed(() => usePage().props.flash.message)
const msg_type = computed(() => usePage().props.flash.msg_type ?? 'warning')
const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
    can: {
        type: Object,
        default: () => ({}),
    },
});

const actions = ['c', 'r', 'u', 'd'];

const delselect = ref(0);
const isModalDangerActive = ref(false);
const isModalDangerActive2 = ref(false);
const authPass = ref('');

const deleteSettinAuth = () => {
    if (delselect.value != 0) {
        router.delete(route("setting.authDestroy", { id: delselect.value, password: authPass.value }), {
            preserveScroll: true,
            resetOnSuccess: false,
            onFinish: () => {
                authPass.value = '';
                delselect.value = 0;
            }
        });
    }
}
let fieldTypes = {
    authPass: 'text',
}
function handleFocus(event) {
    this.fieldTypes.authPass = 'password'
}
function handleBlur(event) {
    if (!event.value) {
        this.fieldTypes.authPass = 'text'
    }

}

</script>

<template>
    <LayoutAuthenticated>

        <Head title="Roles" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiPackage" title="Settings" main>
                <div class="flex">
                    <Link :href="route('setting.create')" v-if="can('setting_create')">
                    <BaseButton class="m-2" :icon="mdiImage" color="success" rounded-full small label="Add New" />
                    </Link>
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar v-if="message" @closed="usePage().props.flash.message = ''" :color="msg_type" :icon="mdiAlert"
                :outline="true">
                {{ message }}
            </NotificationBar>
            <CardBox has-table>
                <Table :resource="settings">
                    <template #cell(actions)="{ item: setting }">
                        <Link :href="route('setting.edit', setting.id)" class="-mb-3 mr-2" v-if="(actions.indexOf('u') !== -1) &&
                            (can('setting_edit'))">
                        <BaseButton color="info" :icon="mdiFileEdit" small />
                        </Link>
                        <BaseButton color="danger" :icon="mdiTrashCan" small
                            @click="delselect = setting.id; isModalDangerActive = true"
                            v-if="(actions.indexOf('d') !== -1) && (can('setting_delete'))" />
                    </template>
                </Table>
            </CardBox>
        </SectionMain>
        <CardBoxModal v-model="isModalDangerActive" buttonLabel="Confirm" title="Please confirm" button="danger" has-cancel
            @confirm="isModalDangerActive2 = true">
            <p>Are you sure to delete?</p>
        </CardBoxModal>
        <CardBoxModal v-model="isModalDangerActive2" buttonLabel="Validate" title="Please Validate" button="danger"
            has-cancel @confirm="deleteSettinAuth">
            <p>Please Enter Your Password To Delete Setting</p>
            <FormField label="Password" help="">
                <FormControl placeholder="Password" v-model="authPass" :type="fieldTypes.authPass" name="authPass" value=""
                    @focus="handleFocus(this)" @blur="handleBlur(this)" autocomplete="off" required />
            </FormField>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
