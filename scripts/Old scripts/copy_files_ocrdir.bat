@echo off
set prefix=Tnuva_20.01.2025_Tnuva 20-01-25 E
set sourceDir=C:\xampp\htdocs\website\ocrDir\Temp
set destDir=C:\xampp\htdocs\website\ocrDir

if not exist "%sourceDir%" (
    echo Source directory does not exist: %sourceDir%
    pause
    exit /b
)

if not exist "%destDir%" (
    mkdir "%destDir%"
)

for %%f in ("%sourceDir%\%prefix%*") do (
    if exist "%%f" (
        copy "%%f" "%destDir%"
    )
)

echo Files with prefix "%prefix%" copied successfully.
::pause
