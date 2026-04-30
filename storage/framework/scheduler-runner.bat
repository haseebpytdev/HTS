@echo off
cd /d "C:\Users\khadi\Apnasafar_new\apnasafar-portal"
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" "C:\Users\khadi\Apnasafar_new\apnasafar-portal\artisan" schedule:run >> "C:\Users\khadi\Apnasafar_new\apnasafar-portal\storage\logs/scheduler-task.log" 2>&1
