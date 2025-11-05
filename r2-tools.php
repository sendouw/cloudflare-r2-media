<?php
/**
 * Cloudflare R2 Migration & Management Tools
 *
 * All-in-one tool for managing R2 media migration
 * Access: https://yoursite.com/wp-content/plugins/cloudflare-r2-media/r2-tools.php?key=YOUR_KEY
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Check for secret key
$required_key = getenv('R2_MIGRATION_KEY') ?: (function_exists('cloudflare_r2_env') ? cloudflare_r2_env('R2_MIGRATION_KEY') : '');
$provided_key = isset($_GET['key']) ? $_GET['key'] : '';

if (empty($required_key)) {
    wp_die('
        <h1>Setup Required</h1>
        <p>Set <code>R2_MIGRATION_KEY</code> environment variable in Vercel/Netlify.</p>
        <p>Suggested value: <code>' . wp_generate_password(32, false) . '</code></p>
    ');
}

if ($provided_key !== $required_key) {
    wp_die('Access denied. Use ?key=YOUR_MIGRATION_KEY', 403);
}

// Load migration functions if available
if (file_exists(__DIR__ . '/migrate-existing-media.php')) {
    require_once __DIR__ . '/migrate-existing-media.php';
}

$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R2 Migration Tools</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        .header p { color: #666; }
        .nav {
            background: white;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .nav a {
            display: inline-block;
            padding: 15px 25px;
            color: #333;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .nav a:hover { background: #f8f9fa; }
        .nav a.active {
            color: #0073aa;
            border-bottom-color: #0073aa;
            background: #f8f9fa;
        }
        .panel {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { color: #333; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            margin: 5px 5px 5px 0;
        }
        .btn:hover { background: #005a87; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #0073aa;
            color: white;
        }
        .status-ok { color: green; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
        }
        .output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            font-size: 13px;
            line-height: 1.6;
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Cloudflare R2 Migration Tools</h1>
            <p>Manage your media migration to Cloudflare R2</p>
        </div>

        <div class="nav">
            <a href="?key=<?php echo urlencode($provided_key); ?>&action=dashboard" class="<?php echo $action === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
            <a href="?key=<?php echo urlencode($provided_key); ?>&action=config" class="<?php echo $action === 'config' ? 'active' : ''; ?>">Configuration</a>
            <a href="?key=<?php echo urlencode($provided_key); ?>&action=check-urls" class="<?php echo $action === 'check-urls' ? 'active' : ''; ?>">Check URLs</a>
            <a href="?key=<?php echo urlencode($provided_key); ?>&action=replace-urls" class="<?php echo $action === 'replace-urls' ? 'active' : ''; ?>">Replace URLs</a>
            <a href="?key=<?php echo urlencode($provided_key); ?>&action=migrate" class="<?php echo $action === 'migrate' ? 'active' : ''; ?>">Upload to R2</a>
        </div>

        <div class="panel">
            <?php
            switch ($action) {
                case 'config':
                    include_template_config();
                    break;
                case 'check-urls':
                    include_template_check_urls();
                    break;
                case 'replace-urls':
                    include_template_replace_urls();
                    break;
                case 'migrate':
                    include_template_migrate();
                    break;
                default:
                    include_template_dashboard();
                    break;
            }
            ?>
        </div>
    </div>
</body>
</html>

<?php

// ============================================================================
// DASHBOARD
// ============================================================================
function include_template_dashboard() {
    ?>
    <h2>📊 Dashboard</h2>

    <div class="info-box">
        <strong>Welcome to R2 Migration Tools!</strong>
        <p>This tool helps you migrate existing WordPress media to Cloudflare R2 and update URLs in your posts.</p>
    </div>

    <h3>Quick Actions</h3>
    <p>
        <a href="?key=<?php echo urlencode($_GET['key']); ?>&action=config" class="btn">🔍 Check Configuration</a>
        <a href="?key=<?php echo urlencode($_GET['key']); ?>&action=check-urls" class="btn">📋 Check Image URLs</a>
        <a href="?key=<?php echo urlencode($_GET['key']); ?>&action=replace-urls" class="btn">🔄 Replace URLs</a>
    </p>

    <h3>Migration Process</h3>
    <ol>
        <li><strong>Check Configuration</strong> - Verify R2 is set up correctly</li>
        <li><strong>Check URLs</strong> - See where your images currently point</li>
        <li><strong>Replace URLs</strong> - Update post content to point to R2</li>
        <li><strong>Upload to R2</strong> (optional) - If files exist locally but not in R2</li>
    </ol>

    <h3>Current Status</h3>
    <table>
        <tr>
            <th>Check</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>R2 Plugin Active</td>
            <td><?php echo function_exists('cloudflare_r2_is_enabled') && cloudflare_r2_is_enabled() ? '<span class="status-ok">✓ YES</span>' : '<span class="status-error">✗ NO</span>'; ?></td>
        </tr>
        <tr>
            <td>Environment Configured</td>
            <td><?php echo function_exists('cloudflare_r2_env') && cloudflare_r2_env('R2_BUCKET') ? '<span class="status-ok">✓ YES</span>' : '<span class="status-error">✗ NO</span>'; ?></td>
        </tr>
        <tr>
            <td>Total Attachments</td>
            <td><?php echo count(get_posts(array('post_type' => 'attachment', 'posts_per_page' => -1))); ?></td>
        </tr>
    </table>
    <?php
}

// ============================================================================
// CONFIGURATION CHECK
// ============================================================================
function include_template_config() {
    ?>
    <h2>🔍 Configuration Check</h2>

    <h3>Environment Variables</h3>
    <table>
        <tr>
            <th>Variable</th>
            <th>Status</th>
            <th>Value</th>
        </tr>
        <?php
        $vars = array(
            'R2_BUCKET',
            'R2_ACCOUNT_ID',
            'R2_ACCESS_KEY_ID',
            'R2_SECRET_ACCESS_KEY',
            'R2_PUBLIC_BASE_URL',
            'R2_OBJECT_PREFIX',
        );

        foreach ($vars as $name) {
            $value = function_exists('cloudflare_r2_env') ? cloudflare_r2_env($name) : '';
            $status = $value ? '<span class="status-ok">✓ SET</span>' : '<span class="status-error">✗ NOT SET</span>';
            $display = $value;

            if (in_array($name, array('R2_SECRET_ACCESS_KEY', 'R2_ACCESS_KEY_ID')) && $value) {
                $display = substr($value, 0, 8) . '...';
            }

            echo "<tr>";
            echo "<td><code>{$name}</code></td>";
            echo "<td>{$status}</td>";
            echo "<td>" . esc_html($display ?: 'Not set') . "</td>";
            echo "</tr>";
        }
        ?>
    </table>

    <h3>R2 URLs</h3>
    <table>
        <tr>
            <th>Type</th>
            <th>URL</th>
        </tr>
        <tr>
            <td>WordPress Upload URL</td>
            <td><code><?php echo esc_html(wp_get_upload_dir()['baseurl']); ?></code></td>
        </tr>
        <tr>
            <td>R2 Base URL</td>
            <td><code><?php echo function_exists('cloudflare_r2_prefixed_base_url') ? esc_html(cloudflare_r2_prefixed_base_url()) : 'N/A'; ?></code></td>
        </tr>
    </table>

    <?php if (!function_exists('cloudflare_r2_env') || !cloudflare_r2_env('R2_PUBLIC_BASE_URL')): ?>
    <div class="warning-box">
        <h4>⚠️ R2_PUBLIC_BASE_URL Not Set</h4>
        <p>Add this in Vercel/Netlify:</p>
        <ul>
            <li><strong>Key:</strong> <code>R2_PUBLIC_BASE_URL</code></li>
            <li><strong>Value:</strong> Your R2 public URL (e.g., <code>https://pub-xxxxx.r2.dev</code>)</li>
        </ul>
    </div>
    <?php endif; ?>
    <?php
}

// ============================================================================
// CHECK URLS
// ============================================================================
function include_template_check_urls() {
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 50,
        'post_mime_type' => 'image',
    ));

    ?>
    <h2>📋 Check Image URLs</h2>

    <p>Found <strong><?php echo count($attachments); ?></strong> image attachments (showing latest 50).</p>

    <?php if (count($attachments) > 0): ?>
    <table>
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Current URL</th>
            <th>Status</th>
        </tr>
        <?php foreach ($attachments as $attachment):
            $url = wp_get_attachment_url($attachment->ID);

            if (strpos($url, 'r2.cloudflarestorage.com') !== false || strpos($url, 'r2.dev') !== false) {
                $status = '<span class="status-ok">✓ R2</span>';
            } elseif (strpos($url, home_url()) !== false) {
                $status = '<span class="status-error">✗ Local</span>';
            } else {
                $status = '? External';
            }
        ?>
        <tr>
            <td><?php echo $attachment->ID; ?></td>
            <td><?php echo esc_html($attachment->post_title); ?></td>
            <td style="font-family: monospace; font-size: 11px; word-break: break-all;"><?php echo esc_html($url); ?></td>
            <td><?php echo $status; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p>No image attachments found.</p>
    <?php endif; ?>
    <?php
}

// ============================================================================
// REPLACE URLS
// ============================================================================
function include_template_replace_urls() {
    if (isset($_GET['run'])) {
        $old_url = isset($_GET['old_url']) ? trim($_GET['old_url']) : '';
        $new_url = isset($_GET['new_url']) ? trim($_GET['new_url']) : '';
        $dry_run = $_GET['run'] === 'dry';

        if (empty($old_url) || empty($new_url)) {
            echo '<div class="warning-box">❌ Both URLs are required</div>';
        } else {
            echo '<div class="output">';
            $result = force_replace_urls($old_url, $new_url, $dry_run);
            echo '</div>';

            if ($result['updated'] > 0) {
                if ($dry_run) {
                    echo '<div class="info-box">ℹ️ This was a DRY RUN - no changes were made. Click "Run Live" to actually update.</div>';
                } else {
                    echo '<div class="success-box">✅ SUCCESS! ' . $result['updated'] . ' posts updated with ' . $result['replacements'] . ' URLs replaced.</div>';
                }
            }
        }
    }

    ?>
    <h2>🔄 Replace URLs</h2>

    <div class="info-box">
        <strong>💡 Tip:</strong> Run "Dry Run" first to preview changes without making actual updates.
    </div>

    <form method="get">
        <input type="hidden" name="key" value="<?php echo esc_attr($_GET['key']); ?>">
        <input type="hidden" name="action" value="replace-urls">

        <div class="form-group">
            <label>Old URL (what to replace):</label>
            <input type="text" name="old_url" value="<?php echo esc_attr(isset($_GET['old_url']) ? $_GET['old_url'] : wp_get_upload_dir()['baseurl']); ?>" required>
            <small>Example: https://yoursite.com/wp-content/uploads</small>
        </div>

        <div class="form-group">
            <label>New R2 URL (replace with):</label>
            <input type="text" name="new_url" value="<?php echo esc_attr(isset($_GET['new_url']) ? $_GET['new_url'] : (function_exists('cloudflare_r2_prefixed_base_url') ? cloudflare_r2_prefixed_base_url() : '')); ?>" required>
            <small>Your R2 bucket URL (e.g., https://pub-xxxxx.r2.dev)</small>
        </div>

        <button type="submit" name="run" value="dry" class="btn btn-secondary">
            🔍 Dry Run (Preview)
        </button>

        <button type="submit" name="run" value="live" class="btn btn-danger">
            ⚡ Run Live (Update Database)
        </button>
    </form>
    <?php
}

// ============================================================================
// MIGRATE FILES
// ============================================================================
function include_template_migrate() {
    if (isset($_GET['run'])) {
        echo '<div class="output">';
        if (function_exists('cloudflare_r2_migrate_existing_media')) {
            $result = cloudflare_r2_migrate_existing_media();
            if (is_wp_error($result)) {
                echo "\n❌ Error: " . $result->get_error_message();
            }
        } else {
            echo "❌ Migration functions not available. Ensure migrate-existing-media.php exists.";
        }
        echo '</div>';
    }

    ?>
    <h2>📤 Upload Files to R2</h2>

    <div class="warning-box">
        <strong>⚠️ Note:</strong> This only works if files exist on the server filesystem. For ServerlessWP/Vercel, files are usually already in R2 - you just need to update URLs.
    </div>

    <p>This will:</p>
    <ul>
        <li>Find all image attachments in WordPress</li>
        <li>Upload original files to R2</li>
        <li>Upload all thumbnail sizes</li>
        <li>Update URLs in posts</li>
    </ul>

    <a href="?key=<?php echo urlencode($_GET['key']); ?>&action=migrate&run=1" class="btn"
       onclick="return confirm('Start migration? This will upload files to R2 and update URLs.')">
        📤 Start Migration
    </a>
    <?php
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function force_replace_urls($old_url, $new_url, $dry_run = false) {
    global $wpdb;

    $old_url = rtrim($old_url, '/');
    $new_url = rtrim($new_url, '/');

    echo "=== URL Replacement ===\n\n";
    echo "FROM: {$old_url}\n";
    echo "TO:   {$new_url}\n";
    echo "Mode: " . ($dry_run ? "DRY RUN" : "LIVE") . "\n\n";

    $posts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts}
            WHERE post_content LIKE %s
            AND post_status IN ('publish', 'draft', 'private', 'pending')
            ORDER BY ID DESC",
            '%' . $wpdb->esc_like($old_url) . '%'
        )
    );

    echo "Found " . count($posts) . " posts with old URLs\n\n";

    $updated = 0;
    $total_replacements = 0;

    foreach ($posts as $post) {
        $count_before = substr_count($post->post_content, $old_url);
        if ($count_before === 0) continue;

        $new_content = str_replace($old_url, $new_url, $post->post_content);
        $replaced = $count_before - substr_count($new_content, $old_url);

        if ($replaced > 0) {
            echo "Post #{$post->ID} \"{$post->post_title}\": {$replaced} URLs replaced\n";

            if (!$dry_run) {
                $wpdb->update(
                    $wpdb->posts,
                    array('post_content' => $new_content),
                    array('ID' => $post->ID),
                    array('%s'),
                    array('%d')
                );
            }

            $updated++;
            $total_replacements += $replaced;
        }
    }

    if (!$dry_run && function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    echo "\n=== Summary ===\n";
    echo "Posts: {$updated}\n";
    echo "URLs replaced: {$total_replacements}\n";

    return array('updated' => $updated, 'replacements' => $total_replacements);
}
