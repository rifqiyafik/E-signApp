import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        host: "10.10.32.37",
        port: 5173,
        strictPort: true,
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
