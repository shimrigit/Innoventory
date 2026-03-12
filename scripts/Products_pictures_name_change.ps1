Add-Type -AssemblyName System.Windows.Forms

# ── Choose Directory ──────────────────────────────────────────────────────────
$folderBrowser = New-Object System.Windows.Forms.FolderBrowserDialog
$folderBrowser.Description = "Select directory containing WhatsApp JPEG files"
$folderBrowser.ShowNewFolderButton = $false
$folderBrowser.SelectedPath = "C:\Users\Shimri-SAS\Dropbox (Personal)\PC\Documents\LS Consulting\Business\Retailomatics\InvoiceArchive\BernardYahudArchive\NewProducts"

if ($folderBrowser.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) {
    Write-Host "No directory selected. Exiting." -ForegroundColor Yellow
    exit
}
$directory = $folderBrowser.SelectedPath

# ── Choose Excel File ─────────────────────────────────────────────────────────
$openFileDialog = New-Object System.Windows.Forms.OpenFileDialog
$openFileDialog.Title  = "Select Excel file with prices"
$openFileDialog.Filter = "Excel Files (*.xlsx;*.xls)|*.xlsx;*.xls"

if ($openFileDialog.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) {
    Write-Host "No Excel file selected. Exiting." -ForegroundColor Yellow
    exit
}
$excelFile = $openFileDialog.FileName

# ── Get JPEG files sorted by timestamp in filename ────────────────────────────
$jpegFiles = Get-ChildItem -Path $directory -Filter "WhatsApp Image *.jpeg" |
    Sort-Object {
        if ($_.Name -match 'WhatsApp Image (\d{4}-\d{2}-\d{2}) at (\d{2})\.(\d{2})\.(\d{2})') {
            [datetime]"$($matches[1]) $($matches[2]):$($matches[3]):$($matches[4])"
        } else {
            [datetime]::MinValue
        }
    }

if ($jpegFiles.Count -eq 0) {
    Write-Host "No WhatsApp JPEG files found in the selected directory." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit
}

# ── Read prices from Excel column A ──────────────────────────────────────────
$excel    = $null
$workbook = $null
$prices   = @()

try {
    $excel                = New-Object -ComObject Excel.Application
    $excel.Visible        = $false
    $excel.DisplayAlerts  = $false
    $workbook             = $excel.Workbooks.Open($excelFile)
    $sheet                = $workbook.Sheets.Item(1)

    $row = 1
    while ($true) {
        $cell = $sheet.Cells.Item($row, 1)
        # Use .Text to get the displayed value (handles both numbers and strings correctly)
        $text = $cell.Text.Trim()
        if ($text -eq "") { break }
        $prices += $text
        $row++
    }
}
catch {
    Write-Host "Error reading Excel file: $_" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit
}
finally {
    if ($workbook) { $workbook.Close($false) }
    if ($excel)    { $excel.Quit() }
    if ($excel)    { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null }
}

if ($prices.Count -eq 0) {
    Write-Host "No prices found in Excel column A." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit
}

# ── Warn if counts differ ─────────────────────────────────────────────────────
if ($jpegFiles.Count -ne $prices.Count) {
    Write-Host ("Warning: {0} JPEG files but {1} prices in Excel." -f $jpegFiles.Count, $prices.Count) -ForegroundColor Yellow
}
$count = [Math]::Min($jpegFiles.Count, $prices.Count)

# ── Rename files ──────────────────────────────────────────────────────────────
$errors  = @()
$renamed = 0

for ($i = 0; $i -lt $count; $i++) {
    $file  = $jpegFiles[$i]
    $price = $prices[$i]

    if ($file.Name -match 'WhatsApp Image (\d{4}-\d{2}-\d{2} at \d{2}\.\d{2}\.\d{2})\.jpeg$') {
        $dateTime = $matches[1]
        $newName  = "$dateTime $price.jpeg"
        $newPath  = Join-Path $directory $newName

        if (Test-Path $newPath) {
            $errors += "Skipped '$($file.Name)': target '$newName' already exists"
            continue
        }

        try {
            Rename-Item -Path $file.FullName -NewName $newName -ErrorAction Stop
            Write-Host "  $($file.Name)  →  $newName" -ForegroundColor Green
            $renamed++
        }
        catch {
            $errors += "Failed to rename '$($file.Name)': $_"
        }
    }
    else {
        $errors += "Skipped '$($file.Name)': does not match WhatsApp filename format"
    }
}

# ── Summary ───────────────────────────────────────────────────────────────────
Write-Host ""
if ($errors.Count -eq 0) {
    Write-Host "Done. $renamed file(s) renamed successfully." -ForegroundColor Green
} else {
    Write-Host "Done. $renamed file(s) renamed." -ForegroundColor Green
    Write-Host "$($errors.Count) issue(s):" -ForegroundColor Yellow
    foreach ($err in $errors) {
        Write-Host "  - $err" -ForegroundColor Red
    }
}

Read-Host "`nPress Enter to exit"
