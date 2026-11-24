[CmdletBinding()]
param(
    [ValidateRange(1024, 65535)][int]$Port = 8080,
    [string]$PhpPath = 'php',
    [ValidateSet('127.0.0.1', '0.0.0.0')][string]$ListenAddress = '127.0.0.1',
    [switch]$CheckOnly,
    [switch]$SetupAdmin
)
$ErrorActionPreference = 'Stop'
$tokyoRoot = $PSScriptRoot
$phpCommand = Get-Command $PhpPath -ErrorAction Stop
$phpExecutable = $phpCommand.Source
$phpFlags = @()
$extensionDirectory = Join-Path (Split-Path $phpExecutable) 'ext'
$loaded = & $phpExecutable -r 'echo implode(",",get_loaded_extensions());'
if ($LASTEXITCODE -ne 0) { throw 'PHP could not start.' }
if (($loaded -split ',') -notcontains 'pdo_sqlite') {
    $sqliteExtension = Join-Path $extensionDirectory 'php_pdo_sqlite.dll'
    if (!(Test-Path -LiteralPath $sqliteExtension)) { throw 'PDO SQLite is missing. Install PHP with PDO SQLite or pass -PhpPath to a compatible PHP executable.' }
    $phpFlags += @('-d', "extension_dir=$extensionDirectory", '-d', 'extension=php_pdo_sqlite.dll')
}
& $phpExecutable @phpFlags -r 'if(!extension_loaded("pdo_sqlite")){fwrite(STDERR,"PDO SQLite unavailable\n");exit(1);} echo "PHP ",PHP_VERSION," / SQLite ",(new PDO("sqlite::memory:"))->query("select sqlite_version()")->fetchColumn(),PHP_EOL;'
if ($LASTEXITCODE -ne 0) { throw 'PHP SQLite check failed.' }
if (!$env:RESTAURANT_DB) {
    $tokyoDataDirectory = Join-Path $env:LOCALAPPDATA 'TokyoPremium\data'
    New-Item -ItemType Directory -Path $tokyoDataDirectory -Force | Out-Null
    $env:RESTAURANT_DB = Join-Path $tokyoDataDirectory 'restaurant.sqlite'
}
Write-Host "Database: $env:RESTAURANT_DB"
if ($CheckOnly) { return }
Push-Location $tokyoRoot
try {
    if ($SetupAdmin) {
        & $phpExecutable @phpFlags (Join-Path $tokyoRoot 'setup_admin.php')
    } else {
        Write-Host "Tokyo Premium listener: ${ListenAddress}:$Port"
        if ($ListenAddress -eq '127.0.0.1') { Write-Host "Tokyo Premium: http://127.0.0.1:$Port" }
        Write-Host 'Keep this window open. Ctrl+C stops the server. Admin: /admin.php'
        & $phpExecutable @phpFlags -d display_errors=0 -d log_errors=1 -S "${ListenAddress}:$Port" -t $tokyoRoot (Join-Path $tokyoRoot 'tools\home-router.php')
    }
    if ($LASTEXITCODE -ne 0) { throw "PHP exited with code $LASTEXITCODE" }
} finally { Pop-Location }
