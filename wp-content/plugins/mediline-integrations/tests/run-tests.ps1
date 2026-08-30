$ErrorActionPreference = 'Stop'

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpPath = if ($phpCommand) { $phpCommand.Source } else { $null }

if (-not $phpPath) {
    $osPanelPhpRoot = 'C:\OSPanel\modules\php'
    if (Test-Path -LiteralPath $osPanelPhpRoot) {
        $candidate = Get-ChildItem -LiteralPath $osPanelPhpRoot -Filter php.exe -Recurse |
            Where-Object { $_.Directory.Name -match '^PHP_(\d+\.\d+)$' -and [version]$Matches[1] -ge [version]'8.0' } |
            Sort-Object { [version]($_.Directory.Name -replace '^PHP_', '') } |
            Select-Object -Last 1
        if ($candidate) {
            $phpPath = $candidate.FullName
        }
    }
}

if (-not $phpPath) {
    throw 'PHP 8.0+ was not found in PATH or C:\OSPanel\modules\php.'
}

$nodeCommand = Get-Command node -ErrorAction SilentlyContinue
if (-not $nodeCommand) {
    throw 'Node.js was not found in PATH.'
}

Write-Host "PHP: $phpPath"
& $phpPath (Join-Path $PSScriptRoot 'test-core.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& $phpPath (Join-Path $PSScriptRoot 'test-store-attribution.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& $phpPath (Join-Path $PSScriptRoot 'test-workflow.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& $phpPath (Join-Path $PSScriptRoot 'test-pap-v3.php')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Node: $($nodeCommand.Source)"
& $nodeCommand.Source (Join-Path $PSScriptRoot 'test-frontend.js')
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& $nodeCommand.Source (Join-Path $PSScriptRoot 'test-store-frontend.js')
exit $LASTEXITCODE
