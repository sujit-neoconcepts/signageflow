<template>
    <div class="barcode-scanner">
        <!-- Scanner will be mounted to the target element -->
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import Quagga from "quagga";
import jsQR from "jsqr";

const props = defineProps({
    target: {
        type: String,
        required: true,
    },
    mode: {
        type: String,
        default: "barcode", // or 'qrcode'
    },
    currentIndex: {
        // Add new prop to track index changes
        type: Number,
    },
});

const emit = defineEmits(["detected"]);

const stream = ref(null);
let animationFrameId = null; // Add this to track animation frame

const startBarcodeScanner = () => {
    Quagga.init(
        {
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: props.target,
                constraints: {
                    facingMode: "environment",
                },
            },
            decoder: {
                readers: [
                    "code_128_reader",
                    "ean_reader",
                    "ean_8_reader",
                    "code_39_reader",
                    "upc_reader",
                    "upc_e_reader",
                ],
            },
        },
        function (err) {
            if (err) {
                console.error(err);
                return;
            }
            console.log("Initialization finished. Ready to start");
            Quagga.start();
        }
    );

    Quagga.onDetected((result) => {
        if (result.codeResult.code) {
            emit("detected", result.codeResult.code);
        }
    });
};

const startQRScanner = () => {
    const video = document.createElement("video");
    const canvasElement = document.createElement("canvas");
    const canvas = canvasElement.getContext("2d", { willReadFrequently: true });
    const targetElement = document.querySelector(props.target);

    if (targetElement) {
        targetElement.innerHTML = ""; // Clear previous content
        targetElement.appendChild(canvasElement);
    }

    navigator.mediaDevices
        .getUserMedia({ video: { facingMode: "environment" } })
        .then((mediaStream) => {
            stream.value = mediaStream; // Store stream reference
            video.srcObject = mediaStream;
            video.setAttribute("playsinline", true);
            video.play();

            const tick = () => {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvasElement.height = video.videoHeight;
                    canvasElement.width = video.videoWidth;
                    canvas.drawImage(
                        video,
                        0,
                        0,
                        canvasElement.width,
                        canvasElement.height
                    );

                    const imageData = canvas.getImageData(
                        0,
                        0,
                        canvasElement.width,
                        canvasElement.height
                    );
                    const code = jsQR(
                        imageData.data,
                        imageData.width,
                        imageData.height
                    );
                    if (code && code.data) {
                        emit("detected", code.data);
                    }
                }
                animationFrameId = requestAnimationFrame(tick);
            };

            animationFrameId = requestAnimationFrame(tick);
        });
};

// Add watch for currentIndex changes
watch(
    () => props.currentIndex,
    () => {
        if (props.mode === "qrcode") {
            // Stop current scanner
            if (stream.value) {
                stream.value.getTracks().forEach((track) => track.stop());
                stream.value = null;
            }
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
            }
            // Restart scanner
            if (props.currentIndex != null) {
                startQRScanner();
            }
        }
    }
);

onMounted(() => {
    console.log(props.currentIndex);
    if (props.mode === "qrcode") {
        //startQRScanner();
    } else {
        startBarcodeScanner();
    }
});

onUnmounted(() => {
    if (props.mode === "barcode") {
        Quagga.stop();
    } else if (props.mode === "qrcode") {
        if (stream.value) {
            stream.value.getTracks().forEach((track) => track.stop());
            stream.value = null;
        }
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
        }
    }

    // Clean up video element and canvas
    const targetElement = document.querySelector(props.target);
    if (targetElement) {
        targetElement.innerHTML = "";
    }
});
</script>

<style scoped>
.barcode-scanner {
    width: 100%;
    max-width: 640px;
    margin: 0 auto;
}

.viewport {
    width: 100%;
    height: 300px;
    position: relative;
}

.viewport > video {
    width: 100%;
    height: 100%;
}

.viewport > canvas {
    position: absolute;
    top: 0;
    left: 0;
}

.result {
    margin-top: 20px;
    padding: 10px;
    background-color: #f0f0f0;
    border-radius: 4px;
}
</style>
