@echo off
setlocal
set "PROJECT_ROOT=%~dp0..\.."
cd /d "%PROJECT_ROOT%"
php artisan schedule:run >> "%PROJECT_ROOT%\storage\logs\scheduler-task.log" 2>&1
