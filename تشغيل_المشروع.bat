@echo off
chcp 65001 >nul
echo ===================================================
echo     تشغيل منصة سمارتورا للرد الآلي (Smartora AI)
echo ===================================================
echo.
echo 1. جاري تشغيل الباك إند (Laravel API على المنفذ 8088)...
start "" /B php -S 127.0.0.1:8088 -t "D:\smartora\backend\public"

echo 2. جاري تشغيل الفرونت إند (Nuxt على المنفذ 3000)...
cd /d "D:\smartora\frontend"
set PORT=3000
set NITRO_PORT=3000
start "" /B node ".output\server\index.mjs"

echo.
echo ===================================================
echo  تم تشغيل المنصة بنجاح!
echo  - رابط الواجهة: http://localhost:3000
echo  - رابط الـ API:  http://127.0.0.1:8088/api
echo ===================================================
pause