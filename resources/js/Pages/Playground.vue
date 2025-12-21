<script setup>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'E-Signer',
    },
    baseOrigin: {
        type: String,
        default: '',
    },
});

const appName = computed(() => props.appName || 'E-Signer');
const baseOriginInput = ref('');
const normalizeOrigin = (value) => value.trim().replace(/\/$/, '');
const baseOrigin = computed(() => normalizeOrigin(baseOriginInput.value) || normalizeOrigin(props.baseOrigin || ''));

const tenantInput = ref('demo');
const authToken = ref('');

const activeTenant = computed(() => tenantInput.value.trim() || 'demo');

const centralBaseUrl = computed(() => (baseOrigin.value ? `${baseOrigin.value}/api` : '/api'));
const tenantBaseUrl = computed(() =>
    baseOrigin.value ? `${baseOrigin.value}/${activeTenant.value}/api` : `/${activeTenant.value}/api`
);

const signPreview = computed(() => `${tenantBaseUrl.value}/documents/sign`);
const verifyPreview = computed(() => `${tenantBaseUrl.value}/verify/{chainId}/v1`);
const downloadPreview = computed(() => `${tenantBaseUrl.value}/documents/{documentId}/versions/latest:download`);

const copyLabel = ref('Copy');
const copyBase = async () => {
    try {
        await navigator.clipboard.writeText(tenantBaseUrl.value);
        copyLabel.value = 'Copied';
    } catch (error) {
        copyLabel.value = 'Copy failed';
    }
    setTimeout(() => {
        copyLabel.value = 'Copy';
    }, 1200);
};

const formatError = (error) => {
    if (error?.response?.data?.message) {
        return error.response.data.message;
    }
    if (error?.response?.data?.errors) {
        return Object.values(error.response.data.errors).flat().join(' ');
    }
    return error?.message || 'Request failed.';
};

const prettyJson = (value) => (value ? JSON.stringify(value, null, 2) : '');

const centralForm = reactive({
    tenantName: '',
    tenantSlug: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
});

const centralState = reactive({
    loading: false,
    error: '',
    data: null,
});

const registerForm = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const registerState = reactive({
    loading: false,
    error: '',
    data: null,
});

const loginForm = reactive({
    email: '',
    password: '',
});

const loginState = reactive({
    loading: false,
    error: '',
    data: null,
});

const profileState = reactive({
    loading: false,
    error: '',
    data: null,
});

const signState = reactive({
    loading: false,
    error: '',
    data: null,
});

const signConsent = ref(true);
const signFile = ref(null);
const signIdempotencyKey = ref('');

const verifyState = reactive({
    loading: false,
    error: '',
    data: null,
});

const verifyFile = ref(null);

const verifyChainState = reactive({
    loading: false,
    error: '',
    data: null,
});

const verifyChainId = ref('');
const verifyChainVersion = ref('1');

const documentState = reactive({
    loading: false,
    error: '',
    data: null,
    versions: null,
});

const documentIdInput = ref('');

const setFromPayload = (payload) => {
    if (!payload) {
        return;
    }
    if (payload.chainId) {
        verifyChainId.value = payload.chainId;
    }
    if (payload.versionNumber) {
        verifyChainVersion.value = String(payload.versionNumber);
    }
    if (payload.documentId) {
        documentIdInput.value = payload.documentId;
    }
};

const createTenant = async () => {
    centralState.loading = true;
    centralState.error = '';
    centralState.data = null;
    try {
        const payload = {
            tenantName: centralForm.tenantName,
            tenantSlug: centralForm.tenantSlug,
            name: centralForm.name,
            email: centralForm.email,
            password: centralForm.password,
            password_confirmation: centralForm.password_confirmation,
            role: centralForm.role,
        };
        if (!payload.tenantSlug) {
            delete payload.tenantSlug;
        }
        if (!payload.role) {
            delete payload.role;
        }
        const response = await axios.post(`${centralBaseUrl.value}/tenants/register`, payload);
        centralState.data = response.data;
        if (response.data?.tenantSlug) {
            tenantInput.value = response.data.tenantSlug;
        }
        if (response.data?.accessToken) {
            authToken.value = response.data.accessToken;
        }
    } catch (error) {
        centralState.error = formatError(error);
    } finally {
        centralState.loading = false;
    }
};

const registerUser = async () => {
    registerState.loading = true;
    registerState.error = '';
    registerState.data = null;
    try {
        const response = await axios.post(`${tenantBaseUrl.value}/auth/register`, {
            name: registerForm.name,
            email: registerForm.email,
            password: registerForm.password,
            password_confirmation: registerForm.password_confirmation,
        });
        registerState.data = response.data;
        if (response.data?.accessToken) {
            authToken.value = response.data.accessToken;
        }
    } catch (error) {
        registerState.error = formatError(error);
    } finally {
        registerState.loading = false;
    }
};

const loginUser = async () => {
    loginState.loading = true;
    loginState.error = '';
    loginState.data = null;
    try {
        const response = await axios.post(`${tenantBaseUrl.value}/auth/login`, {
            email: loginForm.email,
            password: loginForm.password,
        });
        loginState.data = response.data;
        if (response.data?.accessToken) {
            authToken.value = response.data.accessToken;
        }
    } catch (error) {
        loginState.error = formatError(error);
    } finally {
        loginState.loading = false;
    }
};

const fetchProfile = async () => {
    profileState.loading = true;
    profileState.error = '';
    profileState.data = null;
    try {
        if (!authToken.value) {
            throw new Error('Login first to call /auth/me.');
        }
        const response = await axios.get(`${tenantBaseUrl.value}/auth/me`, {
            headers: {
                Authorization: `Bearer ${authToken.value}`,
            },
        });
        profileState.data = response.data;
    } catch (error) {
        profileState.error = formatError(error);
    } finally {
        profileState.loading = false;
    }
};

const signDocument = async () => {
    signState.loading = true;
    signState.error = '';
    signState.data = null;
    try {
        if (!authToken.value) {
            throw new Error('Login first to sign documents.');
        }
        if (!signFile.value) {
            throw new Error('Select a PDF file to sign.');
        }
        const formData = new FormData();
        formData.append('file', signFile.value);
        formData.append('consent', signConsent.value ? '1' : '');
        if (signIdempotencyKey.value) {
            formData.append('idempotencyKey', signIdempotencyKey.value);
        }
        const response = await axios.post(`${tenantBaseUrl.value}/documents/sign`, formData, {
            headers: {
                Authorization: `Bearer ${authToken.value}`,
            },
        });
        signState.data = response.data;
        setFromPayload(response.data);
    } catch (error) {
        signState.error = formatError(error);
    } finally {
        signState.loading = false;
    }
};

const verifyDocumentFile = async () => {
    verifyState.loading = true;
    verifyState.error = '';
    verifyState.data = null;
    try {
        if (!verifyFile.value) {
            throw new Error('Select a PDF file to verify.');
        }
        const formData = new FormData();
        formData.append('file', verifyFile.value);
        const response = await axios.post(`${tenantBaseUrl.value}/verify`, formData);
        verifyState.data = response.data;
        setFromPayload(response.data);
    } catch (error) {
        verifyState.error = formatError(error);
    } finally {
        verifyState.loading = false;
    }
};

const verifyDocumentChain = async () => {
    verifyChainState.loading = true;
    verifyChainState.error = '';
    verifyChainState.data = null;
    try {
        if (!verifyChainId.value) {
            throw new Error('Provide a chainId to verify.');
        }
        const version = verifyChainVersion.value || '1';
        const response = await axios.get(`${tenantBaseUrl.value}/verify/${verifyChainId.value}/v${version}`);
        verifyChainState.data = response.data;
        setFromPayload(response.data);
    } catch (error) {
        verifyChainState.error = formatError(error);
    } finally {
        verifyChainState.loading = false;
    }
};

const fetchDocument = async () => {
    documentState.loading = true;
    documentState.error = '';
    documentState.data = null;
    try {
        if (!authToken.value) {
            throw new Error('Login first to fetch documents.');
        }
        if (!documentIdInput.value) {
            throw new Error('Provide a documentId first.');
        }
        const response = await axios.get(`${tenantBaseUrl.value}/documents/${documentIdInput.value}`, {
            headers: {
                Authorization: `Bearer ${authToken.value}`,
            },
        });
        documentState.data = response.data;
    } catch (error) {
        documentState.error = formatError(error);
    } finally {
        documentState.loading = false;
    }
};

const fetchVersions = async () => {
    documentState.loading = true;
    documentState.error = '';
    documentState.versions = null;
    try {
        if (!authToken.value) {
            throw new Error('Login first to fetch versions.');
        }
        if (!documentIdInput.value) {
            throw new Error('Provide a documentId first.');
        }
        const response = await axios.get(`${tenantBaseUrl.value}/documents/${documentIdInput.value}/versions`, {
            headers: {
                Authorization: `Bearer ${authToken.value}`,
            },
        });
        documentState.versions = response.data;
    } catch (error) {
        documentState.error = formatError(error);
    } finally {
        documentState.loading = false;
    }
};

const handleSignFile = (event) => {
    signFile.value = event.target.files?.[0] || null;
};

const handleVerifyFile = (event) => {
    verifyFile.value = event.target.files?.[0] || null;
};

let observer;

onMounted(() => {
    try {
        const storedOrigin = normalizeOrigin(localStorage.getItem('esigner.baseOrigin') || '');
        if (storedOrigin) {
            baseOriginInput.value = storedOrigin;
        }
    } catch (error) {
        // Ignore storage access issues.
    }

    const elements = document.querySelectorAll('[data-animate]');
    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.2 }
    );
    elements.forEach((element) => observer.observe(element));
});

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect();
    }
});

watch(baseOriginInput, (value) => {
    const normalized = normalizeOrigin(value);
    try {
        if (normalized) {
            localStorage.setItem('esigner.baseOrigin', normalized);
        } else {
            localStorage.removeItem('esigner.baseOrigin');
        }
    } catch (error) {
        // Ignore storage access issues.
    }
});
</script>

<template>
    <Head title="Try E-Signer" />
    <div class="grid" aria-hidden="true"></div>
    <div class="page">
        <div class="orb orb--one" aria-hidden="true"></div>
        <div class="orb orb--two" aria-hidden="true"></div>
        <div class="orb orb--three" aria-hidden="true"></div>

        <header class="nav">
            <div class="brand">
                <span class="logo">ES</span>
                <span>{{ appName }}</span>
            </div>
            <nav class="nav__links">
                <a href="#setup">Setup</a>
                <a href="#auth">Auth</a>
                <a href="#sign">Sign</a>
                <a href="#verify">Verify</a>
                <a href="#documents">Documents</a>
            </nav>
            <div class="nav__actions">
                <a class="btn btn--ghost" href="/">Docs</a>
                <a class="btn btn--primary" href="#start">Start</a>
            </div>
        </header>

        <main>
            <section class="hero" id="start">
                <div class="hero__copy" data-animate>
                    <div class="tag">Playground</div>
                    <h1>Try the full e-sign flow with real data.</h1>
                    <p class="lead">
                        Create tenants, onboard users, sign PDFs, and verify receipts. Every action
                        hits your API and writes to the database.
                    </p>
                    <div class="cta">
                        <a class="btn btn--primary" href="#setup">Start with tenant setup</a>
                        <a class="btn btn--ghost" href="#sign">Go to signing</a>
                    </div>
                </div>

                <div class="panel" data-animate style="--delay: 0.1s;">
                    <div class="panel__row">
                        <div>
                            <div class="panel__label">Tenant Base URL</div>
                            <div class="panel__url">{{ tenantBaseUrl }}</div>
                        </div>
                        <button class="btn btn--ghost" type="button" @click="copyBase">{{ copyLabel }}</button>
                    </div>

                    <label class="field" for="baseOriginInput">Base origin (optional)</label>
                    <input
                        class="input"
                        id="baseOriginInput"
                        type="url"
                        placeholder="https://13.229.151.205"
                        v-model="baseOriginInput"
                    >

                    <label class="field" for="tenantInput">Tenant slug or ID</label>
                    <input class="input" id="tenantInput" type="text" placeholder="demo" v-model="tenantInput">

                    <div class="mini">
                        <div class="mini-row">
                            <span>Sign</span>
                            <code>{{ signPreview }}</code>
                        </div>
                        <div class="mini-row">
                            <span>Verify</span>
                            <code>{{ verifyPreview }}</code>
                        </div>
                        <div class="mini-row">
                            <span>Latest download</span>
                            <code>{{ downloadPreview }}</code>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="setup">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Setup</div>
                    <h2>Create a tenant + owner.</h2>
                    <p>Provision a tenant, owner user, certificate, and access token in one call.</p>
                </div>
                <div class="playground-grid">
                    <article class="form-card" data-animate style="--delay: 0s;">
                        <div class="pill">Central <strong>{{ centralBaseUrl }}/tenants/register</strong></div>
                        <h3>Create tenant + owner</h3>
                        <p>Token from this request will be saved automatically.</p>
                        <form class="form" @submit.prevent="createTenant">
                            <label>Tenant name</label>
                            <input class="input" v-model="centralForm.tenantName" placeholder="Demo Company" required>

                            <label>Tenant slug (optional)</label>
                            <input class="input" v-model="centralForm.tenantSlug" :placeholder="activeTenant">

                            <label>Owner name</label>
                            <input class="input" v-model="centralForm.name" placeholder="Rifqi Yafik" required>

                            <label>Email</label>
                            <input class="input" v-model="centralForm.email" type="email" placeholder="rifqi@domain.com" required>

                            <label>Password</label>
                            <input class="input" v-model="centralForm.password" type="password" placeholder="secret123" required>

                            <label>Confirm password</label>
                            <input class="input" v-model="centralForm.password_confirmation" type="password" placeholder="secret123" required>

                            <label>Role (optional)</label>
                            <input class="input" v-model="centralForm.role" placeholder="Owner">

                            <div class="form-actions">
                                <button class="btn btn--primary" type="submit" :disabled="centralState.loading">
                                    {{ centralState.loading ? 'Creating...' : 'Create tenant' }}
                                </button>
                            </div>
                        </form>
                        <div v-if="centralState.error" class="status status--error">{{ centralState.error }}</div>
                        <div v-else-if="centralState.data" class="status status--ok">
                            Tenant created. Token saved for protected calls.
                        </div>
                        <pre v-if="centralState.data"><code v-text="prettyJson(centralState.data)"></code></pre>
                    </article>

                    <article class="form-card" data-animate style="--delay: 0.05s;">
                        <div class="pill">Session <strong>Bearer token</strong></div>
                        <h3>Access token</h3>
                        <p>Paste a token from register/login to unlock protected endpoints.</p>
                        <div class="form">
                            <label>Bearer token</label>
                            <textarea class="input" v-model="authToken" rows="3" placeholder="eyJ0eXAiOiJKV1Qi..."></textarea>
                            <div class="form-actions">
                                <button class="btn btn--ghost" type="button" @click="authToken = ''">Clear</button>
                                <button class="btn btn--primary" type="button" @click="fetchProfile" :disabled="profileState.loading">
                                    {{ profileState.loading ? 'Checking...' : 'Check /auth/me' }}
                                </button>
                            </div>
                        </div>
                        <div v-if="profileState.error" class="status status--error">{{ profileState.error }}</div>
                        <pre v-if="profileState.data"><code v-text="prettyJson(profileState.data)"></code></pre>
                    </article>
                </div>
            </section>

            <section class="section" id="auth">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Auth</div>
                    <h2>Register and login tenant users.</h2>
                    <p>Use these forms if the tenant already exists.</p>
                </div>
                <div class="playground-grid">
                    <article class="form-card" data-animate style="--delay: 0s;">
                        <div class="pill">Tenant <strong>{{ tenantBaseUrl }}/auth/register</strong></div>
                        <h3>Register user</h3>
                        <p>Create a tenant user and return a token.</p>
                        <form class="form" @submit.prevent="registerUser">
                            <label>Name</label>
                            <input class="input" v-model="registerForm.name" placeholder="Test User" required>

                            <label>Email</label>
                            <input class="input" v-model="registerForm.email" type="email" placeholder="test@example.com" required>

                            <label>Password</label>
                            <input class="input" v-model="registerForm.password" type="password" placeholder="secret123" required>

                            <label>Confirm password</label>
                            <input class="input" v-model="registerForm.password_confirmation" type="password" placeholder="secret123" required>

                            <div class="form-actions">
                                <button class="btn btn--primary" type="submit" :disabled="registerState.loading">
                                    {{ registerState.loading ? 'Registering...' : 'Register user' }}
                                </button>
                            </div>
                        </form>
                        <div v-if="registerState.error" class="status status--error">{{ registerState.error }}</div>
                        <pre v-if="registerState.data"><code v-text="prettyJson(registerState.data)"></code></pre>
                    </article>

                    <article class="form-card" data-animate style="--delay: 0.05s;">
                        <div class="pill">Tenant <strong>{{ tenantBaseUrl }}/auth/login</strong></div>
                        <h3>Login user</h3>
                        <p>Login with tenant credentials to get a fresh token.</p>
                        <form class="form" @submit.prevent="loginUser">
                            <label>Email</label>
                            <input class="input" v-model="loginForm.email" type="email" placeholder="test@example.com" required>

                            <label>Password</label>
                            <input class="input" v-model="loginForm.password" type="password" placeholder="secret123" required>

                            <div class="form-actions">
                                <button class="btn btn--primary" type="submit" :disabled="loginState.loading">
                                    {{ loginState.loading ? 'Logging in...' : 'Login' }}
                                </button>
                            </div>
                        </form>
                        <div v-if="loginState.error" class="status status--error">{{ loginState.error }}</div>
                        <pre v-if="loginState.data"><code v-text="prettyJson(loginState.data)"></code></pre>
                    </article>
                </div>
            </section>

            <section class="section" id="sign">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Sign</div>
                    <h2>Sign a PDF and create a new version.</h2>
                    <p>Requires a Bearer token from register or login.</p>
                </div>
                <div class="playground-grid">
                    <article class="form-card" data-animate style="--delay: 0s;">
                        <div class="pill">Protected <strong>{{ tenantBaseUrl }}/documents/sign</strong></div>
                        <h3>Sign document</h3>
                        <p>Upload a PDF, apply the stamp, and create a new version.</p>
                        <form class="form" @submit.prevent="signDocument">
                            <label>PDF file</label>
                            <input class="input" type="file" accept="application/pdf" @change="handleSignFile" required>

                            <label>Idempotency key (optional)</label>
                            <input class="input" v-model="signIdempotencyKey" placeholder="sign-run-001">

                            <label class="checkbox">
                                <input type="checkbox" v-model="signConsent">
                                I consent to signing this document.
                            </label>

                            <div class="form-actions">
                                <button class="btn btn--primary" type="submit" :disabled="signState.loading">
                                    {{ signState.loading ? 'Signing...' : 'Sign PDF' }}
                                </button>
                            </div>
                        </form>
                        <div v-if="signState.error" class="status status--error">{{ signState.error }}</div>
                        <pre v-if="signState.data"><code v-text="prettyJson(signState.data)"></code></pre>
                    </article>
                </div>
            </section>

            <section class="section" id="verify">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Verify</div>
                    <h2>Verify signatures and versions.</h2>
                    <p>Use file upload or chain URL verification.</p>
                </div>
                <div class="playground-grid">
                    <article class="form-card" data-animate style="--delay: 0s;">
                        <div class="pill">Public <strong>{{ tenantBaseUrl }}/verify</strong></div>
                        <h3>Verify by file</h3>
                        <p>Upload the signed PDF to check its validity.</p>
                        <form class="form" @submit.prevent="verifyDocumentFile">
                            <label>PDF file</label>
                            <input class="input" type="file" accept="application/pdf" @change="handleVerifyFile">
                            <div class="form-actions">
                                <button class="btn btn--primary" type="submit" :disabled="verifyState.loading">
                                    {{ verifyState.loading ? 'Verifying...' : 'Verify file' }}
                                </button>
                            </div>
                        </form>
                        <div v-if="verifyState.error" class="status status--error">{{ verifyState.error }}</div>
                        <pre v-if="verifyState.data"><code v-text="prettyJson(verifyState.data)"></code></pre>
                    </article>

                    <article class="form-card" data-animate style="--delay: 0.05s;">
                        <div class="pill">Public <strong>{{ tenantBaseUrl }}/verify/{chainId}/v{version}</strong></div>
                        <h3>Verify by chain</h3>
                        <p>Use chainId + version to verify from QR link.</p>
                        <form class="form" @submit.prevent="verifyDocumentChain">
                            <label>Chain ID</label>
                            <input class="input" v-model="verifyChainId" placeholder="01KCTY...">

                            <label>Version</label>
                            <input class="input" v-model="verifyChainVersion" placeholder="1">

                            <div class="form-actions">
                                <button class="btn btn--ghost" type="submit" :disabled="verifyChainState.loading">
                                    {{ verifyChainState.loading ? 'Checking...' : 'Verify chain' }}
                                </button>
                            </div>
                        </form>
                        <div v-if="verifyChainState.error" class="status status--error">{{ verifyChainState.error }}</div>
                        <pre v-if="verifyChainState.data"><code v-text="prettyJson(verifyChainState.data)"></code></pre>
                    </article>
                </div>
            </section>

            <section class="section" id="documents">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Documents</div>
                    <h2>Inspect document metadata and versions.</h2>
                    <p>Requires a Bearer token.</p>
                </div>
                <div class="playground-grid">
                    <article class="form-card" data-animate style="--delay: 0s;">
                        <div class="pill">Protected <strong>Documents</strong></div>
                        <h3>Document inspector</h3>
                        <p>Fetch latest metadata and version history.</p>
                        <div class="form">
                            <label>Document ID</label>
                            <input class="input" v-model="documentIdInput" placeholder="01KCTX...">

                            <div class="form-actions">
                                <button class="btn btn--primary" type="button" @click="fetchDocument" :disabled="documentState.loading">
                                    Get document
                                </button>
                                <button class="btn btn--ghost" type="button" @click="fetchVersions" :disabled="documentState.loading">
                                    Get versions
                                </button>
                            </div>
                        </div>
                        <div v-if="documentState.error" class="status status--error">{{ documentState.error }}</div>
                        <pre v-if="documentState.data"><code v-text="prettyJson(documentState.data)"></code></pre>
                        <pre v-if="documentState.versions"><code v-text="prettyJson(documentState.versions)"></code></pre>
                    </article>
                </div>
            </section>
        </main>

        <footer class="footer">
            <div>Playground runs live against your API.</div>
            <div>Docs are available at /</div>
        </footer>
    </div>
</template>
