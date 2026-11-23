@echo off
rem Double-click to install the Student Registration System into XAMPP.
rem Options are passed through, e.g.:  setup.bat -XamppPath "D:\xampp" -DbPassword secret
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup.ps1" %*
echo.
pause
