param (
    [string]$FileNamePrefix = "CountryMZ",
    [string]$MonthAndYear = "04-25"
)

# Prompt for day input
$d = Read-Host "Enter the day of the month"

# Define directories
$sourceDir = "C:\xampp\htdocs\website\ocrDir"
$tempDir = "$sourceDir\Temp"
$downloadDir = [System.Environment]::GetFolderPath("UserProfile") + "\Downloads"


# Ensure Temp directory exists
if (!(Test-Path $tempDir)) {
    New-Item -ItemType Directory -Path $tempDir | Out-Null
}

# Move all .xlsx files from ocrDir to Temp
Get-ChildItem -Path $sourceDir -Filter "*.xlsx" | Move-Item -Destination $tempDir -Force

# Build the new file name
$newFileName = "$FileNamePrefix $d-$MonthAndYear.xlsx"
$newFilePath = Join-Path -Path $sourceDir -ChildPath $newFileName

# Get the latest .xls file from Downloads
$latestXls = Get-ChildItem -Path $downloadDir -Filter "*.xls" | Sort-Object LastWriteTime -Descending | Select-Object -First 1

if ($latestXls) {
    # Open Excel and convert XLS to XLSX
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $workbook = $excel.Workbooks.Open($latestXls.FullName)
    
    # Save as XLSX
    $workbook.SaveAs($newFilePath, 51) # 51 = xlOpenXMLWorkbook (xlsx)
    $workbook.Close($false)
    $excel.Quit()

    Write-Host "Converted and saved as: $newFilePath"
} else {
    Write-Host "No .xls files found in $downloadDir"
}

# Open the ocrDir in Explorer
Start-Process explorer.exe -ArgumentList $sourceDir
