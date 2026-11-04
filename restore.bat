@echo off
setlocal
set "APP_DIR=%~dp0"
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%APP_DIR%restore.ps1" %*
exit /b %ERRORLEVEL%
