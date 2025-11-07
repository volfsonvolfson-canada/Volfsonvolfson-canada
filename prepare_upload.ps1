# Скрипт для подготовки файлов к загрузке на хостинг
# Создает папку UPLOAD_TO_HOSTING со всеми необходимыми файлами

$sourceDir = "c:\Users\volfs\.cursor"
$targetDir = "c:\Users\volfs\.cursor\UPLOAD_TO_HOSTING"

# Список файлов для загрузки
$filesToUpload = @(
    # HTML - публичные страницы
    "index.html",
    "about.html",
    "login.html",
    "massage.html",
    "yoga.html",
    "special.html",
    "order.html",
    "booking-confirmation.html",
    "room-basement.html",
    "room-first-double.html",
    "room-first-twin.html",
    "room-second-suite.html",
    
    # HTML - админ
    "admin.html",
    "admin-login.html",
    "admin-bookings.html",
    "admin-content.html",
    
    # CSS
    "styles.css",
    "common.css",
    "admin.css",
    "dashboard.css",
    
    # JavaScript - публичные
    "script.js",
    "booking.js",
    "payment.js",
    "utils.js",
    "auth.js",
    "availability-calendar.js",
    "theme-init.js",
    "messages.js",
    "dashboard.js",
    
    # JavaScript - админ
    "admin.js",
    "admin-bookings.js",
    "admin-content.js",
    
    # PHP - основные
    "api.php",
    "common.php",
    "config.php",
    "booking_api.php",
    "floorplan_api.php",
    "payment_service.php",
    "email_service.php",
    "airbnb_sync.php",
    "ical_parser.php",
    "upload_image.php",
    "stripe_webhook.php",
    "cron_sync_airbnb.php",
    
    # PHP - утилиты
    "init_database.php",
    "create_table.php",
    "add_floorplan_table.php",
    "add_hero_fields.php"
)

# Создаем целевую папку
if (Test-Path $targetDir) {
    Remove-Item $targetDir -Recurse -Force
}
New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
New-Item -ItemType Directory -Path "$targetDir\templates" -Force | Out-Null

Write-Host "Копирование файлов..." -ForegroundColor Green

# Копируем файлы
$copiedCount = 0
foreach ($file in $filesToUpload) {
    $sourcePath = Join-Path $sourceDir $file
    if (Test-Path $sourcePath) {
        Copy-Item $sourcePath $targetDir -Force
        Write-Host "✓ $file" -ForegroundColor Gray
        $copiedCount++
    } else {
        Write-Host "✗ $file (не найден)" -ForegroundColor Yellow
    }
}

# Копируем templates
$templates = @(
    "templates\email_booking_cancelled.html",
    "templates\email_booking_confirmation.html",
    "templates\email_booking_confirmed.html",
    "templates\email_booking_request.html"
)

foreach ($template in $templates) {
    $sourcePath = Join-Path $sourceDir $template
    if (Test-Path $sourcePath) {
        Copy-Item $sourcePath "$targetDir\templates" -Force
        Write-Host "✓ $template" -ForegroundColor Gray
        $copiedCount++
    }
}

Write-Host "`nDone! Copied files: $copiedCount" -ForegroundColor Green
Write-Host "`nUpload folder: $targetDir" -ForegroundColor Cyan
Write-Host "`nNext steps:" -ForegroundColor Yellow
Write-Host "1. Upload all files from UPLOAD_TO_HOSTING to public_html on hosting" -ForegroundColor White
Write-Host "2. Check config.php - DB settings and API keys" -ForegroundColor White
Write-Host "3. Make sure assets/ folder exists" -ForegroundColor White
Write-Host "4. Check file permissions (644 for files, 755 for folders)" -ForegroundColor White

