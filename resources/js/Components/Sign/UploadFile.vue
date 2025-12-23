<script setup>
import { ref, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import axios from "axios";
import QRCode from "qrcode";
import VuePdfEmbed from "vue-pdf-embed";
import VerificationModal from "@/Components/Sign/VerificationModal.vue";

import "vue-pdf-embed/dist/styles/textLayer.css";
import "vue-pdf-embed/dist/styles/annotationLayer.css";

const activeTab = ref("sign");
const fileInput = ref(null);
const selectedFile = ref(null);
const isSigned = ref(false);
const isSigning = ref(false);
const pdfUrl = ref(null);
const qrCodeUrl = ref(null);
const signResult = ref(null);
const signError = ref("");

const verifyFileInput = ref(null);
const verifyFile = ref(null);
const isVerifying = ref(false);
const verificationResult = ref(null);
const showVerificationModal = ref(false);

const user = computed(() => usePage().props?.auth?.user || null);

const signerList = computed(() => {
    const signers = signResult.value?.signers;
    return Array.isArray(signers) ? signers : [];
});

const signerName = computed(() => {
    if (signerList.value.length > 0) {
        return (
            signerList.value[signerList.value.length - 1]?.name || "Pengguna"
        );
    }
    return user.value?.name || "Pengguna";
});

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || "").replace(
    /\/+$/,
    ""
);
const STORAGE_KEY = "esign_auth";

const getAuthData = () => {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (!stored) {
        return null;
    }

    try {
        return JSON.parse(stored);
    } catch (error) {
        console.warn("Invalid auth data in localStorage", error);
        return null;
    }
};

const getIdempotencyKey = () => {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
};

const formatFileInfo = (file) => {
    if (!file) {
        return { fileName: "-", fileSize: "-" };
    }

    return {
        fileName: file.name,
        fileSize: `${(file.size / 1024).toFixed(2)} KB`,
    };
};

const formatSignedDate = (value) => {
    if (!value) {
        return "-";
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return "-";
    }

    return date.toLocaleString("id-ID");
};

const resolveVerifyMessage = (data) => {
    if (data?.message) {
        return data.message;
    }

    if (data?.reason === "hash_not_found") {
        return "Dokumen tidak ditemukan di sistem verifikasi.";
    }

    return "Dokumen tidak valid atau belum ditandatangani.";
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileSelect = (event) => {
    const file = event.target?.files?.[0];
    if (!file || file.type !== "application/pdf") {
        return;
    }

    selectedFile.value = file;
    isSigned.value = false;
    isSigning.value = false;
    signResult.value = null;
    signError.value = "";
    qrCodeUrl.value = null;
    if (pdfUrl.value) {
        URL.revokeObjectURL(pdfUrl.value);
    }
    pdfUrl.value = URL.createObjectURL(file);
};

const handleDrop = (event) => {
    event.preventDefault();
    const file = event.dataTransfer?.files?.[0];
    if (!file || file.type !== "application/pdf") {
        return;
    }

    selectedFile.value = file;
    isSigned.value = false;
    isSigning.value = false;
    signResult.value = null;
    signError.value = "";
    qrCodeUrl.value = null;
    if (pdfUrl.value) {
        URL.revokeObjectURL(pdfUrl.value);
    }
    pdfUrl.value = URL.createObjectURL(file);
};

const handleSign = async () => {
    if (!selectedFile.value) {
        return;
    }

    const authData = getAuthData();
    if (!authData?.accessToken || !authData?.tenant) {
        signError.value = "Silakan login terlebih dahulu.";
        return;
    }

    signError.value = "";
    isSigning.value = true;

    try {
        const formData = new FormData();
        formData.append("file", selectedFile.value);
        formData.append("consent", "true");
        formData.append("idempotencyKey", getIdempotencyKey());

        const response = await axios.post(
            `${API_BASE_URL}/${authData.tenant}/api/documents/sign`,
            formData,
            {
                headers: {
                    Authorization: `Bearer ${authData.accessToken}`,
                },
            }
        );

        signResult.value = response.data || null;
        isSigned.value = true;

        if (signResult.value?.verificationUrl) {
            qrCodeUrl.value = await QRCode.toDataURL(
                signResult.value.verificationUrl,
                {
                    errorCorrectionLevel: "H",
                }
            );
        }

        if (signResult.value?.signedPdfDownloadUrl) {
            const signedResponse = await axios.get(
                signResult.value.signedPdfDownloadUrl,
                {
                    responseType: "blob",
                    headers: {
                        Authorization: `Bearer ${authData.accessToken}`,
                    },
                }
            );

            if (pdfUrl.value) {
                URL.revokeObjectURL(pdfUrl.value);
            }
            pdfUrl.value = URL.createObjectURL(signedResponse.data);
        }
    } catch (error) {
        console.error("Error signing document", error);
        signError.value =
            error?.response?.data?.message || "Gagal menandatangani dokumen.";
        isSigned.value = false;
    } finally {
        isSigning.value = false;
    }
};

const handleSave = async () => {
    if (!signResult.value?.signedPdfDownloadUrl) {
        signError.value = "Dokumen belum ditandatangani.";
        return;
    }

    const authData = getAuthData();
    if (!authData?.accessToken) {
        signError.value = "Silakan login terlebih dahulu.";
        return;
    }

    signError.value = "";

    try {
        const response = await axios.get(
            signResult.value.signedPdfDownloadUrl,
            {
                responseType: "blob",
                headers: {
                    Authorization: `Bearer ${authData.accessToken}`,
                },
            }
        );

        const url = URL.createObjectURL(response.data);
        const link = document.createElement("a");
        link.href = url;
        link.download = selectedFile.value
            ? `signed-${selectedFile.value.name}`
            : "signed-document.pdf";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (error) {
        console.error("Error downloading signed PDF:", error);
        signError.value =
            error?.response?.data?.message || "Gagal mengunduh dokumen.";
    }
};

const triggerVerifyFileInput = () => {
    verifyFileInput.value?.click();
};

const handleVerifyFileSelect = (event) => {
    const file = event.target?.files?.[0];
    if (file?.type === "application/pdf") {
        verifyFile.value = file;
    }
};

const handleVerifyDrop = (event) => {
    event.preventDefault();
    const file = event.dataTransfer?.files?.[0];
    if (file?.type === "application/pdf") {
        verifyFile.value = file;
    }
};

const handleVerify = async () => {
    if (!verifyFile.value) {
        return;
    }

    const fileMeta = formatFileInfo(verifyFile.value);
    isVerifying.value = true;
    verificationResult.value = null;

    try {
        const authData = getAuthData();
        if (!authData?.tenant) {
            verificationResult.value = {
                isValid: false,
                message: "Tenant tidak ditemukan. Silakan login ulang.",
                ...fileMeta,
            };
            showVerificationModal.value = true;
            return;
        }

        const formData = new FormData();
        formData.append("file", verifyFile.value);

        const response = await axios.post(
            `${API_BASE_URL}/${authData.tenant}/api/verify`,
            formData
        );

        const data = response.data || {};

        if (data.valid) {
            const signers = Array.isArray(data.signers) ? data.signers : [];
            const latestSigner =
                signers.length > 0 ? signers[signers.length - 1] : null;

            verificationResult.value = {
                isValid: true,
                message:
                    "Dokumen asli dan telah ditandatangani secara digital.",
                signerName: latestSigner?.name || "Tidak diketahui",
                signerEmail: latestSigner?.email || "-",
                originalFileName: fileMeta.fileName,
                signedDate: formatSignedDate(latestSigner?.signedAt),
                isValidSignature: data.signatureValid ?? null,
                ...fileMeta,
            };
        } else {
            verificationResult.value = {
                isValid: false,
                message: resolveVerifyMessage(data),
                ...fileMeta,
            };
        }
        showVerificationModal.value = true;
    } catch (error) {
        console.error("Verification error:", error);
        verificationResult.value = {
            isValid: false,
            message:
                error?.response?.data?.message ||
                "Gagal memverifikasi dokumen.",
            ...fileMeta,
        };
        showVerificationModal.value = true;
    } finally {
        isVerifying.value = false;
    }
};

const closeVerificationModal = () => {
    showVerificationModal.value = false;
    verificationResult.value = null;
    verifyFile.value = null;
};
</script>

<template>
    <div class="bg-white overflow-hidden shadow-xl rounded-lg">
        <div class="px-4 lg:px-0 py-4">
            <nav
                class="flex justify-between sm:justify-center gap-4 md:gap-8"
                aria-label="Tabs"
            >
                <button
                    @click="activeTab = 'sign'"
                    :class="[
                        activeTab === 'sign'
                            ? 'bg-[#13087d] text-white'
                            : 'text-gray-900 hover:text-white hover:bg-[#13087d]',
                        'py-3 rounded-full px-6 font-medium text-sm text-center transition-colors duration-300',
                    ]"
                >
                    Tanda Tangan Dokumen
                </button>
                <button
                    @click="activeTab = 'verify'"
                    :class="[
                        activeTab === 'verify'
                            ? 'bg-[#13087d] text-white'
                            : 'text-gray-900 hover:text-white hover:bg-[#13087d]',
                        'py-3 rounded-full px-6 font-medium text-sm text-center transition-colors duration-300',
                    ]"
                >
                    Verifikasi Dokumen
                </button>
            </nav>
        </div>

        <div class="p-8">
            <div v-show="activeTab === 'sign'" class="space-y-6">
                <div v-if="!selectedFile" class="space-y-6">
                    <div class="text-center mb-8 flex flex-col gap-2">
                        <h2 class="text-xl font-bold text-gray-900">
                            Upload Dokumen untuk Ditandatangani
                        </h2>
                        <p class="text-gray-600 text-sm">
                            Format yang didukung: PDF. Maksimal ukuran file:
                            10MB.
                        </p>
                    </div>

                    <div
                        @click="triggerFileInput"
                        @dragover.prevent
                        @drop="handleDrop"
                        class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-[#13087d] transition-colors duration-300 cursor-pointer bg-gray-50 hover:bg-blue-50"
                    >
                        <input
                            type="file"
                            ref="fileInput"
                            class="hidden"
                            accept="application/pdf"
                            @change="handleFileSelect"
                        />
                        <svg
                            class="mx-auto h-12 w-12 text-gray-400"
                            stroke="currentColor"
                            fill="none"
                            viewBox="0 0 48 48"
                            aria-hidden="true"
                        >
                            <path
                                d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <div
                            class="mt-4 flex text-sm text-gray-600 justify-center"
                        >
                            <p>Upload file PDF atau drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-600 mt-2">
                            PDF hingga 10MB
                        </p>
                    </div>
                </div>

                <div v-else class="space-y-6 w-full">
                    <div class="text-center mb-4 flex flex-col gap-2">
                        <h2 class="text-xl font-bold text-gray-900">
                            {{
                                isSigned
                                    ? "Dokumen Berhasil Ditandatangani"
                                    : "Preview Dokumen"
                            }}
                        </h2>
                        <p class="text-gray-600 text-sm">
                            {{ selectedFile.name }}
                        </p>
                    </div>

                    <div
                        class="relative w-full border border-gray-300 rounded-lg overflow-hidden bg-gray-50"
                    >
                        <div
                            class="max-h-200 overflow-y-auto p-4 custom-scrollbar relative"
                        >
                            <VuePdfEmbed
                                v-if="pdfUrl"
                                :source="pdfUrl"
                                class="w-full shadow-md"
                            />
                            <div v-else class="text-gray-500 text-center py-20">
                                Loading Preview...
                            </div>

                            <div
                                v-if="isSigned && qrCodeUrl"
                                class="absolute bottom-10 right-10 bg-white/90 backdrop-blur-sm p-4 border border-gray-200 shadow-2xl rounded-xl z-20 flex flex-col items-center gap-2 animate-bounce-in"
                                style="bottom: 5%; right: 5%"
                            >
                                <img
                                    :src="qrCodeUrl"
                                    alt="Signature QR Code"
                                    class="w-24 h-24"
                                />
                                <div class="text-center">
                                    <p class="text-xs font-bold text-[#13087d]">
                                        DIGITALLY SIGNED
                                    </p>
                                    <p
                                        class="text-[10px] text-gray-600 font-medium"
                                    >
                                        {{ signerName }}
                                    </p>
                                    <p class="text-[10px] text-gray-500">
                                        {{ new Date().toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="signError"
                        class="text-sm text-red-600 text-center"
                    >
                        {{ signError }}
                    </div>
                    <div
                        v-if="isSigned && signerList.length"
                        class="rounded-lg border border-gray-200 bg-white p-4"
                    >
                        <div class="text-sm font-semibold text-gray-900">
                            Penandatangan
                        </div>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="(signer, index) in signerList"
                                :key="signer.userId || signer.email || index"
                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border border-gray-100 rounded-md p-3 bg-gray-50"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-gray-900"
                                    >
                                        {{ signer.name || "Tidak diketahui" }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ signer.email || "-" }}
                                    </p>
                                </div>
                                <div class="text-xs text-gray-500 sm:text-right">
                                    <p>{{ signer.role || "-" }}</p>
                                    <p>
                                        {{
                                            signer.signedAt
                                                ? formatSignedDate(
                                                      signer.signedAt
                                                  )
                                                : "-"
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-center gap-4 mt-2">
                        <button
                            v-if="!isSigned"
                            @click="handleSign"
                            :disabled="isSigning"
                            class="px-6 py-2 bg-[#13087d] text-white rounded-full hover:bg-blue-900 font-medium transition-colors shadow-lg hover:shadow-xl text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <span v-if="isSigning">Memproses...</span>
                            <span v-else>Tanda Tangan Sekarang</span>
                        </button>
                        <button
                            v-if="!isSigned"
                            @click="selectedFile = null"
                            class="px-6 py-2 border border-gray-300 rounded-full text-gray-700 hover:bg-gray-50 font-medium transition-colors text-sm"
                        >
                            Batal
                        </button>
                        <button
                            v-if="isSigned"
                            @click="handleSave"
                            class="px-6 py-2 bg-blue-600 text-white rounded-full hover:bg-blue-700 font-medium transition-colors shadow-lg text-sm flex items-center gap-2"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"
                                />
                            </svg>
                            Simpan Dokumen
                        </button>
                        <button
                            v-if="isSigned"
                            @click="
                                selectedFile = null;
                                isSigned = false;
                            "
                            class="px-6 py-2 bg-green-600 text-white rounded-full hover:bg-green-700 font-medium transition-colors shadow-lg text-sm"
                        >
                            Selesai / Upload Baru
                        </button>
                    </div>
                </div>
            </div>

            <div v-show="activeTab === 'verify'" class="space-y-6">
                <div class="text-center mb-8 flex flex-col gap-2">
                    <h2 class="text-xl font-bold text-gray-900">
                        Verifikasi Dokumen Digital
                    </h2>
                    <p class="text-sm text-gray-600">
                        Upload dokumen yang sudah ditandatangani untuk cek
                        keasliannya.
                    </p>
                </div>

                <div v-if="!verifyFile">
                    <div
                        @click="triggerVerifyFileInput"
                        @dragover.prevent
                        @drop="handleVerifyDrop"
                        class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-[#13087d] transition-colors duration-300 cursor-pointer bg-gray-50 hover:bg-blue-50"
                    >
                        <input
                            type="file"
                            ref="verifyFileInput"
                            class="hidden"
                            accept="application/pdf"
                            @change="handleVerifyFileSelect"
                        />
                        <svg
                            class="mx-auto h-12 w-12 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            ></path>
                        </svg>
                        <div
                            class="mt-4 flex text-sm text-gray-600 justify-center"
                        >
                            <p>Upload dokumen untuk verifikasi</p>
                        </div>
                        <p class="text-xs text-gray-600 mt-2">PDF file</p>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div
                        class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <svg
                                class="h-8 w-8 text-red-600"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <div>
                                <p class="font-medium text-gray-900">
                                    {{ verifyFile.name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ (verifyFile.size / 1024).toFixed(2) }} KB
                                </p>
                            </div>
                        </div>
                        <button
                            @click="verifyFile = null"
                            class="text-gray-400 hover:text-gray-600"
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
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="flex justify-center gap-4">
                        <button
                            @click="handleVerify"
                            :disabled="isVerifying"
                            class="px-6 py-2 bg-[#13087d] text-white rounded-full hover:bg-blue-900 font-medium transition-colors shadow-lg hover:shadow-xl text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="isVerifying">Memverifikasi...</span>
                            <span v-else>Verifikasi Dokumen</span>
                        </button>
                        <button
                            @click="verifyFile = null"
                            class="px-6 py-2 border border-gray-300 rounded-full text-gray-700 hover:bg-gray-50 font-medium transition-colors text-sm"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <VerificationModal
            :show="showVerificationModal"
            :result="verificationResult"
            @close="closeVerificationModal"
        />
    </div>
</template>
