<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Verification</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Space+Mono:wght@400;700&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f7f2ea;
            --ink: #121212;
            --muted: #5a5a5a;
            --accent: #10c7a2;
            --warn: #ff6a3d;
            --card: rgba(255, 255, 255, 0.9);
            --stroke: rgba(18, 18, 18, 0.12);
            --shadow: 0 24px 60px rgba(15, 15, 15, 0.12);
            --radius: 20px;
            --mono: "Space Mono", monospace;
            --display: "Space Grotesk", sans-serif;
            --body: "Sora", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: var(--body);
            color: var(--ink);
            background:
                radial-gradient(circle at 10% 10%, rgba(255, 211, 77, 0.18), transparent 45%),
                radial-gradient(circle at 90% 5%, rgba(16, 199, 162, 0.16), transparent 50%),
                linear-gradient(180deg, #fef8f2 0%, #f5efe7 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 980px;
            margin: 40px auto 64px;
            padding: 0 20px;
            display: grid;
            gap: 18px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--stroke);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        h1 {
            font-family: var(--display);
            font-size: clamp(26px, 3.8vw, 36px);
            margin: 0;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-weight: 600;
        }

        .status--valid {
            background: rgba(16, 199, 162, 0.16);
            color: #0b6a56;
        }

        .status--invalid {
            background: rgba(255, 106, 61, 0.18);
            color: #b44b1f;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .meta {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--stroke);
            border-radius: 14px;
            padding: 12px 14px;
        }

        .meta span {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .mono {
            font-family: var(--mono);
            font-size: 12px;
            word-break: break-all;
        }

        .link {
            display: block;
            background: #111;
            color: #fff;
            padding: 10px 12px;
            border-radius: 12px;
            font-family: var(--mono);
            font-size: 12px;
            word-break: break-all;
        }

        .section-title {
            font-family: var(--display);
            margin: 0 0 8px;
            font-size: 18px;
        }

        .signer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .signer-card {
            border: 1px solid var(--stroke);
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.92);
            display: grid;
            gap: 6px;
            font-size: 13px;
        }

        .signer-card strong {
            font-family: var(--display);
            font-size: 15px;
        }

        .muted {
            color: var(--muted);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .upload-form {
            display: grid;
            gap: 10px;
            align-items: center;
        }

        .upload-form input[type="file"] {
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--stroke);
            background: #fff;
            font-family: var(--body);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid var(--stroke);
            text-decoration: none;
            color: var(--ink);
            font-weight: 600;
            font-size: 13px;
            background: #fff;
        }

        @media (max-width: 600px) {
            .container {
                margin-top: 24px;
            }
        }
    </style>
</head>
<body>
@php
    $valid = (bool) ($payload['valid'] ?? false);
    $signatureValid = $payload['signatureValid'] ?? null;
    $signatureLabel = $signatureValid === true ? 'Valid' : ($signatureValid === false ? 'Tidak valid' : 'Tidak tersedia');
    $signers = $payload['signers'] ?? [];
    $signature = $payload['signature'] ?? [];
    $certificateStatus = $payload['certificateStatus'] ?? '-';
    $revokedAt = $payload['certificateRevokedAt'] ?? null;
    $revokedReason = $payload['certificateRevokedReason'] ?? null;
    $tsaStatus = $payload['tsaStatus'] ?? '-';
    $tsaSignedAt = $payload['tsaSignedAt'] ?? null;
    $tsaReason = $payload['tsaReason'] ?? null;
    $tsaFingerprint = $payload['tsaFingerprint'] ?? null;
    $ltvStatus = $payload['ltvStatus'] ?? '-';
    $ltvGeneratedAt = $payload['ltvGeneratedAt'] ?? null;
    $ltvIssues = $payload['ltvIssues'] ?? [];
    $reason = $payload['reason'] ?? null;
    $expectedHash = $payload['expectedSignedPdfSha256'] ?? null;
@endphp
    <div class="container">
        <section class="card">
            <div class="header">
                <h1>Document verification</h1>
                <span class="status {{ $valid ? 'status--valid' : 'status--invalid' }}">
                    {{ $valid ? 'Valid' : 'Tidak valid' }}
                </span>
            </div>
            <p class="muted">Scan QR ini untuk memastikan dokumen benar dan belum diubah.</p>
            <div class="meta-grid">
                <div class="meta">
                    <span>Document ID</span>
                    <div class="mono">{{ $payload['documentId'] ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>Chain ID</span>
                    <div class="mono">{{ $payload['chainId'] ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>Versi</span>
                    <div>{{ $payload['versionNumber'] ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>Signature check</span>
                    <div>{{ $signatureLabel }}</div>
                </div>
                <div class="meta">
                    <span>Certificate status</span>
                    <div>{{ $certificateStatus }}</div>
                </div>
            </div>
            @if ($revokedAt)
                <div class="meta-grid" style="margin-top: 12px;">
                    <div class="meta">
                        <span>Revoked at</span>
                        <div>{{ $revokedAt }}</div>
                    </div>
                    <div class="meta">
                        <span>Revoked reason</span>
                        <div>{{ $revokedReason ?? '-' }}</div>
                    </div>
                </div>
            @endif
            <div class="meta-grid" style="margin-top: 12px;">
                <div class="meta">
                    <span>TSA status</span>
                    <div>{{ $tsaStatus }}</div>
                </div>
                <div class="meta">
                    <span>TSA signed at</span>
                    <div>{{ $tsaSignedAt ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>LTV status</span>
                    <div>{{ $ltvStatus }}</div>
                </div>
            </div>
            @if ($tsaReason || $tsaFingerprint)
                <div class="meta-grid" style="margin-top: 12px;">
                    <div class="meta">
                        <span>TSA reason</span>
                        <div>{{ $tsaReason ?? '-' }}</div>
                    </div>
                    <div class="meta">
                        <span>TSA fingerprint</span>
                        <div class="mono">{{ $tsaFingerprint ?? '-' }}</div>
                    </div>
                </div>
            @endif
            @if (!empty($ltvIssues))
                <div class="meta-grid" style="margin-top: 12px;">
                    <div class="meta">
                        <span>LTV issues</span>
                        <div class="mono">{{ implode(', ', $ltvIssues) }}</div>
                    </div>
                    @if ($ltvGeneratedAt)
                        <div class="meta">
                            <span>LTV generated at</span>
                            <div>{{ $ltvGeneratedAt }}</div>
                        </div>
                    @endif
                </div>
            @elseif ($ltvGeneratedAt)
                <div class="meta-grid" style="margin-top: 12px;">
                    <div class="meta">
                        <span>LTV generated at</span>
                        <div>{{ $ltvGeneratedAt }}</div>
                    </div>
                </div>
            @endif
            @if (!$valid && $reason)
                <div class="meta-grid" style="margin-top: 12px;">
                    <div class="meta">
                        <span>Reason</span>
                        <div>{{ $reason }}</div>
                    </div>
                    @if ($expectedHash)
                        <div class="meta">
                            <span>Expected SHA-256</span>
                            <div class="mono">{{ $expectedHash }}</div>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="card">
            <h2 class="section-title">Verify file integrity</h2>
            <p class="muted">Upload PDF hasil scan untuk memastikan file yang kamu pegang sama dengan versi yang ditandatangani.</p>
            <form class="upload-form" method="post" enctype="multipart/form-data" action="{{ url()->current() }}">
                <input type="file" name="file" accept="application/pdf" required>
                <div class="actions">
                    <button class="btn" type="submit">Verify uploaded PDF</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2 class="section-title">Links</h2>
            <div class="meta-grid">
                <div class="meta">
                    <span>Verification URL</span>
                    <div class="link">{{ $payload['verificationUrl'] ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>Signed PDF</span>
                    @if (!empty($payload['signedPdfDownloadUrl']))
                        <a class="btn" href="{{ $payload['signedPdfDownloadUrl'] }}">Download PDF</a>
                    @else
                        <div class="muted">Tidak tersedia</div>
                    @endif
                </div>
                <div class="meta">
                    <span>SHA-256</span>
                    <div class="mono">{{ $payload['signedPdfSha256'] ?? '-' }}</div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="section-title">Signer</h2>
            @if (!empty($signers))
                <div class="signer-grid">
                    @foreach ($signers as $signer)
                        <div class="signer-card">
                            <strong>{{ $signer['name'] ?? '-' }}</strong>
                            <div class="muted">{{ $signer['role'] ?? 'Member' }}</div>
                            <div>Email: {{ $signer['email'] ?? '-' }}</div>
                            <div>Signed at: {{ $signer['signedAt'] ?? '-' }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="muted">Tidak ada data signer.</div>
            @endif
        </section>

        <section class="card">
            <h2 class="section-title">Certificate</h2>
            <div class="meta-grid">
                <div class="meta">
                    <span>Algorithm</span>
                    <div>{{ $signature['algorithm'] ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>Fingerprint</span>
                    <div class="mono">{{ $signature['certificateFingerprint'] ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>Subject</span>
                    <div class="mono">{{ $signature['certificateSubject'] ?? '-' }}</div>
                </div>
                <div class="meta">
                    <span>Serial</span>
                    <div class="mono">{{ $signature['certificateSerial'] ?? '-' }}</div>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
