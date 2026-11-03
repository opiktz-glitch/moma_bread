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
docker compose -f compose.shared-db.yaml config --quiet
if errorlevel 1 goto update_failed

echo Building and starting Moma Bread...
docker compose -f compose.shared-db.yaml up -d --build
if errorlevel 1 goto update_failed

echo.
echo Moma Bread update completed. Container status:
docker compose -f compose.shared-db.yaml ps
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
