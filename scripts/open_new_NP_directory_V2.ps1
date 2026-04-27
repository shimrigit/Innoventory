Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

# ── Base Path ────────────────────────────────────────────────────────────────
$invoiceArchiveRoot = "Z:\RetailomaticsCloud\RetailomaticsArchive"

if (-not (Test-Path $invoiceArchiveRoot)) {
    [System.Windows.Forms.MessageBox]::Show(
        "Base path not found:`n$invoiceArchiveRoot",
        "Error",
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Error
    ) | Out-Null
    exit
}

# ── Get customer folders for dropdown ────────────────────────────────────────
$customerFolders = Get-ChildItem -Path $invoiceArchiveRoot -Directory |
    Sort-Object Name |
    Select-Object -ExpandProperty Name

if (-not $customerFolders -or $customerFolders.Count -eq 0) {
    [System.Windows.Forms.MessageBox]::Show(
        "No customer folders were found under:`n$invoiceArchiveRoot",
        "Error",
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Error
    ) | Out-Null
    exit
}

# ── Customer + Date Picker Dialog ────────────────────────────────────────────
$form                 = New-Object System.Windows.Forms.Form
$form.Text            = "Open New NP Directory V2"
$form.Size            = New-Object System.Drawing.Size(420, 210)
$form.StartPosition   = "CenterScreen"
$form.FormBorderStyle = "FixedDialog"
$form.MaximizeBox     = $false
$form.MinimizeBox     = $false

# Customer label
$customerLabel = New-Object System.Windows.Forms.Label
$customerLabel.Text = "Customer:"
$customerLabel.Location = New-Object System.Drawing.Point(20, 20)
$customerLabel.Size = New-Object System.Drawing.Size(100, 20)
$form.Controls.Add($customerLabel)

# Customer dropdown
$customerCombo = New-Object System.Windows.Forms.ComboBox
$customerCombo.Location = New-Object System.Drawing.Point(130, 18)
$customerCombo.Size = New-Object System.Drawing.Size(240, 24)
$customerCombo.DropDownStyle = [System.Windows.Forms.ComboBoxStyle]::DropDownList
[void]$customerCombo.Items.AddRange($customerFolders)
$customerCombo.SelectedIndex = 0
$form.Controls.Add($customerCombo)

# Date label
$dateLabel = New-Object System.Windows.Forms.Label
$dateLabel.Text = "Invoice date:"
$dateLabel.Location = New-Object System.Drawing.Point(20, 65)
$dateLabel.Size = New-Object System.Drawing.Size(100, 20)
$form.Controls.Add($dateLabel)

# Date picker
$datePicker = New-Object System.Windows.Forms.DateTimePicker
$datePicker.Format = [System.Windows.Forms.DateTimePickerFormat]::Short
$datePicker.Value = [datetime]::Today
$datePicker.Location = New-Object System.Drawing.Point(130, 62)
$datePicker.Size = New-Object System.Drawing.Size(140, 24)
$form.Controls.Add($datePicker)

# OK button
$okBtn = New-Object System.Windows.Forms.Button
$okBtn.Text = "OK"
$okBtn.Location = New-Object System.Drawing.Point(110, 115)
$okBtn.Size = New-Object System.Drawing.Size(80, 30)
$okBtn.DialogResult = [System.Windows.Forms.DialogResult]::OK
$form.AcceptButton = $okBtn
$form.Controls.Add($okBtn)

# Cancel button
$cancelBtn = New-Object System.Windows.Forms.Button
$cancelBtn.Text = "Cancel"
$cancelBtn.Location = New-Object System.Drawing.Point(210, 115)
$cancelBtn.Size = New-Object System.Drawing.Size(80, 30)
$cancelBtn.DialogResult = [System.Windows.Forms.DialogResult]::Cancel
$form.CancelButton = $cancelBtn
$form.Controls.Add($cancelBtn)

$result = $form.ShowDialog()

if ($result -ne [System.Windows.Forms.DialogResult]::OK) {
    Write-Host "Cancelled. Exiting." -ForegroundColor Yellow
    exit
}

$selectedCustomer = $customerCombo.SelectedItem
$selectedDate     = $datePicker.Value

if ([string]::IsNullOrWhiteSpace($selectedCustomer)) {
    Write-Host "No customer selected. Exiting." -ForegroundColor Yellow
    exit
}

# ── Date parts ────────────────────────────────────────────────────────────────
$dd = $selectedDate.ToString("dd")
$mm = $selectedDate.ToString("MM")
$yy = $selectedDate.ToString("yy")
$yyyy = $selectedDate.ToString("yyyy")
$dateStr = "$dd-$mm-$yy"

$monthNames = @{
    "01" = "January"
    "02" = "February"
    "03" = "March"
    "04" = "April"
    "05" = "May"
    "06" = "June"
    "07" = "July"
    "08" = "August"
    "09" = "September"
    "10" = "October"
    "11" = "November"
    "12" = "December"
}

$monthFolderName = "$mm" + "_" + $monthNames[$mm]

# ── Build paths ───────────────────────────────────────────────────────────────
$customerBaseDir = Join-Path $invoiceArchiveRoot $selectedCustomer
$newProductsDir  = Join-Path $customerBaseDir "NewProducts"
$yearDir         = Join-Path $newProductsDir $yyyy
$monthDir        = Join-Path $yearDir $monthFolderName
$dailyDir        = Join-Path $monthDir "$dateStr NP"

$xlsxName = "new_products_list_$dateStr to_upload.xlsx"
$xlsxPath = Join-Path $dailyDir $xlsxName

# ── Create missing directories ────────────────────────────────────────────────
foreach ($dir in @($newProductsDir, $yearDir, $monthDir, $dailyDir)) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "Created directory: $dir" -ForegroundColor Green
    }
    else {
        Write-Host "Directory already exists: $dir" -ForegroundColor Yellow
    }
}

# ── Create Excel file with Hebrew headers ────────────────────────────────────
if (Test-Path $xlsxPath) {
    Write-Host "Excel file already exists: $xlsxName" -ForegroundColor Yellow
}
else {
    $excel    = $null
    $workbook = $null

    # Hebrew strings built from char codes (encoding-safe)
    # מס פריט
    $h1 = [char]0x05DE + [char]0x05E1 + ' ' + [char]0x05E4 + [char]0x05E8 + [char]0x05D9 + [char]0x05D8
    # שם פריט
    $h2 = [char]0x05E9 + [char]0x05DD + ' ' + [char]0x05E4 + [char]0x05E8 + [char]0x05D9 + [char]0x05D8
    # ברקוד
    $h3 = [char]0x05D1 + [char]0x05E8 + [char]0x05E7 + [char]0x05D5 + [char]0x05D3
    # מחיר קניה
    $h4 = [char]0x05DE + [char]0x05D7 + [char]0x05D9 + [char]0x05E8 + ' ' + [char]0x05E7 + [char]0x05E0 + [char]0x05D9 + [char]0x05D4
    # ספק
    $h5 = [char]0x05E1 + [char]0x05E4 + [char]0x05E7
    # מחיר מכירה
    $h6 = [char]0x05DE + [char]0x05D7 + [char]0x05D9 + [char]0x05E8 + ' ' + [char]0x05DE + [char]0x05DB + [char]0x05D9 + [char]0x05E8 + [char]0x05D4
    # מחלקה
    $h7 = [char]0x05DE + [char]0x05D7 + [char]0x05DC + [char]0x05E7 + [char]0x05D4

    $headers = @($h1, $h2, $h3, $h4, $h5, $h6, $h7)

    try {
        $excel = New-Object -ComObject Excel.Application
        $excel.Visible = $false
        $excel.DisplayAlerts = $false

        $workbook = $excel.Workbooks.Add()
        $sheet = $workbook.Sheets.Item(1)
        $sheet.Name = "New Products"
        $sheet.DisplayRightToLeft = $false

        for ($i = 0; $i -lt $headers.Count; $i++) {
            $cell = $sheet.Cells.Item(1, $i + 1)
            $cell.Value2 = $headers[$i]
            $cell.Font.Bold = $true
            $cell.Borders.LineStyle = 1
            $cell.Borders.Weight = 2
        }

        $sheet.Columns.AutoFit() | Out-Null

        # 51 = xlOpenXMLWorkbook (.xlsx)
        $workbook.SaveAs($xlsxPath, 51)
        Write-Host "Created Excel file: $xlsxPath" -ForegroundColor Green
    }
    catch {
        Write-Host "Error creating Excel file: $_" -ForegroundColor Red
    }
    finally {
        if ($workbook) { $workbook.Close($false) }
        if ($excel)    { $excel.Quit() }
        if ($sheet)    { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($sheet) | Out-Null }
        if ($workbook) { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($workbook) | Out-Null }
        if ($excel)    { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null }
        [GC]::Collect()
        [GC]::WaitForPendingFinalizers()
    }
}

# ── Open folder in Explorer and pin to Quick Access ──────────────────────────
if (Test-Path $dailyDir) {
    Start-Process explorer.exe $dailyDir

    $shell = New-Object -ComObject Shell.Application
    $shell.Namespace($dailyDir).Self.InvokeVerb("pintohome")
    Write-Host "Pinned to Quick Access: $dailyDir" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "Done." -ForegroundColor Green
Read-Host "`nPress Enter to exit"