# LCI Lookup Tool

The LCI Lookup Tool is an internal web application for managing and searching NID (National ID) and LIC (License) records. It allows users to submit new records and search existing ones across two databases: Shortlist and Longlist.

## Features

- Submit new records with NID, LIC, and Name
- Search records by NID, LIC, or Name
- Display search results from both Shortlist and Longlist databases
- LDAP/Active Directory authentication for secure access

## Usage

### Submitting a Record

1. On the homepage, fill out the form with the NID, LIC, and Name.
2. Click the "Submit" button.
3. If successful, you'll see a confirmation message.

### Searching Records

1. Click the "Search" button in the top-right corner of the page.
2. In the search modal, enter an NID, LIC, or Name to search for.
3. Click the "Search" button in the modal.
4. Results will be displayed below the search input.

## Database Structure

The application uses two tables:

1. `shortlists`: For records with alphanumeric NIDs
2. `longlists`: For records with exactly 4 numeric digits as NIDs

Both tables have the following columns:

- `NID` (unique)
- `LIC` (unique)
- `name`

## For Developers

If you need to make changes to the application:

1. Ensure you have the necessary access to the development environment.
2. Make your changes in the development environment.
3. Test thoroughly before deploying to production.
4. If database changes are required, create and test migrations carefully.

## Deployment on IIS with LDAP Authentication

To deploy this Laravel application on an IIS Windows server with LDAP/Active Directory authentication, follow these steps:

### Prerequisites

1. Windows Server with IIS installed (IIS 10 or later recommended)
2. PHP 8.2 or later installed and configured for IIS
3. URL Rewrite module for IIS
4. Composer installed on the server
5. Access to Active Directory/LDAP server
6. Microsoft Web Platform Installer (optional, but helpful)

### Installation Steps

1. **Prepare your server:**
   - Ensure IIS is installed with the necessary components: CGI, URL Rewrite
   - Install PHP for IIS (PHP 8.2+)
   - Configure PHP to work with IIS

2. **Deploy the application:**
   - Clone or copy the application files to a directory on the server (e.g., C:\inetpub\wwwroot\lci-lookup)
   - Open a command prompt, navigate to the application directory, and run:
     ```
     composer install --no-dev
     ```
   - Copy the `.env.example` file to `.env` and update it with your server's configuration:
     ```
     php -r "copy('.env.example', '.env');"
     ```

3. **Configure the application:**
   - Edit the `.env` file and set the following LDAP parameters:
     ```
     LDAP_CONNECTION=default
     LDAP_HOST=your-ldap-server.domain.com
     LDAP_USERNAME=cn=service-account,dc=domain,dc=com
     LDAP_PASSWORD=your-service-account-password
     LDAP_PORT=389
     LDAP_BASE_DN=dc=domain,dc=com
     LDAP_SSL=false
     LDAP_TLS=false
     ```
   - Generate the application key:
     ```
     php artisan key:generate
     ```
   - Run database migrations:
     ```
     php artisan migrate
     ```
   - Set appropriate permissions on the storage and bootstrap/cache directories
   - Set file permissions to allow IIS to read and write to necessary directories

4. **Configure IIS:**
   - Create a new website in IIS Manager pointing to the public directory of your Laravel application
   - Set up a new application pool:
     - .NET CLR version: "No Managed Code"
     - Managed pipeline mode: "Integrated"
     - Identity: ApplicationPoolIdentity (or a specific domain account with necessary permissions)
   - Enable Windows Authentication and disable Anonymous Authentication:
     - Select your website in IIS Manager
     - Double-click on the "Authentication" icon
     - Disable "Anonymous Authentication"
     - Enable "Windows Authentication"

5. **The web.config file:**
   - The `public/web.config` file is already configured with the necessary settings:
     - URL rewrite rules
     - Windows authentication settings
     - PHP FastCGI handler

6. **Test the deployment:**
   - Access your application through a web browser
   - You should be automatically authenticated via your Windows credentials
   - If authentication fails, check the Laravel logs for detailed error messages

### Troubleshooting

1. **Authentication Issues:**
   - Ensure Windows Authentication is properly enabled in IIS
   - Check the `.env` file for correct LDAP configuration
   - Verify the LDAP service account has read permissions in your Active Directory
   - Check Laravel logs at `storage/logs/laravel.log`

2. **IIS Configuration:**
   - Make sure the URL Rewrite module is installed
   - Verify that PHP is properly configured with IIS
   - Check file permissions on the application directories

3. **Application Errors:**
   - Check PHP error logs
   - Enable debugging in `.env` by setting `APP_DEBUG=true` temporarily
   - Run `php artisan optimize:clear` to clear cached configuration

4. **LDAP Connection Issues:**
   - Test LDAP connectivity from the server using tools like LDP.exe
   - Verify firewall rules allow LDAP traffic (typically port 389 or 636 for LDAPS)
   - Confirm the LDAP service account has the necessary permissions

## Security

This application is for internal use only and utilizes Windows Authentication with LDAP integration to ensure secure access. Do not share access or data with unauthorized individuals.

For more detailed instructions or troubleshooting, please consult the Laravel documentation on deployment or contact the IT support team.
