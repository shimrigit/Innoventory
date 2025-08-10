# Input parameter: Day of the month
param (
    [string]$Day
)

# Check if the day parameter is provided
if (-not $Day) {
    Write-Host "Error: Day parameter is missing." -ForegroundColor Red
    Write-Host "Usage: .\OrganizeImages.ps1 -Day [day_of_month]"
    exit
}

# Define directories
$ArchiveDir = "C:\Users\Shimri-SAS\Sky and Space Global Dropbox\Shimri  Lotan\PC\Documents\LS Consulting\Business\Inoventory\Customers\Rimon\Raw invoices\Archive"
$LandingDir = "C:\xampp\htdocs\website\preProcessDir"

# Define current year and month manually
$CurrentYearMonth = "Nov 2024"

# Construct target directory paths
$MonthDir = Join-Path -Path $ArchiveDir -ChildPath $CurrentYearMonth
$DayDir = Join-Path -Path $MonthDir -ChildPath "$Day-11-24"

# Ensure Month and Day directories exist
if (-not (Test-Path $MonthDir)) {
    New-Item -Path $MonthDir -ItemType Directory | Out-Null
}

if (-not (Test-Path $DayDir)) {
    New-Item -Path $DayDir -ItemType Directory | Out-Null
}

# Copy all .jpg files from the Landing directory to the target directory
Get-ChildItem -Path $LandingDir -Filter *.jpg | ForEach-Object {
    $fileName = $_.BaseName
    $fileExt = $_.Extension
    $targetFile = Join-Path -Path $DayDir -ChildPath "$fileName$fileExt"

    if (Test-Path $targetFile) {
        # Generate a timestamp to append to the filename
        $timestamp = (Get-Date -Format "HHmmss")
        $newFileName = "$fileName`_$timestamp$fileExt"
        $newFilePath = Join-Path -Path $DayDir -ChildPath $newFileName

        Copy-Item -Path $_.FullName -Destination $newFilePath
        Write-Host "File '$fileName$fileExt' was copied as '$newFileName'" -ForegroundColor Green
    } else {
        Copy-Item -Path $_.FullName -Destination $targetFile
        Write-Host "File '$fileName$fileExt' was copied" -ForegroundColor Green
    }
}

Write-Host "Files copied successfully!" -ForegroundColor Cyan
