<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.12),_transparent_40%),linear-gradient(180deg,#f8fafc_0%,#ffffff_70%)]"></div>

    <nav class="sticky top-4 z-30 mx-auto max-w-6xl px-4">
      <div class="flex items-center justify-between rounded-2xl border border-white/60 bg-white/70 px-3 py-2 sm:px-4 sm:py-3 shadow-sm backdrop-blur">
        <div class="flex items-center gap-2 sm:gap-3">
          <span class="flex h-8 w-8 sm:h-9 sm:w-9 items-center justify-center rounded-2xl bg-blue-600 text-white text-sm sm:text-base">E</span>
          <div>
            <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-400">Superadmin</p>
            <p class="text-xs sm:text-sm font-semibold">E-Signer</p>
          </div>
        </div>
        <div class="flex items-center gap-2 sm:gap-3">
          <span class="hidden sm:inline-flex rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-600">God Mode</span>
          <span class="sm:hidden rounded-full bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-600">🔥</span>
          <button class="rounded-full border border-slate-200 bg-white px-2 py-1.5 sm:px-4 sm:py-2 text-xs font-semibold text-slate-600 hover:border-slate-300" @click="logout">
            <span class="hidden sm:inline">Logout</span>
            <span class="sm:hidden">↩</span>
          </button>
        </div>
      </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 pb-16 pt-6 sm:pt-8">
      <div class="flex flex-wrap items-center justify-between gap-3 sm:gap-4">
        <div>
          <h1 class="text-xl sm:text-3xl font-semibold">Superadmin Dashboard</h1>
          <p class="text-xs sm:text-sm text-slate-500">Monitor tenants, users, and system health.</p>
        </div>
        <button
          class="inline-flex items-center rounded-full bg-blue-600 px-4 py-2 sm:px-5 sm:py-3 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700"
          @click="showCreateTenantModal = true"
        >
          + Create Tenant
        </button>
      </div>

      <!-- Mobile Navigation Tabs -->
      <div class="mt-4 lg:hidden overflow-x-auto -mx-4 px-4">
        <div class="flex gap-2 pb-2">
          <button
            class="flex-shrink-0 rounded-full px-4 py-2 text-xs font-semibold transition whitespace-nowrap"
            :class="activeTab === 'tenants' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
            @click="activeTab = 'tenants'"
          >
            🏢 Tenants ({{ stats.totalTenants }})
          </button>
          <button
            class="flex-shrink-0 rounded-full px-4 py-2 text-xs font-semibold transition whitespace-nowrap"
            :class="activeTab === 'users' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
            @click="activeTab = 'users'"
          >
            👥 Global Users
          </button>
          <button
            class="flex-shrink-0 rounded-full px-4 py-2 text-xs font-semibold transition whitespace-nowrap"
            :class="activeTab === 'system' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 border border-slate-200'"
            @click="activeTab = 'system'"
          >
            ⚙️ System
          </button>
        </div>
      </div>

      <div class="mt-4 lg:mt-8 grid grid-cols-12 gap-4">
        <!-- Desktop Sidebar (hidden on mobile) -->
        <div class="hidden lg:block col-span-12 lg:col-span-3">
          <div class="rounded-3xl border border-white/70 bg-white/80 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Navigation</p>
            <div class="mt-4 space-y-2">
              <button
                class="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'tenants' ? 'bg-blue-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = 'tenants'"
              >
                Tenants
                <span class="text-xs">{{ stats.totalTenants }}</span>
              </button>
              <button
                class="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'users' ? 'bg-blue-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = 'users'"
              >
                Global Users
                <span class="text-xs">All</span>
              </button>
              <button
                class="flex w-full items-center justify-between rounded-2xl px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === 'system' ? 'bg-blue-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = 'system'"
              >
                System Health
                <span class="text-xs">Live</span>
              </button>
            </div>
          </div>
        </div>

        <div class="col-span-12 lg:col-span-9">
          <div class="grid gap-3 sm:gap-4 grid-cols-3">
            <div class="rounded-2xl sm:rounded-3xl border border-white/70 bg-white/80 p-3 sm:p-5 shadow-sm">
              <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-400">Total</p>
              <p class="mt-1 sm:mt-3 text-xl sm:text-3xl font-semibold">{{ stats.totalTenants }}</p>
              <div class="mt-2 sm:mt-3 h-1.5 sm:h-2 rounded-full bg-blue-100">
                <div class="h-1.5 sm:h-2 rounded-full bg-blue-600" :style="{ width: `${Math.min(stats.totalTenants * 10, 100)}%` }"></div>
              </div>
            </div>
            <div class="rounded-2xl sm:rounded-3xl border border-white/70 bg-white/80 p-3 sm:p-5 shadow-sm">
              <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-400">Active</p>
              <p class="mt-1 sm:mt-3 text-xl sm:text-3xl font-semibold text-emerald-600">{{ stats.activeTenants }}</p>
              <div class="mt-2 sm:mt-3 h-1.5 sm:h-2 rounded-full bg-emerald-100">
                <div class="h-1.5 sm:h-2 rounded-full bg-emerald-500" :style="{ width: `${Math.min(stats.activeTenants * 10, 100)}%` }"></div>
              </div>
            </div>
            <div class="rounded-2xl sm:rounded-3xl border border-white/70 bg-white/80 p-3 sm:p-5 shadow-sm">
              <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-400">Suspended</p>
              <p class="mt-1 sm:mt-3 text-xl sm:text-3xl font-semibold text-amber-600">{{ stats.suspendedTenants }}</p>
              <div class="mt-2 sm:mt-3 h-1.5 sm:h-2 rounded-full bg-amber-100">
                <div class="h-1.5 sm:h-2 rounded-full bg-amber-500" :style="{ width: `${Math.min(stats.suspendedTenants * 10, 100)}%` }"></div>
              </div>
            </div>
          </div>

          <div v-if="loading" class="mt-6 rounded-3xl border border-white/70 bg-white/80 p-6 text-sm text-slate-500">
            Loading data...
          </div>

          <div v-if="error" class="mt-6 rounded-3xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
            {{ error }}
            <button class="ml-2 text-xs font-semibold underline" @click="fetchTenants">Retry</button>
          </div>

          <div v-if="!loading && activeTab === 'tenants'" class="mt-6 rounded-3xl border border-white/70 bg-white/80 p-4 md:p-6 shadow-sm">
            <div v-if="tenants.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white/60 p-6 md:p-10 text-center text-sm text-slate-500">
              No tenants yet. Create your first workspace.
            </div>

            <!-- Desktop Table (hidden on mobile) -->
            <div v-else class="hidden md:block overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-slate-400">
                  <tr>
                    <th class="pb-3">Tenant</th>
                    <th class="pb-3">Slug</th>
                    <th class="pb-3">Status</th>
                    <th class="pb-3">Created</th>
                    <th class="pb-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="tenant in tenants" :key="tenant.id">
                    <td class="py-3 font-semibold">{{ tenant.name }}</td>
                    <td class="py-3 text-xs text-slate-500">{{ tenant.slug }}</td>
                    <td class="py-3">
                      <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="getStatusBadge(tenant.status)">{{ tenant.status }}</span>
                    </td>
                    <td class="py-3 text-xs text-slate-500">{{ formatDate(tenant.createdAt) }}</td>
                    <td class="py-3 text-right">
                      <div class="flex flex-wrap justify-end gap-2">
                        <button class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 hover:border-slate-300" @click="editTenant(tenant)">
                          Edit
                        </button>
                        <button class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 hover:border-slate-300" @click="showAddUserModal(tenant)">
                          + User
                        </button>
                        <button
                          v-if="tenant.status === 'active'"
                          class="rounded-full bg-amber-500 px-3 py-1 text-xs font-semibold text-white"
                          @click="suspendTenant(tenant)"
                        >
                          Suspend
                        </button>
                        <button
                          v-else-if="tenant.status === 'suspended'"
                          class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white"
                          @click="activateTenant(tenant)"
                        >
                          Activate
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <!-- Mobile Cards -->
            <div v-if="tenants.length > 0" class="space-y-3 md:hidden">
              <div 
                v-for="tenant in tenants" 
                :key="tenant.id" 
                class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 truncate">{{ tenant.name }}</p>
                    <p class="text-xs text-slate-500">{{ tenant.slug }}</p>
                  </div>
                  <span class="flex-shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold" :class="getStatusBadge(tenant.status)">
                    {{ tenant.status }}
                  </span>
                </div>
                <p class="mt-2 text-xs text-slate-400">Created: {{ formatDate(tenant.createdAt) }}</p>
                <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                  <button class="flex-1 rounded-full bg-blue-600 px-3 py-2 text-xs font-semibold text-white" @click="editTenant(tenant)">
                    ✏️ Edit
                  </button>
                  <button class="flex-1 rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600" @click="showAddUserModal(tenant)">
                    + User
                  </button>
                  <button
                    v-if="tenant.status === 'active'"
                    class="rounded-full bg-amber-500 px-3 py-2 text-xs font-semibold text-white"
                    @click="suspendTenant(tenant)"
                  >
                    ⏸️
                  </button>
                  <button
                    v-else-if="tenant.status === 'suspended'"
                    class="rounded-full bg-emerald-600 px-3 py-2 text-xs font-semibold text-white"
                    @click="activateTenant(tenant)"
                  >
                    ▶️
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!loading && activeTab === 'users'" class="mt-6">
            <div class="rounded-3xl border border-white/70 bg-white/80 p-4 md:p-6 shadow-sm">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Global Users</p>
                <input 
                  v-model="userSearch" 
                  type="text" 
                  placeholder="Search users..." 
                  class="w-full sm:w-64 rounded-full border border-slate-200 px-4 py-2 text-sm"
                  @input="debounceSearchUsers"
                />
              </div>
              
              <div v-if="loadingUsers" class="mt-4 text-sm text-slate-500">Loading users...</div>
              
              <!-- Desktop Table (hidden on mobile) -->
              <div v-else class="hidden md:block overflow-x-auto">
                <table class="mt-4 w-full text-sm">
                  <thead>
                    <tr class="border-b text-left text-xs font-semibold uppercase text-slate-400">
                      <th class="py-3">Name</th>
                      <th class="py-3">Email</th>
                      <th class="py-3">Status</th>
                      <th class="py-3">Tenants</th>
                      <th class="py-3 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="user in globalUsers" :key="user.global_id" class="border-b border-slate-100">
                      <td class="py-3 font-medium">{{ user.name }}</td>
                      <td class="py-3 text-slate-500">{{ user.email }}</td>
                      <td class="py-3">
                        <span v-if="user.is_superadmin" class="rounded-full bg-rose-100 px-2 py-1 text-xs text-rose-600">Superadmin</span>
                        <span v-else class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">User</span>
                      </td>
                      <td class="py-3">
                        <span v-if="user.tenants?.length" class="text-xs text-slate-500">
                          {{ user.tenants.map(t => t.tenant_name).join(', ').substring(0, 30) }}{{ user.tenants.length > 1 ? '...' : '' }}
                        </span>
                        <span v-else class="text-xs text-slate-400">No tenants</span>
                      </td>
                      <td class="py-3 text-right">
                        <button class="mr-2 text-xs text-blue-600 hover:underline" @click="openEditUser(user)">Edit</button>
                        <button class="text-xs text-rose-600 hover:underline" @click="confirmDeleteUser(user)">Delete</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <!-- Mobile Cards (visible on mobile only) -->
              <div v-if="!loadingUsers" class="mt-4 space-y-3 md:hidden">
                <div 
                  v-for="user in globalUsers" 
                  :key="user.global_id" 
                  class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                      <p class="font-semibold text-slate-800 truncate">{{ user.name }}</p>
                      <p class="text-xs text-slate-500 truncate">{{ user.email }}</p>
                    </div>
                    <span v-if="user.is_superadmin" class="flex-shrink-0 rounded-full bg-rose-100 px-2 py-1 text-xs text-rose-600">SA</span>
                    <span v-else class="flex-shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">User</span>
                  </div>
                  <div v-if="user.tenants?.length" class="mt-2">
                    <p class="text-xs text-slate-400">Tenants:</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                      <span 
                        v-for="t in user.tenants.slice(0, 3)" 
                        :key="t.tenant_id" 
                        class="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-600"
                      >
                        {{ t.tenant_name }}
                      </span>
                      <span v-if="user.tenants.length > 3" class="text-xs text-slate-400">+{{ user.tenants.length - 3 }} more</span>
                    </div>
                  </div>
                  <div class="mt-3 flex gap-3 border-t border-slate-100 pt-3">
                    <button class="flex-1 rounded-full bg-blue-600 px-3 py-2 text-xs font-semibold text-white" @click="openEditUser(user)">
                      ✏️ Edit
                    </button>
                    <button class="flex-1 rounded-full border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600" @click="confirmDeleteUser(user)">
                      🗑️ Delete
                    </button>
                  </div>
                </div>
              </div>
              
              <p v-if="!loadingUsers && globalUsers.length === 0" class="mt-4 text-sm text-slate-500">No users found.</p>
            </div>
          </div>

          <div v-if="!loading && activeTab === 'system'" class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">System Health</p>
              <p class="mt-3 text-sm text-slate-500">🚧 Monitor CPU, memory, dan queue akan ditampilkan di sini.</p>
            </div>
            <div class="rounded-3xl border border-white/70 bg-white/80 p-6 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Audit Logs</p>
              <p class="mt-3 text-sm text-slate-500">🚧 Log aktivitas admin akan ditampilkan di sini.</p>
            </div>
          </div>
        </div>
      </div>
    </main>

    <div v-if="showCreateTenantModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @click.self="showCreateTenantModal = false">
      <div class="w-full max-w-lg rounded-3xl border border-white/70 bg-white/90 p-6 shadow-2xl">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">New Tenant</p>
            <h3 class="mt-2 text-lg font-semibold">Create a workspace</h3>
          </div>
          <button class="text-sm text-slate-400 hover:text-slate-600" @click="showCreateTenantModal = false">Close</button>
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="createTenant">
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tenant name *</label>
            <input v-model="createForm.tenantName" type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" required />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug</label>
            <input v-model="createForm.tenantSlug" type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" />
          </div>
          <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Primary Admin</p>
            <div class="mt-3 space-y-3">
              <input v-model="createForm.name" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Name" required />
              <input v-model="createForm.email" type="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Email" required />
              <input v-model="createForm.password" type="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Password" required />
              <input v-model="createForm.password_confirmation" type="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Confirm Password" required />
              <input v-model="createForm.role" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Role" />
            </div>
          </div>

          <div v-if="createError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ createError }}
          </div>

          <button class="inline-flex w-full items-center justify-center rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white" :disabled="creating">
            <span v-if="creating" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
            {{ creating ? 'Creating...' : 'Create Tenant' }}
          </button>
        </form>
      </div>
    </div>

    <div v-if="editingTenant" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @click.self="editingTenant = null">
      <div class="w-full max-w-md rounded-3xl border border-white/70 bg-white/90 p-6 shadow-2xl">
        <div class="flex items-start justify-between">
          <h3 class="text-lg font-semibold">Edit tenant</h3>
          <button class="text-sm text-slate-400 hover:text-slate-600" @click="editingTenant = null">Close</button>
        </div>
        <form class="mt-6 space-y-4" @submit.prevent="updateTenant">
          <input v-model="editForm.name" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Tenant name" />
          <input v-model="editForm.slug" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Slug" />
          <select v-model="editForm.status" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm">
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </select>
          <div v-if="editError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ editError }}</div>
          <button class="inline-flex w-full items-center justify-center rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white" :disabled="updating">
            {{ updating ? 'Updating...' : 'Update Tenant' }}
          </button>
        </form>
      </div>
    </div>

    <div v-if="addUserTenant" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @click.self="addUserTenant = null">
      <div class="w-full max-w-lg rounded-3xl border border-white/70 bg-white/90 p-6 shadow-2xl">
        <div class="flex items-start justify-between">
          <h3 class="text-lg font-semibold">Add user to {{ addUserTenant.name }}</h3>
          <button class="text-sm text-slate-400 hover:text-slate-600" @click="addUserTenant = null">Close</button>
        </div>

        <div class="mt-4 flex gap-2">
          <button
            class="rounded-full px-4 py-2 text-xs font-semibold"
            :class="addUserMode === 'new' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
            @click="addUserMode = 'new'"
          >
            New User
          </button>
          <button
            class="rounded-full px-4 py-2 text-xs font-semibold"
            :class="addUserMode === 'existing' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
            @click="addUserMode = 'existing'"
          >
            Existing User
          </button>
        </div>

        <form v-if="addUserMode === 'new'" class="mt-6 space-y-3" @submit.prevent="createUserForTenant">
          <input v-model="userForm.name" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Name" required />
          <input v-model="userForm.email" type="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Email" required />
          <input v-model="userForm.password" type="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Password" required />
          <input v-model="userForm.password_confirmation" type="password" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Confirm Password" required />
          <input v-model="userForm.role" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Role" />
          <div v-if="userError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ userError }}</div>
          <button class="inline-flex w-full items-center justify-center rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white" :disabled="addingUser">
            {{ addingUser ? 'Saving...' : 'Create User' }}
          </button>
        </form>

        <form v-if="addUserMode === 'existing'" class="mt-6 space-y-3" @submit.prevent="assignUserToTenant">
          <input v-model="assignForm.user" type="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Email" required />
          <input v-model="assignForm.role" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="Role" />
          <div v-if="userError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ userError }}</div>
          <button class="inline-flex w-full items-center justify-center rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white" :disabled="addingUser">
            {{ addingUser ? 'Assigning...' : 'Assign User' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div v-if="editingUser" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" @click.self="editingUser = null">
      <div class="w-full max-w-lg rounded-3xl border border-white/70 bg-white/90 p-6 shadow-2xl">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Edit User</p>
            <h3 class="mt-2 text-lg font-semibold">{{ editingUser.name }}</h3>
          </div>
          <button class="text-sm text-slate-400 hover:text-slate-600" @click="editingUser = null">Close</button>
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="updateUser">
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Name</label>
            <input v-model="editUserForm.name" type="text" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" required />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
            <input v-model="editUserForm.email" type="email" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" required />
          </div>
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">New Password (leave empty to keep)</label>
            <input v-model="editUserForm.password" type="password" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm" placeholder="••••••••" />
          </div>
          <div class="flex items-center gap-3">
            <input v-model="editUserForm.is_superadmin" type="checkbox" id="is_superadmin" class="h-4 w-4 rounded border-slate-300" />
            <label for="is_superadmin" class="text-sm font-medium text-slate-600">Superadmin privileges</label>
          </div>
          <div v-if="editUserError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ editUserError }}</div>
          <button class="inline-flex w-full items-center justify-center rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white" :disabled="updatingUser">
            {{ updatingUser ? 'Saving...' : 'Save Changes' }}
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
          <h3 class="text-lg font-semibold text-slate-800">{{ confirmModal.title || 'Confirmation' }}</h3>
          <p class="mt-2 text-sm text-slate-500">{{ confirmModal.message || 'Are you sure?' }}</p>
        </div>
        <div class="mt-6 flex gap-3">
          <button 
            @click="closeConfirmModal" 
            class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          >
            Cancel
          </button>
          <button 
            @click="executeConfirmAction" 
            class="flex-1 rounded-xl px-4 py-2.5 text-sm font-semibold text-white"
            :class="confirmModal.confirmBg || 'bg-rose-600 hover:bg-rose-700'"
          >
            {{ confirmModal.confirmText || 'Yes, Proceed' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="successMessage" class="fixed bottom-6 right-6 z-50 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white">
      {{ successMessage }}
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';

const loading = ref(true);
const error = ref('');
const activeTab = ref('tenants');
const tenants = ref([]);
const successMessage = ref('');

const showCreateTenantModal = ref(false);
const creating = ref(false);
const createError = ref('');
const createForm = reactive({
  tenantName: '',
  tenantSlug: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'super_admin',
});

const editingTenant = ref(null);
const updating = ref(false);
const editError = ref('');
const editForm = reactive({
  name: '',
  slug: '',
  status: '',
});

const addUserTenant = ref(null);
const addUserMode = ref('new');
const addingUser = ref(false);
const userError = ref('');
const userForm = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'user',
});
const assignForm = reactive({
  user: '',
  role: 'user',
});

// Global Users Management
const globalUsers = ref([]);
const loadingUsers = ref(false);
const userSearch = ref('');
let searchTimeout = null;

const editingUser = ref(null);
const updatingUser = ref(false);
const editUserError = ref('');
const editUserForm = reactive({
  name: '',
  email: '',
  password: '',
  is_superadmin: false,
});

// Confirmation Modal
const confirmModal = reactive({
  show: false,
  icon: '⚠️',
  iconBg: 'bg-rose-100',
  title: '',
  message: '',
  confirmText: 'Yes, Proceed',
  confirmBg: 'bg-rose-600 hover:bg-rose-700',
  action: null,
  data: null,
});

const stats = computed(() => ({
  totalTenants: tenants.value.length,
  activeTenants: tenants.value.filter((tenant) => tenant.status === 'active').length,
  suspendedTenants: tenants.value.filter((tenant) => tenant.status === 'suspended').length,
}));

const getToken = () => localStorage.getItem('centralToken');

onMounted(() => {
  fetchTenants();
  fetchGlobalUsers();
});

// Watch tab changes to fetch users when switching to users tab
watch(activeTab, (newTab) => {
  if (newTab === 'users') {
    fetchGlobalUsers();
  }
});

const fetchTenants = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await fetch('/api/superadmin/tenants', {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      if (response.status === 401 || response.status === 403) {
        logout();
        return;
      }
      throw new Error('Failed to load tenants');
    }

    const data = await response.json();
    tenants.value = data.tenants || [];
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
};

const createTenant = () => {
  openConfirmModal({
    icon: '🏢',
    iconBg: 'bg-blue-100',
    title: 'Create New Tenant',
    message: `Create a new workspace "${createForm.tenantName}"?`,
    confirmText: 'Create Tenant',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      creating.value = true;
      createError.value = '';

      try {
        const response = await fetch('/api/tenants/register', {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(createForm),
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'Failed to create tenant');
        }

        showCreateTenantModal.value = false;
        Object.keys(createForm).forEach((key) => {
          createForm[key] = key === 'role' ? 'super_admin' : '';
        });
        showSuccess('Tenant created.');
        fetchTenants();
      } catch (err) {
        createError.value = err.message;
      } finally {
        creating.value = false;
      }
    }
  });
};


const editTenant = (tenant) => {
  editingTenant.value = tenant;
  editForm.name = tenant.name;
  editForm.slug = tenant.slug;
  editForm.status = tenant.status;
  editError.value = '';
};

const updateTenant = () => {
  openConfirmModal({
    icon: '✏️',
    iconBg: 'bg-blue-100',
    title: 'Update Tenant',
    message: `Save changes for tenant "${editingTenant.value.name}"?`,
    confirmText: 'Save Changes',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      updating.value = true;
      editError.value = '';

      try {
        const response = await fetch(`/api/superadmin/tenants/${editingTenant.value.id}`, {
          method: 'PATCH',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(editForm),
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'Failed to update tenant');
        }

        editingTenant.value = null;
        showSuccess('Tenant updated.');
        fetchTenants();
      } catch (err) {
        editError.value = err.message;
      } finally {
        updating.value = false;
      }
    }
  });
};



const suspendTenant = (tenant) => {
  openConfirmModal({
    icon: '⏸️',
    iconBg: 'bg-amber-100',
    title: 'Suspend Tenant',
    message: `Are you sure you want to suspend tenant "${tenant.name}"? Users will not be able to access it.`,
    confirmText: 'Suspend',
    confirmBg: 'bg-amber-600 hover:bg-amber-700',
    action: async () => {
      try {
        const response = await fetch(`/api/superadmin/tenants/${tenant.id}`, {
          method: 'PATCH',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({ status: 'suspended' }),
        });

        if (!response.ok) throw new Error('Failed to suspend tenant');

        showSuccess('Tenant suspended.');
        fetchTenants();
      } catch (err) {
        alert(err.message);
      }
    }
  });
};

const activateTenant = (tenant) => {
  openConfirmModal({
    icon: '▶️',
    iconBg: 'bg-emerald-100',
    title: 'Activate Tenant',
    message: `Activate tenant "${tenant.name}"?`,
    confirmText: 'Activate',
    confirmBg: 'bg-emerald-600 hover:bg-emerald-700',
    action: async () => {
      try {
        const response = await fetch(`/api/superadmin/tenants/${tenant.id}`, {
          method: 'PATCH',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({ status: 'active' }),
        });

        if (!response.ok) throw new Error('Failed to activate tenant');

        showSuccess('Tenant activated.');
        fetchTenants();
      } catch (err) {
        alert(err.message);
      }
    }
  });
};

const showAddUserModal = (tenant) => {
  addUserTenant.value = tenant;
  addUserMode.value = 'new';
  userError.value = '';
  Object.keys(userForm).forEach((key) => {
    userForm[key] = key === 'role' ? 'user' : '';
  });
  assignForm.user = '';
  assignForm.role = 'user';
};

const createUserForTenant = () => {
  openConfirmModal({
    icon: '👤',
    iconBg: 'bg-blue-100',
    title: 'Create User',
    message: `Create new user "${userForm.name}" for tenant "${addUserTenant.value.name}"?`,
    confirmText: 'Create User',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      addingUser.value = true;
      userError.value = '';

      try {
        const response = await fetch(`/api/superadmin/tenants/${addUserTenant.value.id}/users`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(userForm),
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'Failed to create user');
        }

        addUserTenant.value = null;
        showSuccess('User created successfully.');
        fetchTenants(); // Should we fetch global users too? Maybe.
      } catch (err) {
        userError.value = err.message;
      } finally {
        addingUser.value = false;
      }
    }
  });
};

const assignUserToTenant = () => {
  openConfirmModal({
    icon: '🔗',
    iconBg: 'bg-blue-100',
    title: 'Assign User',
    message: `Assign existing user "${assignForm.user}" to tenant "${addUserTenant.value.name}"?`,
    confirmText: 'Assign User',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      addingUser.value = true;
      userError.value = '';

      try {
        const response = await fetch(`/api/superadmin/tenants/${addUserTenant.value.id}/users/assign`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(assignForm),
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'Failed to assign user');
        }

        addUserTenant.value = null;
        showSuccess('User assigned successfully.');
        fetchTenants();
      } catch (err) {
        userError.value = err.message;
      } finally {
        addingUser.value = false;
      }
    }
  });
};


const showSuccess = (message) => {
  successMessage.value = message;
  setTimeout(() => (successMessage.value = ''), 3000);
};

// Global Users Methods
const fetchGlobalUsers = async () => {
  loadingUsers.value = true;
  try {
    const params = userSearch.value ? `?search=${encodeURIComponent(userSearch.value)}` : '';
    const response = await fetch(`/api/superadmin/users${params}`, {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    });
    if (response.ok) {
      const data = await response.json();
      globalUsers.value = data.users || [];
    }
  } catch (err) {
    console.error('Failed to fetch users:', err);
  } finally {
    loadingUsers.value = false;
  }
};

const debounceSearchUsers = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchGlobalUsers();
  }, 300);
};

const openEditUser = (user) => {
  editingUser.value = user;
  editUserForm.name = user.name;
  editUserForm.email = user.email;
  editUserForm.password = '';
  editUserForm.is_superadmin = user.is_superadmin || false;
  editUserError.value = '';
};

const updateUser = () => {
  openConfirmModal({
    icon: '👤',
    iconBg: 'bg-blue-100',
    title: 'Update Global User',
    message: `Save changes for user "${editUserForm.name}"? This will update their details in ALL tenants.`,
    confirmText: 'Save Changes',
    confirmBg: 'bg-blue-600 hover:bg-blue-700',
    action: async () => {
      updatingUser.value = true;
      editUserError.value = '';

      try {
        const payload = {
          name: editUserForm.name,
          email: editUserForm.email,
          is_superadmin: editUserForm.is_superadmin,
        };
    
    if (editUserForm.password) {
      payload.password = editUserForm.password;
    }

    const response = await fetch(`/api/superadmin/users/${editingUser.value.global_id}`, {
      method: 'PATCH',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Failed to update user');
    }

    editingUser.value = null;
    showSuccess('User updated successfully.');
    fetchGlobalUsers();
      } catch (err) {
        editUserError.value = err.message;
      } finally {
        updatingUser.value = false;
      }
    }
  });
};

const confirmDeleteUser = (user) => {
  openConfirmModal({
    icon: '🗑️',
    iconBg: 'bg-rose-100',
    title: 'Delete Global User',
    message: `Are you sure you want to delete ${user.name}? This will remove them from ALL tenants.`,
    confirmText: 'Delete User',
    confirmBg: 'bg-rose-600 hover:bg-rose-700',
    action: async () => {
      try {
        const response = await fetch(`/api/superadmin/users/${user.global_id}`, {
          method: 'DELETE',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            Accept: 'application/json',
          },
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'Failed to delete user');
        }

        showSuccess('User deleted successfully.');
        fetchGlobalUsers();
      } catch (err) {
        alert(err.message);
      }
    }
  });
};

const getStatusBadge = (status) => {
  const map = {
    active: 'bg-emerald-100 text-emerald-700',
    suspended: 'bg-amber-100 text-amber-700',
    deleted: 'bg-rose-100 text-rose-700',
  };
  return map[status] || 'bg-slate-100 text-slate-600';
};

const formatDate = (dateStr) => {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

const openConfirmModal = (options) => {
  confirmModal.icon = options.icon || '⚠️';
  confirmModal.iconBg = options.iconBg || 'bg-rose-100';
  confirmModal.title = options.title || 'Confirmation';
  confirmModal.message = options.message || 'Are you sure?';
  confirmModal.confirmText = options.confirmText || 'Yes, Proceed';
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
    message: 'Are you sure you want to log out?',
    confirmText: 'Logout',
    confirmBg: 'bg-slate-800 hover:bg-slate-900',
    action: () => {
      localStorage.clear();
      window.location.href = '/login';
    }
  });
};
</script>
