<script setup>
import TextInput from "@/Components/Atoms/TextInput.vue";
import PrimaryButton from "@/Components/Atoms/PrimaryButton.vue";
import { Head } from "@inertiajs/vue3";
import { reactive, ref, watch } from "vue";
import axios from "axios";
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || "").replace(
    /\/+$/,
    ""
);

const STORAGE_KEY = "esign_auth";

const FORM_FIELDS = [
    "tenantName",
    "tenantSlug",
    "name",
    "email",
    "password",
    "password_confirmation",
];

const REQUIRED_MESSAGES = {
    tenantName: "Nama tenant wajib diisi.",
    name: "Nama wajib diisi.",
    email: "Email wajib diisi.",
    password: "Password wajib diisi.",
    password_confirmation: "Konfirmasi password wajib diisi.",
};

const form = reactive({
    tenantName: "",
    tenantSlug: "",
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const isSubmitting = ref(false);
const fieldErrors = reactive({
    tenantName: "",
    tenantSlug: "",
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const clearErrors = () => {
    FORM_FIELDS.forEach((field) => {
        fieldErrors[field] = "";
    });
};

const clearFieldError = (field) => {
    if (fieldErrors[field]) {
        fieldErrors[field] = "";
    }
};

const validateForm = (payload) => {
    let isValid = true;

    Object.entries(REQUIRED_MESSAGES).forEach(([field, message]) => {
        if (!payload[field]) {
            fieldErrors[field] = message;
            isValid = false;
        }
    });

    if (
        payload.password &&
        payload.password_confirmation &&
        payload.password !== payload.password_confirmation
    ) {
        fieldErrors.password_confirmation = "Konfirmasi password tidak cocok.";
        isValid = false;
    }

    return isValid;
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

const applyValidationErrors = (errors) => {
    if (!errors) {
        return false;
    }

    FORM_FIELDS.forEach((field) => {
        const messages = errors[field];
        if (Array.isArray(messages) && messages[0]) {
            fieldErrors[field] = messages[0];
        }
    });

    return true;
};

const resolveErrorMessage = (response) => {
    if (response?.status === 409) {
        return "Email sudah terdaftar.";
    }
    return response?.data?.message || "Registrasi gagal. Coba lagi.";
};

const handleSubmit = async () => {
    clearErrors();

    const payload = {
        tenantName: form.tenantName.trim(),
        tenantSlug: form.tenantSlug.trim(),
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        password_confirmation: form.password_confirmation,
    };

    if (!validateForm(payload)) {
        return;
    }

    isSubmitting.value = true;

    try {
        const requestPayload = {
            tenantName: payload.tenantName,
            name: payload.name,
            email: payload.email,
            password: payload.password,
            password_confirmation: payload.password_confirmation,
        };

        if (payload.tenantSlug) {
            requestPayload.tenantSlug = payload.tenantSlug;
        }

        const response = await axios.post(
            `${API_BASE_URL}/api/tenants/register`,
            requestPayload
        );

        const {
            accessToken,
            tenantId,
            userId,
            tenantSlug: responseTenantSlug,
        } = response.data || {};

        if (!accessToken) {
            throw new Error("Token tidak tersedia.");
        }

        const resolvedTenant = responseTenantSlug || payload.tenantSlug;

        saveAuthToStorage({
            accessToken,
            tenantId,
            userId,
            tenant: resolvedTenant,
            email: payload.email,
        });

        axios.defaults.headers.common.Authorization = `Bearer ${accessToken}`;
        toast.success("Registrasi berhasil.");
    } catch (error) {
        const response = error?.response;

        if (response?.status === 422 && applyValidationErrors(response.data?.errors)) {
            toast.error("Data belum lengkap atau tidak valid.", "error");
            return;
        }

        toast.error(resolveErrorMessage(response), "error");
    } finally {
        isSubmitting.value = false;
    }
};

FORM_FIELDS.forEach((field) => {
    watch(
        () => form[field],
        () => clearFieldError(field)
    );
});
</script>

<template>
    <Head title="Register" />
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
                        Selamat datang! Saatnya membuat akun baru.
                    </p>
                    <p class="text-gray-600 font-normal">
                        Lengkapi data berikut untuk mulai menggunakan E-Sign.
                    </p>
                </div>
            </div>
            <form @submit.prevent="handleSubmit">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <TextInput
                            id="tenantName"
                            v-model="form.tenantName"
                            name="tenantName"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            autofocus
                            :disabled="isSubmitting"
                            labelValue="Nama Tenant"
                            placeholder="Masukkan nama perusahaan"
                            autocomplete="organization"
                            autocapitalize="none"
                            spellcheck="false"
                        />
                        <p
                            v-if="fieldErrors.tenantName"
                            class="text-xs text-red-600"
                        >
                            {{ fieldErrors.tenantName }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <TextInput
                            id="tenantSlug"
                            v-model="form.tenantSlug"
                            name="tenantSlug"
                            type="text"
                            class="mt-1 block w-full"
                            :disabled="isSubmitting"
                            labelValue="Tenant Slug (Opsional)"
                            placeholder="contoh: demo"
                            autocomplete="organization"
                            autocapitalize="none"
                            spellcheck="false"
                        />
                        <p
                            v-if="fieldErrors.tenantSlug"
                            class="text-xs text-red-600"
                        >
                            {{ fieldErrors.tenantSlug }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <TextInput
                            id="name"
                            v-model="form.name"
                            name="name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            :disabled="isSubmitting"
                            labelValue="Nama Lengkap"
                            placeholder="Masukkan nama lengkap"
                            autocomplete="name"
                            autocapitalize="words"
                            spellcheck="false"
                        />
                        <p v-if="fieldErrors.name" class="text-xs text-red-600">
                            {{ fieldErrors.name }}
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
                            autocomplete="new-password"
                            placeholder="Masukkan kata sandi"
                        />
                        <p
                            v-if="fieldErrors.password"
                            class="text-xs text-red-600"
                        >
                            {{ fieldErrors.password }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1">
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            required
                            :disabled="isSubmitting"
                            labelValue="Konfirmasi Password"
                            autocomplete="new-password"
                            placeholder="Ulangi kata sandi"
                        />
                        <p
                            v-if="fieldErrors.password_confirmation"
                            class="text-xs text-red-600"
                        >
                            {{ fieldErrors.password_confirmation }}
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
                        <span v-else>Daftar</span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
