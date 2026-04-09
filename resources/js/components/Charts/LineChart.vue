<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from "vue";
import {
  Chart,
  LineElement,
  PointElement,
  LineController,
  LinearScale,
  CategoryScale,
  Tooltip,
} from "chart.js";

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  options: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  height: {
    type: [String, Number],
    required: false,
    default: null,
  },
});

const root = ref(null);

let chart;

Chart.register(
  LineElement,
  PointElement,
  LineController,
  LinearScale,
  CategoryScale,
  Tooltip
);

onMounted(() => {
  // apply height to the canvas element's style if provided
  if (props.height && root.value) {
    const h = typeof props.height === 'number' ? `${props.height}px` : props.height;
    root.value.style.height = h;
  }

  const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: { display: false },
      x: { display: true },
    },
    plugins: { legend: { display: false } },
  };

  const mergedOptions = {
    ...defaultOptions,
    ...(props.options || {}),
    plugins: {
      ...defaultOptions.plugins,
      ...(props.options?.plugins || {}),
    },
    scales: {
      ...defaultOptions.scales,
      ...(props.options?.scales || {}),
    },
  };

  chart = new Chart(root.value, {
    type: "line",
    data: props.data,
    options: mergedOptions,
  });
});

const chartData = computed(() => props.data);

watch(chartData, (data) => {
  if (chart) {
    chart.data = data;
    chart.update();
  }
});

// watch for options changes
watch(() => props.options, (opts) => {
  if (chart && opts) {
    chart.options = {
      ...chart.options,
      ...(opts || {}),
    };
    chart.update();
  }
});

onBeforeUnmount(() => {
  if (chart) {
    try { chart.destroy(); } catch (e) { /* ignore */ }
    chart = null;
  }
});
</script>

<template>
  <canvas ref="root" />
</template>
