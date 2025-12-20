$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8000'
$tenant = 'demo'
$apiBase = "$base/$tenant/api"
$headers = @{ Accept = 'application/json' }
$loginBody = @{ email = 'test@example.com'; password = 'secret123'; deviceName = 'postman' } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$apiBase/auth/login" -ContentType 'application/json' -Headers $headers -Body $loginBody
$signJson = curl.exe -s -X POST "$apiBase/documents/sign" -H "Authorization: Bearer $($login.accessToken)" -H "Accept: application/json" -F "file=@$PWD\tmp-valid.pdf" -F "consent=true"
Write-Output $signJson
