# Define the Shared Drive base directory
$baseDir = "H:\Shared drives\Innoventory Share Drive\Customers"

# Check if the base directory exists
if (!(Test-Path $baseDir)) {
    Write-Host "The Shared Drive path does not exist: $baseDir" -ForegroundColor Red
    exit
}

# Get customer directories
$customers = Get-ChildItem -Path $baseDir -Directory | Select-Object -ExpandProperty Name

# If no customers are found, exit
if ($customers.Count -eq 0) {
    Write-Host "No customer directories found in $baseDir" -ForegroundColor Red
    exit
}

# Prompt the user to choose a customer
Write-Host "Select a customer from the list:"
for ($i = 0; $i -lt $customers.Count; $i++) {
    Write-Host "[$i] $($customers[$i])"
}

$choice = Read-Host "Enter the number of the customer"
if ($choice -match '^\d+$' -and [int]$choice -ge 0 -and [int]$choice -lt $customers.Count) {
    $customerName = $customers[$choice]
    Write-Host "You selected: $customerName" -ForegroundColor Green
} else {
    Write-Host "Invalid selection. Exiting." -ForegroundColor Red
    exit
}

# Define source directories
$sourceDirs = @(
    "C:\xampp\htdocs\website\downloads",
    "C:\xampp\htdocs\website\toBackOffice",
    "C:\xampp\htdocs\website\uploads"
)

# Define destination directories on the Shared Drive
$destDirs = @(
    "$baseDir\$customerName\downloads",
    "$baseDir\$customerName\toBackOffice",
    "$baseDir\$customerName\uploads"
)

# Process each directory
for ($i = 0; $i -lt $sourceDirs.Count; $i++) {
    $source = $sourceDirs[$i]
    $destination = $destDirs[$i]
    $tempDir = "$source\Temp"

    # Ensure destination directories exist
    if (!(Test-Path $destination)) {
        New-Item -ItemType Directory -Path $destination -Force | Out-Null
    }

    # Ensure Temp directory exists inside the source directory
    if (!(Test-Path $tempDir)) {
        New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
    }

    # Copy files to the Shared Drive
    if (Test-Path $source) {
        Write-Host "Copying files from $source to $destination..."
        Get-ChildItem -Path $source -File | Copy-Item -Destination $destination -Force

        # Move files to Temp folder
        Write-Host "Moving files from $source to $tempDir..."
        Get-ChildItem -Path $source -File | Move-Item -Destination $tempDir -Force
    } else {
        Write-Host "Skipping: Source directory does not exist: $source" -ForegroundColor Yellow
    }
}

# Open the "toBackOffice" directory in File Explorer
$toBackOfficePath = "$baseDir\$customerName\toBackOffice"
if (Test-Path $toBackOfficePath) {
    Start-Process explorer.exe -ArgumentList "`"$toBackOfficePath`""
} else {
    Write-Host "Could not open directory: $toBackOfficePath" -ForegroundColor Red
}

Write-Host "File transfer and archiving completed!" -ForegroundColor Green

Read-Host "Press Enter to exit"