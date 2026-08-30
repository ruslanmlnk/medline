$ErrorActionPreference = 'Stop'

$php = 'C:\OSPanel\modules\PHP\PHP_8.1\php.exe'
if (-not (Test-Path -LiteralPath $php)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCommand) { throw 'PHP 8.0+ is required.' }
    $php = $phpCommand.Source
}

& $php (Join-Path $PSScriptRoot 'test-pap-session-bridge.php')
exit $LASTEXITCODE
