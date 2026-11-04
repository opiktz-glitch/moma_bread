param(
    [string]$SqlFile,
    [string]$ImageFile
)

$ErrorActionPreference = 'Stop'
$appDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$backupScript = Join-Path $appDir 'backup.ps1'
$webWasStopped = $false
$restoreSqlInContainer = '/tmp/moma-bread-restore.sql'
$stage = Join-Path $env:TEMP ("moma-bread-restore-" + [Guid]::NewGuid().ToString('N'))

function Invoke-Compose {
    param([string[]]$Arguments)
    & docker compose @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose $($Arguments -join ' ') failed with exit code $LASTEXITCODE."
    }
}

function Get-UploadsPath {
    $configText = & docker compose config --format json
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not read the resolved Docker Compose configuration.'
    }
    $config = ($configText -join "`n") | ConvertFrom-Json
    $uploadMount = $config.services.web.volumes |
        Where-Object { $_.target -eq '/var/www/html/assets/img/uploads' -and $_.type -eq 'bind' } |
        Select-Object -First 1
    if (-not $uploadMount -or -not $uploadMount.source) {
        throw 'The upload bind mount was not found in the Compose configuration.'
    }
    $resolvedPath = [System.IO.Path]::GetFullPath($uploadMount.source).TrimEnd('\', '/')
    $driveRoot = [System.IO.Path]::GetPathRoot($resolvedPath).TrimEnd('\', '/')
    $resolvedAppDir = [System.IO.Path]::GetFullPath($appDir).TrimEnd('\', '/')
    if ($resolvedPath -eq $driveRoot -or $resolvedPath -eq $resolvedAppDir -or
        $resolvedAppDir.StartsWith($resolvedPath + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Unsafe upload bind mount target for restore: $resolvedPath"
    }
    return $resolvedPath
}

Push-Location $appDir
try {
    if (-not (Test-Path -LiteralPath (Join-Path $appDir '.env'))) {
        throw '.env is missing.'
    }
    & $env:ComSpec /d /c 'docker info >nul 2>nul'
    if ($LASTEXITCODE -ne 0) {
        throw 'Docker Desktop engine is unavailable.'
    }

    if ($SqlFile) {
        $SqlFile = (Resolve-Path -LiteralPath $SqlFile).Path
        if ([System.IO.Path]::GetExtension($SqlFile) -ne '.sql') {
            throw 'SqlFile must be a .sql backup.'
        }
        if (-not $ImageFile) {
            $candidate = Join-Path (Split-Path -Parent $SqlFile) (([System.IO.Path]::GetFileNameWithoutExtension($SqlFile)) + '-images.zip')
            if (Test-Path -LiteralPath $candidate) {
                $ImageFile = $candidate
            }
        }
    }
    if ($ImageFile) {
        $ImageFile = (Resolve-Path -LiteralPath $ImageFile).Path
        if ([System.IO.Path]::GetExtension($ImageFile) -ne '.zip') {
            throw 'ImageFile must be a .zip archive.'
        }
    }
    if (-not $SqlFile -and -not $ImageFile) {
        throw 'Specify -SqlFile, -ImageFile, or both.'
    }

    if ($ImageFile) {
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        $zip = [System.IO.Compression.ZipFile]::OpenRead($ImageFile)
        try {
            foreach ($entry in $zip.Entries) {
                $destination = [System.IO.Path]::GetFullPath((Join-Path $stage $entry.FullName))
                $stagePrefix = [System.IO.Path]::GetFullPath($stage) + [System.IO.Path]::DirectorySeparatorChar
                if (-not $destination.StartsWith($stagePrefix, [StringComparison]::OrdinalIgnoreCase)) {
                    throw "Unsafe path in image archive: $($entry.FullName)"
                }
            }
        }
        finally {
            $zip.Dispose()
        }
    }

    Write-Output 'Creating a safety backup before restore...'
    & powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File $backupScript
    if ($LASTEXITCODE -ne 0) {
        throw 'Safety backup failed; restore was cancelled.'
    }

    $confirmation = Read-Host 'This will replace selected database data and/or uploaded images. Type RESTORE to continue'
    if ($confirmation -cne 'RESTORE') {
        throw 'Restore cancelled by user.'
    }

    if ($SqlFile) {
        $dbContainer = (& docker compose ps -q db).Trim()
        if ($LASTEXITCODE -ne 0 -or -not $dbContainer) {
            throw 'MariaDB container is not running.'
        }
    }
    if ($ImageFile) {
        $uploadPath = Get-UploadsPath
        New-Item -ItemType Directory -Force -Path $uploadPath | Out-Null
    }

    if ($SqlFile -or $ImageFile) {
        Invoke-Compose @('stop', 'web')
        $webWasStopped = $true
    }

    if ($SqlFile) {
        & docker cp $SqlFile "${dbContainer}:$restoreSqlInContainer"
        if ($LASTEXITCODE -ne 0) {
            throw 'Copying the SQL backup into MariaDB failed.'
        }
        Invoke-Compose @('exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MARIADB_PASSWORD" mariadb -u"$MARIADB_USER" "$MARIADB_DATABASE" < /tmp/moma-bread-restore.sql')
        Write-Output "Database restored from $SqlFile"
    }

    if ($ImageFile) {
        New-Item -ItemType Directory -Force -Path $stage | Out-Null
        [System.IO.Compression.ZipFile]::ExtractToDirectory($ImageFile, $stage)
        Get-ChildItem -LiteralPath $uploadPath -Force |
            Remove-Item -Recurse -Force
        Get-ChildItem -LiteralPath $stage -Force |
            Copy-Item -Destination $uploadPath -Recurse -Force
        Write-Output "Uploaded images restored to $uploadPath"
    }

    Write-Output 'Restore completed. Review the site and image files.'
}
catch {
    Write-Error $_
    exit 1
}
finally {
    if ($webWasStopped) {
        & docker compose start web
    }
    if (Test-Path -LiteralPath $stage) {
        Remove-Item -LiteralPath $stage -Recurse -Force
    }
    if (Test-Path -LiteralPath (Join-Path $appDir '.env')) {
        & docker compose exec -T db sh -c "rm -f '$restoreSqlInContainer'" *> $null
    }
    Pop-Location
}
