<script setup>
import { router, usePage } from "@inertiajs/vue3";
import { mdiForwardburger, mdiBackburger, mdiMenu } from "@mdi/js";
import { ref, computed } from "vue";
//import { useRouter } from "vue-router";
import menuAside from "@/menuAside.js";
import menuNavBar from "@/menuNavBar.js";
//import { useMainStore } from "@/stores/main.js";
import { useStyleStore } from "@/stores/style.js";
import BaseIcon from "@/components/BaseIcon.vue";
import FormControl from "@/components/FormControl.vue";
import NavBar from "@/components/NavBar.vue";
import NavBarItemPlain from "@/components/NavBarItemPlain.vue";
import AsideMenu from "@/components/AsideMenu.vue";
import FooterBar from "@/components/FooterBar.vue";

import FinancialYearSelector from "@/components/FinancialYearSelector.vue";

const layoutAsidePadding = "xl:pl-60";

const styleStore = useStyleStore();
const page = usePage();

// Determine which menu to use
const currentMenu = computed(() => menuAside);

// Determine which navbar menu to use
const currentNavBarMenu = computed(() => menuNavBar);

//const router = useRouter();

const isAsideMobileExpanded = ref(false);
const isAsideXlExpanded = ref(styleStore.AsideXlExpanded);

const isAsideLgActive = ref(false);

/*router.beforeEach(() => {
  isAsideMobileExpanded.value = false;
  isAsideLgActive.value = false;
});*/

router.on("navigate", () => {
    isAsideMobileExpanded.value = false;
    isAsideLgActive.value = false;
});

// Replace `isLogout` logic:

const menuClick = (event, item) => {
    if (item.isToggleLightDark) {
        styleStore.setDarkMode();
    }
    if (item.isLogout) {
        // Add:
        router.post(route("logout"));
    }
};

const menuExpanded = () => {
    isAsideXlExpanded.value = !isAsideXlExpanded.value;
    styleStore.setExpanded();
};
</script>

<template>
    <div
        :class="{
            dark: styleStore.darkMode,
            'overflow-hidden lg:overflow-visible': isAsideMobileExpanded,
        }"
    >
        <div
            :class="[
                { 'ml-60 lg:ml-0': isAsideMobileExpanded },
                isAsideXlExpanded ? 'xl:pl-60' : 'xl:pl-14',
            ]"
            class="pt-10 min-h-screen w-screen transition-position lg:w-auto bg-gray-50 dark:bg-slate-800 dark:text-slate-100"
        >
            <NavBar
                :menu="currentNavBarMenu"
                :class="[
                    isAsideXlExpanded ? 'xl:pl-60' : 'xl:pl-14',
                    { 'ml-60 lg:ml-0': isAsideMobileExpanded },
                ]"
                @menu-click="menuClick"
            >
                <NavBarItemPlain
                    display="flex hidden xl:flex"
                    @click.prevent="menuExpanded()"
                >
                    <BaseIcon
                        :path="
                            isAsideXlExpanded ? mdiBackburger : mdiForwardburger
                        "
                        size="24"
                    />
                </NavBarItemPlain>

                <NavBarItemPlain
                    display="flex lg:hidden"
                    @click.prevent="
                        isAsideMobileExpanded = !isAsideMobileExpanded
                    "
                >
                    <BaseIcon
                        :path="
                            isAsideMobileExpanded
                                ? mdiBackburger
                                : mdiForwardburger
                        "
                        size="24"
                    />
                </NavBarItemPlain>
                <NavBarItemPlain
                    display="hidden lg:flex xl:hidden"
                    @click.prevent="isAsideLgActive = true"
                >
                    <BaseIcon :path="mdiMenu" size="24" />
                </NavBarItemPlain>
                <FinancialYearSelector />
                <!--<NavBarItemPlain use-margin>
          <FormControl placeholder="Search (ctrl+k)" ctrl-k-focus transparent borderless />
        </NavBarItemPlain>-->
            </NavBar>
            <AsideMenu
                :is-aside-mobile-expanded="isAsideMobileExpanded"
                :is-aside-xl-expanded="isAsideXlExpanded"
                :is-aside-lg-active="isAsideLgActive"
                :menu="currentMenu"
                @menu-click="menuClick"
                @aside-lg-close-click="isAsideLgActive = false"
            />
            <slot />
            <FooterBar></FooterBar>
        </div>
    </div>
</template>
