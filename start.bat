@echo off
TITLE Lunacy v0.1 
cd /d %~dp0

set PHP_BINARY=

where /q php.exe
if %ERRORLEVEL%==0 (
	set PHP_BINARY=php
)

if exist bin\php\php.exe (
	rem Всегда используем локальный PHP, если он есть
	set PHPRC=""
	set PHP_BINARY=bin\php\php.exe
)

if "%PHP_BINARY%"=="" (
	echo [ОШИБКА] PHP не найден в PATH или в папке bin\php.
	echo Пожалуйста, установите PHP или скачайте его для PMMP.
	pause
)

:: Указываем путь к исходному коду вместо phar
if exist src\PocketMine.php (
	set POCKETMINE_FILE=src\PocketMine.php
) else (
	echo [ОШИБКА] src\PocketMine.php не найден!
	echo Убедитесь, что вы распаковали ядро в текущую папку.
	pause
)

:: Проверка наличия папки vendor
if not exist vendor\ (
	echo [ОШИБКА] Папка vendor не найдена! 
	echo Запустите 'composer install', чтобы скачать зависимости.
	pause
)

if exist bin\mintty.exe (
	start "" bin\mintty.exe -o Columns=88 -o Rows=32 -o AllowBlinking=0 -o FontQuality=3 -o Font="Consolas" -o FontHeight=10 -o CursorType=0 -o CursorBlinks=1 -h error -t "NetherGames-MP" -i bin/pocketmine.ico -w max %PHP_BINARY% %POCKETMINE_FILE% --enable-ansi %*
) else (
	%PHP_BINARY% %POCKETMINE_FILE% %* || pause
)