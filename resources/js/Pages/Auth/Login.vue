<script setup>
import TextInput from "@/Components/Atoms/TextInput.vue";
import PrimaryButton from "@/Components/Atoms/PrimaryButton.vue";
import { Head } from "@inertiajs/vue3";
import { onMounted, reactive, ref, watch } from "vue";
import axios from "axios";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || "").replace(
    /\/+$/,
    ""
);

const STORAGE_KEY = "esign_auth";

const REQUIRED_FIELDS = [
    { name: "tenant", message: "Tenant wajib diisi." },
    { name: "email", message: "Email wajib diisi." },
    { name: "password", message: "Password wajib diisi." },
];

const form = reactive({
    tenant: "",
    email: "",
    password: "",
});

const isSubmitting = ref(false);
const fieldErrors = reactive({
    tenant: "",
    email: "",
    password: "",
});

const clearErrors = () => {
    REQUIRED_FIELDS.forEach(({ name }) => {
        fieldErrors[name] = "";
    });
};

const clearFieldError = (field) => {
    if (fieldErrors[field]) {
        fieldErrors[field] = "";
    }
};

const validateForm = (tenant, email, password) => {
    const fieldValues = {
        tenant,
        email,
        password,
    };

    REQUIRED_FIELDS.forEach(({ name, message }) => {
        if (!fieldValues[name]) {
            fieldErrors[name] = message;
        }
    });

    return !REQUIRED_FIELDS.some(({ name }) => fieldErrors[name]);
};

const saveAuthToStorage = ({
    accessToken,
    tenantId,
    userId,
    tenant,
    email,
}) => {
    const authData = {
        tenant,
        email,
        accessToken,
        tenantId: tenantId ?? null,
        userId: userId ?? null,
    };
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(authData));
};

const resolveErrorMessage = (response) => {
    if (response?.status === 404) {
        return "Tenant tidak ditemukan.";
    }
    if (response?.status === 401) {
        return "Email atau password salah.";
    }
    return response?.data?.message || "Login gagal. Coba lagi.";
};

const handleSubmit = async () => {
    clearErrors();

    const tenant = form.tenant.trim();
    const email = form.email.trim();
    const password = form.password;

    if (!validateForm(tenant, email, password)) {
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `${API_BASE_URL}/${tenant}/api/auth/login`,
            {
                email,
                password,
            }
        );

        const { accessToken, tenantId, userId } = response.data || {};

        if (!accessToken) {
            throw new Error("Token tidak tersedia.");
        }

        saveAuthToStorage({
            accessToken,
            tenantId,
            userId,
            tenant,
            email,
        });

        axios.defaults.headers.common.Authorization = `Bearer ${accessToken}`;
        toast.success("Login berhasil.");
    } catch (error) {
        const response = error?.response;

        if (response?.status === 422 && response.data?.errors) {
            toast.error("Email atau password salah", "error");
            return;
        }

        toast.error(resolveErrorMessage(response), "error");
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    const storedAuth = window.localStorage.getItem(STORAGE_KEY);
    if (!storedAuth) {
        return;
    }

    try {
        const parsed = JSON.parse(storedAuth);
        form.tenant = parsed?.tenant || "";
    } catch (error) {
        console.warn("Invalid auth data in localStorage", error);
    }
});

REQUIRED_FIELDS.forEach(({ name }) => {
    watch(
        () => form[name],
        () => clearFieldError(name)
    );
});
</script>

<template>
    <Head title="Log in" />
    <div
        class="flex flex-col justify-center items-center bg-[#13087d] min-h-screen"
    >
        <div
            class="bg-white rounded-2xl max-w-xl w-full p-12 drop-shadow-2xl flex flex-col gap-6"
        >
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 bg-[#13087d] rounded-lg flex items-center justify-center text-white font-bold text-xl transition-transform"
                    >
                        E
                    </div>
                    <span
                        class="text-2xl font-extrabold text-[#13087d] tracking-tight"
                        >E-Sign</span
                    >
                </div>
                <div class="flex flex-col gap-0.5">
                    <p class="text-gray-900 font-medium text-lg">
                        Halo! Senang sekali bertemu denganmu lagi!
                    </p>
                    <p class="text-gray-600 font-normal">
                        Kami sangat senang bisa menyambutmu kembali.
                    </p>
                </div>
            </div>
            <form @submit.prevent="handleSubmit">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <TextInput
                            id="tenant"
                            v-model="form.tenant"
                            name="tenant"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            :disabled="isSubmitting"
                            labelValue="Tenant"
                            placeholder="Masukkan nama perusahaan"
                            autocomplete="organization"
                            autocapitalize="none"
                            spellcheck="false"
                        />
                        <p
                            v-if="fieldErrors.tenant"
                            class="text-xs text-red-600"
                        >
                            {{ fieldErrors.tenant }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <TextInput
                            id="email"
                            v-model="form.email"
                            name="email"
                            type="email"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            :disabled="isSubmitting"
                            labelValue="Email"
                            autocomplete="email"
                            autocapitalize="none"
                            spellcheck="false"
                            inputmode="email"
                            placeholder="Masukkan email anda"
                        />
                        <p
                            v-if="fieldErrors.email"
                            class="text-xs text-red-600"
                        >
                            {{ fieldErrors.email }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <TextInput
                            id="password"
                            v-model="form.password"
                            name="password"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            :disabled="isSubmitting"
                            labelValue="Password"
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi anda"
                        />
                        <p
                            v-if="fieldErrors.password"
                            class="text-xs text-red-600"
                        >
                            {{ fieldErrors.password }}
                        </p>
                    </div>

                    <PrimaryButton
                        class="w-full justify-center py-3 text-xs mt-3"
                        :disabled="isSubmitting"
                    >
                        <span
                            v-if="isSubmitting"
                            class="inline-flex items-center justify-center"
                        >
                            <svg
                                class="h-4 w-4 animate-spin"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                    fill="none"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                />
                            </svg>
                            <span class="sr-only">Memproses...</span>
                        </span>
                        <span v-else>Masuk</span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
