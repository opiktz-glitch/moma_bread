@echo off
setlocal
set "TASK_NAME=Moma Bread Nightly Backup"
set "BACKUP_SCRIPT=%~dp0backup.bat"

if not exist "%BACKUP_SCRIPT%" (
    echo ERROR: backup.bat was not found.
    exit /b 1
)

schtasks /Create /SC DAILY /ST 23:00 /TN "%TASK_NAME%" /TR "%BACKUP_SCRIPT%" /F /IT
if errorlevel 1 (
    echo ERROR: Could not create the scheduled task.
    exit /b 1
)

echo Scheduled daily backup at 23:00 for the current Windows user.
echo Docker Desktop must be running and that user must be signed in.
schtasks /Query /TN "%TASK_NAME%" /FO LIST
