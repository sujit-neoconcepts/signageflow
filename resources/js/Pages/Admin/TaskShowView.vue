<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiFormatListBulleted,
    mdiAlert,
    mdiSend,
    mdiFileOutline,
    mdiCheckCircle,
    mdiCloseCircle,
    mdiRefresh,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import CardBoxModal from "@/components/CardBoxModal.vue";
import FormField from "@/components/FormField.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import VoiceRecorder from "@/components/VoiceRecorder.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onMounted, ref } from "vue";
import axios from "axios";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    task: {
        type: Object,
        default: () => ({}),
    },
    assignees: {
        type: Array,
        default: () => [],
    },
    viewers: {
        type: Array,
        default: () => [],
    },
    files: {
        type: Array,
        default: () => [],
    },
    comments: {
        type: Array,
        default: () => [],
    },
});

const commentForm = useForm({
    comment: "",
    files: [],
});

const statusForm = useForm({
    status: "",
    comment: "",
});

const recordedFiles = ref([]);
const commentFileInputRef = ref(null);
const fileList = ref([]);

const formatBytes = (bytes) => {
    if (!bytes) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
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

const postComment = () => {
    commentForm.post(route("task.addComment", props.task.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            fileList.value = [];
            useToast().success("Comment posted successfully!");
        }
    });
};

const isStatusModalActive = ref(false);
const statusModalAction = ref("");
const statusModalTitle = computed(() => {
    switch (statusModalAction.value) {
        case 'verified': return 'Verify Task';
        case 'closed': return 'Close Task';
        case 'in_progress': return 'Reject Task';
        default: return 'Update Task Status';
    }
});
const statusModalButtonLabel = computed(() => {
    switch (statusModalAction.value) {
        case 'verified': return 'Verify';
        case 'closed': return 'Close';
        case 'in_progress': return 'Reject';
        default: return 'Submit';
    }
});
const statusModalButtonColor = computed(() => {
    switch (statusModalAction.value) {
        case 'verified': return 'success';
        case 'closed': return 'info';
        case 'in_progress': return 'danger';
        default: return 'info';
    }
});

const updateTaskStatus = (newStatus) => {
    statusModalAction.value = newStatus;
    statusForm.status = newStatus;
    statusForm.comment = "";
    isStatusModalActive.value = true;
};

const submitStatusUpdate = () => {
    statusForm.post(route("task.updateTaskStatus", props.task.id), {
        preserveScroll: true,
        onSuccess: () => {
            isStatusModalActive.value = false;
            useToast().success(`Task status updated successfully`);
        }
    });
};

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

const isImageFile = (mimeType) => {
    return mimeType && mimeType.startsWith("image/");
};

const isVideoFile = (mimeType) => {
    return mimeType && mimeType.startsWith("video/");
};

const getStatusColor = (status) => {
    switch (status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400';
        case 'accepted': return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400';
        case 'in_progress': return 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/20 dark:text-purple-400';
        case 'completed': return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400';
        case 'verified': return 'bg-teal-100 text-teal-800 border-teal-200 dark:bg-teal-900/20 dark:text-teal-400';
        case 'closed': return 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-700/50 dark:text-gray-400';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getPriorityColor = (priority) => {
    switch (priority) {
        case 'low': return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
        case 'medium': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
        case 'high': return 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400';
        case 'urgent': return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400 font-bold';
        default: return 'bg-gray-100 text-gray-800';
    }
};

onMounted(() => {
    if (message.value) {
        useToast().success(message.value);
    }
});
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="props.task.title" />

        <SectionMain>
            <SectionTitleLineWithButton
                :icon="mdiFormatListBulleted"
                title="Task Details"
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
                class="mb-4"
            >
                {{ message }}
            </NotificationBar>

            <!-- Grid Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Col 1 & 2: Task Info & Discussion Feed -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Task Information Card -->
                    <CardBox class="relative">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span :class="['text-xs font-semibold px-2.5 py-0.5 rounded border capitalize mr-2', getStatusColor(props.task.status)]">
                                    {{ props.task.status.replace('_', ' ') }}
                                </span>
                                <span :class="['text-xs font-semibold px-2 py-0.5 rounded border uppercase', getPriorityColor(props.task.priority)]">
                                    {{ props.task.priority }}
                                </span>
                            </div>

                            <!-- Manager Workflow Action Controls -->
                            <div class="flex items-center space-x-2" v-if="props.task.status !== 'closed'">
                                <BaseButton
                                    v-if="props.task.status === 'completed'"
                                    :icon="mdiCheckCircle"
                                    color="success"
                                    small
                                    label="Verify"
                                    @click="updateTaskStatus('verified')"
                                />
                                <BaseButton
                                    v-if="props.task.status === 'verified' || props.task.status === 'completed'"
                                    :icon="mdiCloseCircle"
                                    color="info"
                                    small
                                    label="Close"
                                    @click="updateTaskStatus('closed')"
                                />
                                <BaseButton
                                    v-if="props.task.status === 'completed'"
                                    :icon="mdiRefresh"
                                    color="danger"
                                    small
                                    label="Reject"
                                    @click="updateTaskStatus('in_progress')"
                                />
                            </div>
                        </div>

                        <h2 class="text-xl font-bold text-gray-800 dark:text-slate-100 mb-2">
                            {{ props.task.title }}
                        </h2>
                        
                        <p class="text-sm text-gray-600 dark:text-slate-300 whitespace-pre-wrap leading-relaxed mb-6">
                            {{ props.task.description || "No description provided." }}
                        </p>

                        <!-- Attached Media Previews -->
                        <div v-if="props.files.length > 0" class="border-t border-gray-200 dark:border-slate-700 pt-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Task Attachments</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div
                                    v-for="file in props.files"
                                    :key="file.id"
                                    class="border rounded-lg p-3 bg-gray-50 dark:bg-slate-800/40 border-gray-200 dark:border-slate-700/60"
                                >
                                    <!-- Display Player for Voice Notes -->
                                    <div v-if="isAudioFile(file)" class="space-y-1">
                                        <div class="text-xs text-gray-500 font-medium truncate mb-1">🎤 {{ file.file_name }}</div>
                                        <audio :src="file.download_url" controls class="w-full h-8"></audio>
                                    </div>
                                    <!-- Display Image previews -->
                                    <div v-else-if="isImageFile(file.file_type)" class="space-y-2">
                                        <div class="text-xs text-gray-500 font-medium truncate mb-1">🖼 {{ file.file_name }}</div>
                                        <img :src="file.download_url" class="max-h-32 rounded object-cover" />
                                        <a :href="file.download_url" target="_blank" class="text-xs text-blue-500 hover:underline block">View Full Image</a>
                                    </div>
                                    <!-- Document Downloads -->
                                    <div v-else class="flex items-center justify-between">
                                        <span class="text-xs text-gray-700 dark:text-slate-300 truncate font-medium flex-1">📄 {{ file.file_name }}</span>
                                        <div class="flex items-center space-x-2 ml-2">
                                            <span class="text-gray-400 text-xxs font-mono">{{ formatBytes(file.file_size) }}</span>
                                            <a :href="file.download_url" target="_blank" class="text-xs text-blue-600 font-semibold hover:underline">Download</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Discussion & Feedback Feed -->
                    <CardBox>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">
                            Discussion Feed &amp; Logs
                        </h3>

                        <div v-if="props.comments.length === 0" class="text-sm text-gray-500 py-6 text-center">
                            No comments or status updates logged yet.
                        </div>

                        <div v-else class="space-y-4 max-h-96 overflow-y-auto pr-2">
                            <div
                                v-for="c in props.comments"
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

                                <!-- Comment Attachments -->
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

                        <!-- Add Comment form -->
                        <div class="border-t border-gray-200 dark:border-slate-700 pt-4 mt-6 space-y-4">
                            <FormField label="Add Comment / Feedback">
                                <textarea
                                    v-model="commentForm.comment"
                                    class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-sm focus:ring-blue-500"
                                    rows="3"
                                    placeholder="Type comment or notes..."
                                    required
                                ></textarea>
                            </FormField>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Comment attachments -->
                                <div>
                                    <label class="text-xs font-semibold text-gray-500 block mb-1">Attach Files</label>
                                    <div class="flex items-center space-x-2">
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
                                    </div>

                                    <!-- Queued comment files -->
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

                                <!-- Audio note voice recording -->
                                <VoiceRecorder @recorded="handleRecordedVoiceNote" />
                            </div>

                            <div class="flex justify-end pt-2">
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

                <!-- Col 3: Side Panel (Assignees, CC loop) -->
                <div class="space-y-6">
                    <CardBox>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">
                            Assignees Status
                        </h3>

                        <div class="space-y-4">
                            <div
                                v-for="assignee in props.assignees"
                                :key="assignee.id"
                                class="flex items-start justify-between p-2.5 rounded border border-gray-100 bg-gray-50/30 dark:border-slate-800 dark:bg-slate-900/10"
                            >
                                <div class="space-y-1">
                                    <div class="text-sm font-bold text-gray-800 dark:text-slate-200">{{ assignee.name }}</div>
                                    <div class="text-xs text-gray-500">{{ assignee.phone || "No phone number" }}</div>
                                    
                                    <div v-if="assignee.feedback" class="text-xs text-gray-600 dark:text-slate-400 bg-white dark:bg-slate-900 p-1.5 rounded border dark:border-slate-800 mt-1 italic">
                                        &ldquo;{{ assignee.feedback }}&rdquo;
                                    </div>
                                    
                                    <div v-if="assignee.completed_at" class="text-xxs text-gray-400 mt-1">
                                        Submitted: {{ assignee.completed_at }}
                                    </div>
                                </div>

                                <span :class="['text-xs font-bold px-2 py-0.5 rounded capitalize border', getStatusColor(assignee.status)]">
                                    {{ assignee.status.replace('_', ' ') }}
                                </span>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Loop Users CC -->
                    <CardBox v-if="props.viewers.length > 0">
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-3">
                            In CC Loop (View Only)
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="(v, idx) in props.viewers"
                                :key="idx"
                                class="text-xs px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"
                            >
                                {{ v }}
                            </span>
                        </div>
                    </CardBox>

                    <!-- Timing and info panel -->
                    <CardBox>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-3">
                            Task Properties
                        </h3>
                        <div class="space-y-2.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Created By:</span>
                                <span class="font-semibold text-gray-700 dark:text-slate-300">{{ props.task.creator }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Created At:</span>
                                <span class="font-semibold text-gray-700 dark:text-slate-300">{{ props.task.created_at }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Due Date/Time:</span>
                                <span class="font-semibold text-red-600 dark:text-red-400">{{ props.task.due_date }}</span>
                            </div>
                            <div class="flex justify-between" v-if="props.task.is_recurring">
                                <span class="text-gray-500">Recurrence:</span>
                                <span class="font-semibold text-blue-600 dark:text-blue-400 capitalize">{{ props.task.recurrence_type }}</span>
                            </div>
                        </div>
                    </CardBox>
                </div>
            </div>
        </SectionMain>

        <!-- Task Status Action Modal -->
        <CardBoxModal
            v-model="isStatusModalActive"
            :title="statusModalTitle"
            :button="statusModalButtonColor"
            :buttonLabel="statusModalButtonLabel"
            has-cancel
            @confirm="submitStatusUpdate"
        >
            <FormField label="Feedback / Reason" help="Please provide optional feedback or reason for this status update.">
                <textarea
                    v-model="statusForm.comment"
                    class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-slate-800 text-sm focus:ring-blue-500"
                    rows="3"
                    placeholder="Enter reason or feedback..."
                ></textarea>
            </FormField>
        </CardBoxModal>
    </LayoutAuthenticated>
</template>
