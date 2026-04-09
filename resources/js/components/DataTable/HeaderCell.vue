<template>
    <th
        v-show="!cell.hidden"
        class="py-0 px-1 text-sm border-r border-gray-300 dark:border-slate-700"
        :hkey="cell.key"
        :class="{
            ['!' + headerColor + ' dark:!' + headerColor + ' text-white ']:
                headerColor,
        }"
    >
        <component
            :is="cell.sortable ? 'button' : 'div'"
            class="py-0 px-1 w-full"
            :dusk="cell.sortable ? `sort-${cell.key}` : null"
            @click.prevent="onClick"
            :title="cell.extra.info"
        >
            <span
                class="flex"
                :class="{
                    'flex-col': colHeader,
                    'flex-row items-center': !colHeader,
                    'justify-end': cell.extra.align === 'right' && !colHeader,
                    'justify-center': cell.extra.align === 'center' && !colHeader,
                }"
            >
                <slot name="label"
                    ><span class="">{{ cell.label }}</span></slot
                >
                <span
                    class="flex flex-row"
                    :class="{
                        'justify-center':
                            !cell.extra.showhide && !cell.extra.showhide2,
                        'justify-between':
                            cell.extra.showhide || cell.extra.showhide2,
                    }"
                >
                    <slot name="sort">
                        <svg
                            v-if="cell.sortable"
                            aria-hidden="true"
                            class="w-3 h-3"
                            :class="{
                                'text-gray-400': !cell.sorted,
                                'text-green-500': cell.sorted,
                                'ml-2': !colHeader,
                            }"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 320 512"
                            :sorted="cell.sorted"
                        >
                            <path
                                v-if="!cell.sorted"
                                fill="currentColor"
                                d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41zm255-105L177 64c-9.4-9.4-24.6-9.4-33.9 0L24 183c-15.1 15.1-4.4 41 17 41h238c21.4 0 32.1-25.9 17-41z"
                            />

                            <path
                                v-if="cell.sorted === 'asc'"
                                fill="currentColor"
                                d="M279 224H41c-21.4 0-32.1-25.9-17-41L143 64c9.4-9.4 24.6-9.4 33.9 0l119 119c15.2 15.1 4.5 41-16.9 41z"
                            />

                            <path
                                v-if="cell.sorted === 'desc'"
                                fill="currentColor"
                                d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"
                            />
                        </svg>
                    </slot>
                    <BaseIcon
                        v-if="cell.extra.showhide"
                        @click.stop="showhide(cell.extra.showhide)"
                        class="cursor-pointer"
                        :path="mdiArrowRightThick"
                        size="24"
                        title="Show/hide Column"
                        color="green"
                    />
                    <BaseIcon
                        v-if="cell.extra.showhide2"
                        @click.stop="showhide(cell.extra.showhide2)"
                        class="cursor-pointer"
                        :path="mdiArrowRightThick"
                        size="24"
                        title="Show/hide Column"
                        color="cyan"
                    />
                </span>
            </span>
        </component>
    </th>
</template>

<script setup>
import { mdiArrowRightThick } from "@mdi/js";

import BaseIcon from "@/components/BaseIcon.vue";
const props = defineProps({
    cell: {
        type: Object,
        required: true,
    },
    editIcon: {
        type: Boolean,
    },
    headerColor: {
        type: String,
        required: false,
    },
    colHeader: {
        type: Boolean,
        default: false,
        required: false,
    },
});

function onClick() {
    if (props.cell.sortable) {
        props.cell.onSort(props.cell.key);
    }
}

const emit = defineEmits(["columnToggle"]);
function showhide($columns) {
    emit("columnToggle", $columns);
}
</script>
<style lang="css" scoped>
.tooltip {
    display: none;
}

.tooltip[data-show] {
    display: block;
}

.arrow,
.arrow::before {
    position: absolute;
    width: 8px;
    height: 8px;
    background: inherit;
}

.arrow {
    visibility: hidden;
}

.arrow::before {
    visibility: visible;
    content: "";
    transform: rotate(45deg);
}

.tooltip[data-popper-placement^="top"] > .arrow {
    bottom: -4px;
}

.tooltip[data-popper-placement^="bottom"] > .arrow {
    top: -4px;
}

.tooltip[data-popper-placement^="left"] > .arrow {
    right: -4px;
}

.tooltip[data-popper-placement^="right"] > .arrow {
    left: -4px;
}
</style>
