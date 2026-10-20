@echo off
setlocal
cd /d "%~dp0"

echo Starting Lost and Found AI...

where py >nul 2>&1
if not errorlevel 1 (
    echo Python found. Starting server...
    py -3 -u "%~dp0server.py"
    pause
    exit /b
)

where python >nul 2>&1
if not errorlevel 1 (
    echo Python found. Starting server...
    python -u "%~dp0server.py"
    pause
    exit /b
)

echo ERROR: Python is not installed or not in PATH.
pause
