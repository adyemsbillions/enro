# Builds dist/enro-backend.zip for uploading to cPanel.
# Leaves out config.php (server keeps its own), the dev-only router.php, dev credentials and anything git ignores
# (uploads, logs, cache, Firebase key). Run from anywhere:  powershell -File backend/bin/make-zip.ps1
$ErrorActionPreference = 'Stop'
$backend = Split-Path -Parent $PSScriptRoot
Push-Location $backend
try {
    $files = git ls-files -co --exclude-standard | Where-Object {
        $_ -notin @('config.php', 'router.php') -and $_ -notlike 'storage/dev-admin*' -and $_ -notlike 'bin/make-zip.ps1'
    }
    $dist = Join-Path (Split-Path -Parent $backend) 'dist'
    New-Item -ItemType Directory -Force $dist | Out-Null
    $zipPath = Join-Path $dist 'enro-backend.zip'
    if (Test-Path $zipPath) { Remove-Item $zipPath -Confirm:$false }
    Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
    foreach ($f in $files) {
        [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, (Resolve-Path $f).Path, $f.Replace('\', '/'), 'Optimal')
    }
    $zip.Dispose()
    "Wrote $zipPath ($($files.Count) files)"
} finally {
    Pop-Location
}
