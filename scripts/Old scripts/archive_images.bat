@echo off
REM Batch file to organize images into an Archive directory

REM Input parameter: Day of the month
set "day=%1"

REM Check if the day parameter is provided
if "%day%"=="" (
    echo Error: Day parameter is missing.
    echo Usage: archive_images.bat [day_of_month]
    exit /b
)

REM Define directories
set "ArchiveDir=C:\Users\Shimri-SAS\Sky and Space Global Dropbox\Shimri  Lotan\PC\Documents\LS Consulting\Business\Inoventory\Customers\Rimon\Raw invoices\Archive"
set "LandingDir=C:\xampp\htdocs\website\preProcessDir"

REM Define current year and month manually
set "CurrentYearMonth=Nov 2024"

REM Construct target directory paths
set "MonthDir=%ArchiveDir%\%CurrentYearMonth%"
set "DayDir=%MonthDir%\%day%-11-24"

REM Ensure Month and Day directories exist
if not exist "%MonthDir%" (
    mkdir "%MonthDir%"
)

if not exist "%DayDir%" (
    mkdir "%DayDir%"
)

REM Copy all .jpg files from the Landing directory to the target directory
for %%f in ("%LandingDir%\*.jpg") do (
    set "fileName=%%~nf"
    set "fileExt=%%~xf"
    set "targetFile=%DayDir%\%%~nxf"

    REM Delayed variable expansion for loop variables
    setlocal enabledelayedexpansion
    if exist "!targetFile!" (
        REM Generate a timestamp to append to the filename
        for /f "tokens=1-4 delims=:.," %%a in ("%time%") do set "timestamp_dhhmmss=%%a%%b%%c%%d"
        set "newFileName=%DayDir%\!fileName!_!timestamp_dhhmmss!!fileExt!"
        copy "%%f" "!newFileName!"
        echo File %%~nxf was copied
    ) else (
        copy "%%f" "!targetFile!"
        echo File %%~nxf was copied
    )
    endlocal
)

echo Files copied successfully!
