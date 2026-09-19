[CmdletBinding()]
param(
	[string] $DbUser = $env:CLIAPWO_TEST_DB_USER,
	[string] $DbPassword = $env:CLIAPWO_TEST_DB_PASSWORD,
	[string] $DbHost = $env:CLIAPWO_TEST_DB_HOST,

	[string] $Php82 = $env:CLIAPWO_PHP82,
	[string] $Php85 = $env:CLIAPWO_PHP85
)

$ErrorActionPreference = 'Stop'

if (-not $DbUser -or -not $DbHost) {
	throw 'Set CLIAPWO_TEST_DB_USER, CLIAPWO_TEST_DB_PASSWORD, and CLIAPWO_TEST_DB_HOST, or pass the matching parameters.'
}

$pluginRoot = Split-Path -Parent $PSScriptRoot
$cacheRoot = Join-Path $pluginRoot '.cliapwo-test-cache'
$php74 = (& (Join-Path $PSScriptRoot 'install-php74.ps1') | Select-Object -Last 1)

function Resolve-LocalPhp {
	param(
		[string] $RequestedVersion,
		[string] $ExplicitPath
	)

	if ($ExplicitPath) {
		if (-not (Test-Path -LiteralPath $ExplicitPath -PathType Leaf)) {
			throw "The requested PHP $RequestedVersion executable does not exist: $ExplicitPath"
		}
		return (Resolve-Path $ExplicitPath).Path
	}

	$serviceRoot = Join-Path $env:APPDATA 'Local\lightning-services'
	$candidate = Get-ChildItem -LiteralPath $serviceRoot -Directory -Filter "php-$RequestedVersion*" -ErrorAction SilentlyContinue |
		Sort-Object Name -Descending |
		ForEach-Object { Join-Path $_.FullName 'bin\win64\php.exe' } |
		Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } |
		Select-Object -First 1

	if (-not $candidate) {
		throw "PHP $RequestedVersion was not found. Pass -Php$($RequestedVersion.Replace('.', '')) or set CLIAPWO_PHP$($RequestedVersion.Replace('.', ''))."
	}

	return (Resolve-Path $candidate).Path
}

$Php82 = Resolve-LocalPhp -RequestedVersion '8.2' -ExplicitPath $Php82
$Php85 = Resolve-LocalPhp -RequestedVersion '8.5' -ExplicitPath $Php85
$matrix = @(
	@{ Requested = '6.0'; Php = $php74; Ini = $null },
	@{ Requested = '7.0'; Php = $Php82; Ini = 'generated' },
	@{ Requested = 'latest'; Php = $Php85; Ini = 'generated' }
)

function Resolve-WordPressOffer {
	param([string] $Requested)

	$offers = (Invoke-RestMethod -Uri 'https://api.wordpress.org/core/version-check/1.7/' -UseBasicParsing).offers
	if ('latest' -eq $Requested) {
		return $offers | Select-Object -First 1
	}

	$offer = $offers | Where-Object { $_.version -like "$Requested.*" } | Select-Object -First 1
	if (-not $offer) {
		throw "WordPress.org did not return a maintained $Requested.x release."
	}

	return $offer
}

function Invoke-DatabaseScript {
	param(
		[string] $Php,
		[string] $Ini,
		[string] $Database,
		[string] $Operation
	)

	if ($Database -notmatch '^cliapwo_test_[a-z0-9_]+$') {
		throw "Refusing database operation for unsafe database name: $Database"
	}

	$scriptPath = Join-Path $cacheRoot 'database-operation.php'
	$payload = @"
<?php
`$host = getenv('CLIAPWO_TEST_DB_HOST');
`$port = 3306;
if (false !== strpos(`$host, ':')) {
	list(`$host, `$port) = explode(':', `$host, 2);
	`$port = (int) `$port;
}
`$database = getenv('CLIAPWO_TEST_DB_NAME');
if (! preg_match('/^cliapwo_test_[a-z0-9_]+$/', `$database)) {
	fwrite(STDERR, "Unsafe database name.\n");
	exit(2);
}
`$mysqli = new mysqli(`$host, getenv('CLIAPWO_TEST_DB_USER'), getenv('CLIAPWO_TEST_DB_PASSWORD'), '', `$port);
if (`$mysqli->connect_errno) {
	fwrite(STDERR, `$mysqli->connect_error . "\n");
	exit(3);
}
`$verb = getenv('CLIAPWO_TEST_DB_OPERATION') === 'create' ? 'CREATE DATABASE' : 'DROP DATABASE IF EXISTS';
`$suffix = getenv('CLIAPWO_TEST_DB_OPERATION') === 'create' ? ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' : '';
if (! `$mysqli->query(`$verb . ' `' . `$database . '`' . `$suffix)) {
	fwrite(STDERR, `$mysqli->error . "\n");
	exit(4);
}
"@
	Set-Content -LiteralPath $scriptPath -Value $payload -Encoding UTF8
	$env:CLIAPWO_TEST_DB_HOST = $DbHost
	$env:CLIAPWO_TEST_DB_USER = $DbUser
	$env:CLIAPWO_TEST_DB_PASSWORD = $DbPassword
	$env:CLIAPWO_TEST_DB_NAME = $Database
	$env:CLIAPWO_TEST_DB_OPERATION = $Operation
	if ($Ini) {
		& $Php -c $Ini $scriptPath
	} else {
		& $Php $scriptPath
	}
	if ($LASTEXITCODE -ne 0) {
		throw "Database $Operation failed for $Database."
	}
}

function Install-WordPressTestTree {
	param([object] $Offer)

	$version = $Offer.version
	$versionRoot = Join-Path $cacheRoot "wordpress-$version"
	$coreRoot = Join-Path $versionRoot 'wordpress'
	$developRoot = Join-Path $versionRoot "wordpress-develop-$version"

	if (-not (Test-Path -LiteralPath (Join-Path $coreRoot 'wp-settings.php'))) {
		$coreArchive = Join-Path $cacheRoot "wordpress-$version.zip"
		Invoke-WebRequest -Uri $Offer.download -OutFile $coreArchive -UseBasicParsing
		New-Item -ItemType Directory -Force -Path $versionRoot | Out-Null
		Expand-Archive -LiteralPath $coreArchive -DestinationPath $versionRoot -Force
	}

	if (-not (Test-Path -LiteralPath (Join-Path $developRoot 'tests\phpunit\includes\bootstrap.php'))) {
		$developArchive = Join-Path $cacheRoot "wordpress-develop-$version.zip"
		Invoke-WebRequest -Uri "https://github.com/WordPress/wordpress-develop/archive/refs/tags/$version.zip" -OutFile $developArchive -UseBasicParsing
		Expand-Archive -LiteralPath $developArchive -DestinationPath $versionRoot -Force
	}

	return @{ Core = $coreRoot; Develop = $developRoot; Tests = (Join-Path $developRoot 'tests\phpunit') }
}

New-Item -ItemType Directory -Force -Path $cacheRoot | Out-Null

foreach ($entry in $matrix) {
	if ($entry.Ini) {
		$testIni = Join-Path $cacheRoot ("php-{0}-test.ini" -f $entry.Requested.Replace('.', '-'))
		$extensionDir = (Join-Path (Split-Path -Parent $entry.Php) 'ext').Replace('\', '/')
		$testIniContent = @"
[PHP]
extension_dir="$extensionDir"
extension=curl
extension=fileinfo
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
date.timezone=UTC
memory_limit=512M
"@
		Set-Content -LiteralPath $testIni -Value $testIniContent -Encoding ASCII
		$entry.Ini = $testIni
	}

	$offer = Resolve-WordPressOffer -Requested $entry.Requested
	$tree = Install-WordPressTestTree -Offer $offer
	$database = ('cliapwo_test_{0}_{1}' -f ($offer.version -replace '\.', '_'), ([guid]::NewGuid().ToString('N').Substring(0, 8)))
	$configPath = Join-Path $tree.Develop 'wp-tests-config.php'
	$databaseCreated = $false
	$corePath = ((Resolve-Path $tree.Core).Path -replace '\\', '/') + '/'
	$phpBinaryForConfig = (Resolve-Path $entry.Php).Path.Replace('\', '/')
	if ($entry.Ini) {
		$phpBinaryForConfig += ' -c ' + (Resolve-Path $entry.Ini).Path.Replace('\', '/')
	}
	$config = @"
<?php
define('ABSPATH', '$corePath');
define('WP_DEFAULT_THEME', '');
define('WP_DEBUG', true);
define('DB_NAME', '$database');
define('DB_USER', '$($DbUser.Replace("'", "\\'"))');
define('DB_PASSWORD', '$($DbPassword.Replace("'", "\\'"))');
define('DB_HOST', '$($DbHost.Replace("'", "\\'"))');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
`$table_prefix = 'wptests_';
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'SignoffFlow Tests');
define('WP_PHP_BINARY', '$phpBinaryForConfig');
define('WPLANG', '');
"@
	Set-Content -LiteralPath $configPath -Value $config -Encoding UTF8

	try {
		Invoke-DatabaseScript -Php $entry.Php -Ini $entry.Ini -Database $database -Operation 'create'
		$databaseCreated = $true
		$env:WP_TESTS_DIR = $tree.Tests
		if ($entry.Ini) {
			$phpVersion = & $entry.Php -c $entry.Ini -r 'echo PHP_VERSION;'
			Write-Host "Testing WordPress $($offer.version) with $phpVersion"
			& $entry.Php -c $entry.Ini (Join-Path $pluginRoot 'vendor\bin\phpunit') --configuration (Join-Path $pluginRoot 'phpunit.xml.dist')
		} else {
			$phpVersion = & $entry.Php -r 'echo PHP_VERSION;'
			Write-Host "Testing WordPress $($offer.version) with $phpVersion"
			& $entry.Php (Join-Path $pluginRoot 'vendor\bin\phpunit') --configuration (Join-Path $pluginRoot 'phpunit.xml.dist')
		}
		if ($LASTEXITCODE -ne 0) {
			throw "PHPUnit failed for WordPress $($offer.version)."
		}
	}
	finally {
		if ($databaseCreated) {
			Invoke-DatabaseScript -Php $entry.Php -Ini $entry.Ini -Database $database -Operation 'drop'
		}
		Remove-Item -LiteralPath $configPath -Force -ErrorAction SilentlyContinue
	}
}
