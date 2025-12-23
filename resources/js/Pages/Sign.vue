<script setup>
import { Head, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import UploadFile from "@/Components/Sign/UploadFile.vue";
import { onMounted } from "vue";

const STORAGE_KEY = "esign_auth";

const hasValidSession = () => {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (!stored) {
        return false;
    }

    try {
        const parsed = JSON.parse(stored);
        return Boolean(parsed?.accessToken);
    } catch {
        return false;
    }
};

onMounted(() => {
    if (!hasValidSession()) {
        router.visit("/login");
    }
});
</script>

<template>
    <Head title="Sign Document" />
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 lg:px-0">
                <section id="uploadFile">
                    <UploadFile />
                </section>
            </div>
        </div>
    </AppLayout>
</template>
