<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiCheck,
    mdiPlay,
    mdiCheckAll,
    mdiAlert,
    mdiEye,
    mdiSend,
    mdiChevronRight,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import FormField from "@/components/FormField.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import VoiceRecorder from "@/components/VoiceRecorder.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import { formatDisplayDate } from "@/helpers/helpers";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
});

const selectedTask = ref(null);
const selectedTaskDetails = ref(null);
const loadingDetails = ref(false);

const commentForm = useForm({
    comment: "",
    files: [],
});

const statusForm = useForm({
    status: "",
    comment: "",
    files: [],
});

const fileList = ref([]);
const statusFileList = ref([]);
const commentFileInputRef = ref(null);
const statusFileInputRef = ref(null);

const isAudioFile = (file) => {
    if (!file) return false;
    if (typeof file === "string") {
        return file.startsWith("audio/") || file === "video/webm";
    }
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
const isImageFile = (mimeType) => mimeType && mimeType.startsWith("image/");

const formatBytes = (bytes) => {
    if (!bytes) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
};

const selectTask = async (task) => {
    selectedTask.value = task;
    loadingDetails.value = true;
    selectedTaskDetails.value = null;
    fileList.value = [];
    statusFileList.value = [];

    try {
        const res = await axios.get(route("task.show", task.id));
        // Note: we can parse details returned by show, but task.show renders Inertia.
        // We can make an API request or load data from inertia or query directly.
        // Wait, task.show return Inertia. But we can fetch task details via json if we load them.
        // Wait! Let's check: how can we load details?
        // Since we want task details, let's look at TaskController.php: we don't have a json details route yet.
        // Wait, we can add a method `taskDetails` or we can just make `show` return JSON if request expects JSON!
        // Let's look at TaskController.php. We wrote `show` method, which does Inertia rendering.
        // We can easily fetch details by hitting a JSON API or modifying the controller.
        // Wait, let's check: can we call a custom endpoint?
        // Let's add a custom API endpoint in `TaskController.php` or `routes/web.php`?
        // Actually, in `routes/web.php`, let's check if we registered:
        // `Route::resource('task', TaskController::class)` which handles `task.show`.
        // Let's write an API endpoint `task.details` in `TaskController.php` that returns the task with all relations as JSON!
        // That is extremely clean and standard!
        // Let's implement it: `public function getDetails(Task $task) { ... return response()->json(...); }`
        // Wait, let's verify if we already wrote `show(Task $task)` to return details. Yes, it formats the details for Inertia rendering.
        // We can make a separate route `Route::get('task/{task}/json-details', [TaskController::class, 'jsonDetails'])->name('task.jsonDetails');`
        // Let's first make sure we write this route and controller method, or fetch it directly.
        // Let's write the fetch in `MyTasksView.vue` to hit `/admin/task/${task.id}/json-details`.
        const response = await axios.get(`/admin/task/${task.id}/json-details`);
        selectedTaskDetails.value = response.data;
    } catch (err) {
        console.error("Failed to load details:", err);
        useToast().error("Failed to load task details.");
    } finally {
        loadingDetails.value = false;
    }
};

const onCommentFilesSelected = (e) => {
    const files = Array.from(e.target.files || []);
    files.forEach(f => {
        fileList.value.push(f);
        commentForm.files.push(f);
    });
    e.target.value = "";
};

const handleRecordedVoiceNote = (file) => {
    if (!file) return;
    fileList.value.push(file);
    commentForm.files.push(file);
};

const removeFile = (idx) => {
    fileList.value.splice(idx, 1);
    commentForm.files.splice(idx, 1);
};

const onStatusFilesSelected = (e) => {
    const files = Array.from(e.target.files || []);
    files.forEach(f => {
        statusFileList.value.push(f);
        statusForm.files.push(f);
    });
    e.target.value = "";
};

const handleStatusRecordedVoiceNote = (file) => {
    if (!file) return;
    statusFileList.value.push(file);
    statusForm.files.push(file);
};

const removeStatusFile = (idx) => {
    statusFileList.value.splice(idx, 1);
    statusForm.files.splice(idx, 1);
};

const postComment = () => {
    commentForm.post(route("task.addComment", selectedTask.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            fileList.value = [];
            useToast().success("Comment added!");
            selectTask(selectedTask.value); // reload details
        }
    });
};

const updateStatus = (status) => {
    if (status !== 'completed') {
        // Direct update for accept / in_progress
        statusForm.status = status;
        statusForm.comment = `Marked task as ${status}.`;
        statusForm.post(route("task.updateAssigneeStatus", selectedTask.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                useToast().success(`Task marked as ${status}`);
                selectedTask.value.my_status = status;
                selectTask(selectedTask.value);
            }
        });
    }
};

const submitCompletedTask = () => {
    statusForm.status = 'completed';
    statusForm.post(route("task.updateAssigneeStatus", selectedTask.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            useToast().success("Task submitted for verification!");
            selectedTask.value.my_status = 'completed';
            selectTask(selectedTask.value);
        }
    });
};

const getStatusClass = (status) => {
    switch (status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
        case 'accepted': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
        case 'in_progress': return 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400';
        case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
        case 'verified': return 'bg-teal-100 text-teal-800 dark:bg-teal-900/20 dark:text-teal-400';
        case 'closed': return 'bg-gray-100 text-gray-800 dark:bg-gray-700/50 dark:text-gray-400';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getPriorityClass = (priority) => {
    switch (priority) {
        case 'low': return 'bg-gray-100 text-gray-650 dark:bg-gray-800 dark:text-gray-400';
        case 'medium': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
        case 'high': return 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400';
        case 'urgent': return 'bg-red-100 text-red-850 dark:bg-red-950/20 dark:text-red-400 font-bold';
        default: return 'bg-gray-100 text-gray-800';
    }
};

// Select the first task on mount if available
onMounted(() => {
    if (props.tasks.length > 0) {
        selectTask(props.tasks[0]);
    }
});
</script>

<template>
    <LayoutAuthenticated>
        <Head title="My Tasks" />

        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiCheckAll" title="My Tasks" main />

            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :outline="true"
                class="mb-4"
            >
                {{ message }}
            </NotificationBar>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Pane: Tasks List -->
                <div class="space-y-3 lg:col-span-1">
                    <CardBox class="p-2 pr-0 max-h-[calc(100vh-220px)] overflow-y-auto">
                        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider px-3 mb-2">Tasks Assigned to You</h3>
                        
                        <div v-if="props.tasks.length === 0" class="text-sm text-gray-500 p-6 text-center">
                            You have no tasks assigned or CC'd.
                        </div>

                        <div v-else class="space-y-1 pr-2">
                            <button
                                v-for="t in props.tasks"
                                :key="t.id"
                                type="button"
                                class="w-full text-left p-3 rounded-lg border transition-all flex justify-between items-center group"
                                :class="selectedTask && selectedTask.id === t.id
                                    ? 'bg-blue-50/70 border-blue-300 dark:bg-blue-950/10 dark:border-blue-900'
                                    : 'border-gray-100 hover:bg-gray-50 dark:border-slate-800/80 dark:hover:bg-slate-800/30'"
                                @click="selectTask(t)"
                            >
                                <div class="space-y-1.5 truncate pr-2">
                                    <div class="text-sm font-bold text-gray-800 dark:text-slate-200 group-hover:text-blue-600 dark:group-hover:text-blue-400 truncate">
                                        {{ t.title }}
                                    </div>
                                    <div class="flex items-center space-x-2 text-xxs text-gray-500">
                                        <span>Due: {{ t.due_date }}</span>
                                        <span>•</span>
                                        <span class="capitalize">{{ t.creator_name }}</span>
                                    </div>
                                    <div class="flex items-center space-x-1.5 mt-1">
                                        <span :class="['text-xxs font-semibold px-2 py-0.5 rounded capitalize border', getStatusClass(t.my_status)]">
                                            {{ t.my_status.replace('_', ' ') }}
                                        </span>
                                        <span :class="['text-xxs font-semibold px-1.5 py-0.5 rounded border uppercase', getPriorityClass(t.priority)]">
                                            {{ t.priority }}
                                        </span>
                                    </div>
                                </div>
                                <BaseIcon :path="mdiChevronRight" size="20" class="text-gray-400 group-hover:text-blue-500" />
                            </button>
                        </div>
                    </CardBox>
                </div>

                <!-- Right Pane: Task Detail & Work Console -->
                <div class="lg:col-span-2 space-y-6">
                    <CardBox v-if="loadingDetails" class="flex flex-col items-center justify-center py-20">
                        <div class="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-blue-500"></div>
                        <span class="text-xs text-gray-500 mt-2">Loading task details...</span>
                    </CardBox>

                    <div v-else-if="selectedTaskDetails" class="space-y-6">
                        <!-- Details card -->
                        <CardBox>
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <span :class="['text-xs font-bold px-2.5 py-0.5 rounded border capitalize mr-2', getStatusClass(selectedTask.my_status)]">
                                        My Status: {{ selectedTask.my_status.replace('_', ' ') }}
                                    </span>
                                    <span :class="['text-xs font-bold px-2 py-0.5 rounded border uppercase', getPriorityClass(selectedTask.priority)]">
                                        {{ selectedTask.priority }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-400">Due: {{ selectedTask.due_date }}</div>
                            </div>

                            <h2 class="text-xl font-bold text-gray-800 dark:text-slate-100 mb-2">
                                {{ selectedTaskDetails.task.title }}
                            </h2>

                            <p class="text-sm text-gray-600 dark:text-slate-300 whitespace-pre-wrap leading-relaxed mb-6">
                                {{ selectedTaskDetails.task.description || "No description provided." }}
                            </p>

                            <!-- Attachments -->
                            <div v-if="selectedTaskDetails.files.length > 0" class="border-t border-gray-100 dark:border-slate-700/60 pt-4">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Task Attachments</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div
                                        v-for="file in selectedTaskDetails.files"
                                        :key="file.id"
                                        class="border rounded p-2.5 bg-gray-50/50 dark:bg-slate-800/20 border-gray-200 dark:border-slate-700/60"
                                    >
                                        <div v-if="isAudioFile(file)">
                                            <div class="text-xs text-gray-500 truncate mb-1">🎤 {{ file.file_name }}</div>
                                            <audio :src="file.download_url" controls class="w-full h-8"></audio>
                                        </div>
                                        <div v-else-if="isImageFile(file.file_type)" class="space-y-1">
                                            <div class="text-xs text-gray-500 truncate mb-1">🖼 {{ file.file_name }}</div>
                                            <img :src="file.download_url" class="max-h-24 rounded object-cover" />
                                            <a :href="file.download_url" target="_blank" class="text-xs text-blue-500 hover:underline block">View Image</a>
                                        </div>
                                        <div v-else class="flex items-center justify-between text-xs">
                                            <span class="truncate font-medium flex-1 text-gray-700 dark:text-slate-300">📄 {{ file.file_name }}</span>
                                            <a :href="file.download_url" target="_blank" class="text-blue-600 hover:underline font-semibold ml-2">Download</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardBox>

                        <!-- Action Console for Assignee -->
                        <CardBox v-if="selectedTask.is_assignee && !['verified', 'closed'].includes(selectedTask.my_status)">
                            <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">Work Console</h3>

                            <!-- State 1: Pending (Unaccepted) -->
                            <div v-if="selectedTask.my_status === 'pending'" class="flex items-center space-x-3 bg-amber-55 bg-opacity-20 border border-amber-200 p-4 rounded-lg bg-amber-50/40 dark:border-amber-900/30">
                                <span class="text-amber-600">🔔 You have not accepted this task yet.</span>
                                <BaseButton
                                    type="button"
                                    color="info"
                                    :icon="mdiCheck"
                                    label="Accept Task"
                                    @click="updateStatus('accepted')"
                                />
                            </div>

                            <!-- State 2: Accepted -->
                            <div v-else-if="selectedTask.my_status === 'accepted'" class="flex items-center space-x-3 bg-blue-50 bg-opacity-20 border border-blue-200 p-4 rounded-lg dark:border-blue-900/30">
                                <span class="text-blue-600">🚀 Task accepted. Start when ready.</span>
                                <BaseButton
                                    type="button"
                                    color="success"
                                    :icon="mdiPlay"
                                    label="Start Work (In Progress)"
                                    @click="updateStatus('in_progress')"
                                />
                            </div>

                            <!-- State 3: In Progress (Completing form) -->
                            <div v-else-if="selectedTask.my_status === 'in_progress'" class="space-y-4 border border-purple-200 p-4 rounded-lg bg-purple-50/5 dark:border-purple-900/30">
                                <h4 class="text-sm font-semibold text-purple-700 dark:text-purple-400 mb-1">Submit Progress/Complete Task</h4>
                                
                                <FormField label="Submit Comments / Work Summary">
                                    <textarea
                                        v-model="statusForm.comment"
                                        class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-sm focus:ring-blue-500"
                                        rows="3"
                                        placeholder="Summarize what you completed or describe progress..."
                                        required
                                    ></textarea>
                                </FormField>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Files list -->
                                    <div>
                                        <label class="text-xs font-semibold text-gray-500 block mb-1">Attach Completed Files</label>
                                        <BaseButton
                                            type="button"
                                            color="info"
                                            label="Select File"
                                            small
                                            outline
                                            @click="statusFileInputRef.click()"
                                        />
                                        <input
                                            ref="statusFileInputRef"
                                            type="file"
                                            multiple
                                            class="hidden"
                                            @change="onStatusFilesSelected"
                                        />

                                        <div v-if="statusFileList.length > 0" class="mt-2 space-y-1">
                                            <div
                                                v-for="(f, idx) in statusFileList"
                                                :key="idx"
                                                class="flex justify-between items-center bg-gray-100 dark:bg-slate-800 p-1.5 px-2.5 rounded text-xs"
                                            >
                                                <span class="truncate font-medium max-w-44">{{ f.name }}</span>
                                                <button type="button" class="text-red-500 font-bold text-xs ml-2" @click="removeStatusFile(idx)">✕</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Voice note recorder -->
                                    <VoiceRecorder @recorded="handleStatusRecordedVoiceNote" />
                                </div>

                                <div class="flex justify-end pt-2">
                                    <BaseButton
                                        type="button"
                                        color="success"
                                        label="Submit Task for Verification"
                                        :disabled="statusForm.processing || !statusForm.comment.trim()"
                                        @click="submitCompletedTask"
                                    />
                                </div>
                            </div>
                        </CardBox>

                        <!-- Comments feed -->
                        <CardBox>
                            <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">
                                Task History &amp; Discussion
                            </h3>

                            <div v-if="selectedTaskDetails.comments.length === 0" class="text-sm text-gray-500 py-6 text-center">
                                No discussions or comments logged yet.
                            </div>

                            <div v-else class="space-y-4 max-h-[300px] overflow-y-auto pr-2">
                                <div
                                    v-for="c in selectedTaskDetails.comments"
                                    :key="c.id"
                                    class="p-3 border rounded-lg border-gray-150 bg-gray-50/50 dark:border-slate-800 dark:bg-slate-900/20"
                                >
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <span class="text-xs font-bold text-gray-700 dark:text-slate-300">{{ c.user.name }}</span>
                                            <span class="text-xxs px-1.5 py-0.5 rounded bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-400 ml-2 capitalize">
                                                {{ c.user.role }}
                                            </span>
                                        </div>
                                        <span class="text-xxs text-gray-400">{{ c.created_at }}</span>
                                    </div>

                                    <p class="text-sm text-gray-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">
                                        {{ c.comment }}
                                    </p>

                                    <!-- Files in comments -->
                                    <div v-if="c.files && c.files.length > 0" class="mt-2 space-y-1.5 border-t dark:border-slate-800 pt-2">
                                        <div
                                            v-for="file in c.files"
                                            :key="file.id"
                                            class="flex items-center space-x-2 text-xs"
                                        >
                                            <span class="text-gray-500">📎</span>
                                            <a :href="file.download_url" target="_blank" class="text-blue-500 hover:underline truncate">{{ file.file_name }}</a>
                                            <span class="text-gray-400 text-xxs font-mono">({{ formatBytes(file.file_size) }})</span>
                                            <audio v-if="isAudioFile(file)" :src="file.download_url" controls class="h-6 w-48 scale-90 origin-left"></audio>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Comment form -->
                            <div class="border-t border-gray-200 dark:border-slate-700 pt-4 mt-6 space-y-4" v-if="!['verified', 'closed'].includes(selectedTask.my_status)">
                                <FormField label="Add Comment">
                                    <textarea
                                        v-model="commentForm.comment"
                                        class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-sm focus:ring-blue-500"
                                        rows="2"
                                        placeholder="Type comment..."
                                        required
                                    ></textarea>
                                </FormField>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs font-semibold text-gray-500 block mb-1">Attach Files</label>
                                        <BaseButton
                                            type="button"
                                            color="info"
                                            label="Select File"
                                            small
                                            outline
                                            @click="commentFileInputRef.click()"
                                        />
                                        <input
                                            ref="commentFileInputRef"
                                            type="file"
                                            multiple
                                            class="hidden"
                                            @change="onCommentFilesSelected"
                                        />

                                        <div v-if="fileList.length > 0" class="mt-2 space-y-1">
                                            <div
                                                v-for="(f, idx) in fileList"
                                                :key="idx"
                                                class="flex justify-between items-center bg-gray-100 dark:bg-slate-800 p-1.5 px-2.5 rounded text-xs"
                                            >
                                                <span class="truncate font-medium max-w-44">{{ f.name }}</span>
                                                <button type="button" class="text-red-500 font-bold text-xs ml-2" @click="removeFile(idx)">✕</button>
                                            </div>
                                        </div>
                                    </div>

                                    <VoiceRecorder @recorded="handleRecordedVoiceNote" />
                                </div>

                                <div class="flex justify-end pt-1">
                                    <BaseButton
                                        type="button"
                                        color="info"
                                        :icon="mdiSend"
                                        label="Post Comment"
                                        :disabled="commentForm.processing || !commentForm.comment.trim()"
                                        @click="postComment"
                                    />
                                </div>
                            </div>
                        </CardBox>
                    </div>

                    <CardBox v-else class="flex flex-col items-center justify-center py-20 text-gray-500 dark:text-slate-400">
                        <svg class="h-12 w-12 text-gray-300 dark:text-slate-600 mb-2" viewBox="0 0 24 24"><path fill="currentColor" d="M19,3H14.82C14.4,1.84 13.3,1 12,1C10.7,1 9.6,1.84 9.18,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M12,3A1,1 0 0,1 13,4A1,1 0 0,1 12,5A1,1 0 0,1 11,4A1,1 0 0,1 12,3M14,17H7V15H14V17M17,13H7V11H17V13M17,9H7V7H17V9Z"/></svg>
                        <span class="text-sm font-semibold">Select a task from the list to view detail &amp; work console.</span>
                    </CardBox>
                </div>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
