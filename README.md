# Cloudflare R2 Media Offload for ServerlessWP

A lightweight WordPress plugin that automatically offloads media uploads to Cloudflare R2 storage. Designed specifically for ServerlessWP deployments on Vercel, Netlify, or AWS Lambda.

## Why This Plugin?

Serverless environments are stateless - uploaded files disappear after each deployment. This plugin solves that by:

- ✅ Automatically uploading media files to Cloudflare R2
- ✅ Rewriting URLs to serve from R2
- ✅ Handling all image sizes (thumbnails, responsive images)
- ✅ Deleting from R2 when you delete from WordPress
- ✅ Zero configuration UI - just set environment variables

## Features

- **Automatic uploads**: All media files are uploaded to R2 as soon as they're added to WordPress
- **URL rewriting**: Media URLs automatically point to R2 storage
- **Thumbnail support**: All WordPress-generated image sizes are uploaded
- **Responsive images**: Srcset attributes work correctly with R2 URLs
- **Deletion sync**: Deleting media in WordPress also removes it from R2
- **Custom domains**: Support for R2 custom domains/public URLs
- **Path prefixes**: Optional object key prefixes for organization

## Installation

### For ServerlessWP Users (Vercel/Netlify/AWS)

1. **Clone your ServerlessWP repository** (if you haven't already)
   ```bash
   git clone https://github.com/yourusername/your-serverlesswp-site
   cd your-serverlesswp-site
   ```

2. **Install this plugin**
   ```bash
   # Clone the plugin into your plugins directory
   cd wp/wp-content/plugins
   git clone https://github.com/sendouw/cloudflare-r2-media.git
   cd cloudflare-r2-media

   # Install dependencies
   composer install --no-dev
   ```

3. **Create the mu-plugin loader** (if not already present)

   Create `wp/wp-content/mu-plugins/cloudflare-r2-loader.php`:
   ```php
   <?php
   /*
   Plugin Name: Cloudflare R2 Media Loader
   Description: Auto-loads the Cloudflare R2 Media Offload plugin
   Version: 1.0.0
   */

   if ( ! defined( 'ABSPATH' ) ) {
       exit;
   }

   $r2_plugin = WP_PLUGIN_DIR . '/cloudflare-r2-media/cloudflare-r2-media.php';

   if ( file_exists( $r2_plugin ) ) {
       require_once $r2_plugin;
   }
   ```

4. **Configure environment variables** in Vercel/Netlify/AWS

   **Required:**
   - `R2_BUCKET` - Your R2 bucket name
   - `R2_ACCOUNT_ID` - Your Cloudflare account ID
   - `R2_ACCESS_KEY_ID` - R2 API token (access key)
   - `R2_SECRET_ACCESS_KEY` - R2 API token (secret key)

   **Optional:**
   - `R2_PUBLIC_BASE_URL` - Custom domain for serving media (e.g., `https://cdn.yourdomain.com`)
   - `R2_OBJECT_PREFIX` - Path prefix for objects (e.g., `media/uploads`)
   - `R2_REGION` - Defaults to `auto`
   - `R2_ENDPOINT` - Custom endpoint URL
   - `R2_DEBUG` - Set to `1` to enable debug logging

5. **Commit and push** (vendor/ is gitignored, but will deploy)
   ```bash
   cd ../../../..  # Back to repo root
   git add .
   git commit -m "Add Cloudflare R2 media offload plugin"
   git push
   ```

6. **Deployment happens automatically** on Vercel/Netlify

   Note: The `vendor/` directory is ignored by git but **will be included** in the deployment because Vercel/Netlify deploy from your local filesystem.

### Vercel Environment Variables Setup

1. Go to your project on Vercel
2. Settings → Environment Variables
3. Add each variable:
   ```
   R2_BUCKET = your-bucket-name
   R2_ACCOUNT_ID = your-account-id
   R2_ACCESS_KEY_ID = your-access-key
   R2_SECRET_ACCESS_KEY = your-secret-key
   ```
4. Click "Save"
5. Redeploy your site (Deployments → ... → Redeploy)

### Netlify Environment Variables Setup

1. Go to your site on Netlify
2. Site configuration → Environment variables
3. Add each variable with the values from above
4. Save and trigger a new deploy

## Getting Cloudflare R2 Credentials

1. Log in to [Cloudflare Dashboard](https://dash.cloudflare.com)
2. Go to **R2** from the sidebar
3. Create a bucket (or use an existing one)
4. Go to **R2 → Manage R2 API Tokens**
5. Create a new API token with **Edit** permissions
6. Copy the Access Key ID and Secret Access Key
7. Your Account ID is visible in the R2 dashboard URL: `https://dash.cloudflare.com/{account-id}/r2`

### Optional: Custom Domain Setup

To serve media from your own domain (e.g., `cdn.yourdomain.com`):

1. In Cloudflare R2, click your bucket → Settings → Public Access
2. Enable "Allow Access" and connect a custom domain
3. Set the environment variable:
   ```
   R2_PUBLIC_BASE_URL=https://cdn.yourdomain.com
   ```

## How It Works

1. **Upload**: When you upload media in WordPress, the plugin intercepts it
2. **Store**: File is uploaded to R2 using the AWS S3-compatible API
3. **Rewrite**: WordPress URLs are rewritten to point to R2
4. **Serve**: Your serverless site serves media directly from R2 (CDN-backed)

## Migrating Existing Media

If you have images uploaded before installing the plugin, use the **R2 Migration Tools**:

1. **Set migration key** in Vercel:
   - Add `R2_MIGRATION_KEY` environment variable (any random secret string)
   - Redeploy

2. **Access migration tools**:
   ```
   https://yoursite.com/wp-content/plugins/cloudflare-r2-media/r2-tools.php?key=YOUR_MIGRATION_KEY
   ```

3. **Use the dashboard** to:
   - ✅ Check configuration
   - ✅ View current image URLs
   - ✅ Replace old URLs with R2 URLs
   - ✅ Upload files to R2 (if they exist locally)

See [MIGRATION.md](MIGRATION.md) for detailed migration guide.

## Troubleshooting

### Plugin not working after deployment?

**Check environment variables:**
```bash
# Using Vercel CLI
vercel env ls

# Or check in the dashboard
```

**Enable debug mode:**
Set `R2_DEBUG=1` in your environment variables to see detailed logs.

**Check WordPress debug log:**
If you have `WP_DEBUG_LOG` enabled, check `wp-content/debug.log` for R2-related messages.

### Uploads still going to local storage?

The plugin only activates when all required environment variables are set. Check:
1. All 4 required env vars are set (R2_BUCKET, R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY)
2. Values don't have extra spaces or quotes
3. You redeployed after setting the env vars

### Broken image URLs after installing plugin?

If you have existing posts with images uploaded before the plugin was installed:

1. Set `R2_MIGRATION_KEY` environment variable in Vercel
2. Access the migration tools: `r2-tools.php?key=YOUR_KEY`
3. Use "Replace URLs" to update post content

### Composer install fails locally?

You need Composer installed: https://getcomposer.org/download/

Or skip it locally - just push to git and let your deployment handle it.

### Large file push failures?

The `vendor/` directory should NOT be in git. Check your `.gitignore`:
```
wp/wp-content/plugins/*/vendor/
wp/wp-content/plugins/*/composer.lock
```

## Dependencies

- **AWS SDK for PHP** (`aws/aws-sdk-php`) - Provides S3-compatible client for R2
- WordPress 5.0+
- PHP 7.4+

Dependencies are managed via Composer and installed automatically.

## Technical Details

### File Structure
```
cloudflare-r2-media/
├── cloudflare-r2-media.php    # Main plugin file
├── composer.json              # Dependency management
├── composer.lock              # Locked dependency versions
├── README.md                  # This file
├── LICENSE                    # MIT License
└── vendor/                    # Dependencies (gitignored, auto-installed)
```

### Hooks & Filters

The plugin uses these WordPress hooks:
- `wp_handle_upload` - Upload files to R2
- `wp_update_attachment_metadata` - Upload thumbnails
- `delete_attachment` - Delete from R2
- `upload_dir` - Rewrite base upload URL
- `wp_get_attachment_url` - Rewrite attachment URLs
- `wp_calculate_image_srcset` - Rewrite responsive image URLs
- `wp_prepare_attachment_for_js` - Fix media library URLs

### SDK Detection

The plugin can use AWS SDK from multiple sources:
1. Its own `vendor/` directory (preferred)
2. WP Offload Media plugin (if installed)
3. Other plugins that include AWS SDK

## License

MIT License - see [LICENSE](LICENSE) file

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

- [Open an issue](https://github.com/sendouw/cloudflare-r2-media/issues)
- [ServerlessWP Documentation](https://github.com/mitchmac/serverlesswp)

## Credits

Created for the ServerlessWP community by [Andy Sendouw](https://github.com/sendouw)

Built with inspiration from the WordPress and ServerlessWP communities.
