<script setup>
import { Link } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import axios from "axios";

const STORAGE_KEY = "esign_auth";
const SESSION_KEYS = [
    "esign_auth",
    "esign_token",
    "esign_tenant",
    "esign_tenant_id",
    "esign_user_id",
];
const userEmail = ref("");
const showProfileMenu = ref(false);
const showMobileMenu = ref(false);
const profileMenuRef = ref(null);
const mobileMenuRef = ref(null);

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
        if (!userEmail.value) {
            showProfileMenu.value = false;
            showMobileMenu.value = false;
        }
    }
};

const isAuthenticated = computed(() => Boolean(userEmail.value));

const toggleProfileMenu = () => {
    showProfileMenu.value = !showProfileMenu.value;
};

const toggleMobileMenu = () => {
    showMobileMenu.value = !showMobileMenu.value;
};

const logout = () => {
    SESSION_KEYS.forEach((key) => window.localStorage.removeItem(key));
    userEmail.value = "";
    showProfileMenu.value = false;
    showMobileMenu.value = false;

    if (axios.defaults.headers.common.Authorization) {
        delete axios.defaults.headers.common.Authorization;
    }
};

const handleFeaturesClick = (event) => {
    if (event?.preventDefault) {
        event.preventDefault();
    }

    showMobileMenu.value = false;

    if (window.location.pathname !== "/") {
        window.location.href = "/#features";
        return;
    }

    const target = document.getElementById("features");
    if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
        window.location.hash = "features";
    }
};

const handleDocumentClick = (event) => {
    const target = event.target;

    if (
        showProfileMenu.value &&
        profileMenuRef.value &&
        !profileMenuRef.value.contains(target)
    ) {
        showProfileMenu.value = false;
    }

    if (
        showMobileMenu.value &&
        mobileMenuRef.value &&
        !mobileMenuRef.value.contains(target)
    ) {
        showMobileMenu.value = false;
    }
};

onMounted(() => {
    loadAuth();
    window.addEventListener("storage", handleStorage);
    window.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
    window.removeEventListener("storage", handleStorage);
    window.removeEventListener("click", handleDocumentClick);
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
                        href="/#features"
                        class="text-sm font-medium text-gray-600 hover:text-[#13087d] transition-colors"
                        @click="handleFeaturesClick"
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
                    ref="profileMenuRef"
                    class="relative hidden lg:flex items-center gap-3 text-sm font-semibold text-gray-700"
                >
                    <span class="hidden sm:inline-block" :title="userEmail">
                        {{ userEmail }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 aspect-square items-center justify-center rounded-full bg-[#13087d] text-white shadow-md"
                        aria-label="Menu profil"
                        :aria-expanded="showProfileMenu"
                        aria-haspopup="menu"
                        @click="toggleProfileMenu"
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
                    </button>
                    <div
                        v-if="showProfileMenu"
                        class="absolute right-0 top-full mt-3 w-40 rounded-xl border border-gray-100 bg-white shadow-lg p-2 z-50"
                    >
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 rounded-lg"
                            @click="logout"
                        >
                            Logout
                        </button>
                    </div>
                </div>
                <template v-else>
                    <div class="hidden lg:flex items-center gap-4">
                        <Link
                            href="/login"
                            class="text-sm font-semibold text-[#13087d] hover:text-[#190b9f] transition-colors"
                        >
                            Masuk
                        </Link>
                        <Link
                            href="/register"
                            class="bg-[#13087d] hover:bg-[#190b9f] text-white rounded-full shadow-md hover:shadow-lg px-6 py-2.5 text-sm font-bold duration-300 transition-all transform hover:-translate-y-0.5"
                        >
                            Daftar Gratis
                        </Link>
                    </div>
                </template>

                <div ref="mobileMenuRef" class="relative lg:hidden">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center h-10 w-10 rounded-full border border-gray-200 text-gray-700 hover:text-[#13087d] hover:border-[#13087d] transition-colors cursor-pointer"
                        aria-label="Buka menu"
                        :aria-expanded="showMobileMenu"
                        aria-haspopup="menu"
                        @click="toggleMobileMenu"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>

                    <div
                        v-if="showMobileMenu"
                        class="absolute right-0 top-full mt-3 w-64 rounded-xl border border-gray-100 bg-white shadow-lg p-3 z-50"
                    >
                        <template v-if="isAuthenticated">
                            <div
                                class="flex items-center gap-3 px-3 py-2 mb-3 border-b border-gray-100 pb-3"
                            >
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#13087d] text-white shadow-md"
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
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-gray-900 truncate"
                                    >
                                        {{ userEmail }}
                                    </p>
                                    <p class="text-xs text-gray-500">Profil</p>
                                </div>
                            </div>
                        </template>

                        <div class="flex flex-col gap-2">
                            <Link
                                href="/sign"
                                class="px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                                @click="showMobileMenu = false"
                            >
                                Mulai
                            </Link>
                            <Link
                                href="/#features"
                                class="px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                                @click="handleFeaturesClick"
                            >
                                Fitur
                            </Link>
                            <Link
                                href="#"
                                class="px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                                @click="showMobileMenu = false"
                            >
                                Tentang Kami
                            </Link>
                            <Link
                                href="#"
                                class="px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                                @click="showMobileMenu = false"
                            >
                                Kontak
                            </Link>
                        </div>
                        <button
                            v-if="isAuthenticated"
                            type="button"
                            class="mt-2 w-full px-3 py-2 text-left text-sm font-medium hover:bg-gray-50 rounded-lg cursor-pointer text-red-700 hover:font-semibold hover:text-red-900 duration-300 transition-all"
                            @click="logout"
                        >
                            Logout
                        </button>
                        <template v-else>
                            <Link
                                href="/login"
                                class="block px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                                @click="showMobileMenu = false"
                            >
                                Masuk
                            </Link>
                            <Link
                                href="/register"
                                class="mt-2 block px-3 py-2 rounded-lg text-sm font-semibold text-white bg-[#13087d] hover:bg-[#190b9f]"
                                @click="showMobileMenu = false"
                            >
                                Daftar Gratis
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</template>
