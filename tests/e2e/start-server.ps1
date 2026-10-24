$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$databasePath = Join-Path $PSScriptRoot 'runtime.sqlite'

if (-not $databasePath.StartsWith($repositoryRoot + [IO.Path]::DirectorySeparatorChar)) {
    throw 'Refusing to use an E2E database outside the repository.'
}

if (Test-Path -LiteralPath $databasePath) {
    Remove-Item -LiteralPath $databasePath -Force
}

New-Item -ItemType File -Path $databasePath | Out-Null
$env:APP_ENV = 'testing'
$env:APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
$env:APP_DEBUG = 'false'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $databasePath
$env:SESSION_DRIVER = 'file'
$env:CACHE_STORE = 'array'
$env:QUEUE_CONNECTION = 'sync'
$env:MAIL_MAILER = 'array'

Set-Location $repositoryRoot
php artisan migrate:fresh --force
if ($LASTEXITCODE -ne 0) {
    throw 'Disposable E2E migration failed.'
}
php artisan db:seed --force
if ($LASTEXITCODE -ne 0) {
    throw 'Disposable E2E seed failed.'
}
php -S 127.0.0.1:8011 -t public
