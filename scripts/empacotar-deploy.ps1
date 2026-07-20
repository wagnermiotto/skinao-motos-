# ============================================================
#  Skinao Motos - gera os pacotes .zip para a hospedagem
#  Uso:  powershell -ExecutionPolicy Bypass -File scripts\empacotar-deploy.ps1
#
#  IMPORTANTE: nao usa Compress-Archive de proposito.
#  No PowerShell 5.1 ele grava os caminhos com barra invertida
#  (admin\arquivo.php), o que quebra a extracao em servidor Linux/cPanel.
#  Aqui as entradas sao criadas manualmente com barra normal (admin/arquivo.php).
# ============================================================

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$raiz = Split-Path -Parent $PSScriptRoot
$stage = Join-Path $env:TEMP ('skinao-deploy-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
$zipCodigo = Join-Path $raiz 'deploy-skinao.zip'
$zipFotos = Join-Path $raiz 'uploads-fotos.zip'

# Compacta os arquivos de $baseDir preservando os caminhos relativos com barra normal.
function New-ZipComBarraNormal {
    param(
        [string]$Destino,
        [string]$BaseDir,
        [string]$Prefixo = ''
    )
    if (Test-Path $Destino) { Remove-Item $Destino -Force }
    $zip = [System.IO.Compression.ZipFile]::Open($Destino, 'Create')
    try {
        $baseCompleto = (Resolve-Path $BaseDir).Path.TrimEnd('\')
        $arquivos = Get-ChildItem -Path $BaseDir -Recurse -File -Force
        foreach ($a in $arquivos) {
            $rel = $a.FullName.Substring($baseCompleto.Length + 1).Replace('\', '/')
            if ($Prefixo) { $rel = $Prefixo + '/' + $rel }
            $entrada = $zip.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
            $saida = $entrada.Open()
            $leitura = [System.IO.File]::OpenRead($a.FullName)
            try { $leitura.CopyTo($saida) } finally { $leitura.Dispose(); $saida.Dispose() }
        }
        return $arquivos.Count
    }
    finally { $zip.Dispose() }
}

Write-Host 'Montando pacote de deploy...' -ForegroundColor Cyan

# ---- 1. area de preparo com apenas o necessario ----
New-Item -ItemType Directory -Path $stage -Force | Out-Null

foreach ($f in @('index.php', 'favicon.ico', '.htaccess', 'DEPLOY.md')) {
    $origem = Join-Path $raiz $f
    if (Test-Path $origem) { Copy-Item $origem -Destination $stage -Force }
}

foreach ($d in @('admin', 'assets', 'includes', 'sql')) {
    $origem = Join-Path $raiz $d
    if (Test-Path $origem) { Copy-Item $origem -Destination $stage -Recurse -Force }
}

# scripts: somente o criador de admin (+ protecao)
$scriptsDir = Join-Path $stage 'scripts'
New-Item -ItemType Directory -Path $scriptsDir -Force | Out-Null
foreach ($f in @('create-admin.php', '.htaccess')) {
    $origem = Join-Path $raiz "scripts\$f"
    if (Test-Path $origem) { Copy-Item $origem -Destination $scriptsDir -Force }
}

# pasta data so com a protecao (em producao o banco e MySQL)
$dataDir = Join-Path $stage 'data'
New-Item -ItemType Directory -Path $dataDir -Force | Out-Null
$dataHt = Join-Path $raiz 'data\.htaccess'
if (Test-Path $dataHt) { Copy-Item $dataHt -Destination $dataDir -Force }

# ---- 2. remove o que nunca pode ir junto ----
$proibidos = @('config.local.php', '*.sqlite', '*.sqlite3', '*.db', '*.log', '*.tmp', '.DS_Store', 'Thumbs.db')
foreach ($p in $proibidos) {
    Get-ChildItem -Path $stage -Filter $p -Recurse -Force -File -ErrorAction SilentlyContinue |
        Remove-Item -Force -ErrorAction SilentlyContinue
}
foreach ($p in @('.git', '.github', '.claude', '.vscode', 'node_modules', 'import')) {
    Get-ChildItem -Path $stage -Filter $p -Recurse -Force -Directory -ErrorAction SilentlyContinue |
        Remove-Item -Force -Recurse -ErrorAction SilentlyContinue
}

# ---- 3. compacta o codigo ----
$n = New-ZipComBarraNormal -Destino $zipCodigo -BaseDir $stage
Write-Host ("  codigo: {0} arquivos" -f $n)
Remove-Item $stage -Recurse -Force

# ---- 4. compacta as fotos (pacote separado) ----
$uploads = Join-Path $raiz 'uploads'
if (Test-Path $uploads) {
    Write-Host 'Compactando fotos (pode demorar)...' -ForegroundColor Cyan
    $n2 = New-ZipComBarraNormal -Destino $zipFotos -BaseDir $uploads -Prefixo 'uploads'
    Write-Host ("  fotos: {0} arquivos" -f $n2)
}

# ---- 5. resumo ----
Write-Host ''
Write-Host 'Pacotes gerados:' -ForegroundColor Green
foreach ($z in @($zipCodigo, $zipFotos)) {
    if (Test-Path $z) {
        $mb = [math]::Round((Get-Item $z).Length / 1MB, 2)
        Write-Host ("  {0}  ({1} MB)" -f $z, $mb)
    }
}
