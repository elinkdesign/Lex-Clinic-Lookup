# =====================================================
# LCI Lookup Tool - Network Access Setup Script
# =====================================================
# This script configures Apache, Firewall, and Laravel
# to allow network access to the application
# 
# MUST RUN AS ADMINISTRATOR
# Usage: .\setup-network-access.ps1
# =====================================================

# Check if running as Administrator
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Press any key to exit..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  LCI LOOKUP - NETWORK ACCESS SETUP" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Get server information
$hostname = $env:COMPUTERNAME
$fqdn = [System.Net.Dns]::GetHostByName($hostname).HostName
$networkAdapters = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike "127.*" -and $_.PrefixOrigin -ne "WellKnown"}
$primaryIP = ($networkAdapters | Select-Object -First 1).IPAddress

Write-Host "Detected Server Information:" -ForegroundColor Yellow
Write-Host "  Hostname: $hostname"
Write-Host "  FQDN: $fqdn"
Write-Host "  Primary IP: $primaryIP"
Write-Host ""

# Ask user what URL they want to use
Write-Host "How do you want users to access the application?" -ForegroundColor Yellow
Write-Host ""
Write-Host "  1. IP Address only (http://$primaryIP)" -ForegroundColor White
Write-Host "  2. Hostname only (http://$hostname)" -ForegroundColor White
Write-Host "  3. FQDN only (http://$fqdn)" -ForegroundColor White
Write-Host "  4. All of the above (IP + Hostname + FQDN)" -ForegroundColor Green
Write-Host "  5. Custom DNS name (e.g., http://cpdr-lookup.lc.local)" -ForegroundColor White
Write-Host ""

$choice = Read-Host "Enter your choice (1-5)"

$serverNames = @()
$appUrl = ""

switch ($choice) {
    "1" {
        $serverNames = @($primaryIP)
        $appUrl = "http://$primaryIP"
    }
    "2" {
        $serverNames = @($hostname)
        $appUrl = "http://$hostname"
    }
    "3" {
        $serverNames = @($fqdn)
        $appUrl = "http://$fqdn"
    }
    "4" {
        $serverNames = @($primaryIP, $hostname, $fqdn)
        $appUrl = "http://$primaryIP"
    }
    "5" {
        $customName = Read-Host "Enter custom DNS name (without http://)"
        $serverNames = @($customName)
        $appUrl = "http://$customName"
        Write-Host ""
        Write-Host "NOTE: You must create a DNS A record pointing $customName to $primaryIP" -ForegroundColor Yellow
        Write-Host "      Contact your network administrator to add this DNS entry." -ForegroundColor Yellow
    }
    default {
        Write-Host "Invalid choice. Using all options (IP + Hostname + FQDN)..." -ForegroundColor Yellow
        $serverNames = @($primaryIP, $hostname, $fqdn)
        $appUrl = "http://$primaryIP"
    }
}

Write-Host ""
Write-Host "Configuration Summary:" -ForegroundColor Green
Write-Host "  Primary URL: $appUrl"
Write-Host "  All ServerNames: $($serverNames -join ', ')"
Write-Host ""

$confirm = Read-Host "Proceed with setup? (Y/N)"
if ($confirm -ne "Y" -and $confirm -ne "y") {
    Write-Host "Setup cancelled." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  STARTING SETUP" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Configure Apache VirtualHost
Write-Host "[1/5] Configuring Apache VirtualHost..." -ForegroundColor Yellow

$vhostFile = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"
$appPath = "C:/xampp/htdocs"

if (-not (Test-Path $vhostFile)) {
    Write-Host "  ERROR: VirtualHosts file not found at $vhostFile" -ForegroundColor Red
    exit 1
}

# Create VirtualHost configuration
$vhostConfig = @"

# LCI Lookup Tool - Network Access Configuration
# Added by setup-network-access.ps1 on $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
<VirtualHost *:80>
    ServerName $($serverNames[0])
$(if ($serverNames.Count -gt 1) { ($serverNames[1..($serverNames.Count-1)] | ForEach-Object { "    ServerAlias $_" }) -join "`n" })
    DocumentRoot "$appPath/public"

    <Directory "$appPath/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/lc-lookup-network-error.log"
    CustomLog "logs/lc-lookup-network-access.log" common
</VirtualHost>
"@

# Backup existing config
$backupFile = "$vhostFile.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
Copy-Item $vhostFile $backupFile
Write-Host "  Backed up existing config to: $backupFile" -ForegroundColor Green

# Append new VirtualHost
Add-Content -Path $vhostFile -Value $vhostConfig
Write-Host "  Added VirtualHost configuration" -ForegroundColor Green

# Step 2: Configure Windows Firewall
Write-Host ""
Write-Host "[2/5] Configuring Windows Firewall..." -ForegroundColor Yellow

try {
    # Check if rule already exists
    $existingRule = Get-NetFirewallRule -DisplayName "LCI Lookup Tool - HTTP" -ErrorAction SilentlyContinue
    
    if ($existingRule) {
        Write-Host "  Removing existing firewall rule..." -ForegroundColor Yellow
        Remove-NetFirewallRule -DisplayName "LCI Lookup Tool - HTTP"
    }
    
    # Create new firewall rule
    New-NetFirewallRule -DisplayName "LCI Lookup Tool - HTTP" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort 80 `
        -Action Allow `
        -Profile Domain,Private `
        -Description "Allow HTTP traffic for LCI Lookup Tool" | Out-Null
    
    Write-Host "  Firewall rule created successfully" -ForegroundColor Green
} catch {
    Write-Host "  WARNING: Could not configure firewall: $($_.Exception.Message)" -ForegroundColor Yellow
    Write-Host "  You may need to manually allow port 80 through Windows Firewall" -ForegroundColor Yellow
}

# Step 3: Update Laravel .env
Write-Host ""
Write-Host "[3/5] Updating Laravel configuration..." -ForegroundColor Yellow

$envFile = "$appPath\.env"

if (Test-Path $envFile) {
    # Backup .env
    $envBackup = "$envFile.backup.$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item $envFile $envBackup
    Write-Host "  Backed up .env to: $envBackup" -ForegroundColor Green
    
    # Update APP_URL
    $envContent = Get-Content $envFile
    $envContent = $envContent | ForEach-Object {
        if ($_ -match "^APP_URL=") {
            "APP_URL=$appUrl"
        } else {
            $_
        }
    }
    Set-Content -Path $envFile -Value $envContent
    Write-Host "  Updated APP_URL to: $appUrl" -ForegroundColor Green
} else {
    Write-Host "  WARNING: .env file not found at $envFile" -ForegroundColor Yellow
}

# Step 4: Clear Laravel cache
Write-Host ""
Write-Host "[4/5] Clearing Laravel cache..." -ForegroundColor Yellow

Push-Location $appPath
try {
    $phpPath = "C:\Program Files\PHP\v8.2\php.exe"
    if (-not (Test-Path $phpPath)) {
        $phpPath = "C:\xampp\php\php.exe"
    }
    
    if (Test-Path $phpPath) {
        & $phpPath artisan config:clear 2>&1 | Out-Null
        & $phpPath artisan cache:clear 2>&1 | Out-Null
        & $phpPath artisan route:clear 2>&1 | Out-Null
        Write-Host "  Laravel cache cleared" -ForegroundColor Green
    } else {
        Write-Host "  WARNING: Could not find PHP executable" -ForegroundColor Yellow
    }
} catch {
    Write-Host "  WARNING: Error clearing cache: $($_.Exception.Message)" -ForegroundColor Yellow
}
Pop-Location

# Step 5: Restart Apache
Write-Host ""
Write-Host "[5/5] Restarting Apache..." -ForegroundColor Yellow

$apacheService = Get-Service -Name "Apache*" -ErrorAction SilentlyContinue

if ($apacheService) {
    try {
        Restart-Service $apacheService.Name -Force
        Start-Sleep -Seconds 2
        $status = (Get-Service $apacheService.Name).Status
        if ($status -eq "Running") {
            Write-Host "  Apache restarted successfully" -ForegroundColor Green
        } else {
            Write-Host "  WARNING: Apache status is $status" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "  ERROR: Could not restart Apache: $($_.Exception.Message)" -ForegroundColor Red
        Write-Host "  Please restart Apache manually" -ForegroundColor Yellow
    }
} else {
    Write-Host "  WARNING: Apache service not found" -ForegroundColor Yellow
    Write-Host "  If running XAMPP, restart Apache from the XAMPP Control Panel" -ForegroundColor Yellow
}

# Final Summary
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  SETUP COMPLETE!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "The application should now be accessible from the network at:" -ForegroundColor Green
Write-Host ""
foreach ($serverName in $serverNames) {
    Write-Host "  http://$serverName" -ForegroundColor White
}
Write-Host ""

Write-Host "Testing connectivity..." -ForegroundColor Yellow
Write-Host ""

foreach ($serverName in $serverNames) {
    Write-Host "  Testing http://$serverName ... " -NoNewline
    try {
        $response = Invoke-WebRequest -Uri "http://$serverName" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
        Write-Host "[SUCCESS - HTTP $($response.StatusCode)]" -ForegroundColor Green
    } catch {
        Write-Host "[FAILED - $($_.Exception.Message)]" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. Test from another computer on the network"
Write-Host "  2. Log in with a user who is in the 'g-app-webapp-cpdrlist' AD group"
Write-Host "  3. If access is denied, check the AD group membership"
Write-Host ""

Write-Host "Troubleshooting:" -ForegroundColor Yellow
Write-Host "  - If the site doesn't load, check Apache error logs:"
Write-Host "    C:\xampp\apache\logs\lc-lookup-network-error.log"
Write-Host "  - Verify firewall allows port 80:"
Write-Host "    Get-NetFirewallRule -DisplayName '*LCI Lookup*'"
Write-Host "  - Check Apache is running:"
Write-Host "    Get-Service Apache*"
Write-Host ""

Write-Host "Configuration files backed up to:" -ForegroundColor Yellow
Write-Host "  VirtualHosts: $backupFile"
if (Test-Path $envFile) {
    Write-Host "  Laravel .env: $envBackup"
}
Write-Host ""

Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

