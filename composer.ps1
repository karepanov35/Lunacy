# Lunacy: run Composer without a global `composer` command (uses local PHP + composer.phar).
# Usage: .\composer.ps1 install
#        .\composer.ps1 -Php "C:\path\to\php.exe" update

[CmdletBinding(PositionalBinding = $false)]
param(
	[string]$Php = "",
	[Parameter(ValueFromRemainingArguments = $true)]
	[string[]]$ComposerArgs
)

$ErrorActionPreference = "Stop"

function Resolve-PhpBinary {
	param([string]$Explicit)
	if($Explicit -ne ""){
		return $Explicit
	}
	if(Test-Path (Join-Path $PSScriptRoot "bin\php\php.exe")){
		$env:PHPRC = ""
		return (Join-Path $PSScriptRoot "bin\php\php.exe")
	}
	$cmd = Get-Command php -ErrorAction SilentlyContinue
	if($cmd){
		return $cmd.Source
	}
	Write-Error "PHP not found. Install PHP 8.1+ or place php.exe under bin\php\php.exe (see https://doc.pmmp.io/en/rtfd/installation.html)."
	exit 1
}

$phpExe = Resolve-PhpBinary -Explicit $Php
$composerPhar = Join-Path $PSScriptRoot "composer.phar"
$composerUrl = "https://getcomposer.org/download/latest-stable/composer.phar"

if(-not (Test-Path $composerPhar)){
	Write-Host "Downloading Composer to $composerPhar ..."
	try {
		[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
		Invoke-WebRequest -Uri $composerUrl -OutFile $composerPhar -UseBasicParsing
	} catch {
		Write-Error "Failed to download Composer: $_"
		exit 1
	}
}

& $phpExe $composerPhar @ComposerArgs
exit $LASTEXITCODE
