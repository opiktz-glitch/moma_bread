$ErrorActionPreference = 'Stop'
$appDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$backupDir = Join-Path $appDir 'backups'
$logPath = Join-Path $backupDir 'backup.log'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$baseName = "moma-bread-$stamp"
$sqlName = "$baseName.sql"
$imageName = "$baseName-images.zip"
$containerSql = "/tmp/$sqlName"
$sqlTemp = Join-Path $backupDir "$sqlName.partial"
$imageTemp = Join-Path $backupDir "$imageName.partial"
$sqlFinal = Join-Path $backupDir $sqlName
$imageFinal = Join-Path $backupDir $imageName
$stage = Join-Path $env:TEMP $baseName

function Write-BackupLog {
    param([string]$Message)
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
    Write-Output $line
    Add-Content -LiteralPath $logPath -Value $line -Encoding UTF8
}

function Invoke-Compose {
    param([string[]]$Arguments)
    & docker compose @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose $($Arguments -join ' ') failed with exit code $LASTEXITCODE."
    }
}

Push-Location $appDir
try {
    New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
    if (-not (Test-Path -LiteralPath (Join-Path $appDir '.env'))) {
        throw '.env is missing.'
    }
    & docker info *> $null
    if ($LASTEXITCODE -ne 0) {
        throw 'Docker Desktop engine is unavailable.'
    }

    $dbContainer = (& docker compose ps -q db).Trim()
    if ($LASTEXITCODE -ne 0 -or -not $dbContainer) {
        throw 'MariaDB container is not running. Start the application first.'
    }
    $webContainer = (& docker compose ps -q web).Trim()
    if ($LASTEXITCODE -ne 0 -or -not $webContainer) {
        throw 'Web container is not running. Start the application first.'
    }

    Write-BackupLog "Starting backup $baseName in $backupDir"

    $dumpCommand = 'MYSQL_PWD="$MARIADB_PASSWORD" mariadb-dump --single-transaction --skip-lock-tables -u"$MARIADB_USER" "$MARIADB_DATABASE" > ' + $containerSql
    Invoke-Compose @('exec', '-T', 'db', 'sh', '-c', $dumpCommand)
    & docker cp "${dbContainer}:$containerSql" $sqlTemp
    if ($LASTEXITCODE -ne 0) {
        throw 'Copying the SQL dump from MariaDB failed.'
    }
    if (-not (Test-Path -LiteralPath $sqlTemp) -or (Get-Item -LiteralPath $sqlTemp).Length -lt 100) {
        throw 'SQL dump is missing or unexpectedly small.'
    }

    New-Item -ItemType Directory -Force -Path $stage | Out-Null
    & docker cp "${webContainer}:/var/www/html/assets/img/uploads/." $stage
    if ($LASTEXITCODE -ne 0) {
        throw 'Copying uploaded images from the web container failed.'
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $stage,
        $imageTemp,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )
    $zip = [System.IO.Compression.ZipFile]::OpenRead($imageTemp)
    $zip.Dispose()

    Move-Item -LiteralPath $sqlTemp -Destination $sqlFinal
    Move-Item -LiteralPath $imageTemp -Destination $imageFinal
    Write-BackupLog "Created $sqlName ($((Get-Item $sqlFinal).Length) bytes)"
    Write-BackupLog "Created $imageName ($((Get-Item $imageFinal).Length) bytes)"

    $cutoff = (Get-Date).AddDays(-30)
    Get-ChildItem -LiteralPath $backupDir -File -Filter 'moma-bread-*.sql' |
        Where-Object { $_.LastWriteTime -lt $cutoff } |
        ForEach-Object {
            $oldBase = [System.IO.Path]::GetFileNameWithoutExtension($_.Name)
            Remove-Item -LiteralPath $_.FullName -Force
            $oldImages = Join-Path $backupDir "$oldBase-images.zip"
            if (Test-Path -LiteralPath $oldImages) {
                Remove-Item -LiteralPath $oldImages -Force
            }
            Write-BackupLog "Removed expired backup pair $oldBase"
        }

    Write-BackupLog 'Backup completed successfully.'
}
catch {
    Write-BackupLog "ERROR: $($_.Exception.Message)"
    if (Test-Path -LiteralPath $sqlFinal) {
        Remove-Item -LiteralPath $sqlFinal -Force
    }
    if (Test-Path -LiteralPath $imageFinal) {
        Remove-Item -LiteralPath $imageFinal -Force
    }
    exit 1
}
finally {
    if ($stage -and (Test-Path -LiteralPath $stage)) {
        Remove-Item -LiteralPath $stage -Recurse -Force
    }
    if (Test-Path -LiteralPath $sqlTemp) {
        Remove-Item -LiteralPath $sqlTemp -Force
    }
    if (Test-Path -LiteralPath $imageTemp) {
        Remove-Item -LiteralPath $imageTemp -Force
    }
    if (Test-Path -LiteralPath (Join-Path $appDir '.env')) {
        & docker compose exec -T db sh -c "rm -f '$containerSql'" *> $null
    }
    Pop-Location
}
