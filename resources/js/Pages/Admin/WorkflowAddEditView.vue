<script setup>
import { Head, Link, usePage, useForm } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiFormatListBulleted,
    mdiPlus,
    mdiTrashCan,
    mdiChevronUp,
    mdiChevronDown,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import BaseIcon from "@/components/BaseIcon.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Multiselect from "vue-multiselect";
import "../../../css/vue-multiselect.css";
import { computed, onBeforeMount, ref } from "vue";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    formdata: {
        type: Object,
        default: () => ({}),
    },
    executives: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: "",
    description: "",
    is_active: true,
    stages: [
        { id: null, name: "", description: "", default_estimated_hours: "", executives: [] },
    ],
});

onBeforeMount(() => {
    if (props.formdata && props.formdata.id) {
        form.name = props.formdata.name ?? "";
        form.description = props.formdata.description ?? "";
        form.is_active = props.formdata.is_active ?? true;

        if (props.formdata.stages && props.formdata.stages.length > 0) {
            form.stages = props.formdata.stages.map((s) => ({
                id: s.id,
                name: s.name ?? "",
                description: s.description ?? "",
                default_estimated_hours: s.default_estimated_hours ?? "",
                executives: s.default_executives ?? [],
            }));
        }
    }
});

const addStage = () => {
    form.stages.push({
        id: null,
        name: "",
        description: "",
        default_estimated_hours: "",
        executives: [],
    });
};

const removeStage = (idx) => {
    if (form.stages.length > 1) {
        form.stages.splice(idx, 1);
    }
};

const moveStageUp = (idx) => {
    if (idx > 0) {
        const temp = form.stages[idx];
        form.stages[idx] = form.stages[idx - 1];
        form.stages[idx - 1] = temp;
        // Force reactivity
        form.stages = [...form.stages];
    }
};

const moveStageDown = (idx) => {
    if (idx < form.stages.length - 1) {
        const temp = form.stages[idx];
        form.stages[idx] = form.stages[idx + 1];
        form.stages[idx + 1] = temp;
        form.stages = [...form.stages];
    }
};

const submitform = () => {
    if (props.formdata && props.formdata.id) {
        form.put(route("workflow.update", props.formdata.id));
    } else {
        form.post(route("workflow.store"));
    }
};
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="props.formdata?.id ? 'Edit Workflow' : 'Create Workflow'" />

        <SectionMain>
            <SectionTitleLineWithButton
                :icon="mdiFormatListBulleted"
                :title="props.formdata?.id ? 'Edit Workflow' : 'Create Workflow'"
                main
            >
                <div class="flex">
                    <Link :href="route('workflow.index')">
                        <BaseButton
                            class="m-2"
                            :icon="mdiFormatListBulleted"
                            color="success"
                            rounded-full
                            small
                            label="List Workflows"
                        />
                    </Link>
                </div>
            </SectionTitleLineWithButton>

            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>

            <form @submit.prevent="submitform">
                <div class="space-y-6">
                    <!-- Workflow Info -->
                    <CardBox>
                        <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">Workflow Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <FormField label="Workflow Name" :error="form.errors.name">
                                <FormControl v-model="form.name" placeholder="e.g. Signage Installation" required />
                            </FormField>

                            <FormField label="Status">
                                <label class="inline-flex items-center mt-2 cursor-pointer">
                                    <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
                                </label>
                            </FormField>
                        </div>

                        <FormField label="Description" :error="form.errors.description" class="mt-4">
                            <FormControl v-model="form.description" type="textarea" placeholder="Describe this workflow template..." rows="3" />
                        </FormField>
                    </CardBox>

                    <!-- Stages Builder -->
                    <CardBox>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-md font-semibold text-gray-700 dark:text-slate-300">Workflow Stages</h3>
                            <BaseButton
                                type="button"
                                color="info"
                                :icon="mdiPlus"
                                small
                                label="Add Stage"
                                @click="addStage"
                            />
                        </div>

                        <div v-if="form.errors.stages" class="text-red-500 text-xs mb-3">{{ form.errors.stages }}</div>

                        <div class="space-y-4">
                            <div
                                v-for="(stage, idx) in form.stages"
                                :key="idx"
                                class="border border-gray-200 dark:border-slate-700 rounded-lg p-4 bg-gray-50/30 dark:bg-slate-900/20 relative"
                            >
                                <!-- Stage Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold">
                                            {{ idx + 1 }}
                                        </span>
                                        <span class="text-sm font-semibold text-gray-600 dark:text-slate-400">Stage {{ idx + 1 }}</span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <button
                                            type="button"
                                            v-if="idx > 0"
                                            class="p-1 text-gray-400 hover:text-blue-500 transition"
                                            @click="moveStageUp(idx)"
                                            title="Move Up"
                                        >
                                            <BaseIcon :path="mdiChevronUp" size="18" />
                                        </button>
                                        <button
                                            type="button"
                                            v-if="idx < form.stages.length - 1"
                                            class="p-1 text-gray-400 hover:text-blue-500 transition"
                                            @click="moveStageDown(idx)"
                                            title="Move Down"
                                        >
                                            <BaseIcon :path="mdiChevronDown" size="18" />
                                        </button>
                                        <button
                                            type="button"
                                            v-if="form.stages.length > 1"
                                            class="p-1 text-gray-400 hover:text-red-500 transition"
                                            @click="removeStage(idx)"
                                            title="Remove Stage"
                                        >
                                            <BaseIcon :path="mdiTrashCan" size="18" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Stage Fields -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <FormField label="Stage Name" :error="form.errors[`stages.${idx}.name`]">
                                        <FormControl v-model="stage.name" placeholder="e.g. Design" required />
                                    </FormField>

                                    <FormField label="Est. Hours" :error="form.errors[`stages.${idx}.default_estimated_hours`]">
                                        <FormControl v-model="stage.default_estimated_hours" type="number" step="0.5" min="0" placeholder="0" />
                                    </FormField>

                                    <FormField label="Default Executives">
                                        <Multiselect
                                            v-model="stage.executives"
                                            placeholder="Select default assignees"
                                            track-by="id"
                                            label="label"
                                            :multiple="true"
                                            :close-on-select="false"
                                            :options="props.executives"
                                            class="mt-1"
                                        />
                                    </FormField>
                                </div>

                                <FormField label="Description" class="mt-3">
                                    <FormControl v-model="stage.description" type="textarea" placeholder="Stage instructions..." rows="2" />
                                </FormField>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Footer Actions -->
                    <div class="flex">
                        <BaseButton class="mr-2" type="submit" small :disabled="form.processing" color="info" :label="props.formdata?.id ? 'Update Workflow' : 'Save Workflow'" />
                        <Link :href="route('workflow.index')">
                            <BaseButton type="reset" small color="info" outline label="Cancel" />
                        </Link>
                    </div>
                </div>
            </form>
        </SectionMain>
    </LayoutAuthenticated>
</template>
