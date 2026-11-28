<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import { mdiUpload } from "@mdi/js";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import FlashBanner from "@hrms/Components/FlashBanner.vue";

const form = useForm({ file: null });
const submit = () => form.post(route("hrms.employees.import.store"));
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Import Employees" />
        <SectionMain>
            <SectionTitleLineWithButton :icon="mdiUpload" title="Bulk Employee Import" main />
            <FlashBanner />
            <CardBox is-form @submit.prevent="submit">
                <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
                    Columns: <code>Emp Code | First Name | Last Name | DOJ (YYYY-MM-DD) | Dept Code | Desig Code | Shift Code | Email | Phone | Gender</code>
                </p>
                <input type="file" accept=".xlsx,.xls,.csv" @change="form.file = $event.target.files[0]" class="mb-4 block text-sm" />
                <BaseButton type="submit" color="info" label="Upload & Import" :disabled="!form.file || form.processing" />
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>
