<script setup>
import { Head, Link, usePage, useForm } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiFormatListBulleted,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import FormControl from "@/components/FormControl.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import Multiselect from "vue-multiselect";
import "../../../css/vue-multiselect.css";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import VoiceRecorder from "@/components/VoiceRecorder.vue";
import { computed, onBeforeMount, ref, watch } from "vue";
import axios from "axios";

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
    loopUsers: {
        type: Array,
        default: () => [],
    },
    clients: {
        type: Array,
        default: () => [],
    },
    workflows: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    title: "",
    description: "",
    client_id: null,
    workflow_id: null,
    due_date: "",
    estimated_hours: "",
    stages: [],
    temp_files: [],
});

const selectedClient = ref(null);
const selectedWorkflow = ref(null);
const loadingStages = ref(false);
const existingFiles = ref([]);
const newFiles = ref([]);
const fileInputRef = ref(null);
const dragOver = ref(false);
const uploadingFiles = ref(false);
const uploadProgress = ref(0);
const fileUploadError = ref("");
const tempFiles = ref([]);
const isEditMode = computed(() => props.formdata && props.formdata.id);

onBeforeMount(() => {
    if (isEditMode.value) {
        form.title = props.formdata.title ?? "";
        form.description = props.formdata.description ?? "";
        form.client_id = props.formdata.client_id;
        form.workflow_id = props.formdata.workflow_id;
        form.due_date = props.formdata.due_date ?? "";
        form.estimated_hours = props.formdata.estimated_hours ?? "";
        form.stages = props.formdata.stages ?? [];

        if (props.formdata.client_id) {
            selectedClient.value = props.clients.find(c => c.id === props.formdata.client_id) || null;
        }
        if (props.formdata.workflow_id) {
            selectedWorkflow.value = props.workflows.find(w => w.id === props.formdata.workflow_id) || null;
        }

        if (props.formdata.files) {
            existingFiles.value = props.formdata.files.map(f => ({
                id: f.id,
                original_name: f.file_name,
                file_size: f.file_size,
                file_type: f.file_type,
                download_url: `/admin/job-file/${f.id}/download`,
            }));
        }
    }
});

// Fetch workflow stages when workflow is selected
watch(selectedWorkflow, async (newVal) => {
    if (!newVal || isEditMode.value) return;

    form.workflow_id = newVal.id;
    loadingStages.value = true;

    try {
        const res = await axios.get(`/admin/workflow/${newVal.id}/stages-json`);
        form.stages = res.data.stages.map((stage) => ({
            workflow_stage_id: stage.id,
            name: stage.name,
            description: stage.description ?? "",
            estimated_hours: stage.default_estimated_hours ?? "",
            assignees: stage.executives ?? [],
            loop_users: [],
            start_date: "",
            end_date: "",
            start_on_previous_complete: false,
            notify_channels: ["whatsapp", "mobile"],
            need_enquiry_number: stage.need_enquiry_number ?? false,
            need_sales_order_number: stage.need_sales_order_number ?? false,
            need_expense: stage.need_expense ?? false,
        }));
    } catch (err) {
        console.error("Failed to fetch stages:", err);
    } finally {
        loadingStages.value = false;
    }
});

watch(selectedClient, (newVal) => {
    form.client_id = newVal ? newVal.id : null;
});

const isAudioFile = (file) => {
    const type = file.file_type || file.mime_type;
    const name = (file.original_name || file.file_name || "").toLowerCase();
    if (type && (type.startsWith("audio/") || type === "video/webm")) return true;
    if (name.includes("voice_note") || name.endsWith(".webm") || name.endsWith(".ogg") || name.endsWith(".wav") || name.endsWith(".mp3")) return true;
    return false;
};

const formatBytes = (bytes) => {
    if (!bytes) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
};

const onFileInput = (e) => {
    const files = Array.from(e.target.files || []);
    files.forEach((f) => newFiles.value.push(f));
    e.target.value = "";
    uploadNewFiles();
};

const onDrop = (e) => {
    dragOver.value = false;
    const files = Array.from(e.dataTransfer.files || []);
    files.forEach((f) => newFiles.value.push(f));
    uploadNewFiles();
};

const handleRecordedVoiceNote = (file) => {
    if (!file) return;
    newFiles.value.push(file);
    uploadNewFiles();
};

const deleteExistingFile = async (file, idx) => {
    if (!confirm(`Delete "${file.original_name}"?`)) return;
    try {
        if (file.is_temp) {
            existingFiles.value.splice(idx, 1);
            tempFiles.value = tempFiles.value.filter(tf => tf.stored_name !== file.stored_name);
        } else {
            await axios.delete(`/admin/job-file/${file.id}`);
            existingFiles.value.splice(idx, 1);
        }
    } catch {
        alert("Failed to delete file.");
    }
};

const uploadNewFiles = async () => {
    if (newFiles.value.length === 0) return;

    uploadingFiles.value = true;
    uploadProgress.value = 0;
    fileUploadError.value = '';

    const fd = new FormData();
    newFiles.value.forEach((f) => fd.append('files[]', f));

    try {
        const res = await axios.post('/admin/job-upload-temp', fd, {
            onUploadProgress: (e) => {
                if (!e.total) return;
                uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            },
        });

        res.data.files.forEach((f) => tempFiles.value.push({ ...f }));
        res.data.files.forEach((f) => existingFiles.value.push({ ...f, is_temp: true }));

        newFiles.value = [];
        uploadProgress.value = 100;
    } catch (err) {
        fileUploadError.value = "File upload failed. Please try again.";
    } finally {
        uploadingFiles.value = false;
    }
};

const addStageAt = (index) => {
    form.stages.splice(index, 0, {
        workflow_stage_id: null,
        name: "",
        description: "",
        estimated_hours: "",
        assignees: [],
        loop_users: [],
        start_date: "",
        end_date: "",
        start_on_previous_complete: false,
        notify_channels: ["whatsapp", "mobile"],
        need_enquiry_number: false,
        need_sales_order_number: false,
        need_expense: false,
    });
};

const removeStage = (index) => {
    form.stages.splice(index, 1);
};

const submitform = () => {
    form.temp_files = tempFiles.value;

    if (isEditMode.value) {
        form.put(route("job.update", props.formdata.id));
    } else {
        form.post(route("job.store"));
    }
};
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="isEditMode ? 'Edit Job' : 'Create Job'" />

        <SectionMain>
            <SectionTitleLineWithButton
                :icon="mdiFormatListBulleted"
                :title="isEditMode ? 'Edit Job' : 'Create Job'"
                main
            >
                <div class="flex">
                    <Link :href="route('job.index')">
                        <BaseButton
                            class="m-2"
                            :icon="mdiFormatListBulleted"
                            color="success"
                            rounded-full
                            small
                            label="List Jobs"
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Column -->
                    <div class="lg:col-span-2 space-y-6">
                        <CardBox>
                            <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">Job Details</h3>
                            <div class="space-y-4">
                                <FormField label="Job Title" :error="form.errors.title">
                                    <FormControl v-model="form.title" placeholder="Enter job title..." required />
                                </FormField>

                                <FormField label="Description" :error="form.errors.description">
                                    <FormControl v-model="form.description" type="textarea" placeholder="Job description and instructions..." rows="4" />
                                </FormField>
                            </div>
                        </CardBox>

                        <!-- Workflow Stage Assignment (only for create) -->
                        <!-- Workflow Stage Assignment -->
                        <CardBox>
                            <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">
                                {{ isEditMode ? 'Workflow Stages' : 'Workflow & Stage Assignment' }}
                            </h3>

                            <FormField label="Select Workflow" :error="form.errors.workflow_id">
                                <Multiselect
                                    v-model="selectedWorkflow"
                                    placeholder="Select a workflow template..."
                                    track-by="id"
                                    label="label"
                                    :multiple="false"
                                    :options="props.workflows"
                                    class="mt-1"
                                    :disabled="isEditMode"
                                />
                            </FormField>

                            <div v-if="loadingStages" class="flex items-center justify-center py-8">
                                <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-blue-500"></div>
                                <span class="ml-3 text-sm text-gray-500">Loading workflow stages...</span>
                            </div>

                            <!-- Stage Cards -->
                            <div v-if="form.stages.length > 0 && !loadingStages" class="mt-6 space-y-6">
                                <!-- Top Insertion Button -->
                                <div class="flex justify-center -mb-4">
                                    <button 
                                        type="button" 
                                        class="px-3 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/30 dark:hover:bg-blue-950/60 border border-blue-200 dark:border-blue-900 rounded-full transition-all shadow-sm"
                                        @click="addStageAt(0)"
                                    >
                                        + Insert Stage at Beginning
                                    </button>
                                </div>

                                <template v-for="(stage, idx) in form.stages" :key="idx">
                                    <div
                                        class="border border-gray-200 dark:border-slate-700 rounded-lg p-4 bg-gray-50/30 dark:bg-slate-900/20 relative"
                                    >
                                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-3">
                                            <div class="flex items-center space-x-2 flex-grow">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white text-xs font-bold flex-shrink-0">
                                                    {{ idx + 1 }}
                                                </span>
                                                <input 
                                                    v-model="stage.name" 
                                                    placeholder="Stage Name" 
                                                    class="px-3 py-1 text-sm font-semibold bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded w-full max-w-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-800 dark:text-slate-100 shadow-sm"
                                                    required
                                                />
                                            </div>
                                            <button 
                                                type="button" 
                                                class="text-red-500 hover:text-red-700 text-xs font-semibold px-3 py-1 border border-red-200 dark:border-red-950 rounded bg-red-50/50 dark:bg-red-950/20 hover:bg-red-100 dark:hover:bg-red-950/40 transition-colors shadow-sm"
                                                @click="removeStage(idx)"
                                            >
                                                Remove Stage
                                            </button>
                                        </div>
                                        <div class="mb-4">
                                            <input 
                                                v-model="stage.description" 
                                                placeholder="Stage Description (optional)" 
                                                class="px-3 py-1.5 text-xs bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded w-full focus:outline-none focus:ring-1 focus:ring-blue-500 text-gray-600 dark:text-slate-400 shadow-sm"
                                            />
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <FormField label="Assign Executives" :error="form.errors[`stages.${idx}.assignees`]">
                                                <Multiselect
                                                    v-model="stage.assignees"
                                                    placeholder="Select assignees"
                                                    track-by="id"
                                                    label="label"
                                                    :multiple="true"
                                                    :close-on-select="false"
                                                    :options="props.executives"
                                                    class="mt-1"
                                                />
                                            </FormField>

                                            <FormField label="Loop Users (CC)">
                                                <Multiselect
                                                    v-model="stage.loop_users"
                                                    placeholder="Add to CC loop"
                                                    track-by="id"
                                                    label="label"
                                                    :multiple="true"
                                                    :close-on-select="false"
                                                    :options="props.loopUsers"
                                                    class="mt-1"
                                                />
                                            </FormField>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
                                            <FormField label="Estimated Hours">
                                                <FormControl v-model="stage.estimated_hours" type="number" step="0.5" min="0" placeholder="0" />
                                            </FormField>

                                            <FormField label="Start Date/Time">
                                                <VueDatePicker
                                                    v-model="stage.start_date"
                                                    input-class-name="text-gray-500 dark:!text-white shadow-sm text-sm !bg-white dark:!bg-slate-800 !border-gray-700"
                                                    :month-change-on-scroll="false"
                                                    :range="false"
                                                    :enable-time-picker="true"
                                                    arrow-navigation
                                                    format="dd-MM-yyyy HH:mm"
                                                    model-type="yyyy-MM-dd HH:mm:ss"
                                                    auto-apply
                                                    :disabled="stage.start_on_previous_complete"
                                                />
                                            </FormField>

                                            <FormField label="End Date/Time">
                                                <VueDatePicker
                                                    v-model="stage.end_date"
                                                    input-class-name="text-gray-500 dark:!text-white shadow-sm text-sm !bg-white dark:!bg-slate-800 !border-gray-700"
                                                    :month-change-on-scroll="false"
                                                    :range="false"
                                                    :enable-time-picker="true"
                                                    arrow-navigation
                                                    format="dd-MM-yyyy HH:mm"
                                                    model-type="yyyy-MM-dd HH:mm:ss"
                                                    auto-apply
                                                />
                                            </FormField>
                                        </div>

                                        <div class="mt-3">
                                            <label class="block text-xs font-semibold text-gray-550 uppercase tracking-wider mb-2">Notify Channels</label>
                                            <div class="flex items-center space-x-6 mt-1">
                                                <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-slate-350 cursor-pointer">
                                                    <input type="checkbox" v-model="stage.notify_channels" value="email" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                    <span>Email</span>
                                                </label>
                                                <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-slate-350 cursor-pointer">
                                                    <input type="checkbox" v-model="stage.notify_channels" value="whatsapp" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                    <span>WhatsApp</span>
                                                </label>
                                                <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-slate-350 cursor-pointer">
                                                    <input type="checkbox" v-model="stage.notify_channels" value="mobile" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                    <span>Mobile App Push</span>
                                                </label>
                                            </div>
                                            <div v-if="form.errors[`stages.${idx}.notify_channels`]" class="text-red-500 text-xs mt-1">
                                                {{ form.errors[`stages.${idx}.notify_channels`] }}
                                            </div>
                                        </div>

                                         <div class="mt-3 flex flex-wrap gap-4 pt-3 border-t border-gray-100 dark:border-slate-800/80 text-xs" v-if="stage.need_enquiry_number || stage.need_sales_order_number">
                                             <div v-if="stage.need_enquiry_number" class="flex items-center space-x-2">
                                                 <span class="font-semibold text-gray-500">Need Enquiry Number:</span>
                                                 <span class="px-2 py-0.5 rounded-full font-bold border bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900">
                                                     Yes
                                                 </span>
                                             </div>
                                             <div v-if="stage.need_sales_order_number" class="flex items-center space-x-2">
                                                 <span class="font-semibold text-gray-500">Need Sales Order Number:</span>
                                                 <span class="px-2 py-0.5 rounded-full font-bold border bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900">
                                                     Yes
                                                 </span>
                                             </div>
                                         </div>

                                         <div class="mt-3">
                                             <label class="inline-flex items-center cursor-pointer">
                                                 <input type="checkbox" v-model="stage.need_expense" class="sr-only peer" />
                                                 <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                                 <span class="ms-2 text-xs font-medium text-gray-600 dark:text-gray-400">Enable Task Expenses</span>
                                             </label>
                                         </div>

                                        <div class="mt-3" v-if="idx > 0">
                                            <label class="inline-flex items-center cursor-pointer">
                                                <input type="checkbox" v-model="stage.start_on_previous_complete" class="sr-only peer" />
                                                <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                                <span class="ms-2 text-xs font-medium text-gray-600 dark:text-gray-400">Start when previous stage completes</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- In-between Insertion Button -->
                                    <div class="flex justify-center -my-2 relative z-10">
                                        <button 
                                            type="button" 
                                            class="px-3 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/30 dark:hover:bg-blue-950/60 border border-blue-200 dark:border-blue-900 rounded-full transition-all shadow-sm"
                                            @click="addStageAt(idx + 1)"
                                        >
                                            + Insert Stage Here
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div v-if="form.stages.length === 0 && !loadingStages" class="text-sm text-gray-400 mt-4 py-4 text-center">
                                <template v-if="!isEditMode && !selectedWorkflow">
                                    Select a workflow template above, or 
                                    <button 
                                        type="button"
                                        class="text-blue-600 dark:text-blue-400 underline font-semibold focus:outline-none"
                                        @click="addStageAt(0)"
                                    >
                                        start with a custom stage
                                    </button>.
                                </template>
                                <template v-else>
                                    <span class="block mb-2">No stages configured.</span>
                                    <button 
                                        type="button"
                                        class="px-4 py-2 text-xs font-semibold text-blue-600 dark:text-blue-400 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/30 dark:hover:bg-blue-950/60 border border-blue-200 dark:border-blue-900 rounded shadow-sm"
                                        @click="addStageAt(0)"
                                    >
                                        + Add Custom Stage
                                    </button>
                                </template>
                            </div>
                        </CardBox>

                        <!-- Attachments -->
                        <CardBox>
                            <h3 class="text-md font-semibold mb-2 text-gray-700 dark:text-slate-300">Job Artifacts</h3>
                            <p class="text-xs text-gray-500 mb-4">Attach files, images, or voice notes related to this job. These will be visible on all tasks linked to the job.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div
                                    class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors border-gray-300 dark:border-slate-700 hover:border-blue-400"
                                    :class="dragOver ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : ''"
                                    @dragover.prevent="dragOver = true"
                                    @dragleave.prevent="dragOver = false"
                                    @drop.prevent="onDrop"
                                    @click="fileInputRef.click()"
                                >
                                    <svg class="mx-auto mb-2 text-gray-400" style="width:36px;height:36px" viewBox="0 0 24 24"><path fill="currentColor" d="M14,2H6C4.89,2 4,2.89 4,4V20C4,21.11 4.89,22 6,22H18C19.11,22 20,21.11 20,20V8L14,2M18,20H6V4H13V9H18V20M12,19L8,15H10.5V12H13.5V15H16L12,19Z"/></svg>
                                    <p class="text-xs text-gray-500">Drag files here, or <span class="text-blue-600 underline font-medium">browse</span></p>
                                    <input ref="fileInputRef" type="file" multiple class="hidden" @change="onFileInput" />
                                </div>

                                <VoiceRecorder @recorded="handleRecordedVoiceNote" />
                            </div>

                            <div v-if="uploadingFiles" class="mt-4">
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                    <div class="bg-blue-500 h-2 transition-all" :style="{ width: uploadProgress + '%' }"></div>
                                </div>
                                <span class="text-xs text-gray-500 mt-1 block">Uploading files... {{ uploadProgress }}%</span>
                            </div>

                            <div v-if="fileUploadError" class="text-xs text-red-500 mt-2">{{ fileUploadError }}</div>

                            <div v-if="existingFiles.length > 0" class="mt-4 space-y-2">
                                <div
                                    v-for="(f, idx) in existingFiles"
                                    :key="idx"
                                    class="border border-green-200 dark:border-green-950/40 bg-green-50/50 dark:bg-green-950/10 rounded-lg p-3 flex items-center justify-between text-sm"
                                >
                                    <div class="flex-1 truncate">
                                        <span v-if="isAudioFile(f)" class="font-medium text-gray-700 dark:text-slate-300">🎤 {{ f.original_name }}</span>
                                        <a v-else-if="!f.is_temp" :href="f.download_url" target="_blank" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">📄 {{ f.original_name }}</a>
                                        <span v-else class="font-medium text-gray-700 dark:text-slate-300">📄 {{ f.original_name }}</span>
                                    </div>
                                    <div class="flex items-center space-x-3 ml-2 flex-shrink-0">
                                        <span class="text-gray-400 text-xs font-mono">{{ formatBytes(f.file_size) }}</span>
                                        <button type="button" class="text-red-500 hover:text-red-700 text-xs font-bold" @click="deleteExistingFile(f, idx)">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </CardBox>
                    </div>

                    <!-- Side Column -->
                    <div class="space-y-6">
                        <CardBox>
                            <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">Job Settings</h3>
                            <div class="space-y-4">
                                <FormField label="Client" :error="form.errors.client_id">
                                    <Multiselect
                                        v-model="selectedClient"
                                        placeholder="Select client"
                                        track-by="id"
                                        label="label"
                                        :multiple="false"
                                        :options="props.clients"
                                        class="mt-1"
                                    />
                                </FormField>

                                <FormField label="Job Due Date" :error="form.errors.due_date">
                                    <VueDatePicker
                                        v-model="form.due_date"
                                        input-class-name="text-gray-500 dark:!text-white shadow-sm text-sm !bg-white dark:!bg-slate-800 !border-gray-700"
                                        :month-change-on-scroll="false"
                                        :range="false"
                                        :enable-time-picker="true"
                                        arrow-navigation
                                        format="dd-MM-yyyy HH:mm"
                                        model-type="yyyy-MM-dd HH:mm:ss"
                                        auto-apply
                                        required
                                    />
                                </FormField>

                                <FormField label="Estimated Hours (Total)" :error="form.errors.estimated_hours">
                                    <FormControl v-model="form.estimated_hours" type="number" step="0.5" min="0" placeholder="0" />
                                </FormField>
                            </div>
                        </CardBox>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="mt-6 flex">
                    <BaseButton class="mr-2" type="submit" small :disabled="form.processing" color="info" :label="isEditMode ? 'Update Job' : 'Create Job'" />
                    <Link :href="route('job.index')">
                        <BaseButton type="reset" small color="info" outline label="Cancel" />
                    </Link>
                </div>
            </form>
        </SectionMain>
    </LayoutAuthenticated>
</template>
