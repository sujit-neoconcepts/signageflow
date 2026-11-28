import "../css/main.css";

import { createPinia } from "pinia";
import { useStyleStore } from "@/stores/style.js";
import { darkModeKey, styleKey } from "@/config.js";
import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { ZiggyVue } from 'ziggy-js';

const appName =
    window.document.getElementsByTagName("title")[0]?.innerText || "Laravel";

const pinia = createPinia();

const hostPages = import.meta.glob("./Pages/**/*.vue");
const hrmsPages = import.meta.glob("../../packages/signageflow/hrms/resources/js/Pages/**/*.vue");

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        if (name.startsWith("Hrms/")) {
            return resolvePageComponent(
                `../../packages/signageflow/hrms/resources/js/Pages/${name}.vue`,
                hrmsPages
            );
        }
        return resolvePageComponent(`./Pages/${name}.vue`, hostPages);
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue, Ziggy)
            .mount(el);
    },
    progress: {
        color: "#00ff00",
    },
});

const styleStore = useStyleStore(pinia);

/* App style */
styleStore.setStyle(localStorage[styleKey] ?? "white");

/* Dark mode */
if (
    (!localStorage[darkModeKey] &&
        window.matchMedia("(prefers-color-scheme: dark)").matches) ||
    localStorage[darkModeKey] === "1"
) {
    styleStore.setDarkMode(true);
}
