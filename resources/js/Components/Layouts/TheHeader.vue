<script setup>
import { Link } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

const STORAGE_KEY = "esign_auth";
const userEmail = ref("");

const loadAuth = () => {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (!stored) {
        userEmail.value = "";
        return;
    }

    try {
        const parsed = JSON.parse(stored);
        userEmail.value = parsed?.email || "";
    } catch {
        userEmail.value = "";
    }
};

const handleStorage = (event) => {
    if (event.key === STORAGE_KEY) {
        loadAuth();
    }
};

const isAuthenticated = computed(() => Boolean(userEmail.value));

onMounted(() => {
    loadAuth();
    window.addEventListener("storage", handleStorage);
});

onBeforeUnmount(() => {
    window.removeEventListener("storage", handleStorage);
});
</script>

<template>
    <nav
        class="bg-white/80 backdrop-blur-md border-b border-gray-100 top-0 z-50 transition-all duration-300 fixed w-full"
    >
        <div
            class="max-w-7xl flex items-center justify-between mx-auto px-4 lg:px-0 py-4"
        >
            <div class="flex items-center gap-12">
                <Link href="/" class="flex items-center gap-2 group">
                    <div
                        class="w-8 h-8 bg-[#13087d] rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-md group-hover:scale-110 transition-transform"
                    >
                        E
                    </div>
                    <span
                        class="text-2xl font-extrabold text-[#13087d] tracking-tight"
                        >E-Sign</span
                    >
                </Link>

                <div class="hidden lg:flex items-center gap-8">
                    <Link
                        href="#"
                        class="text-sm font-medium text-gray-600 hover:text-[#13087d] transition-colors"
                        >Fitur</Link
                    >
                    <Link
                        href="#"
                        class="text-sm font-medium text-gray-600 hover:text-[#13087d] transition-colors"
                        >Tentang Kami</Link
                    >
                    <Link
                        href="#"
                        class="text-sm font-medium text-gray-600 hover:text-[#13087d] transition-colors"
                        >Kontak</Link
                    >
                </div>
            </div>

            <div class="flex items-center gap-4">
                <div
                    v-if="isAuthenticated"
                    class="flex items-center gap-3 text-sm font-semibold text-gray-700"
                >
                    <span class="hidden sm:inline-block" :title="userEmail">
                        {{ userEmail }}
                    </span>
                    <span
                        class="inline-flex h-9 w-9 aspect-square items-center justify-center rounded-full bg-[#13087d] text-white shadow-md"
                        aria-hidden="true"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-4 w-4"
                            fill="currentColor"
                        >
                            <path
                                d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5Z"
                            />
                        </svg>
                    </span>
                </div>
                <template v-else>
                    <Link
                        href="#"
                        class="hidden sm:inline-block text-sm font-semibold text-[#13087d] hover:text-[#190b9f] transition-colors"
                    >
                        Masuk
                    </Link>
                    <Link
                        href="#"
                        class="bg-[#13087d] hover:bg-[#190b9f] text-white rounded-full shadow-md hover:shadow-lg px-6 py-2.5 text-sm font-bold duration-300 transition-all transform hover:-translate-y-0.5"
                    >
                        Daftar Gratis
                    </Link>
                </template>
            </div>
        </div>
    </nav>
</template>
