@echo off
setlocal
echo Starting Laravel (XAMPP PHP) on http://127.0.0.1:8000
echo Starting Vite on http://127.0.0.1:5173
echo.
start "SalonOS API" cmd /k "%~dp0serve.bat"
npm run dev
