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

REM Define directories (update these paths as needed)
set "ArchiveDir=C:\Users\Shimri-SAS\Sky and Space Global Dropbox\Shimri  Lotan\PC\Documents\LS Consulting\Business\Inoventory\Customers\Rimon\Raw invoices\Archive"
set "LandingDir=C:\xampp\htdocs\website\preProcessDir"

REM Define current year and month manually (update as needed)
set "CurrentYearMonth=Nov 2024"

REM Construct target directory paths
set "MonthDir=%ArchiveDir%\%CurrentYearMonth%"
set "DayDir=%MonthDir%\%day%-12-24" REM Example fixed date format for testing

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
        set count=1
        :loop
        set "newFileName=%DayDir%\!fileName!_(!count!)!fileExt!"
        if exist "!newFileName!" (
            set /a count+=1
            goto :loop
        )
        copy "%%f" "!newFileName!"
    ) else (
        copy "%%f" "!targetFile!"
    )
    endlocal
)

echo Files copied successfully!

