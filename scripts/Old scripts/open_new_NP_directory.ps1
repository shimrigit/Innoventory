Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

# ── Date Picker Dialog ────────────────────────────────────────────────────────
$form                  = New-Object System.Windows.Forms.Form
$form.Text             = "Select Date"
$form.Size             = New-Object System.Drawing.Size(300, 150)
$form.StartPosition    = "CenterScreen"
$form.FormBorderStyle  = "FixedDialog"
$form.MaximizeBox      = $false
$form.MinimizeBox      = $false

$label          = New-Object System.Windows.Forms.Label
$label.Text     = "Invoice date:"
$label.Location = New-Object System.Drawing.Point(20, 20)
$label.Size     = New-Object System.Drawing.Size(80, 20)
$form.Controls.Add($label)

$datePicker          = New-Object System.Windows.Forms.DateTimePicker
$datePicker.Format   = [System.Windows.Forms.DateTimePickerFormat]::Short
$datePicker.Value    = [datetime]::Today
$datePicker.Location = New-Object System.Drawing.Point(110, 17)
$datePicker.Size     = New-Object System.Drawing.Size(150, 20)
$form.Controls.Add($datePicker)

$okBtn          = New-Object System.Windows.Forms.Button
$okBtn.Text     = "OK"
$okBtn.Location = New-Object System.Drawing.Point(100, 65)
$okBtn.Size     = New-Object System.Drawing.Size(80, 28)
$okBtn.DialogResult = [System.Windows.Forms.DialogResult]::OK
$form.AcceptButton  = $okBtn
$form.Controls.Add($okBtn)

if ($form.ShowDialog() -ne [System.Windows.Forms.DialogResult]::OK) {
    Write-Host "Cancelled. Exiting." -ForegroundColor Yellow
    Read-Host "`nPress Enter to exit"
    exit
}

$selectedDate = $datePicker.Value
$dd    = $selectedDate.ToString("dd")
$mm    = $selectedDate.ToString("MM")
$yy    = $selectedDate.ToString("yy")
$dateStr = "$dd-$mm-$yy"

# ── Paths ─────────────────────────────────────────────────────────────────────
$baseDir   = "C:\Users\Shimri-SAS\Dropbox (Personal)\PC\Documents\LS Consulting\Business\Retailomatics\InvoiceArchive\BernardYahudArchive\NewProducts"
$newDir    = Join-Path $baseDir "$dateStr NP"
$xlsxName  = "new_products_list_$dateStr to_upload.xlsx"
$xlsxPath  = Join-Path $newDir $xlsxName

# ── Create Directory ──────────────────────────────────────────────────────────
if (Test-Path $newDir) {
    Write-Host "Directory already exists: $newDir" -ForegroundColor Yellow
} else {
    New-Item -ItemType Directory -Path $newDir | Out-Null
    Write-Host "Created directory: $newDir" -ForegroundColor Green
}

# ── Create Excel file with Hebrew headers ─────────────────────────────────────
if (Test-Path $xlsxPath) {
    Write-Host "Excel file already exists: $xlsxName" -ForegroundColor Yellow
    Read-Host "`nPress Enter to exit"
    exit
}

$excel    = $null
$workbook = $null

# Hebrew strings built from char codes (encoding-safe)
# mes priyt  = מס פריט
$h1 = [char]0x05DE + [char]0x05E1 + ' ' + [char]0x05E4 + [char]0x05E8 + [char]0x05D9 + [char]0x05D8
# shem priyt = שם פריט
$h2 = [char]0x05E9 + [char]0x05DD + ' ' + [char]0x05E4 + [char]0x05E8 + [char]0x05D9 + [char]0x05D8
# barcode    = ברקוד
$h3 = [char]0x05D1 + [char]0x05E8 + [char]0x05E7 + [char]0x05D5 + [char]0x05D3
# mechir kniya = מחיר קניה
$h4 = [char]0x05DE + [char]0x05D7 + [char]0x05D9 + [char]0x05E8 + ' ' + [char]0x05E7 + [char]0x05E0 + [char]0x05D9 + [char]0x05D4
# sapak      = ספק
$h5 = [char]0x05E1 + [char]0x05E4 + [char]0x05E7
# mechir mchira = מחיר מכירה
$h6 = [char]0x05DE + [char]0x05D7 + [char]0x05D9 + [char]0x05E8 + ' ' + [char]0x05DE + [char]0x05DB + [char]0x05D9 + [char]0x05E8 + [char]0x05D4
# machlaka   = מחלקה
$h7 = [char]0x05DE + [char]0x05D7 + [char]0x05DC + [char]0x05E7 + [char]0x05D4

$headers = @($h1, $h2, $h3, $h4, $h5, $h6, $h7)

try {
    $excel               = New-Object -ComObject Excel.Application
    $excel.Visible       = $false
    $excel.DisplayAlerts = $false

    $workbook  = $excel.Workbooks.Add()
    $sheet     = $workbook.Sheets.Item(1)
    $sheet.Name = "New Products"

    # Left-to-right sheet
    $sheet.DisplayRightToLeft = $false

    for ($i = 0; $i -lt $headers.Count; $i++) {
        $cell = $sheet.Cells.Item(1, $i + 1)
        $cell.Value2 = $headers[$i]
        $cell.Font.Bold = $true
        $cell.Borders.LineStyle = 1  # xlContinuous
        $cell.Borders.Weight    = 2  # xlThin
    }

    # Auto-fit columns
    $sheet.Columns.AutoFit() | Out-Null

    $workbook.SaveAs($xlsxPath, 51)  # 51 = xlOpenXMLWorkbook (.xlsx)
    Write-Host "Created Excel file: $xlsxName" -ForegroundColor Green
}
catch {
    Write-Host "Error creating Excel file: $_" -ForegroundColor Red
}
finally {
    if ($workbook) { $workbook.Close($false) }
    if ($excel)    { $excel.Quit() }
    if ($excel)    { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null }
}

Write-Host ""
Write-Host "Done." -ForegroundColor Green
Read-Host "`nPress Enter to exit"
