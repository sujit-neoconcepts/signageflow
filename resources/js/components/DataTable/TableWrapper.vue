<template>
    <div class="flex flex-col">
        <div
            class="overflow-x-auto"
            :class="{
                'h-[calc(100vh-231px)]': stickyHeader,
                'pb-8': actionExpand,
            }"
        >
            <div class="align-middle inline-block min-w-full">
                <div class="border-gray-200 relative">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
const props = defineProps({
    stickyHeader: {
        type: Boolean,
        default: false,
        required: false,
    },
    actionExpand: {
        type: Boolean,
        default: false,
        required: false,
    },
});
</script>
