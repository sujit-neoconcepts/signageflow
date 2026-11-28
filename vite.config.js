import { defineConfig } from "vite";
import path from "path";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    resolve: {
        alias: {
            "@hrms": path.resolve(__dirname, "packages/signageflow/hrms/resources/js"),
        },
    },
    server: {
        hmr: false,
    },
    plugins: [
        tailwindcss(),
        laravel({
            input: "resources/js/app.js",
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
