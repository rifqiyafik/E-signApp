$ErrorActionPreference = 'Stop'

$base = 'http://127.0.0.1:8000'
$tenant = 'demo'
$apiBase = "$base/$tenant/api"

$headers = @{ Accept = 'application/json' }

$loginBody = @{ email = 'test@example.com'; password = 'secret123'; deviceName = 'postman' } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$apiBase/auth/login" -ContentType 'application/json' -Headers $headers -Body $loginBody

$loginOutput = [ordered]@{
  accessToken = if ($login.accessToken) { ($login.accessToken.Substring(0, 12) + '...') } else { $null }
  tenantId = $login.tenantId
  userId = $login.userId
}

$authHeaders = @{ Accept = 'application/json'; Authorization = "Bearer $($login.accessToken)" }
$me = Invoke-RestMethod -Method Get -Uri "$apiBase/auth/me" -Headers $authHeaders

$pdfPath = Join-Path $PWD 'tmp-test.pdf'
@"
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>
endobj
4 0 obj
<< /Length 44 >>
stream
BT /F1 12 Tf 72 120 Td (Test PDF) Tj ET
endstream
endobj
xref
0 5
0000000000 65535 f 
0000000010 00000 n 
0000000061 00000 n 
0000000114 00000 n 
0000000173 00000 n 
trailer
<< /Root 1 0 R /Size 5 >>
startxref
254
%%EOF
"@ | Set-Content -Encoding ASCII $pdfPath

$signJson = curl.exe -s -X POST "$apiBase/documents/sign" -H "Authorization: Bearer $($login.accessToken)" -H "Accept: application/json" -F "file=@$pdfPath" -F "consent=true"
$sign = $signJson | ConvertFrom-Json

$doc = Invoke-RestMethod -Method Get -Uri "$apiBase/documents/$($sign.documentId)" -Headers $authHeaders
$versions = Invoke-RestMethod -Method Get -Uri "$apiBase/documents/$($sign.documentId)/versions" -Headers $authHeaders

$downloadPath = Join-Path $PWD 'tmp-signed.pdf'
curl.exe -s -L -o $downloadPath -H "Authorization: Bearer $($login.accessToken)" $sign.signedPdfDownloadUrl

$verifyJson = curl.exe -s -X POST "$apiBase/verify" -H "Accept: application/json" -F "file=@$pdfPath"
$verify = $verifyJson | ConvertFrom-Json

$verifyQr = Invoke-RestMethod -Method Get -Uri "$apiBase/verify/$($sign.chainId)/v$($sign.versionNumber)" -Headers $headers

Write-Output "LOGIN_RESPONSE="
$loginOutput | ConvertTo-Json -Depth 6
Write-Output "ME_RESPONSE="
$me | ConvertTo-Json -Depth 6
Write-Output "SIGN_RESPONSE="
$sign | ConvertTo-Json -Depth 6
Write-Output "DOCUMENT_RESPONSE="
$doc | ConvertTo-Json -Depth 6
Write-Output "VERSIONS_RESPONSE="
$versions | ConvertTo-Json -Depth 6
Write-Output "VERIFY_RESPONSE="
$verify | ConvertTo-Json -Depth 6
Write-Output "VERIFY_QR_RESPONSE="
$verifyQr | ConvertTo-Json -Depth 6
Write-Output "DOWNLOAD_PATH=$downloadPath"
