@echo off
setlocal
set "PHP=C:\xampp\php\php.exe"
set "PROJECT=%~dp0.."
cd /d "%PROJECT%"

if not exist "%PHP%" (
  echo XAMPP PHP not found at %PHP%
  echo Update the PHP path in scripts\xampp-php.bat
  exit /b 1
)

"%PHP%" %*
