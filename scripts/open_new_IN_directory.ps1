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
$form.Text            = "Open New IN Directory"
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
$dd   = $selectedDate.ToString("dd")
$mm   = $selectedDate.ToString("MM")
$yy   = $selectedDate.ToString("yy")
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
$invoicesDir     = Join-Path $customerBaseDir "Invoices"
$yearDir         = Join-Path $invoicesDir $yyyy
$monthDir        = Join-Path $yearDir $monthFolderName
$dailyDir        = Join-Path $monthDir "$dateStr IN"

# ── Create missing directories ────────────────────────────────────────────────
foreach ($dir in @($invoicesDir, $yearDir, $monthDir, $dailyDir)) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "Created directory: $dir" -ForegroundColor Green
    }
    else {
        Write-Host "Directory already exists: $dir" -ForegroundColor Yellow
    }
}

# ── Open folder in Explorer ───────────────────────────────────────────────────
if (Test-Path $dailyDir) {
    Start-Process explorer.exe $dailyDir
}

Write-Host ""
Write-Host "Done." -ForegroundColor Green
Read-Host "`nPress Enter to exit"
