<script setup>
import { onMounted, ref } from "vue";
import QRCode from "qrcode";

const props = defineProps({
    code: {
        type: String,
        required: true,
    },
    codeid: {
        type: String,
        required: true,
    },
});

//const coilqrcode = ref(null);

onMounted(() => {
    generateCodes(props.code);
});

const generateCodes = (value) => {
    const container = document.getElementById(props.codeid);
    container.innerHTML = "";

    const canvasElement = document.createElement("canvas");
    canvasElement.className = "qrcode mx-auto";
    container.appendChild(canvasElement);

    QRCode.toCanvas(canvasElement, value, {
        width: 100,
        margin: 2,
    });
};
</script>

<template>
    <div class="m-4 flex flex-col items-end">
        <div :id="props.codeid" class="code-container"></div>
        <div class="text-sm whitespace-nowrap" style="text-align: right;">
            {{ props.code }}
        </div>
    </div>
</template>
<style scoped>
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
