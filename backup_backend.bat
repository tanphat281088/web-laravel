@echo off
setlocal

REM ====== ĐƯỜNG DẪN CỦA BẠN ======
set "PROJECT_DIR=H:\Desktop2\Final Software\Laravel-ReactJS-QuanLyKho"
REM Thử tự tìm mysqldump; nếu không có, dùng path Laragon phổ biến
for %%P in (mysqldump.exe) do set "MYSQLDUMP=%%~$PATH:P"
if "%MYSQLDUMP%"=="" set "MYSQLDUMP=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe"

REM ====== DB CONFIG ======
set "DB_NAME=laravel_react_ql_khohang"
set "DB_USER=root"
set "DB_PASS="

REM ====== CHUẨN BỊ ======
set "BACKUP_DIR=%PROJECT_DIR%\_backups"
for /f %%i in ('powershell -NoP -C "(Get-Date).ToString(\"yyyyMMdd_HHmm\")"') do set "TS=%%i"
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

pushd "%PROJECT_DIR%" 2>nul
if errorlevel 1 (
  echo [ERROR] PROJECT_DIR khong ton tai: %PROJECT_DIR%
  goto :END
)

REM ====== GIT SNAPSHOT (nếu có .git) ======
if exist ".git" (
  git add -A
  git commit -m "backup: %TS%" >nul 2>&1
  git branch "backup-%TS%" >nul 2>&1
  git tag "v-backup-%TS%" >nul 2>&1
  echo [OK] Git snapshot: backup-%TS% / v-backup-%TS%
) else (
  echo [WARN] Khong thay .git -> bo qua Git snapshot.
)

REM ====== DUMP DB ======
if exist "%MYSQLDUMP%" (
  "%MYSQLDUMP%" -u %DB_USER% -p%DB_PASS% %DB_NAME% > "%BACKUP_DIR%\db_%TS%.sql"
  if errorlevel 1 ( echo [WARN] Dump DB loi. Kiem tra user/pass DB. ) else ( echo [OK] Dump DB: %BACKUP_DIR%\db_%TS%.sql )
) else (
  echo [WARN] Khong tim thay mysqldump: %MYSQLDUMP%
)

REM ====== ZIP .env + storage (neu co) ======
if exist ".env" (
  powershell -NoLogo -NoProfile -Command ^
    "Compress-Archive -Path '.env','storage\*' -DestinationPath '%BACKUP_DIR%\env_storage_%TS%.zip' -Force" ^
    && echo [OK] Zip env+storage: %BACKUP_DIR%\env_storage_%TS%.zip ^
    || echo [WARN] Nen env/storage loi.
) else (
  echo [WARN] Khong thay .env. Thu zip storage rieng (neu co)...
  if exist "storage\" (
    powershell -NoLogo -NoProfile -Command ^
      "Compress-Archive -Path 'storage\*' -DestinationPath '%BACKUP_DIR%\storage_%TS%.zip' -Force" ^
      && echo [OK] Zip storage: %BACKUP_DIR%\storage_%TS%.zip
  )
)

:END
popd 2>nul
echo.
echo Done. Backups in: %BACKUP_DIR%
pause
endlocal
