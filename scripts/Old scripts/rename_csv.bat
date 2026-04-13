@echo off
setlocal enabledelayedexpansion

:: Define source and destination directories
set "sourceDir=C:\Users\Shimri-SAS\Sky and Space Global Dropbox\Shimri  Lotan\PC\Documents\LS Consulting\Business\Inoventory\Customers\CountryMZ\Invoices\Download"
set "destDir=%sourceDir%\Mod"

:: Ensure the destination directory exists
if not exist "%destDir%" mkdir "%destDir%"

:: Iterate through all files in the source directory
for %%F in ("%sourceDir%\*.*") do (
    :: Extract the full path, filename, and extension
    set "fullpath=%%F"
    set "filename=%%~nF"
    set "ext=%%~xF"

    :: Debugging: Display the file being processed
    echo Processing file: %%~nxF

    :: Check for pattern XX-11-24 in the filename
    echo !filename! | findstr /r "[0-9][0-9]-11-24" >nul
    if !errorlevel! neq 1 (
        :: Replace XX-11-24 with 26-11-24
        set "newFilename=!filename:??-11-24=26-11-24!"
        set "newFilePath=%destDir%\!newFilename!!ext!"

        :: Perform the copy operation
        copy "%%F" "!newFilePath!" >nul
        if !errorlevel! equ 0 (
            echo Renamed and moved: %%~nxF -> !newFilename!!ext!
        ) else (
            echo Error moving file: %%~nxF
        )
    ) else (
        echo Skipped: %%~nxF (No match)
    )
)

echo All files processed.
pause
