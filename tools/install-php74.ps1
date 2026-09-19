[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$pluginRoot = Split-Path -Parent $PSScriptRoot
$toolRoot = Join-Path $pluginRoot '.cliapwo-tools'
$downloadRoot = Join-Path $toolRoot 'downloads'
$runtimeRoot = Join-Path $toolRoot 'php-7.4.33'
$archivePath = Join-Path $downloadRoot 'php-7.4.33-Win32-vc15-x64.zip'
$downloadUrl = 'https://windows.php.net/downloads/releases/archives/php-7.4.33-Win32-vc15-x64.zip'
$expectedHash = 'CDBB85B45F38F282F05764CA08648B5F92DB99C75B2FB3848EB4A559F6553B48'

New-Item -ItemType Directory -Force -Path $downloadRoot | Out-Null

if (-not (Test-Path -LiteralPath $archivePath)) {
	Invoke-WebRequest -Uri $downloadUrl -OutFile $archivePath -UseBasicParsing
}

$actualHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $archivePath).Hash

if ($actualHash -ne $expectedHash) {
	throw "The PHP 7.4 archive checksum did not match. Expected $expectedHash, received $actualHash."
}

if (-not (Test-Path -LiteralPath (Join-Path $runtimeRoot 'php.exe'))) {
	New-Item -ItemType Directory -Force -Path $runtimeRoot | Out-Null
	Expand-Archive -LiteralPath $archivePath -DestinationPath $runtimeRoot -Force
}

$phpIni = Join-Path $runtimeRoot 'php.ini'

if (-not (Test-Path -LiteralPath $phpIni)) {
	$ini = @'
[PHP]
extension_dir="ext"
extension=curl
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
date.timezone=UTC
memory_limit=512M
'@
	Set-Content -LiteralPath $phpIni -Value $ini -Encoding ASCII
}

$phpPath = Join-Path $runtimeRoot 'php.exe'
& $phpPath --version
Write-Output $phpPath
