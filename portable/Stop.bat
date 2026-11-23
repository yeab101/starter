@echo off
rem Use this only if the Start window was closed without pressing Enter.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0runtime\stop.ps1"
echo.
pause
