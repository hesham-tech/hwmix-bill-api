# Hostinger Setup for Backup System (Updated)

Following the confirmation from Hostinger support, here is your finalized setup guide.

## 1. Environment Variables (.env)

Add these to your `.env` file on Hostinger:

```env
# Path to MySQL binaries on Hostinger
DUMP_BINARY_PATH=/usr/bin

# Secondary token for restore operations (Keep this secure!)
BACKUP_RESTORE_TOKEN=your_secure_random_token
```

## 2. Cron Job Details (Confirmed)

Hostinger support (Kodee) has already set this up for you:

-   **Command**: `/usr/local/bin/php /home/u715355537/domains/hwnix.com/public_html/api-teste/artisan schedule:run >> /dev/null 2>&1`
-   **Interval**: Every minute (`* * * * *`)

## 3. Important Notes for Restore

-   Site will enter **Maintenance Mode** automatically.
-   Database will be **wiped** and refreshed with the backup content.
-   Ensure `storage/app/backups` and `storage/app/backup-temp` are writable.

## 4. Verification on Server

To test if everything is working correctly on Hostinger, you can run this command via SSH (if available):

```bash
php artisan backup:run --only-db
```

Or simply wait for the first scheduled backup and check the **Backups** page in your dashboard.
