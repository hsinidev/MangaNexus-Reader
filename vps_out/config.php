<?php
/**
 * config.php — MangaNexus Core Configuration & Database Bootstrap
 */

// Secure session startup (httponly session cookie, secure connection if HTTPS is active, and SameSite enforcement)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Licensing has been disabled. The admin panel is protected by username + password only.

// Global Path Constants
define('BASE_PATH', __DIR__);

// Load Composer Autoloader
require_once BASE_PATH . '/vendor/autoload.php';

define('DATA_DIR', BASE_PATH . '/data');
define('DB_PATH', DATA_DIR . '/manga.db');
define('UPLOAD_DIR', BASE_PATH . '/public_html/uploads');
define('COVERS_DIR', UPLOAD_DIR . '/covers');
define('PAGES_DIR', UPLOAD_DIR . '/pages');
define('BLOG_DIR', UPLOAD_DIR . '/blog');
define('IMPORT_DIR', BASE_PATH . '/import');

// Ensure base directories exist
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(COVERS_DIR)) {
    mkdir(COVERS_DIR, 0755, true);
}
if (!is_dir(PAGES_DIR)) {
    mkdir(PAGES_DIR, 0755, true);
}
if (!is_dir(BLOG_DIR)) {
    mkdir(BLOG_DIR, 0755, true);
}
if (!is_dir(IMPORT_DIR)) {
    mkdir(IMPORT_DIR, 0755, true);
}

// Secure the import directory against direct web visits
$htaccess_import = IMPORT_DIR . '/.htaccess';
if (!file_exists($htaccess_import)) {
    file_put_contents($htaccess_import, "Deny from all\n");
}

// ── Database Connection (PDO SQLite) ──────────────────────────────────────────

try {
    $pdo = \MangaNexus\Database\Database::getConnection();
    \MangaNexus\Database\Migrator::run($pdo);

    $block_count = $pdo->query("SELECT COUNT(*) FROM ad_blocks")->fetchColumn();
    if ($block_count == 0) {
        $ads_table_exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ads'")->fetchColumn();
        $old_data = [];
        if ($ads_table_exists) {
            try {
                $old_rows = $pdo->query("SELECT * FROM ads")->fetchAll();
                foreach ($old_rows as $row) {
                    $old_data[$row['id']] = $row;
                }
            } catch (PDOException $e) {
                // Ignore silent migration errors
            }
        }

        $default_blocks = [
            1 => ['Global Header Ad', 'header', 'header'],
            2 => ['Global Footer Ad', 'footer', 'footer'],
            3 => ['Global Sidebar Ad', 'sidebar', 'sidebar'],
            4 => ['Manga Details Page Ad', 'manga_bottom', 'manga_info'],
            5 => ['Manga Reader Top Ad', 'reader_top', 'reader_top'],
            6 => ['Manga Reader Between Pages Ad', 'reader_between', 'reader_between'],
            7 => ['Manga Reader Bottom Ad', 'reader_bottom', 'reader_bottom'],
        ];

        $stmt = $pdo->prepare("INSERT INTO ad_blocks 
            (block_index, name, code, is_active, insertion_type, between_frequency, target_pages, target_devices) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        for ($i = 1; $i <= 16; $i++) {
            $name = "Ad Block " . $i;
            $code = '';
            $is_active = 0;
            $insertion_type = 'none';
            $between_frequency = 5;
            $target_pages = 'all';
            $target_devices = 'all';

            if (isset($default_blocks[$i])) {
                $def = $default_blocks[$i];
                $name = $def[0];
                $insertion_type = $def[1];
                $old_key = $def[2];

                if (isset($old_data[$old_key])) {
                    $old_ad = $old_data[$old_key];
                    $code = $old_ad['code'];
                    $is_active = (int)$old_ad['is_active'];
                    $between_frequency = (int)$old_ad['frequency'] > 0 ? (int)$old_ad['frequency'] : 5;
                }
            }

            $stmt->execute([
                $i,
                $name,
                $code,
                $is_active,
                $insertion_type,
                $between_frequency,
                $target_pages,
                $target_devices
            ]);
        }

        if ($ads_table_exists) {
            $pdo->exec("DROP TABLE IF EXISTS ads;");
        }
    }

} catch (PDOException $e) {
    \MangaNexus\Logging\Logger::error("Database boot failure: " . $e->getMessage());
    $is_local = (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost'));
    if ($is_local) {
        die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
    } else {
        http_response_code(500);
        die("Critical System Error: An unexpected database error occurred. Please contact the administrator.");
    }
}

// ── Helper Utilities ──────────────────────────────────────────────────────────

/**
 * Execute a query with parameters
 */
function db_query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch one row
 */
function db_fetch($sql, $params = []) {
    return db_query($sql, $params)->fetch();
}

/**
 * Fetch all rows
 */
function db_fetch_all($sql, $params = []) {
    return db_query($sql, $params)->fetchAll();
}

/**
 * Checks if the current visitor is on a mobile device (using User-Agent header)
 */
function is_mobile_device() {
    if (empty($_SERVER['HTTP_USER_AGENT'])) return false;
    return (bool)preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $_SERVER['HTTP_USER_AGENT']);
}

/**
 * Wraps and renders the HTML/JS for a single ad block.
 */
function render_ad_block($block) {
    if (empty($block['code'])) return '';
    
    $class = !empty($block['wrapper_class']) ? htmlspecialchars($block['wrapper_class']) : 'ad-block-wrapper';
    $style = !empty($block['wrapper_style']) ? htmlspecialchars($block['wrapper_style']) : '';
    
    $html = '<div class="' . $class . '"';
    if (!empty($style)) {
        $html .= ' style="' . $style . '"';
    }
    $html .= ' data-block-id="' . $block['block_index'] . '">';
    $html .= $block['code'];
    $html .= '</div>';
    
    return $html;
}

/**
 * Renders the HTML/JS code for all active ad blocks in a specific position/slot.
 * Filtered by targeted page type and client device type.
 */
function show_ad($position) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM ad_blocks WHERE insertion_type = ? AND is_active = 1 ORDER BY block_index ASC");
        $stmt->execute([$position]);
        $blocks = $stmt->fetchAll();
        
        $output = '';
        $is_mobile = is_mobile_device();
        $current_page = isset($GLOBALS['page_type']) ? $GLOBALS['page_type'] : 'all';
        
        foreach ($blocks as $block) {
            // 1. Device Filter
            $device_target = $block['target_devices'] ?? 'all';
            if ($device_target === 'desktop' && $is_mobile) continue;
            if ($device_target === 'mobile' && !$is_mobile) continue;
            
            // 2. Page Filter
            $page_target = $block['target_pages'] ?? 'all';
            if ($page_target !== 'all' && $page_target !== $current_page) continue;
            
            $output .= render_ad_block($block);
        }
        return $output;
    } catch (PDOException $e) {
        return '';
    }
}

/**
 * Legacy compatibility: Returns details for the first active ad block of a given type.
 */
function get_ad($position) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM ad_blocks WHERE insertion_type = ? AND is_active = 1 ORDER BY block_index ASC LIMIT 1");
        $stmt->execute([$position]);
        $block = $stmt->fetch();
        if ($block) {
            return [
                'code' => $block['code'],
                'frequency' => $block['between_frequency'],
                'is_active' => $block['is_active']
            ];
        }
    } catch (PDOException $e) {
        // Fallback
    }
    return null;
}

/**
 * Retrieve global site settings
 */
function get_settings() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $domain = trim(preg_replace('/^https?:\/\//i', '', $host), '/ ');
    
    $settings = db_fetch("SELECT * FROM site_settings WHERE production_domain = ?", [$domain]);
    if (!$settings) {
        $settings = db_fetch("SELECT * FROM site_settings WHERE id = 'global'");
    }
    if (!$settings) {
        // Fallback safety
        return [
            'site_title' => 'MangaNexus',
            'site_description' => 'Read the latest manga series online.',
            'website_mode' => 'general',
            'admin_slug' => 'admin191103400',
            'admin_username' => 'admin',
            'admin_password' => '',
            'current_theme' => 'midnight-dark',
            'homepage_categories' => '[]',
            'geo_config' => '[]',
            'google_analytics_id' => ''
        ];
    }
    return $settings;
}

/**
 * Dispatch prompt to preferred Generative AI provider (Gemini, OpenAI ChatGPT, Ollama)
 */
function dispatch_ai_prompt($prompt, $settings = null) {
    if ($settings === null) {
        $settings = get_settings();
    }
    
    $provider = $settings['preferred_ai_provider'] ?? 'gemini';
    
    if ($provider === 'gemini') {
        $api_key = $settings['gemini_api_key'] ?? '';
        if (empty($api_key)) {
            return ['error' => 'Gemini API Key is missing. Add it in settings.'];
        }
        
        $data = json_encode([
            "contents" => [
                ["parts" => [["text" => $prompt]]]
            ],
            "generationConfig" => ["responseMimeType" => "application/json"]
        ]);
        
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode >= 400) {
            return ['error' => 'Gemini API Error (HTTP ' . $httpcode . ')', 'details' => $response];
        }
        
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Clean markdown code blocks
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/```$/i', '', $text);
        return trim($text);
        
    } elseif ($provider === 'openai') {
        $api_key = $settings['openai_api_key'] ?? '';
        $model = $settings['openai_model'] ?? 'gpt-4o-mini';
        if (empty($api_key)) {
            return ['error' => 'OpenAI API Key is missing. Add it in settings.'];
        }
        
        $payload = [
            "model" => $model,
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ]
        ];
        if (strpos($model, 'o1-') !== 0) {
            $payload["response_format"] = ["type" => "json_object"];
        }
        
        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode >= 400) {
            return ['error' => 'OpenAI API Error (HTTP ' . $httpcode . ')', 'details' => $response];
        }
        
        $result = json_decode($response, true);
        $text = $result['choices'][0]['message']['content'] ?? '';
        
        // Clean markdown code blocks
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/```$/i', '', $text);
        return trim($text);
        
    } elseif ($provider === 'ollama') {
        $api_url = rtrim($settings['ollama_api_url'] ?? 'http://localhost:11434', '/');
        $model = $settings['ollama_model'] ?? 'llama3';
        
        $payload = [
            "model" => $model,
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ],
            "stream" => false,
            "format" => "json"
        ];
        
        $ch = curl_init($api_url . "/api/chat");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return ['error' => 'Ollama Connection Failed', 'details' => $curl_err];
        }
        
        if ($httpcode >= 400) {
            return ['error' => 'Ollama API Error (HTTP ' . $httpcode . ')', 'details' => $response];
        }
        
        $result = json_decode($response, true);
        $text = $result['message']['content'] ?? '';
        
        // Clean markdown code blocks
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/```$/i', '', $text);
        return trim($text);
    }
    
    return ['error' => 'Unsupported AI provider: ' . $provider];
}

/**
 * Renders the Google Analytics Tracking tag in the head if configured.
 * Supports both raw Measurement ID (G-XXXXXXXXXX) and copy-pasted full <script> tag block.
 */
function show_google_analytics_tag() {
    $settings = get_settings();
    $ga_id = !empty($settings['google_analytics_id']) ? trim($settings['google_analytics_id']) : '';
    if (empty($ga_id)) {
        return '';
    }
    
    // If they pasted the entire tracking script instead of just the ID
    if (str_contains($ga_id, '<script')) {
        return $ga_id . "\n";
    }
    
    // Otherwise, construct standard gtag.js code
    return "<!-- Google tag (gtag.js) -->\n" .
           "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . htmlspecialchars($ga_id) . "\"></script>\n" .
           "<script>\n" .
           "  window.dataLayer = window.dataLayer || [];\n" .
           "  function gtag(){dataLayer.push(arguments);}\n" .
           "  gtag('js', new Date());\n" .
           "  gtag('config', '" . htmlspecialchars($ga_id) . "');\n" .
           "</script>\n";
}

/**
 * Generates a cryptographically secure random UUIDv4 string
 */
function uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Normalize and sanitize strings for SEO slug URLs
 */
function sanitize_slug($string) {
    if (function_exists('transliterator_transliterate')) {
        $string = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower();', $string);
    } else {
        $string = strtolower($string);
        // Basic transliteration mappings for common accented chars
        $accents = [
            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'ae', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'ð'=>'d', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o',
            'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'th', 'ÿ'=>'y',
            'đ'=>'d', 'ß'=>'ss', 'æ'=>'ae', 'œ'=>'oe'
        ];
        $string = strtr($string, $accents);
    }
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

/**
 * Formats a database date string into a user-friendly format
 */
function format_manga_date($date_str) {
    if (empty($date_str)) {
        return '—';
    }
    $timestamp = strtotime($date_str);
    return $timestamp ? date('F j, Y', $timestamp) : $date_str;
}

// ── Image Processing / WebP Conversion Helper ───────────────────────────────

/**
 * Helper to process and convert uploaded images to progressive WebP
 * Checks for GD (with WebP support), Imagick, or falls back to direct copies.
 */
function optimize_image($src_path, $dest_path, $quality = 60, $max_width = 1600) {
    // Security: validate the file is a genuine image by reading its binary signature.
    // getimagesize() checks the actual file bytes, not just the extension.
    // A PHP file disguised as image.jpg will fail this check.
    $info = getimagesize($src_path);
    if (!$info) {
        // Not a valid image — reject outright to prevent RCE via disguised scripts
        return false;
    }

    // Security: whitelist allowed image MIME types only
    $allowed_mimes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'image/webp', 'image/avif', 'image/bmp', 'image/svg+xml',
        'image/x-icon', 'image/vnd.microsoft.icon'
    ];
    if (!in_array($info['mime'], $allowed_mimes, true)) {
        return false;
    }

    $mime = $info['mime'];
    $ow = $info[0];
    $oh = $info[1];

    // 1. Try PHP GD extension if available
    if (extension_loaded('gd')) {
        $image = null;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($src_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($src_path);
                // Preserve transparency
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($src_path);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($src_path);
                break;
            case 'image/bmp':
                $image = @imagecreatefrombmp($src_path);
                break;
        }

        if ($image) {
            // Resize if wider than max_width
            if ($max_width > 0 && $ow > $max_width) {
                $nw = $max_width;
                $nh = (int)round($oh * $max_width / $ow);
                $dst_img = imagecreatetruecolor($nw, $nh);
                if ($dst_img) {
                    imagealphablending($dst_img, false);
                    imagesavealpha($dst_img, true);
                    imagecopyresampled($dst_img, $image, 0,0,0,0, $nw,$nh,$ow,$oh);
                    imagedestroy($image);
                    $image = $dst_img;
                }
            }

            // Check WebP support in GD
            if (function_exists('imagewebp')) {
                // progressive webp isn't natively supported, but WebP is
                $success = imagewebp($image, $dest_path, $quality);
                imagedestroy($image);
                if ($success) return true;
            } else {
                // GD exists but WebP support is disabled. Output JPG or PNG.
                $success = false;
                if ($mime === 'image/png') {
                    $success = imagepng($image, $dest_path, 6);
                } else {
                    $success = imagejpeg($image, $dest_path, $quality);
                }
                imagedestroy($image);
                if ($success) return true;
            }
        }
    }

    // 2. Try PHP Imagick extension if GD fails
    if (extension_loaded('imagick')) {
        try {
            $im = new Imagick($src_path);
            if ($max_width > 0 && $ow > $max_width) {
                $nw = $max_width;
                $nh = (int)round($oh * $max_width / $ow);
                $im->resizeImage($nw, $nh, Imagick::FILTER_LANCZOS, 1);
            }
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($quality);
            // Progressive WebP hints
            $im->setOption('webp:lossless', 'false');
            $im->writeImage($dest_path);
            $im->clear();
            $im->destroy();
            return true;
        } catch (Exception $e) {
            // Log or ignore to try fallback
        }
    }

    // 3. Fallback: Copy the validated image file directly.
    // At this point we have already confirmed via getimagesize() above that
    // $src_path is a genuine image, so this copy is safe.
    return copy($src_path, $dest_path);
}

/**
 * Extracts a chapter number from a folder name (e.g. "Chapter 4" -> 4)
 */
function parse_chapter_number($folderName) {
    if (preg_match('/(?:chapter|ch|c)\.?[_\s-]*(\d+(?:\.\d+)?)/i', $folderName, $matches)) {
        return floatval($matches[1]);
    }
    if (preg_match('/(\d+(?:\.\d+)?)/', $folderName, $matches)) {
        return floatval($matches[1]);
    }
    return null;
}

/**
 * Converts a hexadecimal color string to comma-separated RGB values
 */
function hexToRgb($hex) {
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

/**
 * Append cache-busting timestamp based on filemtime if the local file exists.
 */
function cache_bust($url) {
    if (empty($url)) {
        return '';
    }
    $clean_url = parse_url($url, PHP_URL_PATH);
    $local_path = BASE_PATH . $clean_url;
    if (file_exists($local_path)) {
        $mtime = filemtime($local_path);
        return $url . '?v=' . $mtime;
    }
    return $url;
}

/**
 * Automatically regenerates sitemap.xml and robots.txt based on current settings and database records.
 * Strips accidental duplicate protocol prefixes or trailing slashes, automatically handles http/https protocols.
 */
function generate_seo_assets() {
    $settings = get_settings();
    $domain = !empty($settings['production_domain']) ? trim($settings['production_domain']) : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    // Automatically detect or default to HTTPS if not localhost
    $protocol = 'https://';
    if ($domain === 'localhost' || str_starts_with($domain, '127.0.0.1') || preg_match('/^localhost:\d+$/', $domain)) {
        $protocol = 'http://';
    }
    
    // Remove any accidental protocol prefixes (http:// or https://) or trailing slashes in settings
    $domain = preg_replace('/^https?:\/\//i', '', $domain);
    $domain = rtrim($domain, '/');
    $base_url = $protocol . $domain;
    
    try {
        // 1. Generate robots.txt
        $robots_content = "User-agent: *\n";
        $robots_content .= "Allow: /\n";
        $robots_content .= "Sitemap: " . $base_url . "/sitemap.xml\n";
        file_put_contents(BASE_PATH . '/robots.txt', $robots_content);
        
        // 2. Generate sitemap.xml
        $sitemap_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap_content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Add Homepage
        $sitemap_content .= "  <url>\n";
        $sitemap_content .= "    <loc>" . $base_url . "/</loc>\n";
        $sitemap_content .= "    <changefreq>daily</changefreq>\n";
        $sitemap_content .= "    <priority>1.0</priority>\n";
        $sitemap_content .= "  </url>\n";
        
        // Add Mangas & Chapters
        $mangas = db_fetch_all("SELECT id, slug, updated_at FROM mangas");
        foreach ($mangas as $m) {
            $m_date = !empty($m['updated_at']) ? date('c', strtotime($m['updated_at'])) : date('c');
            $sitemap_content .= "  <url>\n";
            $sitemap_content .= "    <loc>" . $base_url . "/manga/" . htmlspecialchars($m['slug']) . "</loc>\n";
            $sitemap_content .= "    <lastmod>" . $m_date . "</lastmod>\n";
            $sitemap_content .= "    <changefreq>weekly</changefreq>\n";
            $sitemap_content .= "    <priority>0.8</priority>\n";
            $sitemap_content .= "  </url>\n";
            
            // Add Chapters of this manga
            $chaps = db_fetch_all("SELECT number, created_at FROM chapters WHERE manga_id = ?", [$m['id']]);
            foreach ($chaps as $c) {
                $c_date = !empty($c['created_at']) ? date('c', strtotime($c['created_at'])) : date('c');
                $sitemap_content .= "  <url>\n";
                $sitemap_content .= "    <loc>" . $base_url . "/manga/" . htmlspecialchars($m['slug']) . "/chapter/" . $c['number'] . "</loc>\n";
                $sitemap_content .= "    <lastmod>" . $c_date . "</lastmod>\n";
                $sitemap_content .= "    <changefreq>monthly</changefreq>\n";
                $sitemap_content .= "    <priority>0.6</priority>\n";
                $sitemap_content .= "  </url>\n";
            }
        }
        
        // Add Custom Pages
        $pages = db_fetch_all("SELECT slug, updated_at FROM custom_pages WHERE is_published = 1");
        foreach ($pages as $p) {
            $p_date = !empty($p['updated_at']) ? date('c', strtotime($p['updated_at'])) : date('c');
            $sitemap_content .= "  <url>\n";
            $sitemap_content .= "    <loc>" . $base_url . "/" . htmlspecialchars($p['slug']) . "</loc>\n";
            $sitemap_content .= "    <lastmod>" . $p_date . "</lastmod>\n";
            $sitemap_content .= "    <changefreq>weekly</changefreq>\n";
            $sitemap_content .= "    <priority>0.7</priority>\n";
            $sitemap_content .= "  </url>\n";
        }
        
        // Add Blog Index & Posts
        $sitemap_content .= "  <url>\n";
        $sitemap_content .= "    <loc>" . $base_url . "/blog</loc>\n";
        $sitemap_content .= "    <changefreq>daily</changefreq>\n";
        $sitemap_content .= "    <priority>0.7</priority>\n";
        $sitemap_content .= "  </url>\n";
        
        $posts = db_fetch_all("SELECT slug, updated_at FROM blog_posts WHERE is_published = 1");
        foreach ($posts as $po) {
            $po_date = !empty($po['updated_at']) ? date('c', strtotime($po['updated_at'])) : date('c');
            $sitemap_content .= "  <url>\n";
            $sitemap_content .= "    <loc>" . $base_url . "/blog/" . htmlspecialchars($po['slug']) . "</loc>\n";
            $sitemap_content .= "    <lastmod>" . $po_date . "</lastmod>\n";
            $sitemap_content .= "    <changefreq>weekly</changefreq>\n";
            $sitemap_content .= "    <priority>0.6</priority>\n";
            $sitemap_content .= "  </url>\n";
        }
        
        $sitemap_content .= '</urlset>';
        file_put_contents(BASE_PATH . '/sitemap.xml', $sitemap_content);
        return true;
    } catch (Exception $e) {
        \MangaNexus\Logging\Logger::error("Auto SEO asset generation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up dynamic AI-generated SEO schemas by injecting correct hosts and logo paths recursively.
 */
function fix_schema_placeholders($schema, $base_url, $logo_url, $manga_url) {
    if (is_array($schema)) {
        foreach ($schema as $key => $value) {
            $schema[$key] = fix_schema_placeholders($value, $base_url, $logo_url, $manga_url);
        }
        return $schema;
    }
    if (is_string($schema)) {
        // If it's a logo placeholder
        if (preg_match('/logo\.(png|jpg|jpeg|webp)/i', $schema) || str_contains($schema, '/logo.') || str_contains($schema, 'logo-placeholder')) {
            return $logo_url;
        }
        
        // If it starts with a web protocol, replace domain placeholders (e.g. example.com, etc.)
        if (preg_match('/^https?:\/\/[^\/]+/i', $schema, $matches)) {
            $matched_origin = $matches[0];
            // If the URL is just root
            if (trim($schema, '/') === trim($matched_origin, '/')) {
                return $base_url . '/';
            }
            if (str_contains($schema, '/manga/')) {
                $path = substr($schema, strpos($schema, '/manga/'));
                return $base_url . $path;
            }
            if (str_contains($schema, '/uploads/')) {
                $path = substr($schema, strpos($schema, '/uploads/'));
                return $base_url . $path;
            }
            return str_replace($matched_origin, $base_url, $schema);
        }
        
        // If it's a relative logo URL
        if (str_starts_with($schema, '/logo.') || str_starts_with($schema, 'logo.')) {
            return $logo_url;
        }
    }
    return $schema;
}

/**
 * Global website domain name migrator.
 * Updates the database tables, site settings, scans and replaces occurrences across all text project files,
 * and regenerates sitemap and robots.txt.
 */
function migrate_project_domain($old_domain, $new_domain) {
    global $pdo;
    
    // Normalize domains
    $old_domain = trim(preg_replace('/^https?:\/\//i', '', $old_domain), '/ ');
    $new_domain = trim(preg_replace('/^https?:\/\//i', '', $new_domain), '/ ');
    
    if (empty($old_domain) || empty($new_domain)) {
        return ['success' => false, 'message' => 'Both old and new domains must be non-empty.'];
    }
    if ($old_domain === $new_domain) {
        return ['success' => false, 'message' => 'Old and new domains are identical. No changes needed.'];
    }
    
    $db_changes = 0;
    $files_updated = 0;
    $errors = [];
    
    // 1. Database Schema migrations
    try {
        $pdo->beginTransaction();
        
        // Get all database tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if ($table === 'migrations') continue;
            
            // Get columns information
            $cols = $pdo->query("PRAGMA table_info([$table])")->fetchAll();
            foreach ($cols as $col) {
                $col_name = $col['name'];
                $type = strtolower($col['type']);
                
                // Skip columns with explicit integer, boolean, or float representations to avoid errors
                if (str_contains($type, 'int') || str_contains($type, 'float') || str_contains($type, 'bool') || str_contains($type, 'numeric')) {
                    continue;
                }
                
                // Replace matching domain occurrences inside rows
                $stmt = $pdo->prepare("UPDATE [$table] SET [$col_name] = REPLACE([$col_name], ?, ?) WHERE [$col_name] LIKE ?");
                $stmt->execute([$old_domain, $new_domain, "%{$old_domain}%"]);
                $db_changes += $stmt->rowCount();
            }
        }
        
        // Explicitly set production_domain in site_settings
        $stmt_settings = $pdo->prepare("UPDATE site_settings SET production_domain = ? WHERE id = 'global'");
        $stmt_settings->execute([$new_domain]);
        
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        \MangaNexus\Logging\Logger::error("Database domain migration failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database update failed: ' . $e->getMessage()];
    }
    
    // 2. Scan and replace project text files
    try {
        $dir_iterator = new RecursiveDirectoryIterator(BASE_PATH, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
        
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile()) {
                $filepath = $fileinfo->getRealPath();
                
                // Exclude system logs, vendor dependencies, and cover/page uploads
                if (str_contains($filepath, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) ||
                    str_contains($filepath, DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                
                // Skip sitemap.xml and robots.txt themselves as we regenerate them
                $filename = $fileinfo->getFilename();
                if ($filename === 'sitemap.xml' || $filename === 'robots.txt') {
                    continue;
                }
                
                $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
                if (in_array($ext, ['php', 'css', 'js', 'json', 'htaccess', 'txt', 'xml', 'md', 'html'])) {
                    try {
                        $content = file_get_contents($filepath);
                        if (str_contains($content, $old_domain)) {
                            $new_content = str_replace($old_domain, $new_domain, $content);
                            file_put_contents($filepath, $new_content);
                            $files_updated++;
                        }
                    } catch (Exception $file_ex) {
                        $errors[] = "File: " . $filename . " (" . $file_ex->getMessage() . ")";
                    }
                }
            }
        }
    } catch (Exception $e) {
        \MangaNexus\Logging\Logger::error("File scanning during domain migration failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'File scanning failed: ' . $e->getMessage()];
    }
    
    // 3. Regenerate SEO assets sitemap.xml and robots.txt
    try {
        generate_seo_assets();
    } catch (Exception $seo_ex) {
        $errors[] = "Asset Regeneration: " . $seo_ex->getMessage();
    }
    
    \MangaNexus\Logging\Logger::info("Domain migration completed: '$old_domain' -> '$new_domain'. DB rows: $db_changes. Code files: $files_updated.");
    
    $msg = "🎉 Domain migration successfully completed from <strong>" . htmlspecialchars($old_domain) . "</strong> to <strong>" . htmlspecialchars($new_domain) . "</strong>! " .
           "Updated <strong>{$db_changes}</strong> database fields and <strong>{$files_updated}</strong> source code files.";
    
    if (!empty($errors)) {
        $msg .= " (Note: " . count($errors) . " minor non-critical issues occurred during updates: " . implode(', ', $errors) . ")";
    }
    
    return ['success' => true, 'message' => $msg];
}

/**
 * Technical GEO SEO Optimizer: Outputs dynamic alternate language link tags based on geo_config JSON values.
 */
function show_geo_hreflang_tags() {
    $settings = get_settings();
    $geo_config = json_decode($settings['geo_config'] ?? '[]', true);
    if (empty($geo_config) || !is_array($geo_config)) {
        return '';
    }
    
    $domain = !empty($settings['production_domain']) ? trim($settings['production_domain']) : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $protocol = 'https://';
    if ($domain === 'localhost' || str_starts_with($domain, '127.0.0.1') || preg_match('/^localhost:\d+$/', $domain)) {
        $protocol = 'http://';
    }
    $domain = preg_replace('/^https?:\/\//i', '', $domain);
    $domain = rtrim($domain, '/');
    $base_url = $protocol . $domain;
    
    $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $current_path = '/' . ltrim($current_path, '/');
    
    $tags = "\n  <!-- GEO Alternate Localization Hreflangs -->\n";
    foreach ($geo_config as $key => $value) {
        if (is_array($value)) {
            // Nested array structure: [{"rel":"alternate","hreflang":"en","href":"..."}]
            $hreflang = $value['hreflang'] ?? '';
            $href = $value['href'] ?? '';
            if (!empty($hreflang) && !empty($href)) {
                $tags .= "  <link rel=\"alternate\" hreflang=\"" . htmlspecialchars($hreflang) . "\" href=\"" . htmlspecialchars($href) . "\" />\n";
            }
        } else {
            // Simple key-value structure: {"en":"domain.com"}
            $lang = htmlspecialchars(trim((string)$key));
            if (!is_scalar($value)) {
                continue;
            }
            $lang_domain = trim(preg_replace('/^https?:\/\//i', '', (string)$value), '/');
            if (empty($lang_domain)) continue;
            
            $alt_url = $protocol . $lang_domain . $current_path;
            $tags .= "  <link rel=\"alternate\" hreflang=\"{$lang}\" href=\"" . htmlspecialchars($alt_url) . "\" />\n";
        }
    }
    return $tags;
}

/**
 * Technical SEO Social Optimizer: Generates standardized Open Graph and Twitter Card tags.
 */
function show_social_seo_tags($title = '', $description = '', $image = '', $type = 'website') {
    $settings = get_settings();
    $domain = !empty($settings['production_domain']) ? trim($settings['production_domain']) : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $protocol = 'https://';
    if ($domain === 'localhost' || str_starts_with($domain, '127.0.0.1') || preg_match('/^localhost:\d+$/', $domain)) {
        $protocol = 'http://';
    }
    $domain = preg_replace('/^https?:\/\//i', '', $domain);
    $domain = rtrim($domain, '/');
    $base_url = $protocol . $domain;
    
    $site_title = $settings['site_title'] ?? 'MangaNexus';
    $title = !empty($title) ? $title : $site_title;
    $description = !empty($description) ? $description : ($settings['site_description'] ?? '');
    
    if (empty($image)) {
        $image = !empty($settings['site_logo']) ? $base_url . '/' . ltrim($settings['site_logo'], '/') : $base_url . '/images/logo.png';
    } else if (!str_starts_with($image, 'http')) {
        $image = $base_url . '/' . ltrim($image, '/');
    }
    
    $current_url = $base_url . ($_SERVER['REQUEST_URI'] ?? '/');
    
    $tags = "\n  <!-- Open Graph / Facebook / WhatsApp -->\n";
    $tags .= "  <meta property=\"og:type\" content=\"" . htmlspecialchars($type) . "\" />\n";
    $tags .= "  <meta property=\"og:title\" content=\"" . htmlspecialchars($title) . "\" />\n";
    $tags .= "  <meta property=\"og:description\" content=\"" . htmlspecialchars($description) . "\" />\n";
    $tags .= "  <meta property=\"og:url\" content=\"" . htmlspecialchars($current_url) . "\" />\n";
    $tags .= "  <meta property=\"og:site_name\" content=\"" . htmlspecialchars($site_title) . "\" />\n";
    $tags .= "  <meta property=\"og:image\" content=\"" . htmlspecialchars($image) . "\" />\n";
    $tags .= "  <meta property=\"og:image:width\" content=\"1200\" />\n";
    $tags .= "  <meta property=\"og:image:height\" content=\"630\" />\n";
    
    $tags .= "\n  <!-- Twitter Cards -->\n";
    $tags .= "  <meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
    $tags .= "  <meta name=\"twitter:title\" content=\"" . htmlspecialchars($title) . "\" />\n";
    $tags .= "  <meta name=\"twitter:description\" content=\"" . htmlspecialchars($description) . "\" />\n";
    $tags .= "  <meta name=\"twitter:image\" content=\"" . htmlspecialchars($image) . "\" />\n";
    
    return $tags;
}
