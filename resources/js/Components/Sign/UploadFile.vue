<script setup>
import { ref, computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import QRCode from "qrcode";
import VuePdfEmbed from "vue-pdf-embed";
import { PDFDocument, rgb } from "pdf-lib";
import VerificationModal from "@/Components/Sign/VerificationModal.vue";

import "vue-pdf-embed/dist/styles/textLayer.css";
import "vue-pdf-embed/dist/styles/annotationLayer.css";

const activeTab = ref("sign");
const fileInput = ref(null);
const selectedFile = ref(null);
const isSigned = ref(false);
const pdfUrl = ref(null);
const qrCodeUrl = ref(null);

const verifyFileInput = ref(null);
const verifyFile = ref(null);
const isVerifying = ref(false);
const verificationResult = ref(null);
const showVerificationModal = ref(false);

const user = computed(() => usePage().props?.auth?.user || null);

const signerName = computed(() => user.value?.name || "Pengguna");
const signerEmail = computed(() => user.value?.email || "user@example.com");

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    processFile(file);
};

const handleDrop = (event) => {
    event.preventDefault();
    const file = event.dataTransfer.files[0];
    processFile(file);
};

const processFile = (file) => {
    if (file && file.type === "application/pdf") {
        selectedFile.value = file;
        isSigned.value = false;
        qrCodeUrl.value = null;
        if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
        pdfUrl.value = URL.createObjectURL(file);
    }
};

const handleSign = async () => {
    try {
        const signatureData = JSON.stringify({
            name: signerName.value,
            email: signerEmail.value,
            file: selectedFile.value.name,
            date: new Date().toISOString(),
            valid: true,
        });

        qrCodeUrl.value = await QRCode.toDataURL(signatureData, {
            errorCorrectionLevel: "H",
        });
        isSigned.value = true;
    } catch (err) {
        console.error("Error generating QR code", err);
    }
};

const handleSave = async () => {
    if (!selectedFile.value || !qrCodeUrl.value) {
        console.error("Missing required data:", {
            hasFile: !!selectedFile.value,
            hasQR: !!qrCodeUrl.value,
        });
        return;
    }

    try {
        const arrayBuffer = await selectedFile.value.arrayBuffer();
        const pdfDoc = await PDFDocument.load(arrayBuffer);

        const signatureMetadata = JSON.stringify({
            name: signerName.value,
            email: signerEmail.value,
            file: selectedFile.value.name,
            date: new Date().toISOString(),
            valid: true,
        });

        pdfDoc.setTitle(`ESIGN_DATA:${signatureMetadata}`);
        pdfDoc.setSubject("Digitally Signed Document");
        pdfDoc.setKeywords([`ESIGN_DATA:${signatureMetadata}`]);

        const pages = pdfDoc.getPages();
        const lastPage = pages[pages.length - 1];
        const { width, height } = lastPage.getSize();

        const qrImageBytes = await fetch(qrCodeUrl.value).then((res) =>
            res.arrayBuffer()
        );
        const qrImage = await pdfDoc.embedPng(qrImageBytes);

        const qrSize = 80;
        const margin = 30;
        const qrX = width - qrSize - margin;
        const qrY = margin;
        lastPage.drawImage(qrImage, {
            x: qrX,
            y: qrY,
            width: qrSize,
            height: qrSize,
        });

        const fontSize = 8;
        const textX = qrX;
        const textY = qrY + qrSize + 5;

        const signedText = "DIGITALLY SIGNED";
        const nameText = signerName.value || "Unknown Signer";
        const dateText = new Date().toLocaleDateString("id-ID");

        lastPage.drawText(signedText, {
            x: textX,
            y: textY,
            size: fontSize,
            color: rgb(0.07, 0.03, 0.49),
        });

        lastPage.drawText(nameText, {
            x: textX,
            y: textY - 10,
            size: fontSize - 1,
            color: rgb(0.4, 0.4, 0.4),
        });

        lastPage.drawText(dateText, {
            x: textX,
            y: textY - 20,
            size: fontSize - 1,
            color: rgb(0.5, 0.5, 0.5),
        });

        const pdfBytes = await pdfDoc.save();
        const blob = new Blob([pdfBytes], { type: "application/pdf" });
        const url = URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = url;
        link.download = `signed-${selectedFile.value.name}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    } catch (error) {
        console.error("Error saving signed PDF:", error);
        console.error("Error details:", {
            message: error.message,
            stack: error.stack,
        });
    }
};

const triggerVerifyFileInput = () => {
    verifyFileInput.value.click();
};

const handleVerifyFileSelect = (event) => {
    const file = event.target.files[0];
    if (file && file.type === "application/pdf") {
        verifyFile.value = file;
    }
};

const handleVerifyDrop = (event) => {
    event.preventDefault();
    const file = event.dataTransfer.files[0];
    if (file && file.type === "application/pdf") {
        verifyFile.value = file;
    }
};

const extractQRFromPDF = async (file) => {
    try {
        const arrayBuffer = await file.arrayBuffer();
        const pdfDoc = await PDFDocument.load(arrayBuffer);

        const title = pdfDoc.getTitle();
        const keywordsArray = pdfDoc.getKeywords();
        const subject = pdfDoc.getSubject();

        const keywords = Array.isArray(keywordsArray)
            ? keywordsArray.join(" ")
            : keywordsArray;

        if (title && title.startsWith("ESIGN_DATA:")) {
            const signatureData = title.replace("ESIGN_DATA:", "");
            return signatureData;
        }

        if (keywords && keywords.startsWith("ESIGN_DATA:")) {
            const signatureData = keywords.replace("ESIGN_DATA:", "");
            return signatureData;
        }

        console.warn("No ESIGN_DATA found in any metadata field");
        return null;
    } catch (error) {
        console.error("Error extracting signature from PDF:", error);
        throw error;
    }
};

const handleVerify = async () => {
    if (!verifyFile.value) {
        return;
    }

    isVerifying.value = true;
    verificationResult.value = null;

    try {
        const qrData = await extractQRFromPDF(verifyFile.value);

        if (!qrData) {
            verificationResult.value = {
                isValid: false,
                message:
                    "Dokumen belum ditandatangani atau tidak memiliki tanda tangan digital.",
                fileName: verifyFile.value.name,
                fileSize: (verifyFile.value.size / 1024).toFixed(2) + " KB",
            };
        } else {
            try {
                const signatureData = JSON.parse(qrData);

                verificationResult.value = {
                    isValid: true,
                    message:
                        "Dokumen asli dan telah ditandatangani secara digital.",
                    signerName: signatureData.name,
                    signerEmail: signatureData.email,
                    originalFileName: signatureData.file,
                    signedDate: new Date(signatureData.date).toLocaleString(
                        "id-ID"
                    ),
                    fileName: verifyFile.value.name,
                    fileSize: (verifyFile.value.size / 1024).toFixed(2) + " KB",
                    isValidSignature: signatureData.valid,
                };
            } catch (parseError) {
                verificationResult.value = {
                    isValid: false,
                    message: "Tanda tangan digital tidak valid atau rusak.",
                    fileName: verifyFile.value.name,
                    fileSize: (verifyFile.value.size / 1024).toFixed(2) + " KB",
                };
            }
        }

        showVerificationModal.value = true;
    } catch (error) {
        console.error("Verification error:", error);
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
                            class="max-h-[800px] overflow-y-auto p-4 custom-scrollbar relative"
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
                    <div class="flex justify-center gap-4 mt-2">
                        <button
                            v-if="!isSigned"
                            @click="handleSign"
                            class="px-6 py-2 bg-[#13087d] text-white rounded-full hover:bg-blue-900 font-medium transition-colors shadow-lg hover:shadow-xl text-sm"
                        >
                            Tanda Tangan Sekarang
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
