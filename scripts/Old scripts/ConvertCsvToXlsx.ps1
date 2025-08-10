# Define Original and Target directories
$OriginalDirectory = "C:\xampp\htdocs\website\preProcessDir"
$TargetDirectory = "C:\xampp\htdocs\website\ocrDir"

# Find the newest CSV file in the OriginalDirectory
$NewestCsv = Get-ChildItem -Path $OriginalDirectory -Filter *.csv | Sort-Object LastWriteTime -Descending | Select-Object -First 1

if (-Not $NewestCsv) {
    Write-Host "No CSV files found in '$OriginalDirectory'. Exiting." -ForegroundColor Red
    exit 1
}

# Define input and output file paths
$InputFile = $NewestCsv.FullName
$OutputFile = Join-Path -Path $TargetDirectory -ChildPath ($NewestCsv.BaseName + ".xlsx")

Write-Host "Processing the newest file: $InputFile" -ForegroundColor Green

try {
    # Create an instance of the Excel application
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false

    # Open the CSV file
    $workbook = $excel.Workbooks.Open($InputFile)
    $worksheet = $workbook.Sheets.Item(1)

    # Change the data type of column A to "Number"
    $columnA = $worksheet.Columns.Item("A")
    $columnA.NumberFormat = "0"  # Set to number format without decimals

    # Save it as an XLSX file
    $workbook.SaveAs($OutputFile, 51)  # 51 = XLSX format

    # Close the workbook
    $workbook.Close($false)

    # Notify the user of successful conversion
    Write-Host "Conversion completed successfully: $OutputFile" -ForegroundColor Green
} catch {
    # Handle errors
    Write-Host "An error occurred:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Yellow
} finally {
    # Ensure Excel quits even if there's an error
    if ($null -ne $excel) {
        $excel.Quit()
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null
        $excel = $null
    }
    [gc]::Collect()  # Force garbage collection
    [gc]::WaitForPendingFinalizers()
}
