# Move all XLSX files from ocrDir to ocrDir\Temp
$sourceDir = "C:\xampp\htdocs\website\ocrDir"
$destDir = "C:\xampp\htdocs\website\ocrDir\Temp"
$csvTempDir = "C:\xampp\htdocs\website\preProcessDir\TempPP"

# Create directories if they don't exist
if (!(Test-Path -Path $destDir)) {
    New-Item -ItemType Directory -Path $destDir
}
if (!(Test-Path -Path $csvTempDir)) {
    New-Item -ItemType Directory -Path $csvTempDir
}

Get-ChildItem -Path $sourceDir -Filter "*.xlsx" | Move-Item -Destination $destDir

# Get the newest CSV file from preProcessDir
$csvDir = "C:\xampp\htdocs\website\preProcessDir"
$newestCSV = Get-ChildItem -Path $csvDir -Filter "*.csv" | Sort-Object LastWriteTime -Descending | Select-Object -First 1

if ($null -eq $newestCSV) {
    Write-Output "No CSV files found in $csvDir"
    exit
}

# Load CSV and convert to XLSX with Column A as Number
$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$workbook = $excel.Workbooks.Open($newestCSV.FullName)
$worksheet = $workbook.Sheets(1)

# Set Column A to Number format
$columnA = $worksheet.Columns("A")
$columnA.NumberFormat = "0"

# Save as XLSX
$outputXLSX = Join-Path -Path $sourceDir -ChildPath ($newestCSV.BaseName + ".xlsx")
$workbook.SaveAs($outputXLSX, 51)  # 51 = xlOpenXMLWorkbook (XLSX)
$workbook.Close()
$excel.Quit()

[System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
Remove-Variable excel

# Move the processed CSV file to TempPP
Move-Item -Path $newestCSV.FullName -Destination $csvTempDir

# Open the ocrDir in File Explorer
Start-Process explorer.exe $sourceDir
