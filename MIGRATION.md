# Migrating Existing Media to R2

If you uploaded media before installing the plugin, those images still have local URLs. This guide will help you migrate them to R2.

## What Gets Migrated?

- ✅ Original image files
- ✅ All WordPress-generated thumbnails
- ✅ URLs in post content and excerpts
- ✅ Attachment metadata

## Option 1: Using WP-CLI (Recommended)

If you have WP-CLI access (most hosting providers support it):

```bash
# SSH into your server or run locally
wp eval-file wp-content/plugins/cloudflare-r2-media/migrate-existing-media.php

# Or use the WP-CLI command (if registered)
wp r2 migrate
```

This will:
1. Find all image attachments
2. Upload them to R2 (with thumbnails)
3. Update URLs in all posts

## Option 2: Using WordPress Admin

1. **Enable Debug Mode** temporarily in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   ```

2. **Access the migration script** in your browser:
   ```
   https://yoursite.com/wp-content/plugins/cloudflare-r2-media/migrate-existing-media.php
   ```

3. **Wait for completion** - You'll see progress in the browser

4. **Disable Debug Mode** in `wp-config.php`:
   ```php
   define('WP_DEBUG', false);
   ```

⚠️ **Security Note**: The script only runs when WP_DEBUG is enabled as a safety measure.

## Option 3: Local Development Then Deploy

If your media files exist locally:

```bash
# On your local machine
cd /path/to/your/wordpress

# Set environment variables locally
export R2_BUCKET=your-bucket
export R2_ACCOUNT_ID=your-account
export R2_ACCESS_KEY_ID=your-key
export R2_SECRET_ACCESS_KEY=your-secret

# Run migration
php wp-content/plugins/cloudflare-r2-media/migrate-existing-media.php
```

Then deploy your updated database.

## Just Update URLs (If Files Already in R2)

If you already uploaded files to R2 manually and just need to update URLs:

```bash
wp r2 update-urls
```

Or create a simple PHP script in `wp-content/`:

```php
<?php
require_once 'wp-load.php';
require_once 'plugins/cloudflare-r2-media/migrate-existing-media.php';
cloudflare_r2_update_post_content_urls();
```

Then access it via browser: `https://yoursite.com/wp-content/your-script.php`

## Rollback (If Something Goes Wrong)

To revert URLs back to local:

```bash
wp r2 rollback-urls
```

Or using PHP:

```php
<?php
require_once 'wp-load.php';
require_once 'plugins/cloudflare-r2-media/migrate-existing-media.php';
cloudflare_r2_rollback_urls();
```

## Verifying Migration

After migration:

1. **Check your R2 bucket** - Files should be there
2. **View your posts** - Images should display correctly
3. **Inspect image URLs** - Should point to R2 (right-click → Copy image address)
4. **Check browser console** - No 404 errors for images

## Troubleshooting

### "R2 is not configured"

Make sure environment variables are set:
```bash
echo $R2_BUCKET
echo $R2_ACCOUNT_ID
# etc.
```

### "File not found" errors

Some attachments may not have actual files. This is normal - they'll be skipped.

### Script times out

For large media libraries (1000+ images), you may need to:

1. Increase PHP timeout:
   ```php
   set_time_limit(600); // 10 minutes
   ```

2. Or run in batches using WP-CLI:
   ```bash
   # Get attachment IDs
   wp post list --post_type=attachment --format=ids

   # Process in batches (implement in script)
   ```

### URLs not updating

Clear your cache:
```bash
wp cache flush
```

Or in WordPress admin: Clear any caching plugin cache.

## For ServerlessWP Users

Since ServerlessWP environments are stateless, the best approach is:

1. **Run migration locally** with your production database
2. **Files upload to R2** from your local environment
3. **Push database changes** to production
4. Future uploads will automatically go to R2

## Performance Notes

- Uploading 100 images takes ~2-5 minutes (depending on size)
- Thumbnails are uploaded alongside originals
- The script is safe to run multiple times (won't re-upload existing files)

## Need Help?

- [Open an issue](https://github.com/sendouw/cloudflare-r2-media/issues)
- Check R2 debug logs: Set `R2_DEBUG=1` in environment variables
