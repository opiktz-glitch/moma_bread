@echo off
setlocal
set "APP_DIR=%~dp0"

pushd "%APP_DIR%" || (
    echo ERROR: Cannot open the application folder.
    exit /b 1
)

if not exist ".env" (
    echo ERROR: .env was not found in %APP_DIR%
    echo Create .env and configure the database before updating.
    popd
    exit /b 1
)

docker info >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker Desktop is not running or the Docker engine is unavailable.
    popd
    exit /b 1
)

for /f "delims=" %%B in ('git branch --show-current 2^>nul') do set "CURRENT_BRANCH=%%B"
if not "%CURRENT_BRANCH%"=="main" (
    echo ERROR: Expected branch main, found "%CURRENT_BRANCH%".
    popd
    exit /b 1
)

git diff --quiet
if errorlevel 1 goto local_changes
git diff --cached --quiet
if errorlevel 1 goto local_changes

echo Pulling latest code from origin/main...
git pull --ff-only origin main
if errorlevel 1 goto update_failed

echo Validating Docker Compose configuration...
docker compose config --quiet
if errorlevel 1 goto update_failed

if exist "%APP_DIR%.backups-migrated" goto backups_ready
echo Migrating application backups from the existing web container...
set "WEB_CONTAINER="
for /f "delims=" %%C in ('docker compose ps -q web 2^>nul') do set "WEB_CONTAINER=%%C"
if not defined WEB_CONTAINER goto migration_failed
if not exist "%APP_DIR%backups\" mkdir "%APP_DIR%backups"
if errorlevel 1 goto migration_failed
docker cp "%WEB_CONTAINER%:/var/www/html/backups/." "%APP_DIR%backups"
if errorlevel 1 goto migration_failed
type nul > "%APP_DIR%.backups-migrated"
if errorlevel 1 goto migration_failed

:backups_ready
if not exist "%APP_DIR%.uploads-migrated" goto migrate_uploads
if not exist "%APP_DIR%Uploads\" goto migration_failed

goto uploads_ready

:migrate_uploads
echo Migrating uploaded images from the existing web container...
set "WEB_CONTAINER="
for /f "delims=" %%C in ('docker compose ps -q web 2^>nul') do set "WEB_CONTAINER=%%C"
if not defined WEB_CONTAINER goto migration_failed

if exist "%APP_DIR%Uploads\" goto uploads_directory_ready
mkdir "%APP_DIR%Uploads"
if errorlevel 1 goto migration_failed

:uploads_directory_ready
docker cp "%WEB_CONTAINER%:/var/www/html/assets/img/uploads/." "%APP_DIR%Uploads"
if errorlevel 1 goto migration_failed
type nul > "%APP_DIR%.uploads-migrated"
if errorlevel 1 goto migration_failed

:uploads_ready
echo Building and starting Moma Bread...
docker compose up -d --build
if errorlevel 1 goto update_failed

echo.
echo Moma Bread update completed. Container status:
docker compose ps
popd
exit /b 0

:local_changes
echo ERROR: Tracked local changes detected. Commit or safely save them before updating.
popd
exit /b 1

:update_failed
echo ERROR: Update failed. Review the message above; the database volume was not removed.
popd
exit /b 1

:migration_failed
echo ERROR: Could not copy uploads from the existing web container.
echo The old container and its Docker data have not been removed.
popd
exit /b 1
