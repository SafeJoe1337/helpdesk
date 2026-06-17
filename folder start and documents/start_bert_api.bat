@echo off
title HelpDesk BERT API
cd /d D:\xamp\htdocs\Helpdesk

echo ================================================
echo  HelpDesk BERT API - Auto Start
echo ================================================
echo.

:: Activate virtual environment
call .venv\Scripts\activate.bat

:: Start the API
echo Starting BERT API on port 5001...
python bert_api.py

:: If it crashes, wait 5 seconds and restart
echo.
echo API stopped. Restarting in 5 seconds...
timeout /t 5
goto :start
