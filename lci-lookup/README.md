# LCI Lookup Tool

The LCI Lookup Tool is an internal web application for managing and searching NID (National ID) and LIC (License) records. It allows users to submit new records and search existing ones across two databases: Shortlist and Longlist.

## Features

- Submit new records with NID, LIC, and Name
- Search records by NID, LIC, or Name
- Display search results from both Shortlist and Longlist databases

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

## Deployment on IIS Windows Server

To deploy this Laravel application on an IIS Windows server, some modifications and additional steps are required:

1. Ensure IIS is installed and configured on your Windows server.
2. Install PHP for IIS. You can download it from the official PHP website.
3. Install Composer on the server if it's not already installed.
4. Install the URL Rewrite module for IIS.
5. Clone or copy the application files to a directory on the server (e.g., C:\inetpub\wwwroot\lci-lookup).
6. Open a command prompt, navigate to the application directory, and run:

   ```
   composer install --no-dev
   ```
7. Copy the `.env.example` file to `.env` and update it with your server's database credentials and other configuration settings.
8. Generate the application key:

   ```
   php artisan key:generate
   ```
9. Run database migrations:

   ```
   php artisan migrate
   ```
10. Set the appropriate permissions on the storage and bootstrap/cache directories.
11. In IIS Manager, create a new website or application pointing to the public directory of your Laravel application.
12. Set up a new application pool for your website with "No Managed Code" as the .NET CLR version.
13. In your project's public folder, create a new web.config file with the following content:

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
        </system.webServer>
    </configuration>
    ```
14. Modify your application's `public/.htaccess` file to include IIS-specific rules:

    ```apache
    <IfModule mod_rewrite.c>
        <IfModule mod_negotiation.c>
            Options -MultiViews -Indexes
        </IfModule>

        RewriteEngine On

        # Redirect Trailing Slashes If Not A Folder...
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_URI} (.+)/$
        RewriteRule ^ %1 [L,R=301]

        # Handle Front Controller...
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </IfModule>

    # IIS Specific Rules
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteRule ^(.*)$ public/$1 [L]
    </IfModule>
    ```
15. Restart the IIS server.

These modifications should allow your Laravel application to run on IIS. However, you may need to troubleshoot and make further adjustments based on your specific server configuration and application requirements.

For more detailed instructions or troubleshooting, please consult the Laravel documentation on deployment or contact the IT support team.

## Security

This application is for internal use only. Do not share access or data with unauthorized individuals.
