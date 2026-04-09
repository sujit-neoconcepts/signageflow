<script setup>
import { containerMaxW } from "@/config.js";
</script>

<template>
    <section
        class="px-3 py-1 flex-1 min-h-[calc(100vh-64px)]"
        :class="containerMaxW"
    >
        <slot />
    </section>
</template>
