# =====================================================
# LCI Lookup Tool - Network Deployment Information
# =====================================================
# Run this script on the SERVER to gather network info
# Usage: .\server-network-info.ps1
# =====================================================

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  LCI LOOKUP TOOL - NETWORK INFO" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 1. Server IP Addresses
Write-Host "[1] SERVER IP ADDRESSES" -ForegroundColor Yellow
Write-Host "----------------------------------------"
$networkAdapters = Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -notlike "127.*" -and $_.PrefixOrigin -ne "WellKnown"}
foreach ($adapter in $networkAdapters) {
    $interfaceAlias = (Get-NetAdapter -InterfaceIndex $adapter.InterfaceIndex).Name
    Write-Host "  Interface: $interfaceAlias" -ForegroundColor Green
    Write-Host "  IP Address: $($adapter.IPAddress)" -ForegroundColor White
    Write-Host "  Subnet: /$($adapter.PrefixLength)"
    Write-Host ""
}

# 2. Server Hostname
Write-Host "[2] SERVER HOSTNAME" -ForegroundColor Yellow
Write-Host "----------------------------------------"
$hostname = $env:COMPUTERNAME
$fqdn = [System.Net.Dns]::GetHostByName($hostname).HostName
Write-Host "  Computer Name: $hostname" -ForegroundColor White
Write-Host "  FQDN: $fqdn" -ForegroundColor White
Write-Host ""

# 3. Apache Status
Write-Host "[3] APACHE STATUS" -ForegroundColor Yellow
Write-Host "----------------------------------------"
$apacheService = Get-Service -Name "Apache*" -ErrorAction SilentlyContinue
if ($apacheService) {
    Write-Host "  Service Name: $($apacheService.Name)" -ForegroundColor White
    Write-Host "  Status: $($apacheService.Status)" -ForegroundColor $(if($apacheService.Status -eq "Running"){"Green"}else{"Red"})
} else {
    Write-Host "  Apache service not found (might be running manually)" -ForegroundColor Yellow
}

# Check if Apache is listening on port 80
$listening = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    Write-Host "  Port 80: LISTENING" -ForegroundColor Green
    $process = Get-Process -Id $listening[0].OwningProcess
    Write-Host "  Process: $($process.Name) (PID: $($process.Id))" -ForegroundColor White
} else {
    Write-Host "  Port 80: NOT LISTENING" -ForegroundColor Red
}
Write-Host ""

# 4. Firewall Rules for Port 80
Write-Host "[4] FIREWALL STATUS (Port 80)" -ForegroundColor Yellow
Write-Host "----------------------------------------"
$firewallRules = Get-NetFirewallRule | Where-Object {
    $_.Enabled -eq $true -and 
    $_.Direction -eq "Inbound"
} | ForEach-Object {
    $portFilter = $_ | Get-NetFirewallPortFilter -ErrorAction SilentlyContinue
    if ($portFilter -and ($portFilter.LocalPort -eq "80" -or $portFilter.LocalPort -eq "Any")) {
        [PSCustomObject]@{
            Name = $_.DisplayName
            Action = $_.Action
            Port = $portFilter.LocalPort
        }
    }
}

if ($firewallRules) {
    foreach ($rule in $firewallRules) {
        $color = if($rule.Action -eq "Allow"){"Green"}else{"Red"}
        Write-Host "  $($rule.Name)" -ForegroundColor $color
        Write-Host "    Action: $($rule.Action) | Port: $($rule.Port)"
    }
} else {
    Write-Host "  No explicit rules found for port 80" -ForegroundColor Yellow
    Write-Host "  (Check Windows Firewall manually)" -ForegroundColor Yellow
}
Write-Host ""

# 5. Current Apache VirtualHosts
Write-Host "[5] APACHE VIRTUAL HOSTS" -ForegroundColor Yellow
Write-Host "----------------------------------------"
$vhostFile = "C:\xampp\apache\conf\extra\httpd-vhosts.conf"
if (Test-Path $vhostFile) {
    Write-Host "  VirtualHosts Config: $vhostFile" -ForegroundColor White
    $content = Get-Content $vhostFile -Raw
    $vhosts = [regex]::Matches($content, '<VirtualHost[^>]*>[\s\S]*?ServerName\s+([^\s\r\n]+)')
    
    if ($vhosts.Count -gt 0) {
        Write-Host "  Configured ServerNames:" -ForegroundColor Green
        foreach ($match in $vhosts) {
            Write-Host "    - $($match.Groups[1].Value)" -ForegroundColor White
        }
    } else {
        Write-Host "  No VirtualHosts found" -ForegroundColor Yellow
    }
} else {
    Write-Host "  VirtualHosts file not found at: $vhostFile" -ForegroundColor Red
}
Write-Host ""

# 6. Laravel .env Configuration
Write-Host "[6] LARAVEL CONFIGURATION" -ForegroundColor Yellow
Write-Host "----------------------------------------"
$envFile = "C:\xampp\htdocs\.env"
if (Test-Path $envFile) {
    $envContent = Get-Content $envFile
    $appUrl = $envContent | Where-Object {$_ -match "^APP_URL="} | Select-Object -First 1
    $appName = $envContent | Where-Object {$_ -match "^APP_NAME="} | Select-Object -First 1
    
    Write-Host "  .env Location: $envFile" -ForegroundColor White
    Write-Host "  $appName" -ForegroundColor White
    Write-Host "  $appUrl" -ForegroundColor White
} else {
    Write-Host "  .env file not found at: $envFile" -ForegroundColor Red
}
Write-Host ""

# 7. Domain/DNS Information
Write-Host "[7] DOMAIN CONTROLLER INFO" -ForegroundColor Yellow
Write-Host "----------------------------------------"
try {
    $domain = [System.DirectoryServices.ActiveDirectory.Domain]::GetCurrentDomain()
    Write-Host "  Domain Name: $($domain.Name)" -ForegroundColor White
    Write-Host "  Forest: $($domain.Forest)" -ForegroundColor White
} catch {
    Write-Host "  Not joined to a domain or unable to retrieve domain info" -ForegroundColor Yellow
}
Write-Host ""

# 8. Network Connectivity Test
Write-Host "[8] NETWORK ACCESSIBILITY TEST" -ForegroundColor Yellow
Write-Host "----------------------------------------"
Write-Host "  Testing if port 80 is reachable from this machine..." -ForegroundColor White

foreach ($adapter in $networkAdapters) {
    $ip = $adapter.IPAddress
    Write-Host "  Testing: http://$ip" -NoNewline
    try {
        $response = Invoke-WebRequest -Uri "http://$ip" -TimeoutSec 3 -UseBasicParsing -ErrorAction Stop
        Write-Host " [SUCCESS - HTTP $($response.StatusCode)]" -ForegroundColor Green
    } catch {
        Write-Host " [FAILED - $($_.Exception.Message)]" -ForegroundColor Red
    }
}
Write-Host ""

# 9. Recommendations
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  RECOMMENDATIONS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "For NETWORK ACCESS, users can connect via:" -ForegroundColor Yellow
foreach ($adapter in $networkAdapters) {
    Write-Host "  http://$($adapter.IPAddress)" -ForegroundColor Green
}
Write-Host "  http://$hostname" -ForegroundColor Green
Write-Host "  http://$fqdn" -ForegroundColor Green
Write-Host ""

Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "  1. Choose an access method (IP, hostname, or custom DNS name)"
Write-Host "  2. Run the setup script to configure Apache and firewall"
Write-Host "  3. Update Laravel .env with the chosen URL"
Write-Host "  4. Test from another computer on the network"
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  COPY THIS INFO FOR THE SETUP SCRIPT" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Summary for easy copy/paste
$primaryIP = ($networkAdapters | Select-Object -First 1).IPAddress
Write-Host "Primary IP: $primaryIP" -ForegroundColor White
Write-Host "Hostname: $hostname" -ForegroundColor White
Write-Host "FQDN: $fqdn" -ForegroundColor White
Write-Host ""

Write-Host "Press any key to exit..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

