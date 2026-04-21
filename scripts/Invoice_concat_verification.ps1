Add-Type -AssemblyName System.Windows.Forms

function Select-Folder($prompt) {
    $app = New-Object -ComObject Shell.Application
    $folder = $app.BrowseForFolder(0, $prompt, 0, 0)
    if ($null -eq $folder) { return $null }
    return $folder.Self.Path
}

function Select-File($prompt, $filter) {
    $dialog = New-Object System.Windows.Forms.OpenFileDialog
    $dialog.Title = $prompt
    $dialog.Filter = $filter
    if ($dialog.ShowDialog() -eq [System.Windows.Forms.DialogResult]::OK) { return $dialog.FileName }
    return $null
}

function Get-PdfPageCount($pdfPath) {
    try {
        # Use Get-Content -LiteralPath -Encoding Byte which handles Hebrew/Unicode paths natively
        $bytes = [byte[]](Get-Content -LiteralPath $pdfPath -Encoding Byte)
        if ($null -eq $bytes -or $bytes.Length -eq 0) { return -1 }
        $text = [System.Text.Encoding]::Latin1.GetString($bytes)
        # Try pattern 1: /Type /Page
        $count = ([regex]::Matches($text, '/Type\s*/Page[^s]')).Count
        if ($count -gt 0) { return $count }
        # Try pattern 2: /Count N in Pages dictionary
        $m = [regex]::Match($text, '/Count\s+(\d+)')
        if ($m.Success) { return [int]$m.Groups[1].Value }
        return 0
    }
    catch {
        Write-Host ('ERROR reading PDF: ' + $pdfPath + ' - ' + $_.Exception.Message)
        return -1
    }
}

$invoicesDir = Select-Folder 'Select the directory containing the invoice PDF files'
if ([string]::IsNullOrWhiteSpace($invoicesDir)) {
    Write-Host 'No directory selected. Script cancelled.'
    exit
}
Write-Host ('Invoices directory: ' + $invoicesDir)

$excelPath = Select-File 'Select the concat summary Excel file' 'Excel Files (*.xlsx;*.xls)|*.xlsx;*.xls'
if ([string]::IsNullOrWhiteSpace($excelPath)) {
    Write-Host 'No Excel file selected. Script cancelled.'
    exit
}
Write-Host ('Excel file: ' + $excelPath)
Write-Host ''

$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false

$workbook = $excel.Workbooks.Open($excelPath)
$sheet    = $workbook.Sheets.Item(1)
$lastRow  = $sheet.UsedRange.Rows.Count

$differences = @()
$notFound    = @()
$processed   = 0

Write-Host ('Processing rows 2 to ' + $lastRow + ' ...')
Write-Host '----------------------------------------'

for ($row = 2; $row -le $lastRow; $row++) {

    $customerNumber = $sheet.Cells.Item($row, 3).Text
    $expectedPages  = $sheet.Cells.Item($row, 4).Value2

    if ([string]::IsNullOrWhiteSpace($customerNumber)) { continue }

    $pdfFiles = Get-ChildItem -Path $invoicesDir -File -Filter '*.pdf' |
                Where-Object { $_.Name -match ('(?<!\d)' + [regex]::Escape($customerNumber) + '(?!\d)') }

    if ($pdfFiles.Count -eq 0) {
        Write-Host ('WARNING row ' + $row + ': No PDF found for customer number ' + $customerNumber)
        $notFound += $customerNumber
        $sheet.Cells.Item($row, 5) = 'NOT FOUND'
        $sheet.Cells.Item($row, 6) = ''
        continue
    }

    if ($pdfFiles.Count -gt 1) {
        Write-Host ('WARNING row ' + $row + ': Multiple PDFs matched customer ' + $customerNumber + ' - using: ' + $pdfFiles[0].Name)
    }

    $pdfFile     = $pdfFiles[0]
    $actualPages = Get-PdfPageCount $pdfFile.FullName

    $sheet.Cells.Item($row, 5) = $pdfFile.Name
    $sheet.Cells.Item($row, 6) = $actualPages

    if ($expectedPages -eq $actualPages) { $status = 'OK' } else { $status = 'MISMATCH' }
    Write-Host ('Row ' + $row + ' | Customer ' + $customerNumber + ' | ' + $pdfFile.Name + ' | Expected: ' + $expectedPages + ' | Actual: ' + $actualPages + ' | ' + $status)

    if ($expectedPages -ne $actualPages) {
        $differences += [PSCustomObject]@{
            Row            = $row
            CustomerNumber = $customerNumber
            FileName       = $pdfFile.Name
            Expected       = $expectedPages
            Actual         = $actualPages
        }
    }

    $processed++
}

$workbook.Save()
$workbook.Close()
$excel.Quit()
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($excel) | Out-Null

Write-Host ''
Write-Host '========================================'
Write-Host ('Done. ' + $processed + ' invoices checked.')

if ($notFound.Count -gt 0) {
    Write-Host ''
    Write-Host ('PDFs NOT FOUND for customer numbers: ' + ($notFound -join ', '))
}

if ($differences.Count -eq 0 -and $notFound.Count -eq 0) {
    Write-Host ''
    Write-Host 'SUCCESS: All page counts match!'
}
elseif ($differences.Count -gt 0) {
    Write-Host ''
    Write-Host ('DIFFERENCES FOUND in ' + $differences.Count + ' file(s):')
    foreach ($diff in $differences) {
        Write-Host ('  Row ' + $diff.Row + ' | Customer ' + $diff.CustomerNumber + ' | File: ' + $diff.FileName + ' | Expected: ' + $diff.Expected + ' pages | Actual: ' + $diff.Actual + ' pages')
    }
}