@echo off
setlocal
set "APP_DIR=%~dp0"
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%APP_DIR%backup.ps1"
if errorlevel 1 (
    echo Backup FAILED. Review backups\backup.log.
    exit /b 1
)
echo Backup completed. Files are in "%APP_DIR%backups".
exit /b 0
