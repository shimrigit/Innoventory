Add-Type -AssemblyName System.Windows.Forms

$app = New-Object -ComObject Shell.Application
$folder = $app.BrowseForFolder(0, 'Select the folder containing customer subdirectories', 0, 0)
if ($null -eq $folder) {
    Write-Host 'No folder selected. Script cancelled.'
    exit
}
$rootFolder = $folder.Self.Path

if (-not (Test-Path $rootFolder)) {
    Write-Host ('Root folder not found: ' + $rootFolder)
    exit
}

$nov = [char]0x05E0 + [char]0x05D5 + [char]0x05D1 + [char]0x05DE + [char]0x05D1 + [char]0x05E8
$dec = [char]0x05D3 + [char]0x05E6 + [char]0x05DE + [char]0x05D1 + [char]0x05E8
$jan = [char]0x05D9 + [char]0x05E0 + [char]0x05D5 + [char]0x05D0 + [char]0x05E8
$feb = [char]0x05E4 + [char]0x05D1 + [char]0x05E8 + [char]0x05D5 + [char]0x05D0 + [char]0x05E8
$mar = [char]0x05DE + [char]0x05E8 + [char]0x05E5

$novemberTotal = 0
$decemberTotal = 0
$januaryTotal  = 0
$februaryTotal = 0
$marchTotal    = 0
$allPdfTotal   = 0

$subDirs = Get-ChildItem -Path $rootFolder -Directory | Sort-Object Name

if ($subDirs.Count -eq 0) {
    Write-Host ('No subdirectories found under: ' + $rootFolder)
    exit
}

Write-Host ''
Write-Host 'PDF count in each subdirectory:'
Write-Host '----------------------------------------'

foreach ($dir in $subDirs) {
    $pdfFiles = Get-ChildItem -Path $dir.FullName -File -Filter *.pdf
    $pdfCount = $pdfFiles.Count
    $allPdfTotal += $pdfCount

    Write-Host ($dir.Name + ' : ' + $pdfCount + ' PDF files')

    foreach ($file in $pdfFiles) {
        $fileName = $file.Name
        if ($fileName -like ('*' + $nov + '*')) { $novemberTotal++ }
        if ($fileName -like ('*' + $dec + '*')) { $decemberTotal++ }
        if ($fileName -like ('*' + $jan + '*')) { $januaryTotal++  }
        if ($fileName -like ('*' + $feb + '*')) { $februaryTotal++ }
        if ($fileName -like ('*' + $mar + '*')) { $marchTotal++    }
    }
}

Write-Host ''
Write-Host '----------------------------------------'
Write-Host ('Grand total PDF files: ' + $allPdfTotal)
Write-Host ''
Write-Host 'Totals by month:'
Write-Host ($nov + '  : ' + $novemberTotal)
Write-Host ($dec + '   : ' + $decemberTotal)
Write-Host ($jan + '   : ' + $januaryTotal)
Write-Host ($feb + '  : ' + $februaryTotal)
Write-Host ($mar + '     : ' + $marchTotal)