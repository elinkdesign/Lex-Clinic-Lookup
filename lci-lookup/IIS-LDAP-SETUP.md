# LCI Lookup Tool - IIS/LDAP Integration Guide

This guide provides step-by-step instructions for deploying the LCI Lookup Tool on IIS with LDAP/Active Directory authentication.

## Access Control

The application is configured to require membership in the Active Directory group: **`g-app-webapp-cpdrlist`**

Users who authenticate via LDAP but are not members of this group will receive a 403 Forbidden error with the message "Access denied. You must be a member of the g-app-webapp-cpdrlist group."

## Prerequisites
Get-WebConfiguration -Filter "system.webServer/fastCgi/application" | Where-Object {$_.fullPath -like "*php*"} | ForEach-Object { Remove-WebConfigurationProperty -Filter "system.webServer/fastCgi" -Name "." -AtElement @{fullPath=$_.fullPath} }
### Server Requirements
- Windows Server 2016 or later
- IIS 10 or later
- PHP 8.2 or later
- Composer
- URL Rewrite module for IIS
- Access to Active Directory/LDAP server

### Software Installation



1. **Install IIS with required components:**
   ```
   # Via PowerShell (as Administrator)
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServer
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-CommonHttpFeatures
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-HttpErrors
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-HttpLogging
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-RequestFiltering
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-StaticContent
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-DefaultDocument
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-DirectoryBrowsing
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebSockets
   Enable-WindowsOptionalFeature -Online -FeatureName IIS-ApplicationInit
   ```

2. **Install PHP for IIS:**
   - Download PHP 8.2+ from https://windows.php.net/download/
   - Extract to `C:\Program Files\PHP\v8.2\`
   - Copy `php.ini-development` to `php.ini`
   - Enable required extensions in `php.ini`:
     ```ini
     extension=curl
     extension=fileinfo
     extension=gd
     extension=mbstring
     extension=openssl
     extension=pdo_sqlite
     extension=sqlite3
     extension=zip
     ```

3. **Install URL Rewrite module:**
   - Download from: https://www.iis.net/downloads/microsoft/url-rewrite
   - Install the downloaded .msi file

4. **Install Composer:**
   - Download from: https://getcomposer.org/download/
   - Run the installer

## Application Deployment

### 1. Deploy Application Files

```powershell
# Create application directory
New-Item -ItemType Directory -Path "C:\inetpub\wwwroot\lci-lookup" -Force

# Copy application files to the directory
# (Copy your application files here)

# Navigate to application directory
cd C:\inetpub\wwwroot\lci-lookup
```

### 2. Install Dependencies

```powershell
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies (if needed for asset compilation)
npm install
npm run build
```

### 3. Configure Environment

```powershell
# Copy environment file
Copy-Item .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure .env File

Edit `C:\inetpub\wwwroot\lci-lookup\.env`:

```env
APP_NAME="LCI Lookup Tool"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database configuration
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=existing_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# LDAP Configuration
LDAP_CONNECTION=default
LDAP_HOST=your-ldap-server.domain.com
LDAP_USERNAME=cn=service-account,dc=domain,dc=com
LDAP_PASSWORD=your-service-account-password
LDAP_PORT=389
LDAP_BASE_DN=dc=domain,dc=com
LDAP_SSL=false
LDAP_TLS=false
LDAP_TIMEOUT=5
LDAP_LOGGING=true
LDAP_CACHE=false

# Session configuration
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
```

### 5. Verify Database Connection

```powershell
# (Optional) Run migrations if you need to create or update tables
php artisan migrate

# Verify the application can connect to MySQL
php artisan tinker --execute="DB::connection()->getPdo(); echo 'MySQL connection successful';"
```

### 6. Configure File Permissions

```powershell
# Set permissions for Laravel directories
icacls "storage" /grant "IIS_IUSRS:(OI)(CI)(F)" /T
icacls "bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)(F)" /T
icacls "database" /grant "IIS_IUSRS:(OI)(CI)(F)" /T

# Set permissions for public directory
icacls "public" /grant "IIS_IUSRS:(OI)(CI)(RX)" /T
```

## IIS Configuration

### 1. Create Application Pool

1. Open IIS Manager
2. Right-click "Application Pools" → "Add Application Pool"
3. Configure:
   - **Name:** `LCI-Lookup-Pool`
   - **.NET CLR Version:** "No Managed Code"
   - **Managed Pipeline Mode:** "Integrated"
   - **Identity:** "ApplicationPoolIdentity"

### 2. Create Website

1. Right-click "Sites" → "Add Website"
2. Configure:
   - **Site name:** `LCI-Lookup`
   - **Application pool:** `LCI-Lookup-Pool`
   - **Physical path:** `C:\inetpub\wwwroot\lci-lookup\public`
   - **Port:** `80` (or your preferred port)

### 3. Configure Authentication

1. Select your website in IIS Manager
2. Double-click "Authentication"
3. **Disable** "Anonymous Authentication"
4. **Enable** "Windows Authentication"

### 4. Configure Handler Mappings

1. Select your website
2. Double-click "Handler Mappings"
3. Add new handler:
   - **Request path:** `*.php`
   - **Executable:** `C:\Program Files\PHP\v8.2\php-cgi.exe`
   - **Name:** `PHP_via_FastCGI`

### 5. Configure URL Rewrite

The `public/web.config` file should already contain the necessary rewrite rules. Verify it exists and contains:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Imported Rule 1" stopProcessing="true">
                    <match url="^(.*)/$" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Redirect" redirectType="Permanent" url="/{R:1}" />
                </rule>
                <rule name="Imported Rule 2" stopProcessing="true">
                    <match url="^" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
        <security>
            <authentication>
                <windowsAuthentication enabled="true" />
                <anonymousAuthentication enabled="false" />
            </authentication>
        </security>
        <defaultDocument>
            <files>
                <clear />
                <add value="index.php" />
            </files>
        </defaultDocument>
        <handlers>
            <remove name="PHP_via_FastCGI" />
            <add name="PHP_via_FastCGI" path="*.php" verb="*" modules="FastCgiModule" scriptProcessor="C:\Program Files\PHP\v8.2\php-cgi.exe" resourceType="Either" requireAccess="Script" />
        </handlers>
    </system.webServer>
</configuration>
```

## LDAP Configuration

### 1. Service Account Setup

Create a service account in Active Directory with read permissions:

```powershell
# Create service account (run in AD PowerShell)
New-ADUser -Name "LCI-Lookup-Service" -AccountPassword (ConvertTo-SecureString "YourPassword123!" -AsPlainText -Force) -Enabled $true -PasswordNeverExpires $true

# Add to appropriate groups if needed
Add-ADGroupMember -Identity "Domain Users" -Members "LCI-Lookup-Service"
```

### 2. Test LDAP Connection

Create a test script `test-ldap.php`:

```php
<?php
$ldap_host = "your-ldap-server.domain.com";
$ldap_port = 389;
$ldap_username = "cn=LCI-Lookup-Service,dc=domain,dc=com";
$ldap_password = "YourPassword123!";
$ldap_base_dn = "dc=domain,dc=com";

$ldap_conn = ldap_connect($ldap_host, $ldap_port);
if (!$ldap_conn) {
    die("Could not connect to LDAP server");
}

ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

$bind = ldap_bind($ldap_conn, $ldap_username, $ldap_password);
if (!$bind) {
    die("LDAP bind failed: " . ldap_error($ldap_conn));
}

echo "LDAP connection successful!\n";

// Test search
$search = ldap_search($ldap_conn, $ldap_base_dn, "(objectClass=user)");
if ($search) {
    $entries = ldap_get_entries($ldap_conn, $search);
    echo "Found " . $entries["count"] . " users\n";
}

ldap_unbind($ldap_conn);
?>
```

Run: `php test-ldap.php`

## Testing and Troubleshooting

### 1. Test Basic Access

1. Open browser and navigate to your site
2. You should be prompted for Windows credentials
3. After authentication, you should see the LCI Lookup Tool interface

### 2. Common Issues and Solutions

**Issue: "500 Internal Server Error"**
- Check PHP error logs: `C:\inetpub\logs\LogFiles\`
- Verify file permissions
- Check if PHP extensions are loaded

**Issue: "Authentication failed"**
- Verify LDAP configuration in `.env`
- Test LDAP connection with test script
- Check service account permissions

**Issue: "Database connection failed"**
- Verify SQLite file exists and has proper permissions
- Check database path in `.env`

**Issue: "Assets not loading"**
- Run `npm run build` to compile assets
- Check if `public/build` directory exists

### 3. Enable Debugging

Temporarily enable debugging in `.env`:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

Check logs at: `storage/logs/laravel.log`

### 4. Performance Optimization

```powershell
# Clear and cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

## Security Considerations

1. **SSL Certificate:** Install SSL certificate for HTTPS
2. **Firewall:** Configure firewall to allow only necessary ports
3. **Service Account:** Use least-privilege principle for LDAP service account
4. **Logging:** Monitor authentication logs for suspicious activity
5. **Updates:** Keep PHP, IIS, and application updated

## Monitoring

### 1. Application Logs
- Laravel logs: `storage/logs/laravel.log`
- IIS logs: `C:\inetpub\logs\LogFiles\`

### 2. Performance Monitoring
- Use IIS performance counters
- Monitor database size and performance
- Check LDAP connection health

## Backup Strategy

1. **Application Files:** Regular backup of application directory
2. **Database:** Backup SQLite file regularly
3. **Configuration:** Backup `.env` and IIS configuration
4. **Logs:** Archive logs periodically

## Support

For issues not covered in this guide:
1. Check Laravel documentation: https://laravel.com/docs
2. Review IIS documentation: https://docs.microsoft.com/en-us/iis/
3. Check LDAP/Active Directory documentation
4. Review application logs for specific error messages

---

**Note:** This guide assumes a Windows Server environment with Active Directory. Adjust LDAP settings according to your specific LDAP server configuration. 