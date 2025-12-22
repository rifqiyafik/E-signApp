<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import Headline from "@/Components/Dashboard/Headline.vue";
import Feature from "@/Components/Dashboard/Feature.vue";
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="py-12 flex flex-col gap-12">
            <Headline />
            <Feature />
        </div>
    </AppLayout>
</template>
