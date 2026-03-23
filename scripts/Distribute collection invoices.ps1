Add-Type -AssemblyName System.Windows.Forms

# --------------------------------------------------
# Select invoices source folder
# --------------------------------------------------
function Select-Folder {
    $defaultFolder = "C:\Users\Shimri-SAS\Dropbox (Personal)\PC\Documents\LS Consulting\Business\Retailomatics\InvoiceArchive\BernardYahudArchive\Collection"

    $app = New-Object -ComObject Shell.Application
    $folder = $app.BrowseForFolder(
        0,
        "Select the folder that contains the PDF invoices",
        0,
        $defaultFolder
    )

    if ($null -eq $folder) {
        return $null
    }

    return $folder.Self.Path
}

$invoicesFolder = Select-Folder

if ([string]::IsNullOrWhiteSpace($invoicesFolder)) {
    Write-Host "No source folder selected. Script cancelled."
    exit
}

Write-Host "Invoices folder selected: $invoicesFolder"
Write-Host ""

# --------------------------------------------------
# Fixed customers root folder
# --------------------------------------------------
$customersRoot = "C:\Users\Shimri-SAS\Dropbox (Personal)\PC\Documents\LS Consulting\Business\Retailomatics\InvoiceArchive\BernardYahudArchive\Collection\BernardCustomersCollection"

if (-not (Test-Path $customersRoot)) {
    Write-Host "Customers root folder does not exist:"
    Write-Host $customersRoot
    exit
}

# --------------------------------------------------
# Build lookup table: customer number -> directory
# Customer number = any standalone 3-digit number
# in the directory name
# --------------------------------------------------
$customerMap = @{}

Get-ChildItem -Path $customersRoot -Directory | ForEach-Object {
    $dirName = $_.Name
    $customerNumber = $null

    if ($dirName -match '(?<!\d)(\d{3})(?!\d)') {
        $customerNumber = $matches[1]
    }

    if ($customerNumber) {
        if (-not $customerMap.ContainsKey($customerNumber)) {
            $customerMap[$customerNumber] = $_.FullName
        }
        else {
            Write-Host "Warning: duplicate customer number found in directories: $customerNumber"
            Write-Host "First directory kept: $($customerMap[$customerNumber])"
            Write-Host "Ignored directory: $($_.FullName)"
        }
    }
}

if ($customerMap.Count -eq 0) {
    Write-Host "No valid customer directories were found under:"
    Write-Host $customersRoot
    exit
}

# --------------------------------------------------
# Get PDF invoices only
# --------------------------------------------------
$invoiceFiles = Get-ChildItem -Path $invoicesFolder -File -Filter *.pdf

if ($invoiceFiles.Count -eq 0) {
    Write-Host "No PDF files were found in the selected invoices folder."
    exit
}

# --------------------------------------------------
# Process invoices
# Invoice customer number = any standalone 3-digit
# number in the file name
# --------------------------------------------------
$assignedCount = 0
$notAssignedCount = 0
$invoiceIndex = 0
$totalInvoices = $invoiceFiles.Count

foreach ($file in $invoiceFiles) {
    $invoiceIndex++
    $baseName = [System.IO.Path]::GetFileNameWithoutExtension($file.Name)
    $invoiceCustomerNumber = $null

    Write-Host "Processing invoice $invoiceIndex / $totalInvoices : $($file.Name)"

    if ($baseName -match '(?<!\d)(\d{3})(?!\d)') {
        $invoiceCustomerNumber = $matches[1]
    }

    if ($invoiceCustomerNumber) {
        if ($customerMap.ContainsKey($invoiceCustomerNumber)) {
            $targetDirectory = $customerMap[$invoiceCustomerNumber]
            $targetFile = Join-Path $targetDirectory $file.Name

            Copy-Item -Path $file.FullName -Destination $targetFile -Force

            Write-Host "$($file.Name) was assigned to $([System.IO.Path]::GetFileName($targetDirectory)) directory"
            Write-Host ""
            $assignedCount++
        }
        else {
            Write-Host "$($file.Name) was NOT assigned - no matching customer directory found for customer number $invoiceCustomerNumber"
            Write-Host ""
            $notAssignedCount++
        }
    }
    else {
        Write-Host "$($file.Name) was NOT assigned - no 3-digit customer number found in file name"
        Write-Host ""
        $notAssignedCount++
    }
}

# --------------------------------------------------
# Summary
# --------------------------------------------------
Write-Host "Finished."
Write-Host "$assignedCount invoices were assigned."

if ($notAssignedCount -gt 0) {
    Write-Host "$notAssignedCount invoices were not assigned."
}