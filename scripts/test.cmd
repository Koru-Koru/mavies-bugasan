@echo off
REM One-command test runner for Mavies Bugasan.
REM Works from any terminal, or double-click this file.
REM Pass extra PHPUnit args through:  test.cmd tests\Feature\ReportTest.php

powershell -ExecutionPolicy Bypass -File "%~dp0test.ps1" %*

if errorlevel 1 (
    echo.
    echo FAILED - tests did not pass.
    pause
    exit /b 1
)

echo.
echo All tests passed. Window closes in 5s.
REM ping instead of timeout: works even with no interactive stdin.
ping -n 6 127.0.0.1 >nul