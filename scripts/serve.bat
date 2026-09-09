@echo off
setlocal
call "%~dp0xampp-php.bat" artisan serve %*
