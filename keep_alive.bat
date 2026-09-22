@echo off
:loop
tailscale funnel --https=443 off >nul 2>&1
timeout /t 2 /nobreak >nul
tailscale funnel --bg --https=443 --yes http://localhost:80 >nul 2>&1
echo [%date% %time%] Funnel restarted >> C:\xampp\htdocs\shandev\funnel_log.txt
timeout /t 300 /nobreak >nul
goto loop
