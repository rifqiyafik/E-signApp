<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.12),_transparent_40%),linear-gradient(180deg,#f8fafc_0%,#ffffff_70%)]"></div>

    <!-- Navbar -->
    <nav class="sticky top-2 sm:top-4 z-30 mx-auto max-w-6xl px-2 sm:px-4">
      <div class="flex items-center justify-between rounded-2xl border border-white/60 bg-white/70 px-3 py-2 sm:px-4 sm:py-3 shadow-sm backdrop-blur">
        <div class="flex items-center gap-2 sm:gap-3">
          <span class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-xl sm:rounded-2xl bg-blue-600 text-white font-bold text-sm">E</span>
          <div class="hidden sm:block">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Workspace</p>
            <p class="text-sm font-semibold truncate max-w-[120px] md:max-w-none">{{ currentTenant?.name || 'Dashboard' }}</p>
          </div>
          <p class="sm:hidden text-xs font-semibold truncate max-w-[80px]">{{ currentTenant?.name || 'Dashboard' }}</p>
        </div>
        <div class="flex items-center gap-2 sm:gap-4">
          <!-- User info (hidden on mobile) -->
          <div class="hidden md:block text-right">
            <p class="text-sm font-semibold text-slate-700">{{ user?.name || 'User' }}</p>
            <p class="text-xs text-slate-500">{{ getRoleLabel(user?.role) }}</p>
          </div>
          <!-- Role badge -->
          <span 
            class="rounded-full px-2 py-1 sm:px-3 text-[10px] sm:text-xs font-semibold"
            :class="isAdmin ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'"
          >
            <span class="hidden sm:inline">{{ isAdmin ? '👑 Admin' : '👤 Member' }}</span>
            <span class="sm:hidden">{{ isAdmin ? '👑' : '👤' }}</span>
          </span>
          <button class="rounded-full border border-slate-200 bg-white px-2 py-1.5 sm:px-4 sm:py-2 text-xs font-semibold text-slate-600 hover:border-slate-300" @click="logout">
            <span class="hidden sm:inline">Logout</span>
            <span class="sm:hidden">↩</span>
          </button>
        </div>
      </div>
    </nav>

    <main class="mx-auto max-w-6xl px-2 sm:px-4 pb-16 pt-4 sm:pt-8">
      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-2 sm:gap-4">
        <div>
          <h1 class="text-xl sm:text-3xl font-semibold">Dashboard</h1>
          <p class="text-xs sm:text-sm text-slate-500">Kelola dokumen tanda tangan digital</p>
        </div>
      </div>

      <!-- Main Navigation Tabs -->
      <div class="mt-4 sm:mt-6 overflow-x-auto -mx-2 sm:mx-0 px-2 sm:px-0">
        <div class="flex gap-2 border-b border-slate-200 pb-2 min-w-max sm:min-w-0 sm:flex-wrap">
          <button
            class="rounded-full px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm font-semibold transition whitespace-nowrap"
            :class="activeMainTab === 'inbox' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:border-blue-300'"
            @click="activeMainTab = 'inbox'"
          >
            📥 Inbox
            <span v-if="totalInboxCount > 0" class="ml-1 inline-flex h-4 w-4 sm:h-5 sm:w-5 items-center justify-center rounded-full bg-rose-500 text-[8px] sm:text-[10px] text-white">{{ totalInboxCount }}</span>
          </button>
          <button
            v-if="isAdmin"
            class="rounded-full px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm font-semibold transition whitespace-nowrap"
            :class="activeMainTab === 'documents' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:border-blue-300'"
            @click="activeMainTab = 'documents'"
          >
            📄 Dokumen
          </button>
          <button
            v-if="isAdmin"
            class="rounded-full px-3 py-1.5 sm:px-4 sm:py-2 text-xs sm:text-sm font-semibold transition whitespace-nowrap"
            :class="activeMainTab === 'users' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:border-blue-300'"
            @click="activeMainTab = 'users'"
          >
            👥 User
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="mt-6 sm:mt-8 flex items-center justify-center py-8 sm:py-12">
        <div class="h-6 w-6 sm:h-8 sm:w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></div>
        <span class="ml-3 text-sm text-slate-500">Memuat data...</span>
      </div>

      <!-- ==================== INBOX TAB ==================== -->
      <section v-if="!loading && activeMainTab === 'inbox'" class="mt-4 sm:mt-8 space-y-4 sm:space-y-6">
        <!-- Stats Cards -->
        <div class="grid gap-2 sm:gap-4 grid-cols-2 lg:grid-cols-4">
          <div class="rounded-xl sm:rounded-2xl border border-white/70 bg-white/80 p-3 sm:p-5 shadow-sm cursor-pointer hover:shadow-md transition" :class="activeInboxTab === 'need_signature' ? 'ring-2 ring-rose-400' : ''" @click="activeInboxTab = 'need_signature'">
            <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-rose-500">🖊️ Giliran Anda</p>
            <p class="mt-1 sm:mt-2 text-xl sm:text-3xl font-bold text-rose-600">{{ inbox.needSignature.length }}</p>
            <p class="hidden sm:block mt-1 text-xs text-slate-500">Perlu tanda tangan sekarang</p>
          </div>
          <div class="rounded-xl sm:rounded-2xl border border-white/70 bg-white/80 p-3 sm:p-5 shadow-sm cursor-pointer hover:shadow-md transition" :class="activeInboxTab === 'upcoming' ? 'ring-2 ring-blue-400' : ''" @click="activeInboxTab = 'upcoming'">
            <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-blue-500">⏳ Menunggu</p>
            <p class="mt-1 sm:mt-2 text-xl sm:text-3xl font-bold text-blue-600">{{ inbox.upcoming.length }}</p>
            <p class="hidden sm:block mt-1 text-xs text-slate-500">Menunggu signer sebelumnya</p>
          </div>
          <div class="rounded-xl sm:rounded-2xl border border-white/70 bg-white/80 p-3 sm:p-5 shadow-sm cursor-pointer hover:shadow-md transition" :class="activeInboxTab === 'waiting' ? 'ring-2 ring-amber-400' : ''" @click="activeInboxTab = 'waiting'">
            <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-amber-500">✅ Sudah TTD</p>
            <p class="mt-1 sm:mt-2 text-xl sm:text-3xl font-bold text-amber-600">{{ inbox.waiting.length }}</p>
            <p class="hidden sm:block mt-1 text-xs text-slate-500">Menunggu signer lain selesai</p>
          </div>
          <div class="rounded-xl sm:rounded-2xl border border-white/70 bg-white/80 p-3 sm:p-5 shadow-sm cursor-pointer hover:shadow-md transition" :class="activeInboxTab === 'completed' ? 'ring-2 ring-emerald-400' : ''" @click="activeInboxTab = 'completed'">
            <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-emerald-500">🎉 Selesai</p>
            <p class="mt-1 sm:mt-2 text-xl sm:text-3xl font-bold text-emerald-600">{{ inbox.completed.length }}</p>
            <p class="hidden sm:block mt-1 text-xs text-slate-500">Semua sudah menandatangani</p>
          </div>
        </div>

        <!-- Inbox Sub-tabs -->
        <div class="overflow-x-auto -mx-2 sm:mx-0 px-2 sm:px-0">
          <div class="flex gap-2 min-w-max sm:min-w-0 sm:flex-wrap">
            <button
              class="rounded-lg px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-semibold whitespace-nowrap"
              :class="activeInboxTab === 'need_signature' ? 'bg-rose-500 text-white' : 'bg-slate-100 text-slate-600'"
              @click="activeInboxTab = 'need_signature'"
            >
              🖊️ Giliran ({{ inbox.needSignature.length }})
            </button>
            <button
              class="rounded-lg px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-semibold whitespace-nowrap"
              :class="activeInboxTab === 'upcoming' ? 'bg-blue-500 text-white' : 'bg-slate-100 text-slate-600'"
              @click="activeInboxTab = 'upcoming'"
            >
              ⏳ Tunggu ({{ inbox.upcoming.length }})
            </button>
            <button
              class="rounded-lg px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-semibold whitespace-nowrap"
              :class="activeInboxTab === 'waiting' ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600'"
              @click="activeInboxTab = 'waiting'"
            >
              ✅ Sudah ({{ inbox.waiting.length }})
            </button>
            <button
              class="rounded-lg px-2 py-1 sm:px-3 sm:py-1.5 text-[10px] sm:text-xs font-semibold whitespace-nowrap"
              :class="activeInboxTab === 'completed' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600'"
              @click="activeInboxTab = 'completed'"
            >
              🎉 Selesai ({{ inbox.completed.length }})
            </button>
          </div>
        </div>

        <!-- Document List -->
        <div class="rounded-xl sm:rounded-2xl border border-white/70 bg-white/80 shadow-sm">
          <div v-if="getCurrentInboxList().length === 0" class="p-6 sm:p-8 text-center text-slate-400">
            <p class="text-3xl sm:text-4xl mb-2">📭</p>
            <p class="text-xs sm:text-sm" v-if="activeInboxTab === 'need_signature'">Tidak ada dokumen yang perlu ditandatangani</p>
            <p class="text-xs sm:text-sm" v-else-if="activeInboxTab === 'upcoming'">Tidak ada dokumen menunggu giliran</p>
            <p class="text-xs sm:text-sm" v-else-if="activeInboxTab === 'waiting'">Tidak ada dokumen yang sudah Anda TTD</p>
            <p class="text-xs sm:text-sm" v-else>Belum ada dokumen selesai</p>
          </div>
          <div v-else class="divide-y divide-slate-100">
            <div v-for="doc in getCurrentInboxList()" :key="doc.documentId" class="p-3 sm:p-4 hover:bg-slate-50">
              <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-sm sm:text-base truncate">{{ doc.title || 'Dokumen ' + doc.documentId.slice(-6) }}</p>
                    <span :class="getDocStatusBadge(doc)" class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold">
                      {{ getDocStatusText(doc) }}
                    </span>
                  </div>
                  <p class="text-[10px] sm:text-xs text-slate-500 mt-1">
                    <span v-if="activeInboxTab === 'need_signature'">
                      🖊️ <strong>Giliran Anda!</strong> (Urutan #{{ doc.yourSignerIndex }})
                    </span>
                    <span v-else-if="activeInboxTab === 'upcoming'">
                      ⏳ Menunggu signer #{{ doc.currentSignerIndex }}
                    </span>
                    <span v-else-if="activeInboxTab === 'waiting'">
                      ✅ Menunggu {{ doc.totalSigners - doc.signedCount }} signer lagi
                    </span>
                    <span v-else>
                      🎉 Selesai {{ formatDate(doc.completedAt) }}
                    </span>
                  </p>
                  
                  <!-- Signer Progress (hidden on mobile for cleaner look) -->
                  <div v-if="doc.signers && doc.signers.length" class="mt-2 hidden sm:flex flex-wrap gap-1">
                    <span 
                      v-for="(signer, idx) in doc.signers" 
                      :key="idx"
                      class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                      :class="signer.status === 'signed' ? 'bg-emerald-100 text-emerald-700' : signer.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600'"
                    >
                      {{ idx + 1 }}. {{ signer.name || signer.email?.split('@')[0] || 'Signer' }}
                      <span v-if="signer.status === 'signed'">✓</span>
                      <span v-else-if="signer.isCurrent">⏳</span>
                    </span>
                  </div>
                </div>
                
                <div class="flex gap-2 flex-shrink-0">
                  <!-- Preview Button -->
                  <button @click="openPreview(doc)" class="flex-1 sm:flex-initial rounded-full border border-slate-200 bg-white px-3 py-1.5 sm:px-4 sm:py-2 text-xs font-semibold text-slate-600 hover:border-blue-300">
                    👁️ <span class="hidden sm:inline">Lihat</span>
                  </button>
                  
                  <!-- Sign Button -->
                  <button v-if="activeInboxTab === 'need_signature'" @click="openPreview(doc, true)" class="flex-1 sm:flex-initial rounded-full bg-blue-600 px-3 py-1.5 sm:px-4 sm:py-2 text-xs font-semibold text-white hover:bg-blue-700">
                    ✍️ <span class="hidden sm:inline">Tanda Tangan</span>
                  </button>
                  
                  <!-- Download Button - Only for completed -->
                  <button v-if="activeInboxTab === 'completed'" @click="downloadDocument(doc)" class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                    ⬇️ Download
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ==================== KELOLA DOKUMEN TAB ==================== -->
      <section v-if="!loading && activeMainTab === 'documents'" class="mt-8 space-y-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-semibold">Kelola Dokumen</h2>
            <p class="text-sm text-slate-500">Upload, atur signer, dan pantau status dokumen</p>
          </div>
          <button class="w-full sm:w-auto rounded-full bg-blue-600 px-4 py-2 sm:px-5 sm:py-2.5 text-xs sm:text-sm font-semibold text-white shadow hover:bg-blue-700" @click="showUploadModal = true">
            + Upload Dokumen
          </button>
        </div>

        <div class="rounded-xl sm:rounded-2xl border border-white/70 bg-white/80 shadow-sm overflow-hidden">
          <div v-if="myDocuments.length === 0" class="p-6 sm:p-8 text-center text-slate-400">
            <p class="text-3xl sm:text-4xl mb-2">📁</p>
            <p class="text-xs sm:text-sm">Belum ada dokumen. Upload dokumen pertama Anda.</p>
          </div>
          
          <!-- Desktop Table -->
          <table v-else class="hidden md:table w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Dokumen</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Signers</th>
                <th class="px-4 py-3">Tanggal</th>
                <th class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="doc in myDocuments" :key="doc.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium">{{ doc.title || doc.id.slice(-8) }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="getStatusBadge(doc.status)">
                    {{ getStatusLabel(doc.status) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">
                  <div v-if="doc.signers?.length" class="flex flex-wrap gap-1">
                    <span 
                      v-for="(s, idx) in doc.signers" 
                      :key="idx"
                      class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                      :class="s.status === 'signed' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                      {{ s.user?.name?.split(' ')[0] || s.email?.split('@')[0] || 'Signer' }}
                      <span v-if="s.status === 'signed'">✓</span>
                    </span>
                  </div>
                  <span v-else class="text-slate-400">Belum diatur</span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(doc.created_at) }}</td>
                <td class="px-4 py-3 text-right">
                  <button v-if="doc.status === 'draft'" @click="openAssignModal(doc)" class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-200 mr-1">
                    Atur Signer
                  </button>
                  <button v-if="doc.status === 'completed'" @click="downloadDocument(doc)" class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-200 mr-1">
                    Download
                  </button>
                  <button @click="deleteDocument(doc)" class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-200">
                    Hapus
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          
          <!-- Mobile Cards -->
          <div v-if="myDocuments.length > 0" class="md:hidden divide-y divide-slate-100">
            <div v-for="doc in myDocuments" :key="doc.id" class="p-4">
              <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-sm truncate">{{ doc.title || doc.id.slice(-8) }}</p>
                  <p class="text-[10px] text-slate-500 mt-0.5">{{ formatDate(doc.created_at) }}</p>
                </div>
                <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="getStatusBadge(doc.status)">
                  {{ getStatusLabel(doc.status) }}
                </span>
              </div>
              <div v-if="doc.signers?.length" class="mt-2 flex flex-wrap gap-1">
                <span 
                  v-for="(s, idx) in doc.signers.slice(0, 3)" 
                  :key="idx"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                  :class="s.status === 'signed' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ s.user?.name?.split(' ')[0] || s.email?.split('@')[0] || 'S' }}
                  <span v-if="s.status === 'signed'" class="ml-0.5">✓</span>
                </span>
                <span v-if="doc.signers.length > 3" class="text-[10px] text-slate-400">+{{ doc.signers.length - 3 }}</span>
              </div>
              <div class="mt-3 flex gap-2">
                <button v-if="doc.status === 'draft'" @click="openAssignModal(doc)" class="flex-1 rounded-full bg-blue-600 px-3 py-2 text-xs font-semibold text-white">
                  📝 Signer
                </button>
                <button v-if="doc.status === 'completed'" @click="downloadDocument(doc)" class="flex-1 rounded-full bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">
                  📥 Download
                </button>
                <button @click="deleteDocument(doc)" class="rounded-full border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600">
                  🗑️
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ==================== KELOLA USER TAB ==================== -->
      <section v-if="!loading && activeMainTab === 'users'" class="mt-4 sm:mt-8 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-lg sm:text-xl font-semibold">Kelola User</h2>
            <p class="text-xs sm:text-sm text-slate-500">Tambah dan kelola user di tenant ini</p>
          </div>
          <button class="w-full sm:w-auto rounded-full bg-blue-600 px-4 py-2 sm:px-5 sm:py-2.5 text-xs sm:text-sm font-semibold text-white shadow hover:bg-blue-700" @click="showAddUserModal = true">
            + Tambah User
          </button>
        </div>

        <div class="rounded-xl sm:rounded-2xl border border-white/70 bg-white/80 shadow-sm overflow-hidden">
          <div v-if="tenantUsers.length === 0" class="p-6 sm:p-8 text-center text-slate-400">
            <p class="text-3xl sm:text-4xl mb-2">👤</p>
            <p class="text-xs sm:text-sm">Belum ada user lain. Tambahkan user pertama.</p>
          </div>
          
          <!-- Desktop Table -->
          <table v-else class="hidden md:table w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Nama</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="u in tenantUsers" :key="u.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium">{{ u.name }}</td>
                <td class="px-4 py-3 text-slate-500">{{ u.email }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ u.role || 'member' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <button
                    v-if="u.global_id !== user?.global_id"
                    @click="removeUser(u)"
                    class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-200"
                  >
                    Hapus
                  </button>
                  <span v-else class="text-xs text-slate-400">(Anda)</span>
                </td>
              </tr>
            </tbody>
          </table>
          
          <!-- Mobile Cards -->
          <div v-if="tenantUsers.length > 0" class="md:hidden divide-y divide-slate-100">
            <div v-for="u in tenantUsers" :key="u.id" class="p-4">
              <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-sm truncate">{{ u.name }}</p>
                  <p class="text-[10px] text-slate-500 truncate mt-0.5">{{ u.email }}</p>
                </div>
                <span class="flex-shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                  {{ u.role || 'member' }}
                </span>
              </div>
              <div class="mt-3 flex gap-2">
                <button
                  v-if="u.global_id !== user?.global_id"
                  @click="removeUser(u)"
                  class="flex-1 rounded-full border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600"
                >
                  🗑️ Hapus
                </button>
                <span v-else class="flex-1 text-center py-2 text-xs text-slate-400 border border-slate-100 rounded-full">(Anda)</span>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- ==================== MODALS ==================== -->

    <!-- Document Preview Modal -->
    <div v-if="previewDoc" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4">
      <div class="w-full max-w-4xl h-[90vh] rounded-2xl bg-white shadow-2xl flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50">
          <div>
            <h3 class="text-lg font-semibold">{{ previewDoc.title || 'Dokumen ' + previewDoc.documentId?.slice(-6) }}</h3>
            <div class="flex items-center gap-2 mt-1">
              <span :class="getDocStatusBadge(previewDoc)" class="rounded-full px-2 py-0.5 text-xs font-semibold">
                {{ getDocStatusText(previewDoc) }}
              </span>
              <span v-if="previewDoc.signers" class="text-xs text-slate-500">
                {{ previewDoc.signers.filter(s => s.status === 'signed').length }}/{{ previewDoc.signers.length }} sudah tanda tangan
              </span>
            </div>
          </div>
          <button class="text-slate-400 hover:text-slate-600 text-2xl" @click="closePreview">✕</button>
        </div>
        
        <!-- PDF Viewer -->
        <div class="flex-1 bg-slate-100 overflow-auto">
          <div v-if="previewLoading" class="flex items-center justify-center h-full">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent"></div>
            <span class="ml-3 text-slate-500">Memuat dokumen...</span>
          </div>
          <iframe 
            v-else-if="previewUrl" 
            :src="previewUrl" 
            class="w-full h-full border-0"
          ></iframe>
          <div v-else class="flex items-center justify-center h-full text-slate-400">
            <p>Tidak dapat memuat preview dokumen</p>
          </div>
        </div>
        
        <!-- Footer Actions -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-slate-200 bg-slate-50">
          <div class="text-sm text-slate-500">
            <span v-if="previewDoc.signers">
              Signer: 
              <span v-for="(s, idx) in previewDoc.signers" :key="idx" class="inline-flex items-center">
                <span :class="s.status === 'signed' ? 'text-emerald-600' : 'text-slate-600'">
                  {{ s.name || s.email?.split('@')[0] || 'Signer' }}
                  <span v-if="s.status === 'signed'">✓</span>
                </span>
                <span v-if="idx < previewDoc.signers.length - 1" class="mx-1">→</span>
              </span>
            </span>
          </div>
          <div class="flex gap-2">
            <button @click="closePreview" class="rounded-full border border-slate-200 bg-white px-5 py-2 text-sm font-semibold text-slate-600">
              Tutup
            </button>
            <button 
              v-if="previewReadyToSign" 
              @click="confirmSignFromPreview" 
              :disabled="signing"
              class="rounded-full bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {{ signing ? 'Memproses...' : '✍️ Tanda Tangan Sekarang' }}
            </button>
            <button 
              v-if="previewDoc.status === 'completed' || activeInboxTab === 'completed'" 
              @click="downloadDocument(previewDoc); closePreview()" 
              class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
            >
              ⬇️ Download
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->
    <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="showUploadModal = false">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Upload Dokumen Baru</h3>
          <button class="text-slate-400 hover:text-slate-600" @click="showUploadModal = false">✕</button>
        </div>
        <form @submit.prevent="uploadDocument" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Judul Dokumen</label>
            <input v-model="uploadForm.title" type="text" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Masukkan judul dokumen" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">File PDF</label>
            <input type="file" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" accept=".pdf" @change="handleFileSelect" required />
          </div>
          <div v-if="uploadError" class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ uploadError }}</div>
          <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700" :disabled="uploading">
            {{ uploading ? 'Mengupload...' : 'Upload & Lanjutkan' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Assign Signers Modal -->
    <div v-if="assignModalDoc" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Atur Urutan Penandatangan</h3>
          <button class="text-slate-400 hover:text-slate-600" @click="assignModalDoc = null">✕</button>
        </div>
        <p class="text-sm text-slate-500 mb-4">Urutkan signer dari pertama hingga terakhir. Drag untuk mengubah urutan.</p>
        
        <div class="space-y-2 mb-4">
          <div
            v-for="(signer, idx) in signerList"
            :key="idx"
            class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2"
            draggable="true"
            @dragstart="onDragStart(idx)"
            @dragover.prevent
            @drop="onDrop(idx)"
          >
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">{{ idx + 1 }}</span>
            <select v-model="signer.email" class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
              <option value="" disabled>Pilih user...</option>
              <option v-for="u in tenantUsers" :key="u.id" :value="u.email">{{ u.name }} ({{ u.email }})</option>
            </select>
            <button @click="removeSignerRow(idx)" class="text-rose-500 hover:text-rose-700">✕</button>
          </div>
        </div>

        <button @click="addSignerRow" class="text-sm text-blue-600 font-semibold hover:underline mb-4">+ Tambah Signer</button>

        <div v-if="assignError" class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700 mb-4">{{ assignError }}</div>

        <div class="flex justify-end gap-2">
          <button @click="assignModalDoc = null" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">Batal</button>
          <button @click="assignSigners" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white" :disabled="assigning">
            {{ assigning ? 'Menyimpan...' : 'Simpan & Kirim' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add User Modal -->
    <div v-if="showAddUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="showAddUserModal = false">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Tambah User Baru</h3>
          <button class="text-slate-400 hover:text-slate-600" @click="showAddUserModal = false">✕</button>
        </div>
        <form @submit.prevent="createUser" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nama</label>
            <input v-model="userForm.name" type="text" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" placeholder="Nama lengkap" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input v-model="userForm.email" type="email" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" placeholder="email@example.com" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <input v-model="userForm.password" type="password" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm" placeholder="Min 8 karakter" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
            <select v-model="userForm.role" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm">
              <option value="member">Member</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div v-if="userError" class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">{{ userError }}</div>
          <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">
            Tambah User
          </button>
        </form>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="confirmModal.show" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/60 p-4" @click.self="closeConfirmModal">
      <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl animate-in zoom-in-95">
        <div class="text-center">
          <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full" :class="confirmModal.iconBg || 'bg-rose-100'">
            <span class="text-2xl">{{ confirmModal.icon || '⚠️' }}</span>
          </div>
          <h3 class="text-lg font-semibold text-slate-800">{{ confirmModal.title || 'Konfirmasi' }}</h3>
          <p class="mt-2 text-sm text-slate-500">{{ confirmModal.message || 'Apakah Anda yakin?' }}</p>
        </div>
        <div class="mt-6 flex gap-3">
          <button 
            @click="closeConfirmModal" 
            class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          >
            Batal
          </button>
          <button 
            @click="executeConfirmAction" 
            class="flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold text-white"
            :class="confirmModal.confirmBg || 'bg-rose-600 hover:bg-rose-700'"
          >
            {{ confirmModal.confirmText || 'Ya, Lanjutkan' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Success Toast -->
    <div v-if="successMessage" class="fixed bottom-6 right-6 z-50 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-lg animate-in slide-in-from-bottom-5 fade-in duration-300">
      ✓ {{ successMessage }}
    </div>

    <!-- Error Toast -->
    <div v-if="errorMessage" class="fixed bottom-6 right-6 z-50 rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-lg animate-in slide-in-from-bottom-5 fade-in duration-300">
      ✕ {{ errorMessage }}
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, defineProps } from 'vue';

const props = defineProps({ tenant: Object });

// State
const user = ref(null);
const currentTenant = ref(null);
const loading = ref(false);

const successMessage = ref('');
const errorMessage = ref('');

const showSuccess = (msg) => {
  successMessage.value = msg;
  setTimeout(() => successMessage.value = '', 3000);
};

const showError = (msg) => {
  errorMessage.value = msg;
  setTimeout(() => errorMessage.value = '', 5000);
};

// Tabs
const activeMainTab = ref('inbox');
const activeInboxTab = ref('need_signature');

// Data
const inbox = reactive({ needSignature: [], upcoming: [], waiting: [], completed: [] });
const myDocuments = ref([]);
const tenantUsers = ref([]);
const totalInboxCount = computed(() => inbox.needSignature.length + inbox.waiting.length);

// Modals
const showUploadModal = ref(false);
const showAddUserModal = ref(false);
const assignModalDoc = ref(null);

// Preview
const previewDoc = ref(null);
const previewUrl = ref('');
const previewLoading = ref(false);
const previewReadyToSign = ref(false);

// Forms
const uploadFile = ref(null);
const uploadForm = reactive({ title: '' });
const uploading = ref(false);
const uploadError = ref('');

const signerList = ref([{ email: '' }]);
const assigning = ref(false);
const assignError = ref('');
const dragIndex = ref(null);

const userForm = reactive({ name: '', email: '', password: '', role: 'member' });
const userError = ref('');

const signing = ref(false);
const signError = ref('');

// Confirmation Modal
const confirmModal = reactive({
  show: false,
  icon: '⚠️',
  iconBg: 'bg-rose-100',
  title: '',
  message: '',
  confirmText: 'Ya, Lanjutkan',
  confirmBg: 'bg-rose-600 hover:bg-rose-700',
  action: null,
  data: null,
});

// Computed
const isAdmin = computed(() => {
  const role = user.value?.role;
  return role === 'super_admin' || role === 'admin' || role === 'owner';
});

// Lifecycle
onMounted(() => {
  init();
});

// Methods
const init = async () => {
  const storedTenant = localStorage.getItem('currentTenant');
  const storedUser = localStorage.getItem('user');
  if (storedTenant) currentTenant.value = JSON.parse(storedTenant);
  if (storedUser) user.value = JSON.parse(storedUser);
  if (props.tenant) currentTenant.value = props.tenant;

  // Always fetch fresh user profile to ensure role is correct
  await fetchUserProfile();
  await fetchAllData();
};

const fetchUserProfile = async () => {
  try {
    const res = await fetch(`/${getSlug()}/api/auth/me`, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    if (res.ok) {
      const data = await res.json();
      // Update user with fresh data including role from membership
      user.value = {
        global_id: data.profile?.userId,
        name: data.profile?.name,
        email: data.profile?.email,
        role: data.membership?.role || 'member',
        is_owner: data.membership?.isOwner || false,
      };
      // Save to localStorage for persistence
      localStorage.setItem('user', JSON.stringify(user.value));
    }
  } catch (err) {
    console.error('Failed to fetch user profile:', err);
  }
};

const getToken = () => localStorage.getItem('tenantToken');
const getSlug = () => currentTenant.value?.slug || window.location.pathname.split('/')[1];

const fetchAllData = async () => {
  loading.value = true;
  await Promise.all([fetchInbox(), fetchDocuments(), fetchUsers()]);
  loading.value = false;
};

const fetchInbox = async () => {
  try {
    const res = await fetch(`/${getSlug()}/api/documents/inbox`, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    if (res.ok) {
      const data = await res.json();
      inbox.needSignature = data.needSignature || [];
      inbox.upcoming = data.upcoming || [];
      inbox.waiting = data.waiting || [];
      inbox.completed = data.completed || [];
    }
  } catch (err) {
    console.error(err);
  }
};

const fetchDocuments = async () => {
  if (!isAdmin.value) return;
  try {
    const res = await fetch(`/${getSlug()}/api/documents`, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    if (res.ok) {
      const data = await res.json();
      myDocuments.value = data.data || [];
    }
  } catch (err) {
    console.error(err);
  }
};

const fetchUsers = async () => {
  if (!isAdmin.value) return;
  try {
    const res = await fetch(`/${getSlug()}/api/admin/users`, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    if (res.ok) {
      const data = await res.json();
      tenantUsers.value = data.users || [];
    }
  } catch (err) {
    console.error(err);
  }
};

const getCurrentInboxList = () => {
  if (activeInboxTab.value === 'need_signature') return inbox.needSignature;
  if (activeInboxTab.value === 'upcoming') return inbox.upcoming;
  if (activeInboxTab.value === 'waiting') return inbox.waiting;
  return inbox.completed;
};

// Preview
const openPreview = async (doc, readyToSign = false) => {
  previewDoc.value = doc;
  previewReadyToSign.value = readyToSign;
  previewLoading.value = true;
  previewUrl.value = '';
  
  try {
    const docId = doc.documentId || doc.id;
    const res = await fetch(`/${getSlug()}/api/documents/${docId}/versions/latest:download`, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });
    
    if (res.ok) {
      const blob = await res.blob();
      previewUrl.value = URL.createObjectURL(blob);
    }
  } catch (err) {
    console.error('Preview error:', err);
  } finally {
    previewLoading.value = false;
  }
};

const closePreview = () => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
  }
  previewDoc.value = null;
  previewUrl.value = '';
  previewReadyToSign.value = false;
};

const confirmSignFromPreview = () => {
  if (!previewDoc.value) return;
  
  openConfirmModal({
    icon: '✍️',
    iconBg: 'bg-blue-100',
    title: 'Tanda Tangani Dokumen',
    message: `Saya menyatakan bahwa saya telah membaca dan menyetujui dokumen ini. Lanjutkan tanda tangan?`,
    confirmText: 'Sah & Tanda Tangan',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      signing.value = true;
      try {
        const res = await fetch(`/${getSlug()}/api/documents/${previewDoc.value.documentId}/sign`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ consent: true }),
        });
        
        if (!res.ok) throw new Error('Gagal menandatangani');
        
        closePreview();
        await fetchInbox();
        showSuccess('Dokumen berhasil ditandatangani! Cek tab "Menunggu" atau "Selesai".');
        activeInboxTab.value = 'waiting';
      } catch (err) {
        showError('Gagal: ' + err.message);
        console.error(err);
      } finally {
        signing.value = false;
      }
    }
  });
};


// File Upload
const handleFileSelect = (e) => {
  uploadFile.value = e.target.files[0];
};

const uploadDocument = () => {
  openConfirmModal({
    icon: '📤',
    iconBg: 'bg-blue-100',
    title: 'Upload Dokumen',
    message: `Upload dokumen "${uploadForm.title || uploadFile.value?.name}"?`,
    confirmText: 'Upload Sekarang',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      uploading.value = true;
      uploadError.value = '';
      try {
        const fd = new FormData();
        fd.append('file', uploadFile.value);
        if (uploadForm.title) fd.append('title', uploadForm.title);

        const res = await fetch(`/${getSlug()}/api/documents/drafts`, {
          method: 'POST',
          body: fd,
          headers: { Authorization: `Bearer ${getToken()}` },
        });

        if (!res.ok) throw new Error('Upload gagal');
        const data = await res.json();

        showUploadModal.value = false;
        uploadFile.value = null;
        uploadForm.title = '';
        fetchDocuments();

        openAssignModal(data.document);
        showSuccess('Dokumen diupload. Silakan atur signer.');
      } catch (e) {
        uploadError.value = e.message;
      } finally {
        uploading.value = false;
      }
    }
  });
};

// Assign Signers
const openAssignModal = (doc) => {
  assignModalDoc.value = doc;
  signerList.value = [{ email: '' }];
  assignError.value = '';
};

const addSignerRow = () => signerList.value.push({ email: '' });
const removeSignerRow = (i) => signerList.value.splice(i, 1);

const onDragStart = (idx) => {
  dragIndex.value = idx;
};

const onDrop = (idx) => {
  if (dragIndex.value === null || dragIndex.value === idx) return;
  const item = signerList.value.splice(dragIndex.value, 1)[0];
  signerList.value.splice(idx, 0, item);
  dragIndex.value = null;
};

const assignSigners = () => {
  const signersCount = signerList.value.filter((s) => s.email).length;
  if (signersCount === 0) {
    assignError.value = 'Minimal 1 signer wajib diisi';
    return;
  }

  openConfirmModal({
    icon: '📝',
    iconBg: 'bg-blue-100',
    title: 'Simpan & Kirim Workflow',
    message: `Kirim dokumen ke ${signersCount} signer sesuai urutan?`,
    confirmText: 'Kirim Dokumen',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      assigning.value = true;
      assignError.value = '';
      try {
        const signers = signerList.value.filter((s) => s.email).map((s) => ({
          user: s.email,
          role: 'signer',
        }));

        const docId = assignModalDoc.value.id || assignModalDoc.value.documentId;
        const res = await fetch(`/${getSlug()}/api/documents/${docId}/signers`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ signers }),
        });

        if (!res.ok) throw new Error('Gagal assign signers');

        assignModalDoc.value = null;
        fetchDocuments();
        fetchInbox();
        showSuccess('Workflow dimulai! Signer pertama akan menerima notifikasi.');
      } catch (e) {
        assignError.value = e.message;
      } finally {
        assigning.value = false;
      }
    }
  });
};


// User Management
const createUser = () => {
  openConfirmModal({
    icon: '👤',
    iconBg: 'bg-blue-100',
    title: 'Tambah User Baru',
    message: `Tambahkan user "${userForm.name}" (${userForm.email}) sebagai ${userForm.role}?`,
    confirmText: 'Tambah User',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      userError.value = '';
      try {
        const res = await fetch(`/${getSlug()}/api/admin/users`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(userForm),
        });
        if (!res.ok) {
          const errData = await res.json();
          throw new Error(errData.message || 'Gagal tambah user');
        }
        
        // Reset form
        userForm.name = '';
        userForm.email = '';
        userForm.password = '';
        userForm.role = 'member';
        
        showAddUserModal.value = false;
        fetchUsers();
        showSuccess('User berhasil ditambahkan!');
      } catch (e) {
        userError.value = e.message;
      }
    }
  });
};

const removeUser = (u) => {
  openConfirmModal({
    icon: '👤',
    iconBg: 'bg-rose-100',
    title: 'Hapus User',
    message: `Apakah Anda yakin ingin menghapus user "${u.name}" dari workspace ini?`,
    confirmText: 'Hapus User',
    confirmBg: 'bg-rose-600 hover:bg-rose-700',
    action: async () => {
      try {
        const response = await fetch(`/${getSlug()}/api/admin/users/${u.global_id}`, {
          method: 'DELETE',
          headers: { Authorization: `Bearer ${getToken()}` },
        });
        
        if (!response.ok) throw new Error('Gagal menghapus user');
        
        fetchUsers();
        showSuccess('User berhasil dihapus.');
      } catch (e) {
        alert('Gagal menghapus user.');
      }
    }
  });
};

// Download
const downloadDocument = async (doc) => {
  try {
    const docId = doc.documentId || doc.id;
    const url = `/${getSlug()}/api/documents/${docId}/versions/latest:download`;

    const res = await fetch(url, {
      headers: { Authorization: `Bearer ${getToken()}` },
    });

    if (!res.ok) throw new Error('Download gagal');

    const blob = await res.blob();
    const filename = doc.title || `document_${docId}.pdf`;

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;
    link.click();
    URL.revokeObjectURL(link.href);
  } catch (e) {
    console.error('Download error:', e);
    alert('Gagal download dokumen');
  }
};

// Delete
// Delete
const deleteDocument = (doc) => {
  openConfirmModal({
    icon: '🗑️',
    iconBg: 'bg-rose-100',
    title: 'Hapus Dokumen',
    message: `Apakah Anda yakin ingin menghapus dokumen "${doc.title || doc.id}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmText: 'Hapus',
    confirmBg: 'bg-rose-600 hover:bg-rose-700',
    action: async () => {
      try {
        const response = await fetch(`/${getSlug()}/api/documents/${doc.id}`, {
          method: 'DELETE',
          headers: { Authorization: `Bearer ${getToken()}` },
        });
        
        if (!response.ok) throw new Error('Gagal menghapus');
        
        fetchDocuments();
        fetchInbox();
        showSuccess('Dokumen berhasil dihapus.');
      } catch (e) {
        alert('Gagal menghapus dokumen.');
      }
    }
  });
};

// Helpers
const showSuccess = (message) => {
  successMessage.value = message;
  setTimeout(() => (successMessage.value = ''), 4000);
};

const getStatusBadge = (status) => {
  if (status === 'draft') return 'bg-slate-100 text-slate-600';
  if (status === 'need_signature') return 'bg-blue-100 text-blue-700';
  if (status === 'completed') return 'bg-emerald-100 text-emerald-700';
  return 'bg-amber-100 text-amber-700';
};

const getStatusLabel = (status) => {
  const labels = {
    'draft': 'Draft',
    'need_signature': 'Perlu TTD',
    'waiting': 'Menunggu',
    'completed': 'Selesai'
  };
  return labels[status] || status;
};

const getRoleLabel = (role) => {
  const labels = {
    'super_admin': 'Super Admin',
    'admin': 'Administrator',
    'owner': 'Owner',
    'member': 'Member',
  };
  return labels[role] || role || 'User';
};

const getDocStatusBadge = (doc) => {
  if (activeInboxTab.value === 'need_signature') return 'bg-rose-500 text-white';
  if (activeInboxTab.value === 'upcoming') return 'bg-blue-500 text-white';
  if (activeInboxTab.value === 'waiting') return 'bg-amber-500 text-white';
  if (activeInboxTab.value === 'completed' || doc.status === 'completed') return 'bg-emerald-500 text-white';
  return 'bg-slate-100 text-slate-600';
};

const getDocStatusText = (doc) => {
  if (activeInboxTab.value === 'need_signature') return '🖊️ Giliran Anda';
  if (activeInboxTab.value === 'upcoming') return '⏳ Belum Giliran';
  if (activeInboxTab.value === 'waiting') return '✅ Sudah Anda TTD';
  if (activeInboxTab.value === 'completed' || doc.status === 'completed') return '🎉 Selesai';
  return doc.status;
};

const formatDate = (dateStr) => (dateStr ? new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-');


const openConfirmModal = (options) => {
  confirmModal.icon = options.icon || '⚠️';
  confirmModal.iconBg = options.iconBg || 'bg-rose-100';
  confirmModal.title = options.title || 'Konfirmasi';
  confirmModal.message = options.message || 'Apakah Anda yakin?';
  confirmModal.confirmText = options.confirmText || 'Ya, Lanjutkan';
  confirmModal.confirmBg = options.confirmBg || 'bg-rose-600 hover:bg-rose-700';
  confirmModal.action = options.action;
  confirmModal.data = options.data;
  confirmModal.show = true;
};

const closeConfirmModal = () => {
  confirmModal.show = false;
  setTimeout(() => {
    confirmModal.action = null;
    confirmModal.data = null;
  }, 300);
};

const executeConfirmAction = () => {
  if (confirmModal.action) {
    confirmModal.action(confirmModal.data);
  }
  closeConfirmModal();
};

const logout = () => {
  openConfirmModal({
    icon: '👋',
    iconBg: 'bg-slate-100',
    title: 'Logout',
    message: 'Apakah Anda yakin ingin keluar dari aplikasi?',
    confirmText: 'Logout',
    confirmBg: 'bg-slate-800 hover:bg-slate-900',
    action: () => {
      localStorage.clear();
      window.location.href = '/login';
    }
  });
};
</script>
