# Build PocketMine-MP.phar (requires: composer install --no-dev, vendor/ present).
# Output: PocketMine-MP.phar in project root.

[CmdletBinding()]
param(
	[string]$Php = ""
)

$ErrorActionPreference = "Stop"
$root = $PSScriptRoot

if($Php -ne ""){
	$phpExe = $Php
}elseif(Test-Path "$root\bin\php\php.exe"){
	$env:PHPRC = ""
	$phpExe = "$root\bin\php\php.exe"
}else{
	$cmd = Get-Command php -ErrorAction SilentlyContinue
	if(-not $cmd){ throw "PHP not found. Install bin\php or add php to PATH." }
	$phpExe = $cmd.Source
}

if(-not (Test-Path "$root\vendor\autoload.php")){
	throw "vendor/autoload.php missing. Run: .\composer.ps1 install --no-dev"
}
if(Test-Path "$root\vendor\phpunit"){
	throw "Dev deps present. Run: .\composer.ps1 install --no-dev"
}

$out = Join-Path $root "PocketMine-MP.phar"
Write-Host "Building $out ..."
& $phpExe "-dphar.readonly=0" "$root\build\server-phar.php" --out $out
exit $LASTEXITCODE
