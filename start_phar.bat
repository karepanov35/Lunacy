@echo off
TITLE Lunacy (PocketMine-MP.phar)
cd /d %~dp0

set PHP_BINARY=

where /q php.exe
if %ERRORLEVEL%==0 (
	set PHP_BINARY=php
)

if exist bin\php\php.exe (
	set PHPRC=
	set PHP_BINARY=bin\php\php.exe
)

if "%PHP_BINARY%"=="" (
	echo [ОШИБКА] PHP не найден в PATH или в папке bin\php.
	pause
	exit /b 1
)

if not exist PocketMine-MP.phar (
	echo [ОШИБКА] Файл PocketMine-MP.phar не найден в этой папке.
	echo Соберите ядро: см. README или запустите build-phar.ps1
	pause
	exit /b 1
)

if exist bin\mintty.exe (
	start "" bin\mintty.exe -o Columns=88 -o Rows=32 -o AllowBlinking=0 -o FontQuality=3 -o Font="Consolas" -o FontHeight=10 -o CursorType=0 -o CursorBlinks=1 -h error -t "Lunacy" -i bin/pocketmine.ico -w max %PHP_BINARY% PocketMine-MP.phar --enable-ansi %*
) else (
	%PHP_BINARY% PocketMine-MP.phar %* || pause
)
