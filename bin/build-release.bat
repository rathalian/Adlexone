@echo off
setlocal
cd /d "%~dp0.."

set "PHP=php"
where php >nul 2>nul
if errorlevel 1 (
  set "PHP="
  for /d %%D in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.*") do if exist "%%D\php.exe" set "PHP=%%D\php.exe"
)
if not defined PHP (
  echo php.exe was not found. Install PHP 8.1 or later, then open a new window.
  goto :done
)

"%PHP%" bin\build-release.php %*
if errorlevel 1 echo The build failed. The message above says why.

:done
echo %cmdcmdline% | find /i "/c" >nul && pause
endlocal
