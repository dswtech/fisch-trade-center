$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$clusterPath = Join-Path $PSScriptRoot '.pgdata'
$logPath = Join-Path $PSScriptRoot 'postgres.log'
$port = 55432
$database = 'fisch_trade_benchmark'
$started = $false

function Invoke-HiddenProcess {
    param([string]$FilePath, [string[]]$Arguments)
    $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -WindowStyle Hidden -PassThru
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) { throw "$FilePath exited with code $($process.ExitCode)." }
}

if (-not $clusterPath.StartsWith($repositoryRoot + [IO.Path]::DirectorySeparatorChar)) {
    throw 'Refusing to create a PostgreSQL cluster outside the repository.'
}

$initdb = (Get-Command initdb -ErrorAction Stop).Source
$pgCtl = (Get-Command pg_ctl -ErrorAction Stop).Source
$createdb = (Get-Command createdb -ErrorAction Stop).Source

try {
    if (Test-Path -LiteralPath $clusterPath) { Remove-Item -LiteralPath $clusterPath -Recurse -Force }
    if (Test-Path -LiteralPath $logPath) { Remove-Item -LiteralPath $logPath -Force }
    Invoke-HiddenProcess $initdb @('-D', $clusterPath, '--auth=trust', '--username=postgres', '--encoding=UTF8', '--no-locale')
    Invoke-HiddenProcess $pgCtl @('-D', $clusterPath, '-l', $logPath, '-o', "`"-p $port -h 127.0.0.1`"", '-w', 'start')
    $started = $true
    Invoke-HiddenProcess $createdb @('-h', '127.0.0.1', '-p', "$port", '-U', 'postgres', $database)

    $env:APP_ENV = 'benchmark'
    $env:APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = "$port"
    $env:DB_DATABASE = $database
    $env:DB_USERNAME = 'postgres'
    $env:DB_PASSWORD = ''
    $env:SESSION_DRIVER = 'array'
    $env:CACHE_STORE = 'array'
    $env:QUEUE_CONNECTION = 'sync'

    Set-Location $repositoryRoot
    php artisan migrate:fresh --force
    php tests/benchmark/negotiation-concurrency.php
    php tests/benchmark/search-matrix.php
}
finally {
    if ($started) { Invoke-HiddenProcess $pgCtl @('-D', $clusterPath, '-m', 'fast', '-w', 'stop') }
    if (Test-Path -LiteralPath $clusterPath) { Remove-Item -LiteralPath $clusterPath -Recurse -Force }
    if (Test-Path -LiteralPath $logPath) { Remove-Item -LiteralPath $logPath -Force }
}
