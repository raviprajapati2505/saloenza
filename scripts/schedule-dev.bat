@echo off
REM Run Laravel scheduler every minute (appointment reminders, subscription expiry, renewal emails).
REM Keep this window open while developing locally on XAMPP.

cd /d "%~dp0.."
echo SalonOS scheduler — Ctrl+C to stop
:loop
C:\xampp\php\php.exe artisan schedule:run
timeout /t 60 /nobreak >nul
goto loop
