<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiPackageVariantPlus,
    mdiAlert,
    mdiAccountBoxMultiple,
    mdiFileEdit,
    mdiTrashCan,
    mdiSecurity
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
    users: {
        type: Object,
        default: () => ({}),
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});

const actions = ['c', 'r', 'u', 'd'];

const delselect = ref(0);
const isModalDangerActive = ref(false);
const isModalDangerActive2 = ref(false);
const authPass = ref('');
/*
const deletePage = () => {
    if (delselect.value != 0) {
        router.delete(route("user.destroy", delselect.value), {
            preserveScroll: true,
            resetOnSuccess: false,
        });
    }
}
*/
const deleteUserAuth = () => {
    if (delselect.value != 0) {
        router.delete(route("user.authDestroy", { id: delselect.value, password: authPass.value }), {
            preserveScroll: true,
            resetOnSuccess: false,
            onFinish: () => {
                authPass.value = '';
                delselect.value = 0;
            }
        });
    }
}
const fieldTypes = ref('text')

function handleFocus(event) {
    fieldTypes.value = 'password'
}
function handleBlur(event) {
    if (!authPass.value) {
        fieldTypes.value = 'text'
    }

}

</script>

<template>
    <LayoutAuthenticated>

        <Head title="Roles" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiAccountBoxMultiple" title="Users" main>
                <div class="flex">
                    <Link :href="route('user.create')" v-if="can('user_create')">
                    <BaseButton class="m-2" :icon="mdiPackageVariantPlus" color="success" rounded-full small
                        label="Add New" />
                    </Link>
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar v-if="message" @closed="usePage().props.flash.message = ''" :color="msg_type" :icon="mdiAlert"
                :outline="true">
                {{ message }}
            </NotificationBar>
            <CardBox has-table>
                <Table :resource="users" :resourceNeo="resourceNeo">
                    <template #cell(actions)="{ item: user }">
                        <Link :href="route('user.edit', user.id)" class="-mb-3 mr-2" v-if="user.role_name != 'super-admin' && (actions.indexOf('u') !== -1) &&
                            can('user_edit')">
                        <BaseButton color="info" :icon="mdiFileEdit" small />
                        </Link>
                        <Link :href="route('user.permissions', user.id)" class="-mb-3 mr-2" v-if="user.role_name != 'super-admin' && (actions.indexOf('u') !== -1) &&
                            can('user_edit')">
                        <BaseButton color="info" :icon="mdiSecurity" small />
                        </Link>
                        <BaseButton color="danger" :icon="mdiTrashCan" small
                            @click="delselect = user.id; isModalDangerActive = true"
                            v-if="user.role_name != 'super-admin' && (actions.indexOf('d') !== -1) && can('user_delete')" />
                    </template>
                </Table>
            </CardBox>
        </SectionMain>
        <CardBoxModal v-model="isModalDangerActive" buttonLabel="Confirm" title="Please confirm" button="danger" has-cancel
            @confirm="isModalDangerActive2 = true">
            <p>Are you sure to delete?</p>
        </CardBoxModal>
        <form>
            <CardBoxModal v-model="isModalDangerActive2" buttonLabel="Validate" title="Please Validate" button="danger"
                has-cancel @confirm="deleteUserAuth">
                <p>Please Enter Your Password To Delete User</p>

                <FormField label="Password" help="">
                    <FormControl placeholder="Password" v-model="authPass" :type="fieldTypes" name="authPass" value=""
                        @focus="handleFocus()" @blur="handleBlur()" autocomplete="off" required />
                </FormField>
            </CardBoxModal>
        </form>
    </LayoutAuthenticated>
</template>
