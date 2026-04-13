$rootFolder = "Z:\RetailomaticsCloud\RetailomaticsArchive\BernardYahud\Collection\2026\ShopCustomersCollection"

if (-not (Test-Path $rootFolder)) {
    Write-Host "Root folder not found:"
    Write-Host $rootFolder
    exit
}

$novemberTotal = 0
$decemberTotal = 0
$januaryTotal  = 0
$februaryTotal = 0
$allPdfTotal   = 0

$subDirs = Get-ChildItem -Path $rootFolder -Directory | Sort-Object Name

if ($subDirs.Count -eq 0) {
    Write-Host "No subdirectories found under:"
    Write-Host $rootFolder
    exit
}

Write-Host ""
Write-Host "PDF count in each subdirectory:"
Write-Host "----------------------------------------"

foreach ($dir in $subDirs) {
    $pdfFiles = Get-ChildItem -Path $dir.FullName -File -Filter *.pdf
    $pdfCount = $pdfFiles.Count
    $allPdfTotal += $pdfCount

    Write-Host "$($dir.Name) : $pdfCount PDF files"

    foreach ($file in $pdfFiles) {
        $fileName = $file.Name

        if ($fileName -match "??????") {
            $novemberTotal++
        }
        if ($fileName -match "?????") {
            $decemberTotal++
        }
        if ($fileName -match "?????") {
            $januaryTotal++
        }
        if ($fileName -match "??????") {
            $februaryTotal++
        }
    }
}

Write-Host ""
Write-Host "----------------------------------------"
Write-Host "Grand total PDF files: $allPdfTotal"
Write-Host ""
Write-Host "Totals by month:"
Write-Host "??????  : $novemberTotal"
Write-Host "?????   : $decemberTotal"
Write-Host "?????   : $januaryTotal"
Write-Host "??????  : $februaryTotal"
