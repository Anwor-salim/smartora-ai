@echo off
chcp 65001 >nul
echo جاري إيقاف خوادم سمارتورا...
taskkill /F /IM php.exe >nul 2>&1
echo تم إيقاف الخوادم بنجاح.
pause