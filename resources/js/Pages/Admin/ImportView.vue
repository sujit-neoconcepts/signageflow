<script setup>
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import BaseButton from "@/components/BaseButton.vue";
import FormField from "@/components/FormField.vue";

import { ref, computed } from "vue";
import { mdiUpload, mdiDownload, mdiAlert } from "@mdi/js";

const message = computed(() => usePage().props.flash.message);
const msg_type = computed(() => usePage().props.flash.msg_type ?? "warning");

const props = defineProps({
    resourceNeo: {
        type: Object,
        required: true,
    },
    sampleData: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    file: null,
});

const fileInput = ref(null);
const selectedFileName = ref("");

const submit = () => {
    form.post(route(props.resourceNeo.resourceName + ".import"), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedFileName.value = "";
            if (fileInput.value) {
                fileInput.value.value = "";
            }
        },
    });
};

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        // Validate file type
        const allowedTypes = ["text/csv", "application/csv", "text/plain"];
        if (!allowedTypes.includes(file.type) && !file.name.endsWith(".csv")) {
            alert("Please select a valid CSV file.");
            e.target.value = "";
            selectedFileName.value = "";
            return;
        }

        // Validate file size (2MB limit)
        const maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if (file.size > maxSize) {
            alert("File size must be less than 2MB.");
            e.target.value = "";
            selectedFileName.value = "";
            return;
        }

        form.file = file;
        selectedFileName.value = file.name;
    } else {
        selectedFileName.value = "";
    }
};

const downloadSample = () => {
    // Convert sample data to CSV
    const headers = Object.keys(props.sampleData[0]).join(",");
    const rows = props.sampleData.map((row) => Object.values(row).join(","));
    const csv = [headers, ...rows].join("\n");

    // Create and trigger download
    const blob = new Blob([csv], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${props.resourceNeo.resourceName}_sample.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
};
</script>

<template>
    <LayoutAuthenticated>
        <Head :title="`Import ${props.resourceNeo.resourceTitle}`" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="props.resourceNeo.iconPath"
                :title="`Import ${props.resourceNeo.resourceTitle}`"
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
                                :icon="exLink.icon"
                                color="success"
                                rounded-full
                                small
                                :label="exLink.label"
                            /> </Link
                    ></span>
                </div>
            </SectionTitleLineWithButton>
            <NotificationBar
                v-if="message"
                @closed="usePage().props.flash.message = ''"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
            >
                <div class="whitespace-pre-line">{{ message }}</div>
            </NotificationBar>
            <!-- Instructions Card -->
            <CardBox class="mb-6">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div
                            class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center"
                        >
                            <svg
                                class="w-4 h-4 text-blue-600 dark:text-blue-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd"
                                ></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3
                            class="text-lg font-medium text-gray-900 dark:text-white mb-2"
                        >
                            Import Instructions
                        </h3>
                        <div
                            class="text-sm text-gray-600 dark:text-gray-300 space-y-2"
                        >
                            <p>• Upload a CSV file with the required columns</p>
                            <p>
                                • Download the sample CSV below to see the
                                correct format
                            </p>
                            <p>
                                • Ensure all required fields are filled and
                                follow the validation rules
                            </p>
                            <p>• The first row should contain column headers</p>
                        </div>
                    </div>
                </div>
            </CardBox>

            <!-- Upload Form Card -->
            <CardBox>
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                >
                    Upload CSV File
                </h3>
                <form @submit.prevent="submit" class="space-y-6">
                    <FormField
                        label="Select CSV File"
                        help="Choose a CSV file to import. Maximum file size: 2MB"
                        :error="form.errors.file"
                    >
                        <input
                            type="file"
                            ref="fileInput"
                            accept=".csv,.txt"
                            @change="handleFileChange"
                            class="block w-full text-sm text-gray-900 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900 dark:file:text-blue-300 dark:hover:file:bg-blue-800"
                            :class="{
                                'border-red-500 dark:border-red-400 focus:ring-red-500 focus:border-red-500':
                                    form.errors.file,
                            }"
                        />
                        <div
                            v-if="selectedFileName"
                            class="mt-2 text-sm text-gray-600 dark:text-gray-400"
                        >
                            Selected file:
                            <span class="font-medium">{{
                                selectedFileName
                            }}</span>
                        </div>
                    </FormField>

                    <div class="flex items-center space-x-3">
                        <BaseButton
                            type="submit"
                            :icon="mdiUpload"
                            label="Import Data"
                            color="info"
                            :disabled="!form.file || form.processing"
                            :loading="form.processing"
                        />
                        <Link
                            :href="
                                route(props.resourceNeo.resourceName + '.index')
                            "
                        >
                            <BaseButton
                                type="button"
                                label="Cancel"
                                color="info"
                                outline
                            />
                        </Link>
                    </div>
                </form>
            </CardBox>

            <!-- Sample Data Card -->
            <CardBox class="mt-6">
                <div class="flex items-center justify-between mb-4">
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Sample Data Format
                    </h3>
                    <BaseButton
                        :icon="mdiDownload"
                        label="Download Sample CSV"
                        color="success"
                        small
                        @click="downloadSample"
                    />
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th
                                    v-for="(value, key) in props.sampleData[0]"
                                    :key="key"
                                    class="text-left font-medium text-gray-900 dark:text-white"
                                >
                                    {{ key }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in props.sampleData"
                                :key="index"
                            >
                                <td
                                    v-for="(value, key) in row"
                                    :key="key"
                                    class="text-gray-600 dark:text-gray-300"
                                >
                                    {{ value }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
