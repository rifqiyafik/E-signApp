<script setup>
const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    result: {
        type: Object,
        default: () => null,
    },
});

const emit = defineEmits(["close"]);

const close = () => {
    emit("close");
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click.self="close"
    >
        <div
            class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8 relative animate-bounce-in"
        >
            <button
                @click="close"
                class="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
            >
                <svg
                    class="h-6 w-6"
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

            <div v-if="result?.isValid" class="text-center">
                <div
                    class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4"
                >
                    <svg
                        class="h-10 w-10 text-green-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">
                    Dokumen Asli
                </h3>
                <p class="text-gray-600 mb-6">
                    {{ result.message }}
                </p>

                <div class="bg-gray-50 rounded-lg p-4 text-left space-y-3">
                    <div class="border-b border-gray-200 pb-3">
                        <p class="text-xs text-gray-500 uppercase">
                            Penandatangan
                        </p>
                        <p class="font-semibold text-gray-900">
                            {{ result.signerName }}
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ result.signerEmail }}
                        </p>
                    </div>

                    <div class="border-b border-gray-200 pb-3">
                        <p class="text-xs text-gray-500 uppercase">
                            Tanggal Ditandatangani
                        </p>
                        <p class="font-medium text-gray-900">
                            {{ result.signedDate }}
                        </p>
                    </div>

                    <div class="border-b border-gray-200 pb-3">
                        <p class="text-xs text-gray-500 uppercase">
                            Nama File Asli
                        </p>
                        <p class="font-medium text-gray-900 text-sm">
                            {{ result.originalFileName }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">
                            Informasi File
                        </p>
                        <p class="text-sm text-gray-900">
                            {{ result.fileName }}
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ result.fileSize }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-else class="text-center">
                <div
                    class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4"
                >
                    <svg
                        class="h-10 w-10 text-red-600"
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
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">
                    Dokumen Tidak Valid
                </h3>
                <p class="text-gray-600 mb-6">
                    {{ result?.message }}
                </p>

                <div class="bg-gray-50 rounded-lg p-4 text-left space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">
                            Informasi File
                        </p>
                        <p class="text-sm text-gray-900">
                            {{ result?.fileName }}
                        </p>
                        <p class="text-sm text-gray-600">
                            {{ result?.fileSize }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4"
                >
                    <p class="text-sm text-yellow-800">
                        <strong>Catatan:</strong> Dokumen ini mungkin belum
                        ditandatangani atau tanda tangan digitalnya telah
                        dimodifikasi.
                    </p>
                </div>
            </div>

            <button
                @click="close"
                class="mt-6 w-full px-6 py-3 bg-[#13087d] text-white rounded-full hover:bg-blue-900 font-medium transition-colors shadow-lg"
            >
                Tutup
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes bounce-in {
    0% {
        opacity: 0;
        transform: scale(0.3);
    }
    50% {
        opacity: 1;
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        transform: scale(1);
    }
}

.animate-bounce-in {
    animation: bounce-in 0.6s ease-out;
}
</style>
