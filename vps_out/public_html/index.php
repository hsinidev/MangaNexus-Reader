<?php
/**
 * index.php — Front Controller and Router for MangaNexus PHP
 */

// ── PHP-level gzip compression (fallback if mod_deflate not enabled on VPS) ──
if (!headers_sent() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', 'On');
    ini_set('zlib.output_compression_level', '6');
}

require_once __DIR__ . '/../config.php';

// ── Secure HTTP Response Headers (Attacker & Malware Defenses) ──
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}

try {
    // Normalize route
    $route = isset($_GET['_route']) ? trim($_GET['_route'], '/') : '';

    // If rewriting is not active, look at PATH_INFO or REQUEST_URI
    if (empty($route) && isset($_SERVER['PATH_INFO'])) {
        $route = trim($_SERVER['PATH_INFO'], '/');
    }

    // Dynamic multi-site tenant lookup handler based on HTTP_HOST
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $domain = trim(preg_replace('/^https?:\/\//i', '', $host), '/ ');
    $settings = db_fetch("SELECT * FROM site_settings WHERE production_domain = ?", [$domain]);
    if (!$settings) {
        $settings = get_settings();
    }
    $admin_slug = !empty($settings['admin_slug']) ? $settings['admin_slug'] : 'admin191103400';

    // Global variables for layouts
    $theme = !empty($settings['current_theme']) ? $settings['current_theme'] : 'midnight-dark';
    $site_title = !empty($settings['site_title']) ? $settings['site_title'] : 'MangaNexus';
    $site_description = !empty($settings['site_description']) ? $settings['site_description'] : 'Read the latest manga online.';

    // ── Routing Logic ────────────────────────────────────────────────────────────

    // 1. Check if it is an Admin route
    $route_parts = explode('/', $route);
    $is_admin_route = ($route_parts[0] === $admin_slug);

    // Validate CSRF token for admin POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin_route) {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!\MangaNexus\Security\Csrf::validate($csrfToken)) {
            \MangaNexus\Logging\Logger::warning("Blocked unauthorized request (CSRF check failed).");
            http_response_code(403);
            die("Error: Invalid CSRF Token.");
        }
    }

    if ($is_admin_route) {
        // Admin Session Protection — username + password only
        $logged_in = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;

        // Handle logout action
        if (isset($route_parts[1]) && $route_parts[1] === 'logout') {
            session_destroy();
            header("Location: /" . $admin_slug);
            exit;
        }

        // Redirect to login if not authenticated
        if (!$logged_in) {
            require_once BASE_PATH . '/templates/admin_login.php';
            exit;
        }

        // Routing inside admin panel
        $sub_route = isset($route_parts[1]) ? $route_parts[1] : '';

        switch ($sub_route) {
            case '':
            case 'dashboard':
                require_once BASE_PATH . '/templates/admin_dashboard.php';
                break;
                
            case 'manga':
                require_once BASE_PATH . '/templates/admin_manga.php';
                break;
                
            case 'chapters':
                $manga_id = isset($route_parts[2]) ? $route_parts[2] : '';
                require_once BASE_PATH . '/templates/admin_chapters.php';
                break;
                
            case 'settings':
                require_once BASE_PATH . '/templates/admin_settings.php';
                break;
                
            case 'theme':
                require_once BASE_PATH . '/templates/admin_theme.php';
                break;
                
            case 'ads':
                require_once BASE_PATH . '/templates/admin_ads.php';
                break;
                
            case 'single-manga':
                require_once BASE_PATH . '/templates/admin_single_manga.php';
                break;
                
            case 'visitors':
                require_once BASE_PATH . '/templates/admin_visitors.php';
                break;

            case 'pages':
                require_once BASE_PATH . '/templates/admin_pages.php';
                break;

            case 'blog':
                require_once BASE_PATH . '/templates/admin_blog.php';
                break;
                
            default:
                http_response_code(404);
                die("Admin Page Not Found.");
        }
        exit;
    }

    // 2. Public Frontend Routes
    if ($route === 'login') {
        $GLOBALS['page_type'] = 'login';
        require_once BASE_PATH . '/templates/login.php';
        exit;
    }

    if ($route === 'signup') {
        $GLOBALS['page_type'] = 'signup';
        require_once BASE_PATH . '/templates/signup.php';
        exit;
    }

    if ($route === 'logout') {
        \MangaNexus\Security\Auth::logoutVisitor();
        header("Location: /");
        exit;
    }

    if ($route === 'cookie-policy') {
        $GLOBALS['page_type'] = 'cookie-policy';
        require_once BASE_PATH . '/templates/cookie_policy.php';
        exit;
    }

    if ($route === 'blog') {
        $GLOBALS['page_type'] = 'blog';
        require_once BASE_PATH . '/templates/blog.php';
        exit;
    }

    if (preg_match('/^blog\/([a-zA-Z0-9_-]+)$/', $route, $matches)) {
        $blog_slug = $matches[1];
        $GLOBALS['page_type'] = 'blog_post';
        require_once BASE_PATH . '/templates/blog_post.php';
        exit;
    }

    if ($route === '') {
        // Homepage
        $GLOBALS['page_type'] = 'home';
        require_once BASE_PATH . '/templates/home.php';
        exit;
    }

    // Route: external-media/[page_id]
    if (preg_match('/^external-media\/([a-zA-Z0-9_-]+)$/', $route, $matches)) {
        $page_id = $matches[1];
        $page = db_fetch("SELECT external_path FROM pages WHERE id = ?", [$page_id]);
        if ($page && !empty($page['external_path'])) {
            $real_path = $page['external_path'];
            if (file_exists($real_path)) {
                $ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
                $mime_types = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    'bmp' => 'image/bmp'
                ];
                $mime = $mime_types[$ext] ?? 'image/jpeg';
                header("Content-Type: $mime");
                header("Content-Length: " . filesize($real_path));
                header("Cache-Control: public, max-age=2592000"); // Cache for 30 days
                readfile($real_path);
                exit;
            }
        }
        http_response_code(404);
        die("File Not Found.");
    }

    // Route: manga/[slug]
    if (preg_match('/^manga\/([a-zA-Z0-9_-]+)$/', $route, $matches)) {
        $manga_slug = $matches[1];
        
        // Redirect to home if in single series mode and this is the primary manga
        if (!empty($settings['website_mode']) && $settings['website_mode'] === 'single') {
            $primary_manga = null;
            if (!empty($settings['primary_manga_id'])) {
                $primary_manga = db_fetch("SELECT slug FROM mangas WHERE id = ?", [$settings['primary_manga_id']]);
            }
            if (!$primary_manga) {
                $primary_manga = db_fetch("SELECT slug FROM mangas ORDER BY sort_order ASC, created_at DESC LIMIT 1");
            }
            
            if ($primary_manga && $primary_manga['slug'] === $manga_slug) {
                header("Location: /");
                exit;
            }
        }
        
        $GLOBALS['page_type'] = 'manga';
        require_once BASE_PATH . '/templates/manga.php';
        exit;
    }

    // Route: manga/[slug]/chapter/[number]
    if (preg_match('/^manga\/([a-zA-Z0-9_-]+)\/chapter\/([0-9.]+)$/', $route, $matches)) {
        $manga_slug = $matches[1];
        $chapter_number = floatval($matches[2]);
        $GLOBALS['page_type'] = 'reader';
        require_once BASE_PATH . '/templates/reader.php';
        exit;
    }

    // Route: dynamic custom page matching (e.g. privacy-policy, contact, etc.)
    if (preg_match('/^([a-zA-Z0-9_-]+)$/', $route, $matches)) {
        $page_slug = $matches[1];
        $custom_page = db_fetch("SELECT * FROM custom_pages WHERE slug = ? AND is_published = 1", [$page_slug]);
        if ($custom_page) {
            $GLOBALS['page_type'] = 'custom_page';
            require_once BASE_PATH . '/templates/custom_page.php';
            exit;
        }
    }

    // 404 Fallback
    http_response_code(404);

} catch (\Throwable $e) {
    \MangaNexus\Logging\Logger::error("Routing exception: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    http_response_code(500);
    $is_local = (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost'));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>500 Internal Server Error</title>
        <style>
            body { background: #09090b; color: #fafafa; font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .box { max-width: 600px; padding: 2rem; border: 1px solid #27272a; border-radius: 1rem; background: #121214; }
            h1 { color: #f87171; margin-top: 0; }
            pre { background: #18181b; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; color: #a1a1aa; font-family: monospace; font-size: 0.85rem; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>500 Internal Server Error</h1>
            <p>An unexpected error occurred. Please contact the administrator.</p>
            <?php if ($is_local): ?>
                <pre><?php echo htmlspecialchars((string)$e); ?></pre>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - <?php echo htmlspecialchars($site_title); ?></title>
    <link rel="stylesheet" href="/theme.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #09090b;
            color: #fafafa;
        }
        .container {
            text-align: center;
            max-width: 400px;
            padding: 2rem;
            border: 1px solid #27272a;
            border-radius: 1rem;
            background-color: #121214;
        }
        h1 {
            font-size: 3rem;
            margin: 0 0 1rem 0;
            background: linear-gradient(to right, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            color: #a1a1aa;
            margin-bottom: 2rem;
        }
        a {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(to right, #8b5cf6, #06b6d4);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">
    <div class="container">
        <h1>404</h1>
        <p>The page you are looking for does not exist or has been moved.</p>
        <a href="/">Back to Library</a>
    </div>
</body>
</html>
<?php
exit;
?>
