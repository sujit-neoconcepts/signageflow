<script setup>
import { Head, Link, usePage, useForm } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiFormatListBulleted,
    mdiAlert,
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
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onBeforeMount, ref } from "vue";
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
    openJobs: {
        type: Array,
        default: () => [],
    },
    resourceNeo: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    title: "",
    description: "",
    due_date: "",
    priority: "medium",
    assignees: [],
    loop_users: [],
    notify_channels: ["email"],
    reminder_before_due: 60,
    is_recurring: false,
    recurrence_type: "",
    recurrence_config: {
        days: [] // e.g. [1,2,3,4,5] for weekly days
    },
    recurrence_end_date: "",
    temp_files: [],
    job_id: null,
});

const selectedAssignees = ref([]);
const selectedLoopUsers = ref([]);
const selectedJob = ref(null);
const existingFiles = ref([]);
const newFiles = ref([]);
const fileInputRef = ref(null);
const dragOver = ref(false);
const uploadingFiles = ref(false);
const uploadProgress = ref(0);
const fileUploadError = ref("");
const tempFiles = ref([]);

const weekDays = [
    { id: 1, label: "Mon" },
    { id: 2, label: "Tue" },
    { id: 3, label: "Wed" },
    { id: 4, label: "Thu" },
    { id: 5, label: "Fri" },
    { id: 6, label: "Sat" },
    { id: 7, label: "Sun" },
];

onBeforeMount(() => {
    if (props.formdata.id) {
        form.title = props.formdata.title ?? "";
        form.description = props.formdata.description ?? "";
        form.due_date = props.formdata.due_date_formatted ?? "";
        form.priority = props.formdata.priority ?? "medium";
        form.reminder_before_due = props.formdata.reminder_before_due ?? 60;
        form.is_recurring = props.formdata.is_recurring ?? false;
        form.recurrence_type = props.formdata.recurrence_type ?? "";
        form.recurrence_config = props.formdata.recurrence_config ?? { days: [] };
        form.recurrence_end_date = props.formdata.recurrence_end_date_formatted ?? "";
        form.notify_channels = props.formdata.notify_channels ?? ["email"];

        selectedAssignees.value = props.formdata.assignees ?? [];
        selectedLoopUsers.value = props.formdata.viewers ?? [];

        if (props.formdata.files) {
            existingFiles.value = props.formdata.files.map(f => ({
                id: f.id,
                original_name: f.file_name,
                file_size: f.file_size,
                file_type: f.file_type,
                download_url: `/admin/task-file/${f.id}/download`,
            }));
        }

        // Set job selection
        if (props.formdata.job_id && props.openJobs) {
            selectedJob.value = props.openJobs.find(j => j.id === props.formdata.job_id) || null;
            form.job_id = props.formdata.job_id;
        }
    }
});

const isAudioFile = (file) => {
    const type = file.file_type || file.mime_type;
    const name = (file.original_name || file.file_name || "").toLowerCase();
    if (type && (type.startsWith("audio/") || type === "video/webm")) {
        return true;
    }
    if (name.includes("voice_note") || name.endsWith(".webm") || name.endsWith(".ogg") || name.endsWith(".wav") || name.endsWith(".mp3")) {
        return true;
    }
    return false;
};

const toggleDay = (dayId) => {
    if (!form.recurrence_config.days) {
        form.recurrence_config.days = [];
    }
    const idx = form.recurrence_config.days.indexOf(dayId);
    if (idx > -1) {
        form.recurrence_config.days.splice(idx, 1);
    } else {
        form.recurrence_config.days.push(dayId);
    }
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

const removeNewFile = (idx) => {
    newFiles.value.splice(idx, 1);
};

const deleteExistingFile = async (file, idx) => {
    if (!confirm(`Delete "${file.original_name}"?`)) return;
    try {
        if (file.is_temp) {
            existingFiles.value.splice(idx, 1);
            tempFiles.value = tempFiles.value.filter(tf => tf.stored_name !== file.stored_name);
        } else {
            await axios.delete(`/admin/task-file/${file.id}`);
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
        const url = props.formdata.id ? `/admin/task/${props.formdata.id}/files` : '/admin/task-upload-temp';
        // Note: For existing tasks, we map to store files direct, but let's handle in taskController.
        // If edit mode, we can upload using the same storeFiles or we can make it temp and sync on save.
        // Let's use the task-upload-temp endpoint for simplicity in both modes, and the controller updates it on save!
        const res = await axios.post('/admin/task-upload-temp', fd, {
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

const submitform = () => {
    form.assignees = selectedAssignees.value;
    form.loop_users = selectedLoopUsers.value;
    form.temp_files = tempFiles.value;
    form.job_id = selectedJob.value ? selectedJob.value.id : null;

    if (props.formdata.id) {
        form.put(route("task.update", props.formdata.id));
    } else {
        form.post(route("task.store"));
    }
};
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
                    <Link :href="route('task.index')">
                        <BaseButton
                            class="m-2"
                            :icon="mdiFormatListBulleted"
                            color="success"
                            rounded-full
                            small
                            label="List Tasks"
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
                    <!-- Column 1 & 2: Task Info -->
                    <div class="lg:col-span-2 space-y-6">
                        <CardBox>
                            <div class="space-y-4">
                                <FormField label="Task Title" :error="form.errors.title">
                                    <FormControl v-model="form.title" placeholder="What needs to be done?" required />
                                </FormField>

                                <FormField label="Description" :error="form.errors.description">
                                    <FormControl v-model="form.description" type="textarea" placeholder="Provide detailed instructions..." rows="6" />
                                </FormField>
                            </div>
                        </CardBox>

                        <!-- Recurrence Config -->
                        <CardBox>
                            <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">Recurrence Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormField label="Is Repeating Task?">
                                    <label class="inline-flex items-center mt-2 cursor-pointer">
                                        <input type="checkbox" v-model="form.is_recurring" class="sr-only peer" />
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                        <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">Repeat Task</span>
                                    </label>
                                </FormField>

                                <FormField v-if="form.is_recurring" label="Repeat Period" :error="form.errors.recurrence_type">
                                    <select v-model="form.recurrence_type" class="w-full border rounded px-3 py-2 bg-white dark:bg-slate-800" required>
                                        <option value="">Select Frequency</option>
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </FormField>
                            </div>

                            <div v-if="form.is_recurring" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormField v-if="form.recurrence_type === 'weekly'" label="Repeat on Days">
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <button
                                            v-for="day in weekDays"
                                            :key="day.id"
                                            type="button"
                                            class="px-3 py-1.5 rounded text-xs font-semibold border transition-all"
                                            :class="form.recurrence_config.days && form.recurrence_config.days.includes(day.id)
                                                ? 'bg-blue-600 border-blue-600 text-white'
                                                : 'bg-white dark:bg-slate-800 dark:border-slate-700 text-gray-700 dark:text-slate-300 hover:bg-gray-50'"
                                            @click="toggleDay(day.id)"
                                        >
                                            {{ day.label }}
                                        </button>
                                    </div>
                                </FormField>

                                <FormField label="Repeat Ends On" :error="form.errors.recurrence_end_date">
                                    <VueDatePicker
                                        v-model="form.recurrence_end_date"
                                        input-class-name="text-gray-500 dark:!text-white shadow-sm text-sm !bg-white dark:!bg-slate-800 !border-gray-700"
                                        :month-change-on-scroll="false"
                                        :range="false"
                                        :enable-time-picker="false"
                                        arrow-navigation
                                        format="dd-MM-yyyy"
                                        model-type="yyyy-MM-dd"
                                        auto-apply
                                        required
                                    />
                                </FormField>
                            </div>
                        </CardBox>

                        <!-- Attachments & Audio Recorder -->
                        <CardBox>
                            <h3 class="text-md font-semibold mb-2 text-gray-700 dark:text-slate-300">Attachments</h3>
                            <p class="text-xs text-gray-500 mb-4">Upload documents, screenshots, voice notes, or videos related to the task.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- File Drop Zone -->
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

                                <!-- Audio Recording component -->
                                <VoiceRecorder @recorded="handleRecordedVoiceNote" />
                            </div>

                            <!-- Uploading Indicator -->
                            <div v-if="uploadingFiles" class="mt-4">
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                    <div class="bg-blue-500 h-2 transition-all" :style="{ width: uploadProgress + '%' }"></div>
                                </div>
                                <span class="text-xs text-gray-500 mt-1 block">Uploading files... {{ uploadProgress }}%</span>
                            </div>

                            <div v-if="fileUploadError" class="text-xs text-red-500 mt-2">{{ fileUploadError }}</div>

                            <!-- File Lists -->
                            <div v-if="existingFiles.length > 0" class="mt-4 space-y-2">
                                <div
                                    v-for="(f, idx) in existingFiles"
                                    :key="idx"
                                    class="border border-green-200 dark:border-green-950/40 bg-green-50/50 dark:bg-green-950/10 rounded-lg p-3 flex flex-col space-y-2"
                                >
                                    <div class="flex items-center justify-between text-sm">
                                        <div class="flex-1 truncate">
                                            <span v-if="isAudioFile(f)" class="font-medium text-gray-700 dark:text-slate-300">
                                                🎤 {{ f.original_name }}
                                            </span>
                                            <a v-else-if="!f.is_temp" :href="f.download_url" target="_blank" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                                📄 {{ f.original_name }}
                                            </a>
                                            <span v-else class="font-medium text-gray-700 dark:text-slate-300">
                                                📄 {{ f.original_name }}
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-3 ml-2 flex-shrink-0">
                                            <span class="text-gray-400 text-xs font-mono">{{ formatBytes(f.file_size) }}</span>
                                            <button type="button" class="text-red-500 hover:text-red-700 text-xs font-bold" @click="deleteExistingFile(f, idx)">Delete</button>
                                        </div>
                                    </div>

                                    <div v-if="isAudioFile(f) && !f.is_temp" class="w-full">
                                        <audio :src="f.download_url" controls class="w-full h-8 max-w-xs"></audio>
                                    </div>
                                    <div v-else-if="isAudioFile(f) && f.is_temp" class="text-xs text-amber-600 font-medium">
                                        (Audio note uploaded. Playable after save.)
                                    </div>
                                </div>
                            </div>
                        </CardBox>
                    </div>

                    <!-- Column 3: Task Assignment & Notifications -->
                    <div class="space-y-6">
                        <CardBox>
                            <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">Assignment</h3>
                            <div class="space-y-4">
                                <FormField label="Assign to Executives" :error="form.errors.assignees">
                                    <Multiselect
                                        v-model="selectedAssignees"
                                        placeholder="Select assignees"
                                        track-by="id"
                                        label="label"
                                        :multiple="true"
                                        :close-on-select="false"
                                        :options="props.executives"
                                        class="mt-1"
                                    />
                                </FormField>

                                <FormField label="Loop Users (View Only)" :error="form.errors.loop_users">
                                    <Multiselect
                                        v-model="selectedLoopUsers"
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
                        </CardBox>

                        <!-- Link to Job (Optional) -->
                        <CardBox v-if="props.openJobs && props.openJobs.length > 0">
                            <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">Link to Job (Optional)</h3>
                            <FormField label="Select Job">
                                <Multiselect
                                    v-model="selectedJob"
                                    placeholder="Link this task to a job..."
                                    track-by="id"
                                    label="label"
                                    :multiple="false"
                                    :options="props.openJobs"
                                    :allow-empty="true"
                                    class="mt-1"
                                />
                            </FormField>
                            <p class="text-xs text-gray-400 mt-2">Optionally link this task to an open job. The task will appear in the job's task list.</p>
                        </CardBox>

                        <CardBox>
                            <h3 class="text-md font-semibold mb-4 text-gray-700 dark:text-slate-300">Due Date &amp; Alerts</h3>
                            <div class="space-y-4">
                                <FormField label="Task Due Date/Time" :error="form.errors.due_date">
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

                                <FormField label="Task Priority" :error="form.errors.priority">
                                    <select v-model="form.priority" class="w-full border rounded px-3 py-2 bg-white dark:bg-slate-800" required>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </FormField>

                                <FormField label="Send Reminder Before (Minutes)" :error="form.errors.reminder_before_due">
                                    <FormControl v-model="form.reminder_before_due" type="number" min="0" required />
                                </FormField>

                                <FormField label="Notify Channels">
                                    <div class="space-y-2 mt-2">
                                        <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-slate-300 cursor-pointer">
                                            <input type="checkbox" v-model="form.notify_channels" value="email" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            <span>Email</span>
                                        </label>
                                        <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-slate-300 cursor-pointer">
                                            <input type="checkbox" v-model="form.notify_channels" value="whatsapp" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            <span>WhatsApp</span>
                                        </label>
                                        <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-slate-300 cursor-pointer">
                                            <input type="checkbox" v-model="form.notify_channels" value="mobile" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            <span>Mobile App Push</span>
                                        </label>
                                    </div>
                                    <div v-if="form.errors.notify_channels" class="text-red-500 text-xs mt-1">{{ form.errors.notify_channels }}</div>
                                </FormField>
                            </div>
                        </CardBox>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="mt-6 flex">
                    <BaseButton class="mr-2" type="submit" small :disabled="form.processing" color="info" :label="props.formdata.id ? 'Update' : 'Save'" />
                    <Link :href="route('task.index')">
                        <BaseButton type="reset" small color="info" outline label="Cancel" />
                    </Link>
                </div>
            </form>
        </SectionMain>
    </LayoutAuthenticated>
</template>
