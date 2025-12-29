<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.12),_transparent_40%),linear-gradient(180deg,#f8fafc_0%,#ffffff_70%)]"></div>

    <div class="mx-auto max-w-5xl px-4 py-12">
      <nav class="mb-8 flex items-center justify-between">
        <div class="flex items-center gap-3 rounded-full border border-white/60 bg-white/70 px-4 py-2 text-sm font-semibold shadow-sm backdrop-blur">
          <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white">E</span>
          <span>E-Signer</span>
        </div>
        <button class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="logout">Logout</button>
      </nav>

      <div class="flex flex-col gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Workspace</p>
        <h1 class="text-3xl font-semibold">Welcome back{{ userName ? `, ${userName}` : '' }}</h1>
        <p class="text-sm text-slate-500">Choose your tenant to continue.</p>
      </div>

      <div v-if="loading" class="mt-8 rounded-3xl border border-white/70 bg-white/70 p-6 text-sm text-slate-500">
        Loading your tenants...
      </div>

      <div v-if="error" class="mt-8 rounded-3xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">
        {{ error }}
      </div>

      <div v-if="!loading" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="tenant in tenants"
          :key="tenant.id"
          class="group flex flex-col justify-between rounded-3xl border border-white/70 bg-white/80 p-5 shadow-sm transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-lg"
        >
          <div>
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600/10 text-sm font-semibold text-blue-600">
                {{ getInitials(tenant.name) }}
              </div>
              <div>
                <p class="text-sm font-semibold">{{ tenant.name }}</p>
                <p class="text-xs text-slate-500">{{ tenant.slug }}</p>
              </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
              <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ tenant.role }}</span>
              <span v-if="tenant.isOwner" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Owner</span>
            </div>
          </div>

          <button
            class="mt-6 inline-flex items-center justify-center rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition group-hover:-translate-y-0.5 group-hover:bg-slate-800"
            @click="selectTenant(tenant)"
          >
            Enter Workspace
          </button>
        </div>

        <div v-if="tenants.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white/70 p-8 text-center text-sm text-slate-500">
          No tenant available yet.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const tenants = ref([]);
const loading = ref(false);
const error = ref('');
const userName = ref('');
const preferredDashboard = ref('user');

onMounted(() => {
  const storedTenants = localStorage.getItem('tenants');
  const storedUser = localStorage.getItem('user');
  const storedPreferred = localStorage.getItem('preferredDashboard');

  preferredDashboard.value = storedPreferred || 'user';

  if (storedUser) {
    try {
      userName.value = JSON.parse(storedUser).name || '';
    } catch (err) {
      userName.value = '';
    }
  }

  if (storedTenants) {
    tenants.value = JSON.parse(storedTenants);
  } else {
    fetchTenants();
  }
});

const fetchTenants = async () => {
  const token = localStorage.getItem('centralToken');
  if (!token) {
    router.visit('/login');
    return;
  }

  loading.value = true;
  try {
    const response = await fetch('/api/auth/me', {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error('Session expired');
    }

    const data = await response.json();
    tenants.value = data.tenants || [];
  } catch (err) {
    router.visit('/login');
  } finally {
    loading.value = false;
  }
};

const selectTenant = async (tenant) => {
  loading.value = true;
  error.value = '';

  const token = localStorage.getItem('centralToken');

  try {
    const response = await fetch('/api/auth/select-tenant', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ tenant: tenant.slug }),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Failed to select tenant');
    }

    localStorage.setItem('tenantToken', data.accessToken);
    localStorage.setItem('currentTenant', JSON.stringify(data.tenant));

    const storedUser = JSON.parse(localStorage.getItem('user') || '{}');
    storedUser.role = data.tenant.role;
    storedUser.isOwner = data.tenant.isOwner;
    localStorage.setItem('user', JSON.stringify(storedUser));

    const next = preferredDashboard.value === 'admin' ? '/admin/dashboard' : `/${tenant.slug}/dashboard`;
    router.visit(next);
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
};

const logout = () => {
  localStorage.clear();
  router.visit('/login');
};

const getInitials = (name) => {
  if (!name) return 'T';
  return name
    .split(' ')
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase();
};
</script>
