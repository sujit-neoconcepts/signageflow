<script setup>
import { ref, onUnmounted } from 'vue';
import { mdiMicrophone, mdiStop, mdiPlay, mdiDelete, mdiAlertCircle } from '@mdi/js';
import BaseButton from '@/components/BaseButton.vue';
import BaseIcon from '@/components/BaseIcon.vue';

const emit = defineEmits(['recorded']);

const isRecording = ref(false);
const audioUrl = ref(null);
const audioBlob = ref(null);
const recordingTime = ref(0);
const error = ref(null);

let mediaRecorder = null;
let audioChunks = [];
let timerInterval = null;

const startRecording = async () => {
  audioChunks = [];
  audioUrl.value = null;
  audioBlob.value = null;
  error.value = null;

  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mediaRecorder = new MediaRecorder(stream);
    
    mediaRecorder.ondataavailable = (event) => {
      audioChunks.push(event.data);
    };

    mediaRecorder.onstop = () => {
      audioBlob.value = new Blob(audioChunks, { type: 'audio/webm' });
      audioUrl.value = URL.createObjectURL(audioBlob.value);
      
      // Emit the audio file back to parent form
      const audioFile = new File([audioBlob.value], `voice_note_${Date.now()}.webm`, {
        type: 'audio/webm',
        lastModified: Date.now()
      });
      emit('recorded', audioFile);

      // Stop all tracks in the stream to release microphone
      stream.getTracks().forEach(track => track.stop());
    };

    mediaRecorder.start();
    isRecording.value = true;
    recordingTime.value = 0;
    
    timerInterval = setInterval(() => {
      recordingTime.value++;
    }, 1000);
  } catch (err) {
    console.error('Microphone access denied or error:', err);
    error.value = 'Microphone access denied or not available. Please allow permissions.';
  }
};

const stopRecording = () => {
  if (mediaRecorder && isRecording.value) {
    mediaRecorder.stop();
    isRecording.value = false;
    clearInterval(timerInterval);
  }
};

const deleteRecording = () => {
  audioUrl.value = null;
  audioBlob.value = null;
  recordingTime.value = 0;
  emit('recorded', null); // clear recorded file in parent
};

const formatTime = (seconds) => {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
  <div class="p-4 border border-gray-200 dark:border-slate-700 rounded-lg bg-gray-50 dark:bg-slate-800">
    <div class="flex flex-col items-center justify-center space-y-3">
      <div class="text-sm font-semibold text-gray-500 dark:text-slate-400">
        Voice Note Recorder
      </div>

      <!-- Error message -->
      <div v-if="error" class="flex items-center text-xs text-red-500 space-x-1">
        <BaseIcon :path="mdiAlertCircle" size="16" />
        <span>{{ error }}</span>
      </div>

      <!-- State: Not recording & no audio yet -->
      <div v-if="!isRecording && !audioUrl" class="flex items-center space-x-2">
        <BaseButton
          type="button"
          :icon="mdiMicrophone"
          color="danger"
          label="Start Recording"
          @click="startRecording"
          rounded-full
        />
      </div>

      <!-- State: Recording in progress -->
      <div v-if="isRecording" class="flex flex-col items-center space-y-2">
        <div class="flex items-center space-x-2">
          <span class="w-3 h-3 bg-red-500 rounded-full animate-ping"></span>
          <span class="text-lg font-mono font-bold text-red-500">
            {{ formatTime(recordingTime) }}
          </span>
        </div>
        <BaseButton
          type="button"
          :icon="mdiStop"
          color="info"
          label="Stop Recording"
          @click="stopRecording"
          rounded-full
        />
      </div>

      <!-- State: Recorded Audio Player -->
      <div v-if="audioUrl && !isRecording" class="flex flex-col items-center w-full space-y-2">
        <audio :src="audioUrl" controls class="w-full h-10 max-w-xs"></audio>
        <div class="flex space-x-2">
          <BaseButton
            type="button"
            :icon="mdiMicrophone"
            color="danger"
            label="Record Again"
            @click="startRecording"
            small
            outline
          />
          <BaseButton
            type="button"
            :icon="mdiDelete"
            color="danger"
            label="Discard"
            @click="deleteRecording"
            small
          />
        </div>
      </div>
    </div>
  </div>
</template>
