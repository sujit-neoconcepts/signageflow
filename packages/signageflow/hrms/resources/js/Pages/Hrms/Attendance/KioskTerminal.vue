<script setup>
import { ref } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";

const props = defineProps({ kioskCode: String });

const kioskCode = ref(props.kioskCode ?? "");
const deviceToken = ref("");
const empCode = ref("");
const pin = ref("");
const qrToken = ref("");
const direction = ref("auto");
const statusMsg = ref("");
const isError = ref(false);
const loading = ref(false);

const submitPunch = async () => {
    loading.value = true;
    statusMsg.value = "";
    isError.value = false;
    try {
        const res = await axios.post(route("hrms.kiosk.punch"), {
            kiosk_code: kioskCode.value,
            device_token: deviceToken.value,
            emp_code: empCode.value || null,
            pin: pin.value || null,
            qr_token: qrToken.value || null,
            direction: direction.value,
        });
        statusMsg.value = res.data.message;
        empCode.value = "";
        pin.value = "";
        qrToken.value = "";
    } catch (err) {
        isError.value = true;
        statusMsg.value = err.response?.data?.message ?? "Punch failed.";
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="SignageFlow HRMS Kiosk" />
    <div class="min-h-screen bg-slate-900 text-white flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-slate-800 rounded-2xl p-6 shadow-xl border border-slate-700">
            <h1 class="text-2xl font-bold mb-1">Attendance Kiosk</h1>
            <p class="text-xs text-slate-400 mb-5">Enter Employee Code + PIN or scan QR Badge</p>

            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <input v-model="kioskCode" placeholder="Kiosk Code" class="rounded bg-slate-900 border-slate-700" />
                    <input v-model="deviceToken" type="password" placeholder="Device Token" class="rounded bg-slate-900 border-slate-700" />
                </div>
                <input v-model="empCode" placeholder="Employee Code" class="w-full rounded bg-slate-900 border-slate-700 text-lg py-2" />
                <input v-model="pin" type="password" placeholder="PIN" class="w-full rounded bg-slate-900 border-slate-700 text-lg py-2" />
                <input v-model="qrToken" placeholder="Or QR Badge Token" class="w-full rounded bg-slate-900 border-slate-700" />
                <select v-model="direction" class="w-full rounded bg-slate-900 border-slate-700">
                    <option value="auto">Auto Detect (In / Out)</option>
                    <option value="in">Clock In</option>
                    <option value="out">Clock Out</option>
                </select>
                <button @click="submitPunch" :disabled="loading" class="w-full py-3 rounded-xl bg-sky-600 hover:bg-sky-500 font-bold text-base">
                    {{ loading ? "Recording..." : "Record Attendance Punch" }}
                </button>
                <div v-if="statusMsg" class="p-3 rounded text-center font-semibold" :class="isError ? 'bg-rose-900/60 text-rose-200' : 'bg-emerald-900/60 text-emerald-200'">
                    {{ statusMsg }}
                </div>
            </div>
        </div>
    </div>
</template>
