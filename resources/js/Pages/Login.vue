<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.15),_transparent_45%),linear-gradient(180deg,#f8fafc_0%,#ffffff_70%)]"></div>

    <div class="mx-auto flex min-h-screen max-w-md items-center px-4 py-12">
      <div class="w-full rounded-3xl border border-white/70 bg-white/80 p-8 shadow-2xl backdrop-blur">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Welcome back</p>
            <h1 class="mt-2 text-2xl font-semibold">Login to E-Signer</h1>
          </div>
          <Link href="/" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Back</Link>
        </div>

        <div v-if="error" class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
          {{ error }}
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="handleLogin">
          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
            <input
              v-model="form.email"
              type="email"
              class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm transition focus:border-blue-500 focus:outline-none"
              placeholder="email@example.com"
              required
              :disabled="loading"
            />
          </div>

          <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Password</label>
            <input
              v-model="form.password"
              type="password"
              class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm transition focus:border-blue-500 focus:outline-none"
              placeholder="Minimum 8 characters"
              required
              :disabled="loading"
            />
          </div>

          <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-700"
            :disabled="loading"
          >
            <span v-if="loading" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
            <span>{{ loading ? 'Signing in...' : 'Login' }}</span>
          </button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">
          Need access? Contact your admin to invite you.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const loading = ref(false);
const error = ref('');

const form = reactive({
  email: '',
  password: '',
});

const adminRoles = ['super_admin', 'admin', 'owner', 'tenant_admin'];

const handleLogin = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(form),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Login failed');
    }

    localStorage.setItem('centralToken', data.accessToken);
    localStorage.setItem('user', JSON.stringify(data.user));
    localStorage.setItem('tenants', JSON.stringify(data.tenants));

    if (data.user.isSuperadmin) {
      router.visit('/superadmin/dashboard');
      return;
    }

    if (!data.tenants || data.tenants.length === 0) {
      error.value = 'No tenant access assigned yet.';
      return;
    }

    const hasAdminTenant = data.tenants.some((tenant) => adminRoles.includes(tenant.role));
    localStorage.setItem('preferredDashboard', hasAdminTenant ? 'admin' : 'user');

    if (hasAdminTenant && data.tenants.length === 1) {
      await selectTenant(data.tenants[0].slug, data.accessToken, '/admin/dashboard');
      return;
    }

    router.visit('/select-tenant');
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
};

const selectTenant = async (tenantSlug, token, redirectTo) => {
  try {
    const response = await fetch('/api/auth/select-tenant', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ tenant: tenantSlug }),
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

    router.visit(redirectTo || `/${tenantSlug}/dashboard`);
  } catch (err) {
    error.value = err.message;
  }
};
</script>
