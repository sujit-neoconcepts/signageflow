<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { mdiViewDashboard, mdiAlert, mdiCurrencyUsd, mdiFileDocumentMultiple, mdiPackageVariantClosed, mdiPipe, mdiSquare, mdiCircle } from "@mdi/js";

import { computed } from "vue";

import SectionMain from "@/components/SectionMain.vue";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionTitleLineWithButton from "@/components/SectionTitleLineWithButton.vue";
import NotificationBar from "@/components/NotificationBar.vue";
import CardBoxWidget from "@/components/CardBoxWidget.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import BaseButtons from "@/components/BaseButtons.vue";

const page = usePage();

const message = computed(() => page.props.flash.message);
const msg_type = computed(() => page.props.flash.msg_type ?? "warning");

const user_rol_name = page.props.auth.user.name + " Dashboard";

const totalSales = computed(() => page.props.totalSales);
const totalEnquiries = computed(() => page.props.totalEnquiries);
const totalCoils = computed(() => page.props.totalCoils);
const totalSheets = computed(() => page.props.totalSheets);
const totalBlusters = computed(() => page.props.totalBlusters);
const totalBlackTubes = computed(() => page.props.totalBlackTubes);

</script>

<template>
    <LayoutAuthenticated>
        <Head title="Dashboard" />
        <SectionMain>
            <SectionTitleLineWithButton
                :icon="mdiViewDashboard"
                :title="user_rol_name"
                main
            >
                &nbsp;
            </SectionTitleLineWithButton>
            <NotificationBar
                v-if="message"
                @closed="page.props.flash.message = ''"
                :color="msg_type"
                :icon="mdiAlert"
                :outline="true"
            >
                {{ message }}
            </NotificationBar>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
                <CardBoxWidget
                    trend="Overall"
                    trend-type="up"
                    color="text-emerald-500"
                    :icon="mdiCurrencyUsd"
                    :number="totalSales"
                    label="Total Sales"
                />
                <CardBoxWidget
                    trend="Overall"
                    trend-type="up"
                    color="text-blue-500"
                    :icon="mdiFileDocumentMultiple"
                    :number="totalEnquiries"
                    label="Total Enquiries"
                />
            </div>

            <SectionTitleLineWithButton
                :icon="mdiPackageVariantClosed"
                title="Stock Overview"
            />

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4 mb-6">
                <CardBoxWidget
                    color="text-purple-500"
                    :icon="mdiPipe"
                    :number="totalCoils"
                    label="Total Coils"
                />
                <CardBoxWidget
                    color="text-yellow-500"
                    :icon="mdiSquare"
                    :number="totalSheets"
                    label="Total Sheets"
                />
                <CardBoxWidget
                    color="text-green-500"
                    :icon="mdiCircle"
                    :number="totalBlusters"
                    label="Total Blusters"
                />
                <CardBoxWidget
                    color="text-orange-500"
                    :icon="mdiPipe"
                    :number="totalBlackTubes"
                    label="Total Black Tubes"
                />
            </div>

            <CardBox has-table>
                <div class="p-4">
                    <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>
                    <p>Implement a table or list for recent sales, enquiries, or stock movements here.</p>
                    <BaseButtons>
                        <BaseButton
                            label="View All Sales"
                            color="info"
                            :href="route('sales.index')"
                        />
                        <BaseButton
                            label="View All Enquiries"
                            color="info"
                            :href="route('enquiry.index')"
                        />
                    </BaseButtons>
                </div>
            </CardBox>

        </SectionMain>
    </LayoutAuthenticated>
</template>
