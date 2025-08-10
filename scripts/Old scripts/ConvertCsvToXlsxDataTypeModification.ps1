# Define input and output file paths
$InputFile = "C:\xampp\htdocs\website\ocrDir\test.csv"
$OutputFile = "C:\xampp\htdocs\website\ocrDir\test.xlsx"

# Check if the input file exists
if (-Not (Test-Path $InputFile)) {
    Write-Host "The file '$InputFile' does not exist. Please check the path." -ForegroundColor Red
    exit 1
}

try {
    # Create an instance of the Excel application
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false

    # Open the CSV file
    $workbook = $excel.Workbooks.Open($InputFile)
    $worksheet = $workbook.Sheets.Item(1)

    # Change the data type of column B to "Number"
    $columnB = $worksheet.Columns.Item("B")
    $columnB.NumberFormat = "0"  # Set to number format without decimals

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
