<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'E-Signer',
    },
    baseOrigin: {
        type: String,
        default: '',
    },
    tenant: {
        type: Object,
        default: null,
    },
});

const appName = computed(() => props.appName || 'E-Signer');
const baseOriginInput = ref('');
const normalizeOrigin = (value) => value.trim().replace(/\/$/, '');
const baseOrigin = computed(() => normalizeOrigin(baseOriginInput.value) || normalizeOrigin(props.baseOrigin || ''));

const tenantSlug = computed(() => props.tenant?.slug || props.tenant?.id || 'demo');
const tenantName = computed(() => props.tenant?.name || 'Tenant Workspace');
const tenantId = computed(() => props.tenant?.id || '-');

const tenantInput = ref(tenantSlug.value);
const activeSlug = computed(() => tenantInput.value.trim() || tenantSlug.value || 'demo');

const baseUrl = computed(() => {
    if (!baseOrigin.value) {
        return `/${activeSlug.value}/api`;
    }
    return `${baseOrigin.value}/${activeSlug.value}/api`;
});

const signPreview = computed(() => `${baseUrl.value}/documents/sign`);
const verifyPreview = computed(() => `${baseUrl.value}/verify/{chainId}/v1`);
const downloadPreview = computed(() => `${baseUrl.value}/documents/{documentId}/versions/latest:download`);

const copyLabel = ref('Copy');
const copyBase = async () => {
    try {
        await navigator.clipboard.writeText(baseUrl.value);
        copyLabel.value = 'Copied';
    } catch (error) {
        copyLabel.value = 'Copy failed';
    }
    setTimeout(() => {
        copyLabel.value = 'Copy';
    }, 1200);
};

const tenantMetrics = computed(() => [
    {
        title: 'Tenant',
        description: tenantName.value,
    },
    {
        title: 'Slug',
        description: tenantSlug.value,
    },
    {
        title: 'ID',
        description: tenantId.value,
    },
]);

const flowSteps = [
    {
        step: '01',
        tag: 'Public',
        tagClass: '',
        title: 'Check tenant info',
        description: 'Fetch tenant details before onboarding or in public landing screens.',
        code: 'GET /{tenant}/api/public/info',
        delay: '0s',
    },
    {
        step: '02',
        tag: 'Auth',
        tagClass: 'chip--warm',
        title: 'Register or login',
        description: 'Create tenant user or login to get a personal access token.',
        code: 'POST /{tenant}/api/auth/login',
        delay: '0.05s',
    },
    {
        step: '03',
        tag: 'Sign',
        tagClass: '',
        title: 'Sign the PDF',
        description: 'Upload a PDF, embed QR + text stamp, and create a new version.',
        code: 'POST /{tenant}/api/documents/sign',
        delay: '0.1s',
    },
    {
        step: '04',
        tag: 'Verify',
        tagClass: '',
        title: 'Verify receipts',
        description: 'Verify by uploading the PDF or visiting the QR verification URL.',
        code: 'POST /{tenant}/api/verify',
        delay: '0.15s',
    },
];

const endpoints = [
    {
        method: 'GET',
        scope: 'Public',
        path: '/{tenant}/api/public/info',
        description: 'Return tenant id, name, and slug.',
        delay: '0s',
    },
    {
        method: 'POST',
        scope: 'Public',
        path: '/{tenant}/api/auth/register',
        description: 'Create tenant user + certificate, return token.',
        delay: '0.05s',
    },
    {
        method: 'POST',
        scope: 'Public',
        path: '/{tenant}/api/auth/login',
        description: 'Login tenant user, return token.',
        delay: '0.1s',
    },
    {
        method: 'GET',
        scope: 'Protected',
        path: '/{tenant}/api/auth/me',
        description: 'Return profile, tenant, and membership metadata.',
        delay: '0.15s',
    },
    {
        method: 'POST',
        scope: 'Protected',
        path: '/{tenant}/api/documents/sign',
        description: 'Sign PDF, create new version, return verification payload.',
        delay: '0.2s',
    },
    {
        method: 'GET',
        scope: 'Protected',
        path: '/{tenant}/api/documents/{documentId}',
        description: 'Fetch latest version and signer chain.',
        delay: '0.25s',
    },
    {
        method: 'GET',
        scope: 'Protected',
        path: '/{tenant}/api/documents/{documentId}/versions',
        description: 'List all versions with hashes and download URLs.',
        delay: '0.3s',
    },
    {
        method: 'GET',
        scope: 'Protected',
        path: '/{tenant}/api/documents/{documentId}/versions/latest:download',
        description: 'Download latest signed PDF or use v{version}:download.',
        delay: '0.35s',
    },
    {
        method: 'POST',
        scope: 'Public',
        path: '/{tenant}/api/verify',
        description: 'Upload PDF, verify hash + signature validity.',
        delay: '0.4s',
    },
    {
        method: 'GET',
        scope: 'Public',
        path: '/{tenant}/api/verify/{chainId}/v{version}',
        description: 'Verification URL embedded in QR code.',
        delay: '0.45s',
    },
];

const payloadHighlights = [
    'documentId, chainId, versionNumber',
    'verificationUrl, signedPdfDownloadUrl, signedPdfSha256',
    'signature metadata plus signer array with certificate info',
];

const payloadExample = computed(() => {
    const slug = activeSlug.value;
    return `{
  "documentId": "01KCTX...",
  "chainId": "01KCTY...",
  "versionNumber": 2,
  "verificationUrl": "http://host/${slug}/api/verify/01KCTY.../v2",
  "signedPdfDownloadUrl": "http://host/${slug}/api/documents/01KCTX.../versions/v2:download",
  "signedPdfSha256": "ad8f6b6d4a...",
  "signature": {
    "algorithm": "sha256WithRSAEncryption",
    "certificateFingerprint": "2c8c3b4b...",
    "certificateSubject": "CN=User, emailAddress=user@example.com",
    "certificateSerial": "1f9a..."
  },
  "signers": [
    {
      "index": 1,
      "tenantId": "${slug}",
      "userId": "u-001",
      "name": "Test User",
      "email": "test@example.com",
      "role": "Direktur",
      "signedAt": "2025-12-20T08:52:44+00:00",
      "certificate": {
        "serial": "A1B2C3D4",
        "issuedBy": "CN=Test User, emailAddress=test@example.com, O=E-Signer, C=ID",
        "validFrom": "2025-01-01",
        "validTo": "2027-01-01"
      }
    }
  ]
}`;
});

const trustItems = [
    {
        title: 'Idempotency ready',
        description: 'Send Idempotency-Key to avoid duplicate versions on retries.',
        delay: '0s',
    },
    {
        title: 'Multi-signer chain',
        description: 'Signing an already signed PDF creates a new version and adds signer index.',
        delay: '0.05s',
    },
    {
        title: 'Verification options',
        description: 'Verify by uploading the PDF or visiting the QR verification URL.',
        delay: '0.1s',
    },
];

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
    <Head :title="`${tenantName} | ${appName}`" />
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
                <a href="#flow">Flow</a>
                <a href="#endpoints">Endpoints</a>
                <a href="#payload">Payload</a>
                <a href="#trust">Trust</a>
            </nav>
            <div class="nav__actions">
                <a class="btn btn--ghost" href="/try">Try this app</a>
                <a class="btn btn--primary" href="#start">Go sign</a>
            </div>
        </header>

        <main>
            <section class="hero" id="start">
                <div class="hero__copy" data-animate>
                    <div class="tag">Tenant workspace</div>
                    <h1>{{ tenantName }} is ready to sign.</h1>
                    <p class="lead">
                        You are inside the tenant context. Use the base path below to onboard members,
                        sign PDFs, and verify every version with QR receipts.
                    </p>
                    <div class="cta">
                        <a class="btn btn--primary" href="#flow">Run the flow</a>
                        <a class="btn btn--ghost" href="#endpoints">See endpoints</a>
                    </div>
                    <div class="metrics">
                        <div class="metric" v-for="metric in tenantMetrics" :key="metric.title">
                            <span>{{ metric.title }}</span>
                            {{ metric.description }}
                        </div>
                    </div>
                </div>

                <div class="panel" data-animate style="--delay: 0.1s;">
                    <div class="panel__row">
                        <div>
                            <div class="panel__label">Base URL</div>
                            <div class="panel__url">{{ baseUrl }}</div>
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
                    <input class="input" id="tenantInput" type="text" :placeholder="tenantSlug" v-model="tenantInput">

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

                    <div class="panel__label" style="margin-top: 18px;">Tenant info</div>
                    <div class="mini">
                        <div class="mini-row">
                            <span>Name</span>
                            <code>{{ tenantName }}</code>
                        </div>
                        <div class="mini-row">
                            <span>Slug</span>
                            <code>{{ tenantSlug }}</code>
                        </div>
                        <div class="mini-row">
                            <span>ID</span>
                            <code>{{ tenantId }}</code>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="flow">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Flow</div>
                    <h2>Your tenant flow in four moves.</h2>
                    <p>Use the public info endpoint, authenticate members, sign PDFs, then share the verification URL.</p>
                </div>
                <div class="flow-grid">
                    <article
                        class="card"
                        v-for="step in flowSteps"
                        :key="step.step"
                        data-animate
                        :style="{ '--delay': step.delay }"
                    >
                        <div class="card__top">
                            <span class="step">{{ step.step }}</span>
                            <span class="chip" :class="step.tagClass">{{ step.tag }}</span>
                        </div>
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.description }}</p>
                        <span class="code-pill">{{ step.code }}</span>
                    </article>
                </div>
            </section>

            <section class="section" id="endpoints">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Endpoints</div>
                    <h2>Tenant endpoints at a glance.</h2>
                    <p>Public endpoints are open, protected ones require Bearer token in Authorization header.</p>
                </div>
                <div class="endpoint-grid">
                    <article
                        class="endpoint"
                        v-for="endpoint in endpoints"
                        :key="endpoint.path"
                        data-animate
                        :style="{ '--delay': endpoint.delay }"
                    >
                        <div class="method"><span>{{ endpoint.method }}</span> {{ endpoint.scope }}</div>
                        <div class="path">{{ endpoint.path }}</div>
                        <p>{{ endpoint.description }}</p>
                    </article>
                </div>
            </section>

            <section class="section" id="payload">
                <div class="payload-grid">
                    <div data-animate>
                        <div class="eyebrow">Payload</div>
                        <h2>Receipts that read clean.</h2>
                        <p>Signing and verification responses share the same payload, so the UI can reuse one view for preview, receipts, and audit trail.</p>
                        <ul class="list">
                            <li v-for="item in payloadHighlights" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                    <div data-animate style="--delay: 0.1s;">
                        <pre><code v-text="payloadExample"></code></pre>
                    </div>
                </div>
            </section>

            <section class="section" id="trust">
                <div class="section-head" data-animate>
                    <div class="eyebrow">Trust layer</div>
                    <h2>Built-in proof, not vibes only.</h2>
                    <p>Signatures are generated per user, stored with certificate metadata, and verifiable with a detached signature check.</p>
                </div>
                <div class="trust-grid">
                    <div
                        class="trust-card"
                        v-for="item in trustItems"
                        :key="item.title"
                        data-animate
                        :style="{ '--delay': item.delay }"
                    >
                        <h4>{{ item.title }}</h4>
                        <p>{{ item.description }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer">
            <div>Tenant {{ tenantSlug }} live on E-Signer.</div>
            <div>Docs live in docs/API.md</div>
        </footer>
    </div>
</template>
