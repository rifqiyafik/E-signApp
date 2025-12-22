<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import UploadFile from "@/Components/Sign/UploadFile.vue";
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
