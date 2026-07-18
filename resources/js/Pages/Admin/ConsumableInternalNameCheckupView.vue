<script setup>
import { Head, Link, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiClipboardCheck,
    mdiArrowLeft,
    mdiPencil,
    mdiAlertCircle,
    mdiCheckCircle,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseIcon from "@/components/BaseIcon.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import { computed } from "vue";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    groups: {
        type: Array,
        default: () => [],
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Consistency Checkup" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="mdiClipboardCheck"
                title="Product Internal Name Consistency Checkup"
                main
            >
                <div class="flex">
                    <Link :href="route('consumableInternalName.index')">
                        <BaseButton
                            class="m-2"
                            :icon="mdiArrowLeft"
                            color="success"
                            rounded-full
                            small
                            label="Back to List"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar
                v-if="message"
                class="mb-4"
                :color="msg_type"
                :icon="mdiAlertCircle"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>

            <div class="mb-6 p-4 bg-blue-50 dark:bg-slate-800 border-l-4 border-blue-500 rounded text-sm text-blue-700 dark:text-blue-300">
                <div class="flex items-start">
                    <BaseIcon :path="mdiAlertCircle" class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" />
                    <div>
                        <p class="font-bold mb-1">Consistency Rules</p>
                        <p>Ideally, all <b>Product Internal Names</b> assigned to the same <b>Group</b> must share identical values for <b>Unit</b>, <b>Alt Unit</b>, and <b>Margin %</b>. This page lists any groups violating this rule so you can verify and align them.</p>
                    </div>
                </div>
            </div>

            <!-- SUCCESS PANEL: NO DISCREPANCIES -->
            <CardBox v-if="groups.length === 0" class="p-8 text-center bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 shadow-sm rounded-lg">
                <div class="flex flex-col items-center justify-center space-y-4">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-950/40 rounded-full flex items-center justify-center text-green-600 dark:text-green-400">
                        <BaseIcon :path="mdiCheckCircle" class="w-10 h-10" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200">All Groups Consistent</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-md">No unit name, alt unit, or open stock margin discrepancies were found among items in the same groups.</p>
                    <Link :href="route('consumableInternalName.index')">
                        <BaseButton color="success" label="Go to Product list" class="mt-2" />
                    </Link>
                </div>
            </CardBox>

            <!-- WARNING PANEL: DISCREPANCIES FOUND -->
            <div v-else class="space-y-8">
                <div class="p-4 bg-orange-50 dark:bg-orange-950/20 border-l-4 border-orange-500 rounded text-sm text-orange-800 dark:text-orange-300 shadow-sm">
                    <p class="font-bold flex items-center">
                        <BaseIcon :path="mdiAlertCircle" class="w-5 h-5 mr-2 flex-shrink-0 text-orange-600 dark:text-orange-400" />
                        Inconsistencies Detected in {{ groups.length }} Group(s)
                    </p>
                </div>

                <div v-for="group in groups" :key="group.id" class="transition-shadow duration-300 hover:shadow-md">
                    <CardBox class="overflow-hidden border border-gray-200 dark:border-slate-800 shadow-sm">
                        <!-- Group Header -->
                        <div class="bg-gray-50 dark:bg-slate-800/80 px-6 py-4 border-b border-gray-200 dark:border-slate-700 flex justify-between items-center">
                            <div>
                                <h3 class="text-md font-bold text-gray-800 dark:text-gray-200">
                                    Group: {{ group.name }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ group.items.length }} member items in group
                                </p>
                            </div>
                            <Link :href="route('consumableInternalNameGroup.edit', group.id)">
                                <BaseButton :icon="mdiPencil" label="Edit Group Info" small color="info" outline />
                            </Link>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 uppercase text-xs border-b border-gray-200 dark:border-slate-700">
                                        <th class="p-4 text-left font-semibold">Item Name</th>
                                        <th class="p-4 text-left font-semibold">Unit Name</th>
                                        <th class="p-4 text-left font-semibold">Unit Alt Name</th>
                                        <th class="p-4 text-right font-semibold">Margin %</th>
                                        <th class="p-4 text-center font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="item in group.items" 
                                        :key="item.id" 
                                        class="border-b border-gray-100 dark:border-slate-800 hover:bg-gray-50/50 dark:hover:bg-slate-800/30"
                                    >
                                        <td class="p-4 font-medium text-gray-800 dark:text-gray-200">{{ item.name }}</td>
                                        
                                        <!-- Unit Name Column -->
                                        <td 
                                            class="p-4" 
                                            :class="[
                                                item.mismatch_unit 
                                                    ? 'bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-400 font-medium' 
                                                    : 'text-gray-600 dark:text-gray-300'
                                            ]"
                                        >
                                            <div class="flex items-center space-x-1">
                                                <span>{{ item.unitName || '(none)' }}</span>
                                                <span 
                                                    v-if="item.mismatch_unit" 
                                                    class="text-[10px] bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200 px-1.5 py-0.5 rounded uppercase font-semibold tracking-wider"
                                                >
                                                    Mismatch
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Unit Alt Name Column -->
                                        <td 
                                            class="p-4" 
                                            :class="[
                                                item.mismatch_alt_unit 
                                                    ? 'bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-400 font-medium' 
                                                    : 'text-gray-600 dark:text-gray-300'
                                            ]"
                                        >
                                            <div class="flex items-center space-x-1">
                                                <span>{{ item.unitAltName || '(none)' }}</span>
                                                <span 
                                                    v-if="item.mismatch_alt_unit" 
                                                    class="text-[10px] bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200 px-1.5 py-0.5 rounded uppercase font-semibold tracking-wider"
                                                >
                                                    Mismatch
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Margin Column -->
                                        <td 
                                            class="p-4 text-right" 
                                            :class="[
                                                item.mismatch_margin 
                                                    ? 'bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-400 font-medium' 
                                                    : 'text-gray-600 dark:text-gray-300'
                                            ]"
                                        >
                                            <div class="flex items-center justify-end space-x-1.5">
                                                <span>{{ item.openStockMarginPercent }}%</span>
                                                <span 
                                                    v-if="item.mismatch_margin" 
                                                    class="text-[10px] bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-200 px-1.5 py-0.5 rounded uppercase font-semibold tracking-wider"
                                                >
                                                    Mismatch
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td class="p-4 text-center">
                                            <Link :href="route('consumableInternalName.edit', item.id)" target="_blank">
                                                <BaseButton 
                                                    :icon="mdiPencil" 
                                                    color="info" 
                                                    small 
                                                    label="Edit Item" 
                                                />
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardBox>
                </div>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>

<style scoped>
/* Scrollbar visibility on overflow table */
.overflow-x-auto {
    scrollbar-width: thin;
}
</style>
