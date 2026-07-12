<script setup>
import { Head, Link, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import {
    mdiFormatListBulleted,
    mdiFileEdit,
    mdiEye,
} from "@mdi/js";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import BaseButton from "@/components/BaseButton.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import { useToast } from "vue-toast-notification";
import "vue-toast-notification/dist/theme-sugar.css";
import { computed, onMounted } from "vue";
import { can } from '@/utils/permissions';

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    job: { type: Object, default: () => ({}) },
    tasks: { type: Array, default: () => [] },
    customTasks: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    progress: { type: Number, default: 0 },
    totalTasks: { type: Number, default: 0 },
    completedTasks: { type: Number, default: 0 },
    groupedComments: { type: Array, default: () => [] },
    groupedExpenses: { type: Array, default: () => [] },
    expenseSummary: { type: Object, default: () => ({}) },
});

const getStatusColor = (status) => {
    switch (status) {
        case 'not_started': case 'pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400';
        case 'in_progress': return 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/20 dark:text-purple-400';
        case 'accepted': return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400';
        case 'completed': return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400';
        case 'verified': return 'bg-teal-100 text-teal-800 border-teal-200 dark:bg-teal-900/20 dark:text-teal-400';
        case 'closed': return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700/50 dark:text-gray-400';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const getJobStatusColor = (status) => {
    switch (status) {
        case 'not_started': return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400';
        case 'in_progress': return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400';
        case 'completed': return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400';
        case 'closed': return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700/50 dark:text-slate-400';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const formatBytes = (bytes) => {
    if (!bytes) return "0 B";
    const k = 1024;
    const sizes = ["B", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
};

const isAudioFile = (file) => {
    const type = file.file_type || "";
    const name = (file.file_name || "").toLowerCase();
    if (type.startsWith("audio/") || type === "video/webm") return true;
    if (name.includes("voice_note") || name.endsWith(".webm") || name.endsWith(".ogg") || name.endsWith(".wav") || name.endsWith(".mp3")) return true;
    return false;
};

const isImageFile = (mimeType) => mimeType && mimeType.startsWith("image/");

const getTaskProgress = (task) => {
    if (!task.assignees || task.assignees.length === 0) return 0;
    const completed = task.assignees.filter(a => ['completed', 'verified', 'closed'].includes(a.status)).length;
    return Math.round((completed / task.assignees.length) * 100);
};

onMounted(() => {
    if (message.value) {
        useToast().success(message.value);
    }
});
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="props.job.title" />

        <SectionMain>
            <SectionTitleLineWithButton
                :icon="mdiFormatListBulleted"
                title="Job Details"
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
                    <Link v-if="can('job_edit')" :href="route('job.edit', props.job.id)">
                        <BaseButton
                            class="m-2"
                            :icon="mdiFileEdit"
                            color="info"
                            rounded-full
                            small
                            label="Edit Job"
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Job Header -->
                    <CardBox>
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span :class="['text-xs font-semibold px-2.5 py-0.5 rounded border capitalize mr-2', getJobStatusColor(props.job.status)]">
                                    {{ (props.job.status || '').replace(/_/g, ' ') }}
                                </span>
                            </div>
                        </div>

                        <h2 class="text-xl font-bold text-gray-800 dark:text-slate-100 mb-2">
                            {{ props.job.title }}
                        </h2>

                        <p class="text-sm text-gray-600 dark:text-slate-300 whitespace-pre-wrap leading-relaxed mb-6">
                            {{ props.job.description || "No description provided." }}
                        </p>

                        <!-- Progress Bar -->
                        <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Overall Progress</span>
                                <span class="text-sm font-bold" :class="props.progress >= 100 ? 'text-green-600' : 'text-blue-600'">
                                    {{ props.completedTasks }}/{{ props.totalTasks }} tasks · {{ props.progress }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                <div
                                    class="h-3 rounded-full transition-all duration-500"
                                    :class="props.progress >= 100 ? 'bg-green-500' : 'bg-blue-500'"
                                    :style="{ width: props.progress + '%' }"
                                ></div>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Stage Pipeline / Tasks -->
                    <CardBox>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">
                            Stage Pipeline
                        </h3>

                        <div v-if="props.tasks.length === 0" class="text-sm text-gray-500 py-6 text-center">
                            No tasks linked to this job.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(task, idx) in props.tasks"
                                :key="task.id"
                                class="border rounded-lg p-4 transition-all hover:shadow-sm"
                                :class="[
                                    ['completed', 'verified', 'closed'].includes(task.status)
                                        ? 'border-green-200 bg-green-50/30 dark:border-green-900/30 dark:bg-green-950/10'
                                        : task.status === 'in_progress'
                                            ? 'border-purple-200 bg-purple-50/30 dark:border-purple-900/30 dark:bg-purple-950/10'
                                            : 'border-gray-200 bg-gray-50/30 dark:border-slate-700 dark:bg-slate-900/20'
                                ]"
                            >
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center space-x-3">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold"
                                            :class="[
                                                ['completed', 'verified', 'closed'].includes(task.status)
                                                    ? 'bg-green-600 text-white'
                                                    : task.status === 'in_progress'
                                                        ? 'bg-purple-600 text-white'
                                                        : 'bg-gray-300 dark:bg-slate-600 text-gray-700 dark:text-slate-300'
                                            ]"
                                        >
                                            {{ task.job_stage_sort_order !== null ? task.job_stage_sort_order + 1 : idx + 1 }}
                                        </span>
                                        <div>
                                            <Link :href="route('task.show', task.id)" class="text-sm font-bold text-gray-800 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ task.title }}
                                            </Link>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                <span v-if="task.due_date">Due: {{ task.due_date }}</span>
                                                <span v-if="task.estimated_hours"> · {{ task.estimated_hours }}h est.</span>
                                                <span v-if="task.start_on_previous_complete" class="text-blue-500 ml-1">⟳ Auto-start</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <span :class="['text-xs font-semibold px-2 py-0.5 rounded border capitalize', getStatusColor(task.status)]">
                                            {{ (task.status || '').replace(/_/g, ' ') }}
                                        </span>
                                        <Link :href="route('task.show', task.id)">
                                            <BaseButton
                                                :icon="mdiEye"
                                                color="info"
                                                small
                                                title="View Task"
                                            />
                                        </Link>
                                    </div>
                                </div>

                                <!-- Assignees -->
                                <div v-if="task.assignees && task.assignees.length > 0" class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="a in task.assignees"
                                        :key="a.id"
                                        :class="['text-xs px-2 py-0.5 rounded border', getStatusColor(a.status)]"
                                    >
                                        {{ a.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Custom Tasks -->
                    <CardBox v-if="props.customTasks && props.customTasks.length > 0">
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">
                            Custom Tasks
                        </h3>

                        <div class="space-y-3">
                            <div
                                v-for="(task, idx) in props.customTasks"
                                :key="task.id"
                                class="border rounded-lg p-4 transition-all hover:shadow-sm"
                                :class="[
                                    ['completed', 'verified', 'closed'].includes(task.status)
                                        ? 'border-green-200 bg-green-50/30 dark:border-green-900/30 dark:bg-green-950/10'
                                        : task.status === 'in_progress'
                                            ? 'border-purple-200 bg-purple-50/30 dark:border-purple-900/30 dark:bg-purple-950/10'
                                            : 'border-gray-200 bg-gray-50/30 dark:border-slate-700 dark:bg-slate-900/20'
                                ]"
                            >
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center space-x-3">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-400"
                                        >
                                            C{{ idx + 1 }}
                                        </span>
                                        <div>
                                            <Link :href="route('task.show', task.id)" class="text-sm font-bold text-gray-800 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400">
                                                {{ task.title }}
                                            </Link>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                <span v-if="task.due_date">Due: {{ task.due_date }}</span>
                                                <span v-if="task.estimated_hours"> · {{ task.estimated_hours }}h est.</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <span :class="['text-xs font-semibold px-2 py-0.5 rounded border capitalize', getStatusColor(task.status)]">
                                            {{ (task.status || '').replace(/_/g, ' ') }}
                                        </span>
                                        <Link :href="route('task.show', task.id)">
                                            <BaseButton
                                                :icon="mdiEye"
                                                color="info"
                                                small
                                                title="View Task"
                                            />
                                        </Link>
                                    </div>
                                </div>

                                <!-- Assignees -->
                                <div v-if="task.assignees && task.assignees.length > 0" class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="a in task.assignees"
                                        :key="a.id"
                                        :class="['text-xs px-2 py-0.5 rounded border', getStatusColor(a.status)]"
                                    >
                                        {{ a.name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Job Artifacts -->
                    <CardBox v-if="props.files.length > 0">
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">
                            Job Artifacts
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div
                                v-for="file in props.files"
                                :key="file.id"
                                class="border rounded-lg p-3 bg-gray-50 dark:bg-slate-800/40 border-gray-200 dark:border-slate-700/60"
                            >
                                <div v-if="isAudioFile(file)" class="space-y-1">
                                    <div class="text-xs text-gray-500 font-medium truncate mb-1">🎤 {{ file.file_name }}</div>
                                    <audio :src="file.download_url" controls class="w-full h-8"></audio>
                                </div>
                                <div v-else-if="isImageFile(file.file_type)" class="space-y-2">
                                    <div class="text-xs text-gray-500 font-medium truncate mb-1">🖼 {{ file.file_name }}</div>
                                    <img :src="file.download_url" class="max-h-32 rounded object-cover" />
                                    <a :href="file.download_url" target="_blank" class="text-xs text-blue-500 hover:underline block">View Full Image</a>
                                </div>
                                <div v-else class="flex items-center justify-between">
                                    <span class="text-xs text-gray-700 dark:text-slate-300 truncate font-medium flex-1">📄 {{ file.file_name }}</span>
                                    <div class="flex items-center space-x-2 ml-2">
                                        <span class="text-gray-400 text-xxs font-mono">{{ formatBytes(file.file_size) }}</span>
                                        <a :href="file.download_url" target="_blank" class="text-xs text-blue-600 font-semibold hover:underline">Download</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Task History & Discussion Grouped by Stage -->
                    <CardBox v-if="props.groupedComments && props.groupedComments.length > 0">
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-4">
                            Task History &amp; Discussion
                        </h3>
                        <div class="space-y-6">
                            <div v-for="group in props.groupedComments" :key="group.stage_name" class="border-b border-gray-100 dark:border-slate-800 pb-4 last:border-0 last:pb-0">
                                <h4 class="text-sm font-bold text-blue-600 dark:text-blue-400 mb-3 flex items-center">
                                    <span class="mr-1.5">🏁</span> Stage: {{ group.stage_name }}
                                </h4>
                                <div class="space-y-3 pl-4 border-l-2 border-gray-100 dark:border-slate-800">
                                    <div v-for="c in group.comments" :key="c.id" class="p-3 bg-gray-50/50 dark:bg-slate-900/10 border border-gray-100 dark:border-slate-800 rounded-lg">
                                        <div class="flex justify-between items-start mb-1 text-xxs">
                                            <div>
                                                <span class="font-bold text-gray-700 dark:text-slate-350">{{ c.user.name }}</span>
                                                <span class="px-1 py-0.2 rounded bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-400 ml-1.5 capitalize">{{ c.user.role }}</span>
                                            </div>
                                            <span class="text-gray-400 font-mono">{{ c.created_at }}</span>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-slate-305 whitespace-pre-wrap leading-relaxed">{{ c.comment }}</p>
                                        
                                        <!-- Files in comments -->
                                        <div v-if="c.files && c.files.length > 0" class="mt-2 pt-2 border-t border-gray-100 dark:border-slate-800 space-y-1">
                                            <div v-for="file in c.files" :key="file.id" class="flex items-center space-x-2 text-xs">
                                                <span class="text-gray-400">📎</span>
                                                <a :href="file.download_url" target="_blank" class="text-blue-500 hover:underline truncate">{{ file.file_name }}</a>
                                                <span class="text-gray-400 text-xxs font-mono">({{ formatBytes(file.file_size) }})</span>
                                                <audio v-if="isAudioFile(file)" :src="file.download_url" controls class="h-6 w-48 scale-90 origin-left"></audio>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardBox>
                    <CardBox v-else>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-2">
                            Task History &amp; Discussion
                        </h3>
                        <div class="text-sm text-gray-500 py-4 text-center">
                            No task discussions logged yet for this job.
                        </div>
                    </CardBox>

                    <!-- Expenses Grouped by Stage -->
                    <CardBox>
                        <div class="flex justify-between items-center mb-4 border-b border-gray-150 dark:border-slate-700 pb-3">
                            <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200">
                                Job Expenses &amp; Deposits
                            </h3>
                            <div class="flex space-x-4 text-xs font-bold">
                                <div class="text-green-600">Total Deposits: {{ props.expenseSummary.total_deposits }}</div>
                                <div class="text-red-650">Total Expenses: {{ props.expenseSummary.total_expenses }}</div>
                                <div :class="props.expenseSummary.balance.replace(/,/g, '') >= 0 ? 'text-blue-600' : 'text-orange-600'">
                                    Balance: {{ props.expenseSummary.balance }}
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards Top row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="p-3 bg-green-50/50 dark:bg-green-950/10 border border-green-100 dark:border-green-900/30 rounded-lg flex flex-col">
                                <span class="text-xxs font-semibold text-green-700 uppercase tracking-wider">Deposits Received</span>
                                <span class="text-lg font-bold text-green-700 mt-1">{{ props.expenseSummary.total_deposits }}</span>
                            </div>
                            <div class="p-3 bg-red-50/50 dark:bg-red-950/10 border border-red-100 dark:border-red-900/30 rounded-lg flex flex-col">
                                <span class="text-xxs font-semibold text-red-700 uppercase tracking-wider">Expenses Incurred</span>
                                <span class="text-lg font-bold text-red-700 mt-1">{{ props.expenseSummary.total_expenses }}</span>
                            </div>
                            <div class="p-3 bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100 dark:border-blue-900/30 rounded-lg flex flex-col">
                                <span class="text-xxs font-semibold text-blue-700 uppercase tracking-wider">Net Balance</span>
                                <span class="text-lg font-bold text-blue-700 mt-1">{{ props.expenseSummary.balance }}</span>
                            </div>
                        </div>

                        <div v-if="props.groupedExpenses && props.groupedExpenses.length > 0" class="space-y-6">
                            <div v-for="group in props.groupedExpenses" :key="group.stage_name" class="border border-gray-100 dark:border-slate-800 rounded-lg overflow-hidden">
                                <div class="bg-gray-50 dark:bg-slate-800 p-3 flex justify-between items-center border-b border-gray-100 dark:border-slate-800">
                                    <h4 class="text-xs font-bold text-gray-700 dark:text-slate-300 uppercase tracking-wider flex items-center">
                                        <span class="mr-1.5">📁</span> Stage: {{ group.stage_name }}
                                    </h4>
                                    <div class="flex space-x-3 text-xxs font-bold">
                                        <span class="text-green-600" v-if="group.total_deposits > 0">Dep: {{ group.total_deposits_formatted }}</span>
                                        <span class="text-red-500" v-if="group.total_expenses > 0">Exp: {{ group.total_expenses_formatted }}</span>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-gray-50/30 dark:bg-slate-900/10 text-gray-500 dark:text-slate-400 font-semibold border-b border-gray-100 dark:border-slate-800">
                                                <th class="p-3">Date</th>
                                                <th class="p-3">Category</th>
                                                <th class="p-3">Type</th>
                                                <th class="p-3">Done By</th>
                                                <th class="p-3">Details</th>
                                                <th class="p-3 text-right">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                            <tr v-for="exp in group.expenses" :key="exp.id" class="hover:bg-gray-50/50 dark:hover:bg-slate-800/10 text-gray-700 dark:text-slate-350">
                                                <td class="p-3 font-mono">{{ exp.exp_date }}</td>
                                                <td class="p-3">{{ exp.exp_cate }}</td>
                                                <td class="p-3">
                                                    <span :class="['px-1.5 py-0.5 rounded text-xxs font-bold uppercase border', exp.amt_type === 'Deposit' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200']">
                                                        {{ exp.amt_type }}
                                                    </span>
                                                </td>
                                                <td class="p-3">{{ exp.doneby || '—' }}</td>
                                                <td class="p-3 truncate max-w-56" :title="exp.details">{{ exp.details || '—' }}</td>
                                                <td class="p-3 text-right font-bold text-gray-900 dark:text-slate-100">{{ exp.amount }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-gray-500 py-6 text-center border border-dashed border-gray-250 dark:border-slate-800 rounded-lg">
                            No expenses logged yet for this job.
                        </div>
                    </CardBox>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <CardBox>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-3">Job Properties</h3>
                        <div class="space-y-2.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Client:</span>
                                <span class="font-semibold text-gray-700 dark:text-slate-300">{{ props.job.client || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Workflow:</span>
                                <span class="font-semibold text-gray-700 dark:text-slate-300">{{ props.job.workflow || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Created By:</span>
                                <span class="font-semibold text-gray-700 dark:text-slate-300">{{ props.job.creator }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Created At:</span>
                                <span class="font-semibold text-gray-700 dark:text-slate-300">{{ props.job.created_at }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Due Date:</span>
                                <span class="font-semibold text-red-600 dark:text-red-400">{{ props.job.due_date }}</span>
                            </div>
                            <div class="flex justify-between" v-if="props.job.estimated_hours">
                                <span class="text-gray-500">Est. Hours:</span>
                                <span class="font-semibold text-gray-700 dark:text-slate-300">{{ props.job.estimated_hours }}h</span>
                            </div>

                            <div class="flex flex-col pt-2 border-t border-gray-150 dark:border-slate-800" v-if="props.job.enquiry_nos && props.job.enquiry_nos.length > 0">
                                <span class="text-gray-550 mb-1 font-medium">Enquiry Numbers:</span>
                                <div class="space-y-1 pl-2">
                                    <div v-for="enq in props.job.enquiry_nos" :key="enq" class="font-semibold text-gray-700 dark:text-slate-300 text-xs">
                                        🔗 {{ enq }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col pt-2 border-t border-gray-150 dark:border-slate-800" v-if="props.job.sales_order_nos && props.job.sales_order_nos.length > 0">
                                <span class="text-gray-550 mb-1 font-medium">Sales Orders:</span>
                                <div class="space-y-1 pl-2">
                                    <div v-for="so in props.job.sales_order_nos" :key="so" class="font-semibold text-gray-700 dark:text-slate-300 text-xs">
                                        📝 {{ so }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardBox>

                    <!-- Task Summary -->
                    <CardBox>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-slate-200 mb-3">Task Summary</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Total Tasks:</span>
                                <span class="font-bold text-gray-700 dark:text-slate-300">{{ props.totalTasks }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Completed:</span>
                                <span class="font-bold text-green-600">{{ props.completedTasks }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Remaining:</span>
                                <span class="font-bold text-orange-600">{{ props.totalTasks - props.completedTasks }}</span>
                            </div>
                        </div>
                    </CardBox>
                </div>
            </div>
        </SectionMain>
    </LayoutAuthenticated>
</template>
