$PDFDir = "C:\xampp\htdocs\website\preProcessDir"
$TempDir = Join-Path $PDFDir "TempPP"

# Create TempPP if it doesn't exist
if (-not (Test-Path $TempDir)) {
    New-Item -Path $TempDir -ItemType Directory | Out-Null
}

Write-Host "Processing PDFs in $PDFDir..."
Write-Host "Multi-page originals will be moved to: $TempDir`n"

# Get all PDF files (case-insensitive)
Get-ChildItem -Path $PDFDir -Filter *.pdf -File | ForEach-Object {
    $pdfPath = $_.FullName
    $fileName = $_.Name

    # Get number of pages using pdftk dump_data
    $pageCountLine = & pdftk $pdfPath dump_data | Select-String "NumberOfPages"
    if ($pageCountLine -match "\d+") {
        $pageCount = [int]$matches[0]
    } else {
        Write-Host "❌ Could not read page count for $fileName"
        return
    }

    if ($pageCount -gt 1) {
        Write-Host "➕ Splitting $fileName ($pageCount pages)..."

        # Split into pages using pdftk
        & pdftk $pdfPath burst output "$PDFDir\page%d-$fileName"

        # Remove doc_data.txt if it exists
        $docDataPath = Join-Path $PDFDir "doc_data.txt"
        if (Test-Path $docDataPath) {
            Remove-Item $docDataPath -Force
        }

        # Move original to TempPP
        Move-Item -Path $pdfPath -Destination $TempDir -Force
        Write-Host "📦 Moved $fileName to TempPP`n"
    } else {
        Write-Host "✅ Skipping single-page file: $fileName`n"
    }
}

Write-Host "`n✅ Done!"

# Open the folder in Explorer
Start-Process "explorer.exe" "$PDFDir"

# Pause at the end until Enter is pressed
Write-Host "`nPress Enter to exit..."
[void][System.Console]::ReadLine()

