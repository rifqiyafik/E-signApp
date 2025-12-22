<script setup>
import { Link } from "@inertiajs/vue3";
import TheHeader from "@/Components/Layouts/TheHeader.vue";
import TheFooter from "@/Components/Layouts/TheFooter.vue";
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex flex-col overflow-x-hidden">
        <TheHeader />
        <main class="flex-1 pt-20">
            <slot />
        </main>
        <TheFooter />
    </div>
</template>
