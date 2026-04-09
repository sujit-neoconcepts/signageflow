<script setup>
import { Head } from "@inertiajs/vue3";
import LayoutAuthenticated from "@/layouts/LayoutAuthenticated.vue";
import SectionMain from "@/components/SectionMain.vue";
import CardBox from "@/components/CardBox.vue";
import BaseButton from "@/components/BaseButton.vue";
import QRCode from "qrcode";
import { onMounted, ref } from "vue";

const props = defineProps({
    barcodeData: {
        type: Object,
        required: true,
    },
});

const codeType = ref("qrcode"); // Changed default to qrcode instead of barcode

onMounted(() => {
    generateCodes();
});

const generateCodes = () => {
    const quantity = parseInt(props.barcodeData.quantity);
    // Clear previous elements first
    for (let i = 1; i <= quantity; i++) {
        const container = document.getElementById(`code-container-${i}`);
        container.innerHTML = "";

        const canvasElement = document.createElement("canvas");
        canvasElement.id = `code-${i}`;
        canvasElement.className = "qrcode mx-auto";
        container.appendChild(canvasElement);

        QRCode.toCanvas(canvasElement, props.barcodeData.internalName, {
            width: 100,
            margin: 2,
        });
    }
};

const toggleCodeType = () => {
    codeType.value = codeType.value === "barcode" ? "qrcode" : "barcode";
    generateCodes();
};

const printCodes = () => {
    window.print();
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <LayoutAuthenticated>
        <Head title="Print Codes" />
        <SectionMain class="print-area">
            <CardBox>
                <div class="mb-4 text-right">
                    <BaseButton
                        class="mr-2"
                        :label="`Print ${
                            codeType === 'barcode' ? 'Barcodes' : 'QR Codes'
                        }`"
                        @click="printCodes"
                        color="info"
                    />
                    <BaseButton label="Back" @click="goBack" color="info" />
                </div>
                <div
                    class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-6 lg:grid-cols-8 gap-2 text-xs font-bold"
                >
                    <div
                        v-for="i in parseInt(barcodeData.quantity)"
                        :key="i"
                        class="code-item p-1 border rounded"
                    >
                        <div class="text-center mb-1">
                            {{ barcodeData.internalName }} /
                            {{ barcodeData.group_name }}
                        </div>
                        <div class="float-left text-left text-xs mb-2">
                            {{ barcodeData.invoiceID }}
                        </div>
                        <div class="float-right text-right text-xs mb-2">
                            <div>
                                Unit: {{ barcodeData.unit }} {{ i }} of
                                {{ parseInt(barcodeData.quantity) }}
                            </div>
                        </div>

                        <div class="text-center">
                            <div
                                :id="`code-container-${i}`"
                                class="code-container"
                            ></div>
                        </div>
                        <div class="float-left text-left text-xs">
                            {{ barcodeData.pur_incharge }}
                        </div>
                        <div class="float-right text-right text-xs">
                            {{ barcodeData.pur_loc }}
                        </div>
                    </div>
                </div>
            </CardBox>
        </SectionMain>
    </LayoutAuthenticated>
</template>

<style scoped>
.barcode {
    width: 100%;
}
.qrcode {
    display: block;
}
.code-container {
    width: 100%;
    min-height: 100px;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>
