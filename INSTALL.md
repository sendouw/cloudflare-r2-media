# Quick Installation Guide

## For ServerlessWP Users

### 1. Install the Plugin

```bash
cd your-serverlesswp-project/wp/wp-content/plugins
git clone https://github.com/sendouw/cloudflare-r2-media.git
cd cloudflare-r2-media
composer install --no-dev
```

### 2. Create the Loader

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

### 3. Set Environment Variables

**In Vercel Dashboard:**
1. Go to your project → Settings → Environment Variables
2. Add these variables:
   - `R2_BUCKET` = your-bucket-name
   - `R2_ACCOUNT_ID` = your-account-id
   - `R2_ACCESS_KEY_ID` = your-access-key-id
   - `R2_SECRET_ACCESS_KEY` = your-secret-access-key

**In Netlify Dashboard:**
1. Go to Site configuration → Environment variables
2. Add the same variables as above

### 4. Deploy

```bash
git add .
git commit -m "Add Cloudflare R2 media offload"
git push
```

Vercel/Netlify will auto-deploy. The `vendor/` directory will be included in the deployment even though it's gitignored.

### 5. Test

1. Go to your WordPress admin
2. Upload an image
3. Check your R2 bucket - the image should appear there
4. View the image URL - it should point to R2

## Getting R2 Credentials

1. Log in to [Cloudflare Dashboard](https://dash.cloudflare.com)
2. R2 → Create bucket (or use existing)
3. R2 → Manage R2 API Tokens → Create API Token
4. Copy the credentials
5. Your Account ID is in the URL: `dash.cloudflare.com/{ACCOUNT_ID}/r2`

## Troubleshooting

**Plugin not activating?**
- Check all 4 required env vars are set
- Redeploy after setting env vars
- Enable `R2_DEBUG=1` to see logs

**Composer not installed?**
- Install from: https://getcomposer.org/download/
- Or let your deployment handle it

**Need help?**
- [Full README](README.md)
- [Open an issue](https://github.com/sendouw/cloudflare-r2-media/issues)
