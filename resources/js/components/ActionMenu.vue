<template>
    <div :class="{ 'flex space-x-2': !actionExpand }">
        <BaseButton
            v-if="actionExpand"
            :icon="mdiDotsVertical"
            small
            @click.stop="$emit('toggle')"
        />
        <div
            v-if="isVisible"
            :class="[
                { 'py-1': actionExpand },
                actionExpand ? menuClasses.dropdown : 'flex space-x-2',
            ]"
            @click.stop
        >
            <template v-for="exLink in extraLinks" :key="exLink.link">
                <a
                    v-if="
                        exLink.openInNewTab &&
                        checkConditions(
                            item,
                            exLink.conditions || [
                                {
                                    key: exLink.key,
                                    cond: exLink.cond,
                                    compvl: exLink.compvl,
                                },
                            ]
                        )
                    "
                    :href="route(exLink.link, item.params ?? item.id)"
                    target="_blank"
                    :class="menuItemClass"
                >
                    <BaseButton
                        :class="[menuClasses.button, 'w-auto']"
                        color="info"
                        :icon="exLink.icon"
                        small
                        :label="actionExpand ? exLink.label : ''"
                        :title="exLink.label"
                    />
                </a>
                <Link
                    v-else-if="
                        checkConditions(
                            item,
                            exLink.conditions || [
                                {
                                    key: exLink.key,
                                    cond: exLink.cond,
                                    compvl: exLink.compvl,
                                },
                            ]
                        )
                    "
                    :href="route(exLink.link, item.params ?? item.id)"
                    :class="menuItemClass"
                >
                    <BaseButton
                        :class="[menuClasses.button, 'w-auto']"
                        color="info"
                        :icon="exLink.icon"
                        small
                        :label="actionExpand ? exLink.label : ''"
                        :title="exLink.label"
                    />
                </Link>
            </template>
            <slot></slot>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { mdiDotsVertical } from "@mdi/js";
import BaseButton from "@/components/BaseButton.vue";

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    extraLinks: {
        type: Array,
        default: () => [],
    },
    actionExpand: {
        type: Boolean,
        default: false,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
    menuClasses: {
        type: Object,
        required: true,
    },
});

defineEmits(["toggle"]);

const isVisible = computed(
    () => !props.actionExpand || (props.actionExpand && props.isOpen)
);
const menuItemClass = computed(() => [
    props.menuClasses.menuItem,
    { "p-2": props.actionExpand },
]);

const checkConditions = (item, conditions) => {
    if (!conditions) return true;
    return conditions.every((rule) => {
        if (rule.cond === "==") return item[rule.key] == rule.compvl;
        if (rule.cond === "!=") return item[rule.key] != rule.compvl;
        if (rule.cond === ">") return item[rule.key] > rule.compvl;
        if (rule.cond === "<") return item[rule.key] < rule.compvl;
        if (rule.cond === "*") return true;
        return false;
    });
};
</script>
