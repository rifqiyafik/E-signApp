<script setup>
import { computed } from "vue";

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

const statusLabel = computed(() =>
    props.result?.isValid ? "VALID" : "TIDAK VALID"
);

const formatStatus = (value) => {
    if (value === true) {
        return "VALID";
    }
    if (value === false) {
        return "INVALID";
    }
    if (value === null || value === undefined || value === "") {
        return "-";
    }

    const text = String(value).replace(/_/g, " ");
    return text.charAt(0).toUpperCase() + text.slice(1);
};

const signatureStatus = computed(() =>
    formatStatus(props.result?.isValidSignature)
);
const certificateStatus = computed(() =>
    formatStatus(props.result?.certificateStatus)
);
const tsaStatus = computed(() => formatStatus(props.result?.tsaStatus));
const ltvStatus = computed(() => formatStatus(props.result?.ltvStatus));

const versionLabel = computed(() => {
    const version = props.result?.versionNumber;
    return version ? `v${version}` : "-";
});

const fileLabel = computed(
    () => props.result?.originalFileName || props.result?.fileName || "-"
);

const signerList = computed(() =>
    Array.isArray(props.result?.signers) ? props.result.signers : []
);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click.self="close"
    >
        <div
            class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 relative animate-bounce-in max-h-[90vh] overflow-y-auto"
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

            <div class="text-left">
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-full"
                        :class="
                            result?.isValid
                                ? 'bg-green-100 text-green-600'
                                : 'bg-red-100 text-red-600'
                        "
                    >
                        <svg
                            v-if="result?.isValid"
                            class="h-6 w-6"
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
                        <svg
                            v-else
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
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-gray-900">
                                Verifikasi Dokumen
                            </h3>
                            <span
                                class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                :class="
                                    result?.isValid
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'
                                "
                            >
                                {{ statusLabel }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ result?.message }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 bg-gray-50 rounded-xl p-4 text-sm space-y-2">
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-gray-500">File</span>
                        <span class="text-right text-gray-900 break-words">
                            {{ fileLabel }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Versi</span>
                        <span class="font-medium text-gray-900">
                            {{ versionLabel }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Signature</span>
                        <span class="font-medium text-gray-900">
                            {{ signatureStatus }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Sertifikat</span>
                        <span class="font-medium text-gray-900">
                            {{ certificateStatus }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">TSA</span>
                        <span class="font-medium text-gray-900">
                            {{ tsaStatus }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">LTV</span>
                        <span class="font-medium text-gray-900">
                            {{ ltvStatus }}
                        </span>
                    </div>
                </div>

                <div
                    class="mt-4 rounded-xl border border-[#e6e0f7] bg-[#f7f4ff] p-4"
                >
                    <p class="text-xs font-semibold text-[#6a4cc0] uppercase">
                        Penandatangan
                    </p>
                    <div v-if="signerList.length" class="mt-3 space-y-3">
                        <div
                            v-for="(signer, index) in signerList"
                            :key="signer.userId || signer.email || index"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#ebe5fb] text-[#6a4cc0] text-xs font-semibold"
                            >
                                {{ signer.index ?? index + 1 }}
                            </span>
                            <div class="min-w-0">
                                <p
                                    class="text-sm font-semibold text-gray-900 truncate"
                                >
                                    {{ signer.name || "Tidak diketahui" }}
                                </p>
                                <p class="text-xs text-gray-500 truncate">
                                    {{ signer.email || "-" }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-xs text-gray-500">
                        Belum ada data penandatangan.
                    </p>
                </div>
            </div>

            <button
                @click="close"
                class="mt-6 w-full px-6 py-2.5 bg-[#6a4cc0] text-white rounded-full hover:bg-[#5a3fb3] font-medium transition-colors shadow-lg"
            >
                OK
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
