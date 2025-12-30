<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="relative isolate overflow-hidden">
      <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.15),_transparent_45%),radial-gradient(circle_at_30%_30%,_rgba(99,102,241,0.12),_transparent_50%),linear-gradient(180deg,#f8fafc_0%,#ffffff_70%)]"></div>

      <nav class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4">
        <div class="flex items-center gap-3 rounded-full border border-white/60 bg-white/70 px-4 py-2 text-sm font-semibold shadow-sm backdrop-blur">
          <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white font-bold">E</span>
          <span>E-Signer</span>
        </div>
        <Link
          href="/login"
          class="inline-flex items-center rounded-full bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-blue-700"
        >
          Login
        </Link>
      </nav>

      <section class="mx-auto max-w-6xl px-4 pb-20 pt-10">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
          <div>
            <p class="inline-flex items-center gap-2 rounded-full border border-blue-200/70 bg-white/70 px-3 py-1 text-xs font-semibold text-blue-700">
              Multi-tenant Digital Signature
            </p>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl">
              Verifikasi tanda tangan digital secara instan.
            </h1>
            <p class="mt-4 text-lg text-slate-600">
              Upload file PDF yang sudah ditandatangani dan dapatkan verifikasi instan dengan detail signer, timestamp, dan bukti audit.
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
              <button
                type="button"
                class="inline-flex items-center rounded-full bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700"
                @click="openPicker"
              >
                Verifikasi Dokumen
              </button>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
              <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Security</p>
                <p class="mt-2 text-sm text-slate-600">X.509 certificate checks dan TSA timestamps.</p>
              </div>
              <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Multi-tenant</p>
                <p class="mt-2 text-sm text-slate-600">Satu identitas, banyak workspace.</p>
              </div>
            </div>
          </div>

          <div>
            <div
              class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-xl backdrop-blur"
              @dragover.prevent="dragging = true"
              @dragleave.prevent="dragging = false"
              @drop.prevent="onDrop"
            >
              <div
                class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed p-10 text-center transition"
                :class="dragging ? 'border-blue-400 bg-blue-50' : 'border-slate-200 bg-slate-50'"
              >
                <div v-if="verifying" class="flex flex-col items-center">
                  <div class="h-10 w-10 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></div>
                  <p class="mt-4 text-sm text-slate-600">Memverifikasi dokumen...</p>
                </div>
                <template v-else>
                  <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600/10 text-blue-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6">
                      <path d="M12 3v12" />
                      <path d="m7 8 5-5 5 5" />
                      <rect x="4" y="15" width="16" height="6" rx="2" />
                    </svg>
                  </div>
                  <h3 class="mt-4 text-lg font-semibold">Drop PDF Anda di sini</h3>
                  <p class="mt-2 text-sm text-slate-500">atau klik untuk browse file</p>
                  <button
                    type="button"
                    class="mt-5 inline-flex items-center rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                    @click="openPicker"
                  >
                    Pilih File
                  </button>
                </template>
                <input ref="fileInput" type="file" accept=".pdf" class="hidden" @change="onFileChange" />
              </div>

              <div class="mt-6 flex items-center justify-between text-xs text-slate-500">
                <span>PDF only. Max 10MB.</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-1 font-semibold text-blue-700">
                  Instant verification
                </span>
              </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
              <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Workflow</p>
                <p class="mt-2 text-sm text-slate-600">Track urutan signer dengan visual sequencing.</p>
              </div>
              <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Audit</p>
                <p class="mt-2 text-sm text-slate-600">Jejak audit untuk kebutuhan legal dan compliance.</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Verification Result Modal -->
    <transition name="page">
      <div v-if="verification" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
        <div class="w-full max-w-lg rounded-3xl border border-white/60 bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Hasil Verifikasi</p>
              <h3 class="mt-2 text-xl font-semibold">{{ verification.fileName }}</h3>
            </div>
            <button class="text-slate-400 hover:text-slate-600 text-xl" @click="closeModal">✕</button>
          </div>

          <!-- Main Status -->
          <div class="mt-6 rounded-2xl border p-4" :class="verification.valid ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'">
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold">
                {{ verification.valid ? 'Dokumen Valid' : 'Dokumen Tidak Valid' }}
              </span>
              <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="verification.valid ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
              >
                {{ verification.valid ? '✓ Terverifikasi' : '✗ Ditolak' }}
              </span>
            </div>
            <p v-if="!verification.valid && verification.reason" class="mt-2 text-xs text-slate-600">
              Alasan: {{ getReasonText(verification.reason) }}
            </p>
          </div>

          <!-- Details (only if valid) -->
          <template v-if="verification.valid">
            <!-- Signature Status -->
            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status Tanda Tangan</p>
              <div class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-slate-600">Signature</span>
                  <span :class="verification.signatureValid ? 'text-emerald-600 font-semibold' : 'text-rose-600'">
                    {{ verification.signatureValid === true ? '✓ Valid' : verification.signatureValid === false ? '✗ Invalid' : '- N/A' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-600">Certificate</span>
                  <span :class="verification.certificateStatus === 'valid' ? 'text-emerald-600 font-semibold' : 'text-amber-600'">
                    {{ verification.certificateStatus || 'N/A' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-600">Timestamp (TSA)</span>
                  <span :class="verification.tsaStatus === 'valid' ? 'text-emerald-600 font-semibold' : 'text-amber-600'">
                    {{ verification.tsaStatus || 'N/A' }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-600">LTV Status</span>
                  <span :class="verification.ltvStatus === 'ready' ? 'text-emerald-600 font-semibold' : 'text-amber-600'">
                    {{ verification.ltvStatus || 'N/A' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Document Info -->
            <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Informasi Dokumen</p>
              <div class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-slate-600">Document ID</span>
                  <span class="font-mono text-xs">{{ verification.documentId?.slice(-12) || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-600">Chain ID</span>
                  <span class="font-mono text-xs">{{ verification.chainId?.slice(-12) || 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-600">Version</span>
                  <span>{{ verification.versionNumber || 'N/A' }}</span>
                </div>
                <div v-if="verification.tsaSignedAt" class="flex justify-between">
                  <span class="text-slate-600">Signed At</span>
                  <span>{{ formatTime(verification.tsaSignedAt) }}</span>
                </div>
              </div>
            </div>

            <!-- Signers -->
            <div v-if="verification.signers && verification.signers.length" class="mt-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Signers</p>
              <div class="mt-3 space-y-2">
                <div
                  v-for="(signer, idx) in verification.signers"
                  :key="idx"
                  class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white px-4 py-3"
                >
                  <div>
                    <p class="text-sm font-semibold">{{ signer.name || signer.email || 'Unknown' }}</p>
                    <p class="text-xs text-slate-500">Signer #{{ signer.signerIndex || idx + 1 }}</p>
                  </div>
                  <span 
                    class="rounded-full px-2 py-1 text-xs font-semibold"
                    :class="signer.status === 'signed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                  >
                    {{ signer.status === 'signed' ? 'Signed' : signer.status }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <!-- Hash Info -->
          <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">SHA-256 Hash</p>
            <p class="mt-2 font-mono text-xs text-slate-500 break-all">{{ verification.signedPdfSha256 || 'N/A' }}</p>
          </div>
        </div>
      </div>
    </transition>

    <!-- Error Toast -->
    <div v-if="errorMessage" class="fixed bottom-6 right-6 z-50 rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-lg">
      ✗ {{ errorMessage }}
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const dragging = ref(false);
const verifying = ref(false);
const verification = ref(null);
const errorMessage = ref('');
const fileInput = ref(null);

const openPicker = () => {
  fileInput.value?.click();
};

const onFileChange = (event) => {
  handleFiles(event.target.files);
  event.target.value = '';
};

const onDrop = (event) => {
  dragging.value = false;
  handleFiles(event.dataTransfer.files);
};

const handleFiles = async (files) => {
  if (!files || !files.length) return;
  const file = files[0];
  
  // Validate file
  if (!file.name.toLowerCase().endsWith('.pdf')) {
    showError('Hanya file PDF yang diperbolehkan');
    return;
  }
  
  if (file.size > 10 * 1024 * 1024) {
    showError('Ukuran file maksimal 10MB');
    return;
  }

  verifying.value = true;
  errorMessage.value = '';
  
  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch('/api/verify', {
      method: 'POST',
      body: formData,
    });

    if (!response.ok) {
      throw new Error('Gagal memverifikasi dokumen');
    }

    const data = await response.json();
    
    verification.value = {
      fileName: file.name,
      ...data,
    };
  } catch (error) {
    console.error('Verification error:', error);
    showError(error.message || 'Terjadi kesalahan saat verifikasi');
  } finally {
    verifying.value = false;
  }
};

const closeModal = () => {
  verification.value = null;
};

const showError = (message) => {
  errorMessage.value = message;
  setTimeout(() => {
    errorMessage.value = '';
  }, 5000);
};

const getReasonText = (reason) => {
  const reasons = {
    'hash_not_found': 'Dokumen tidak ditemukan dalam sistem. Mungkin belum ditandatangani melalui E-Signer.',
    'hash_mismatch': 'Hash dokumen tidak cocok dengan yang tersimpan.',
  };
  return reasons[reason] || reason;
};

const formatTime = (value) => {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
};
</script>
