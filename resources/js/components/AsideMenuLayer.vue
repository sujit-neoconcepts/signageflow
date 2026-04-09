<script setup>
import { mdiLogout, mdiClose } from "@mdi/js";
import { computed } from "vue";
import { useStyleStore } from "@/stores/style.js";
import AsideMenuList from "@/components/AsideMenuList.vue";
import AsideMenuItem from "@/components/AsideMenuItem.vue";
import BaseIcon from "@/components/BaseIcon.vue";

defineProps({
    menu: {
        type: Array,
        required: true,
    },
    isAsideXlExpanded: Boolean,
});

const emit = defineEmits(["menu-click", "aside-lg-close-click"]);

const styleStore = useStyleStore();

const logoutItem = computed(() => ({
    label: "Logout",
    icon: mdiLogout,
    color: "info",
    isLogout: true,
}));

const menuClick = (event, item) => {
    emit("menu-click", event, item);
};

const asideLgCloseClick = (event) => {
    emit("aside-lg-close-click", event);
};
</script>

<template>
    <aside
        id="aside"
        class="lg:py-2 lg:pl-2 w-60 fixed flex z-40 top-0 h-screen transition-position overflow-hidden"
        :class="[isAsideXlExpanded ? 'xl:w-60' : 'xl:w-14']"
    >
        <div
            :class="styleStore.asideStyle"
            class="lg:rounded-2xl flex-1 flex flex-col overflow-hidden dark:bg-slate-900"
        >
            <div
                :class="styleStore.asideBrandStyle"
                class="flex flex-col h-auto items-center justify-between dark:bg-slate-900 py-3"
            >
                <div class="text-center flex-1 lg:pl-6 xl:pl-0 w-full">
                    <!-- Logo for expanded sidebar -->
                    <div class="flex flex-col items-center justify-center gap-2" :class="isAsideXlExpanded ? 'xl:flex' : 'xl:hidden'">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Symbol -->
                        <img
                            src="/logo-b.png"
                            class="hidden dark:block w-18 flex-shrink-0"
                        />
                        <img
                            src="/logo-w.png"
                            class="block dark:hidden w-18 flex-shrink-0"
                        />
                            <!-- Text -->
                            <span class="text-xl font-bold text-gray-700 dark:text-gray-300"></span>
                        </div>
                    </div>
                    <!-- Symbol only for collapsed sidebar -->
                    <div class="flex items-center justify-center" :class="isAsideXlExpanded ? 'xl:hidden' : 'xl:flex'">
                        <img
                        src="/logo-b.png"
                        class="mx-auto hidden dark:block w-16"
                    />
                    <img
                        src="/logo-w.png"
                        class="mx-auto block dark:hidden w-16"
                    />
                    </div>
                </div>
                <button
                    class="hidden lg:inline-block xl:hidden p-3"
                    @click.prevent="asideLgCloseClick"
                >
                    <BaseIcon :path="mdiClose" />
                </button>
            </div>
            <div
                :class="
                    styleStore.darkMode
                        ? 'aside-scrollbars-[slate]'
                        : styleStore.asideScrollbarsStyle
                "
                class="flex-1 overflow-y-auto overflow-x-hidden"
            >
                <AsideMenuList :menu="menu" :is-aside-xl-expanded="isAsideXlExpanded" @menu-click="menuClick" />
            </div>

            <ul>
                <AsideMenuItem :item="logoutItem" @menu-click="menuClick" />
            </ul>
        </div>
    </aside>
</template>
