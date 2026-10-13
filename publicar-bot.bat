@echo off
rem Publishes the bot with a double click: it runs deploy/publicar.sh in Git Bash (see that file).
cd /d "%~dp0"
set CHERE_INVOKING=1
set "GITBASH=%ProgramFiles%\Git\bin\bash.exe"
if not exist "%GITBASH%" set "GITBASH=%LocalAppData%\Programs\Git\bin\bash.exe"
if not exist "%GITBASH%" (
  echo Nao achei o Git Bash. Abra o Git Bash nesta pasta e rode: bash deploy/deploy.sh
  pause
  exit /b 1
)
"%GITBASH%" --login deploy/publicar.sh
echo.
pause
