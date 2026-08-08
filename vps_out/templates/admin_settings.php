<?php
/**
 * admin_settings.php — Global Site Settings & Assets Generator (PHP Version)
 */

$error = '';
$success = '';

$socials = json_decode($settings['social_links'] ?? '{}', true) ?: [];

// AJAX endpoints for translation studio
if (isset($_GET['action'])) {
    $ajax_action = $_GET['action'];
    if ($ajax_action === 'get_translatable_items') {
        header('Content-Type: application/json');
        
        $mangas = db_fetch_all("SELECT id, title FROM mangas ORDER BY title ASC");
        $blogs = db_fetch_all("SELECT id, title FROM blog_posts ORDER BY title ASC");
        
        echo json_encode([
            'success' => true,
            'mangas' => $mangas,
            'blogs' => $blogs
        ]);
        exit;
    }
    
    if ($ajax_action === 'translate_item_ajax') {
        header('Content-Type: application/json');
        
        $type = $_POST['type'] ?? '';
        $item_id = $_POST['id'] ?? '';
        $target_lang = trim($_POST['lang'] ?? '');
        
        if (empty($type) || empty($item_id) || empty($target_lang)) {
            echo json_encode(['success' => false, 'error' => 'Missing type, id, or target language.']);
            exit;
        }
        
        $lang_names = [
            'ar' => 'Arabic',
            'fr' => 'French',
            'de' => 'German',
            'es' => 'Spanish',
            'en' => 'English',
            'it' => 'Italian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'tr' => 'Turkish',
            'id' => 'Indonesian',
            'vi' => 'Vietnamese'
        ];
        $target_lang_name = $lang_names[$target_lang] ?? $target_lang;
        
        $settings = get_settings();
        
        if ($type === 'manga') {
            $manga = db_fetch("SELECT * FROM mangas WHERE id = ?", [$item_id]);
            if (!$manga) {
                echo json_encode(['success' => false, 'error' => 'Manga not found.']);
                exit;
            }
            
            $prompt = "Translate the following manga details to the target language: '{$target_lang_name}'.
Keep the manga title '{$manga['title']}' EXACTLY in its original language, do not translate or alter it.
Translate:
1. Description: " . $manga['description'] . "
2. Blog Content (HTML formatted content): " . $manga['blog_content'] . "
3. Meta Title: " . $manga['meta_title'] . "
4. Meta Description: " . $manga['meta_description'] . "
5. Meta Keywords: " . $manga['meta_keywords'] . "

Output must be strictly valid JSON matching this schema:
{
  \"description\": \"...\",
  \"blog_content\": \"<p>HTML formatted blog content</p>\",
  \"meta_title\": \"...\",
  \"meta_description\": \"...\",
  \"meta_keywords\": \"...\"
}
Do not use markdown code blocks around the JSON output, just pure JSON.";
            
            $response = dispatch_ai_prompt($prompt, $settings);
            if (is_array($response) && isset($response['error'])) {
                echo json_encode(['success' => false, 'error' => $response['error'], 'details' => $response['details'] ?? '']);
                exit;
            }
            
            $json_out = json_decode($response, true);
            if (!$json_out) {
                echo json_encode(['success' => false, 'error' => 'Failed to parse JSON response from AI.', 'raw' => $response]);
                exit;
            }
            
            db_query(
                "UPDATE mangas SET 
                    description = ?, 
                    blog_content = ?, 
                    meta_title = ?, 
                    meta_description = ?, 
                    meta_keywords = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?",
                [
                    $json_out['description'] ?? $manga['description'],
                    $json_out['blog_content'] ?? $manga['blog_content'],
                    $json_out['meta_title'] ?? $manga['meta_title'],
                    $json_out['meta_description'] ?? $manga['meta_description'],
                    $json_out['meta_keywords'] ?? $manga['meta_keywords'],
                    $item_id
                ]
            );
            
            echo json_encode(['success' => true]);
            exit;
            
        } elseif ($type === 'blog') {
            $post = db_fetch("SELECT * FROM blog_posts WHERE id = ?", [$item_id]);
            if (!$post) {
                echo json_encode(['success' => false, 'error' => 'Blog post not found.']);
                exit;
            }
            
            $prompt = "Translate the following blog post details to the target language: '{$target_lang_name}'.
Translate the title, excerpt, and content (HTML) to '{$target_lang_name}'. Keep any specific manga names or titles in their original language.
Translate:
1. Title: " . $post['title'] . "
2. Excerpt: " . $post['excerpt'] . "
3. Content (HTML formatted content): " . $post['content'] . "

Output must be strictly valid JSON matching this schema:
{
  \"title\": \"...\",
  \"excerpt\": \"...\",
  \"content\": \"<p>HTML formatted blog content</p>\"
}
Do not use markdown code blocks around the JSON output, just pure JSON.";
            
            $response = dispatch_ai_prompt($prompt, $settings);
            if (is_array($response) && isset($response['error'])) {
                echo json_encode(['success' => false, 'error' => $response['error'], 'details' => $response['details'] ?? '']);
                exit;
            }
            
            $json_out = json_decode($response, true);
            if (!$json_out) {
                echo json_encode(['success' => false, 'error' => 'Failed to parse JSON response from AI.', 'raw' => $response]);
                exit;
            }
            
            $new_title = $json_out['title'] ?? $post['title'];
            $new_slug = sanitize_slug($new_title);
            
            $dup = db_fetch("SELECT id FROM blog_posts WHERE slug = ? AND id != ?", [$new_slug, $item_id]);
            if ($dup) {
                $new_slug .= '-' . rand(100, 999);
            }
            
            db_query(
                "UPDATE blog_posts SET 
                    title = ?, 
                    slug = ?, 
                    excerpt = ?, 
                    content = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?",
                [
                    $new_title,
                    $new_slug,
                    $json_out['excerpt'] ?? $post['excerpt'],
                    $json_out['content'] ?? $post['content'],
                    $item_id
                ]
            );
            
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid type specified.']);
            exit;
        }
    }
}

// Handle assets generation action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_assets') {
    try {
        generate_seo_assets();
        $success = 'Robots.txt and Sitemap.xml regenerated successfully in root directory.';
    } catch (Exception $e) {
        $error = 'Failed to generate assets: ' . $e->getMessage();
    }
}

// Handle global domain migration action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate_domain') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!\MangaNexus\Security\Csrf::validate($csrfToken)) {
        $error = 'Error: Invalid CSRF Token.';
    } else {
        $new_domain = trim($_POST['new_domain']);
        $old_domain = $settings['production_domain'];
        
        $res = migrate_project_domain($old_domain, $new_domain);
        if ($res['success']) {
            $success = $res['message'];
            $settings = get_settings();
            $admin_slug = $settings['admin_slug'];
        } else {
            $error = $res['message'];
        }
    }
}

// Handle Form Submission for Settings Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $site_title = trim($_POST['site_title']);
    $site_description = trim($_POST['site_description']);
    $website_mode = $_POST['website_mode'];
    $primary_manga_id = trim($_POST['primary_manga_id']) ?: null;
    $admin_username = trim($_POST['admin_username']);
    $admin_password_input = trim($_POST['admin_password']);
    $admin_slug_new = sanitize_slug(trim($_POST['admin_slug']));
    $production_domain = trim($_POST['production_domain']);
    $current_theme = $_POST['current_theme'];
    $homepage_blog_articles = $_POST['homepage_blog_articles'];
    $homepage_schema = $_POST['homepage_schema'];
    $homepage_categories = trim($_POST['homepage_categories']) ?: '[]';
    $geo_config = trim($_POST['geo_config']) ?: '[]';
    $google_analytics_id = trim($_POST['google_analytics_id']);
    $gemini_api_key = trim($_POST['gemini_api_key']);
    $openai_api_key = trim($_POST['openai_api_key']);
    $openai_model = trim($_POST['openai_model'] ?? 'gpt-4o-mini');
    $ollama_api_url = trim($_POST['ollama_api_url'] ?? 'http://localhost:11434');
    $ollama_model = trim($_POST['ollama_model'] ?? 'llama3');
    $preferred_ai_provider = trim($_POST['preferred_ai_provider'] ?? 'gemini');
    $blog_ai_prompt = trim($_POST['blog_ai_prompt'] ?? '');
    
    $custom_hero_title = trim($_POST['custom_hero_title'] ?? '');
    $custom_hero_desc = trim($_POST['custom_hero_desc'] ?? '');
    $custom_hero_link = trim($_POST['custom_hero_link'] ?? '');
    $custom_hero_btn_text = trim($_POST['custom_hero_btn_text'] ?? '');
    $custom_hero_image = trim($_POST['custom_hero_image_url'] ?? '');

    $social_links_arr = [
        'facebook' => trim($_POST['social_facebook'] ?? ''),
        'twitter' => trim($_POST['social_twitter'] ?? ''),
        'linkedin' => trim($_POST['social_linkedin'] ?? ''),
        'tumblr' => trim($_POST['social_tumblr'] ?? ''),
        'pinterest' => trim($_POST['social_pinterest'] ?? ''),
        'youtube' => trim($_POST['social_youtube'] ?? ''),
        'discord' => trim($_POST['social_discord'] ?? '')
    ];
    $social_links = json_encode($social_links_arr);

    if (empty($site_title) || empty($admin_username) || empty($admin_slug_new)) {
        $error = 'Site Title, Admin Username, and Slug are required.';
    } else {
        // Hashing password if set, else keeping existing
        if (empty($admin_password_input)) {
            $admin_password = $settings['admin_password'];
        } else {
            if (strlen($admin_password_input) < 8) {
                $error = 'Admin password must be at least 8 characters long.';
            } elseif (!preg_match('/[A-Z]/', $admin_password_input) || !preg_match('/[a-z]/', $admin_password_input) || !preg_match('/[0-9]/', $admin_password_input) || !preg_match('/[^a-zA-Z0-9]/', $admin_password_input)) {
                $error = 'Admin password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
            } else {
                $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                $admin_password = password_hash($admin_password_input, $algo);
            }
        }

        // File Uploads: Logo & Favicon
        $site_logo = $settings['site_logo'];
        $site_favicon = $settings['site_favicon'];
        $allowed_img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'ico'];

        if (empty($error) && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($logo_ext, $allowed_img_exts)) {
                $tmp_logo = $_FILES['logo']['tmp_name'];
                $logo_name = 'logo_' . time() . '.' . $logo_ext;
                if (move_uploaded_file($tmp_logo, UPLOAD_DIR . '/' . $logo_name)) {
                    $site_logo = '/uploads/' . $logo_name;
                }
            } else {
                $error = 'Logo file type is not allowed. Only JPG, PNG, GIF, WebP, AVIF, and ICO are permitted.';
            }
        }

        if (empty($error) && isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $favicon_ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
            if (in_array($favicon_ext, $allowed_img_exts)) {
                $tmp_favicon = $_FILES['favicon']['tmp_name'];
                $favicon_name = 'favicon_' . time() . '.' . $favicon_ext;
                if (move_uploaded_file($tmp_favicon, UPLOAD_DIR . '/' . $favicon_name)) {
                    $site_favicon = '/uploads/' . $favicon_name;
                }
            } else {
                $error = 'Favicon file type is not allowed. Only JPG, PNG, GIF, WebP, AVIF, and ICO are permitted.';
            }
        }

        // Custom Hero Image File Upload
        if (empty($error) && isset($_FILES['custom_hero_image_file']) && $_FILES['custom_hero_image_file']['error'] === UPLOAD_ERR_OK) {
            $hero_ext = strtolower(pathinfo($_FILES['custom_hero_image_file']['name'], PATHINFO_EXTENSION));
            if (in_array($hero_ext, $allowed_img_exts)) {
                $tmp_hero = $_FILES['custom_hero_image_file']['tmp_name'];
                $hero_name = 'hero_custom_' . time() . '.' . $hero_ext;
                if (move_uploaded_file($tmp_hero, UPLOAD_DIR . '/' . $hero_name)) {
                    $custom_hero_image = '/uploads/' . $hero_name;
                }
            } else {
                $error = 'Custom hero background file type is not allowed. Only JPG, PNG, GIF, WebP, AVIF, and ICO are permitted.';
            }
        } else {
            if (empty($custom_hero_image)) {
                $custom_hero_image = $settings['custom_hero_image'] ?? '';
            }
        }

        if (empty($error)) {
            try {
                db_query(
                    "UPDATE site_settings SET 
                        site_title = ?, 
                        site_description = ?, 
                        website_mode = ?, 
                        primary_manga_id = ?, 
                        admin_username = ?, 
                        admin_password = ?, 
                        admin_slug = ?, 
                        production_domain = ?, 
                        current_theme = ?, 
                        homepage_blog_articles = ?, 
                        homepage_schema = ?, 
                        homepage_categories = ?, 
                        geo_config = ?, 
                        site_logo = ?, 
                        site_favicon = ?, 
                        google_analytics_id = ?,
                        gemini_api_key = ?,
                        openai_api_key = ?,
                        openai_model = ?,
                        ollama_api_url = ?,
                        ollama_model = ?,
                        preferred_ai_provider = ?,
                        social_links = ?,
                        blog_ai_prompt = ?,
                        custom_hero_title = ?,
                        custom_hero_desc = ?,
                        custom_hero_image = ?,
                        custom_hero_link = ?,
                        custom_hero_btn_text = ?,
                        updated_at = CURRENT_TIMESTAMP 
                     WHERE id = 'global'",
                    [
                        $site_title, $site_description, $website_mode, $primary_manga_id, 
                        $admin_username, $admin_password, $admin_slug_new, $production_domain, 
                        $current_theme, $homepage_blog_articles, $homepage_schema, 
                        $homepage_categories, $geo_config, $site_logo, $site_favicon,
                        $google_analytics_id, $gemini_api_key, 
                        $openai_api_key, $openai_model, $ollama_api_url, $ollama_model, $preferred_ai_provider,
                        $social_links, $blog_ai_prompt,
                        $custom_hero_title, $custom_hero_desc, $custom_hero_image, $custom_hero_link, $custom_hero_btn_text
                    ]
                );
                $success = 'Site settings updated successfully.';
                $socials = $social_links_arr; // update local socials representation
                \MangaNexus\Logging\Logger::info("Site settings updated successfully by admin.");
                
                // Reload configuration values in session/views
                $settings = get_settings();
                $admin_slug = $settings['admin_slug'];
                
                // Automatically regenerate sitemap & robots.txt with new production domain
                try {
                    generate_seo_assets();
                } catch (Exception $seo_ex) {
                    \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets after setting save: " . $seo_ex->getMessage());
                }
                
                // If admin slug has changed, redirect to prevent 404
                if ($admin_slug_new !== $admin_slug) {
                    header("Location: /" . $admin_slug_new . "/settings");
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Failed to save settings: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all mangas for selections
$mangas = db_fetch_all("SELECT id, title FROM mangas ORDER BY title ASC");

// 10 Premium Themes array
$themes = [
    'midnight-dark' => 'Midnight Dark (Default Pro)',
    'madara' => 'Madara Red Style',
    'otaku-crimson' => 'Otaku Crimson (Gothic Noir)',
    'minimalist-scanlation' => 'Minimalist Scanlation (Academic Grid)',
    'manga-classic' => 'Manga Classic (Traditional Ink)',
    'cyberpunk-district' => 'Cyberpunk District',
    'shonen-punch' => 'Otaku Shōnen Punch',
    'amethyst-fantasy' => 'Amethyst Fantasy',
    'solarized-novel' => 'Solarized Nocturnal',
    'e-reader-mono' => 'E-Reader Mono',
    'deep-ocean' => 'Deep Ocean Abyssal',
    'light-scarlet' => 'Scarlet Red Light',
    'light-emerald' => 'Emerald Green Light',
    'light-amber' => 'Amber Gold Light',
    'light-sapphire' => 'Sapphire Blue Light',
    'light-orange' => 'Tokyo Orange Light',
    'light-teal' => 'Clinical Teal Light',
    'light-sakura' => 'Sakura Pink Light',
    'light-lime' => 'Volt Lime Light',
    'light-lavender' => 'Lavender Fields Light',
    'light-cyan' => 'Vivid Cyan Light'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Site Settings - MangaNexus</title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?> admin-layout">

  <!-- Sidebar -->
  <aside class="admin-sidebar">
    <div class="sidebar-brand">
      <div class="logo-box-mini">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <span>MangaNexus Panel</span>
    </div>
    <nav class="sidebar-nav">
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Dashboard
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        Manga CRUD
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/ads" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 18H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h9M12 6l7-4v20l-7-4M19 12h3M19 8h2M19 16h2"/></svg>
        Ads Manager
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/settings" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Site Settings
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/visitors" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Registered Visitors
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/single-manga" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20M4 19.5V2.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5z"/></svg>
        Micro-Niche Studio
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/theme" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="19" cy="13" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="10" cy="19.5" r="2.5"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke-opacity=".3"/></svg>
        Theme Studio
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/pages" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Pages Manager
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/blog" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M16 8h2"/><path d="M16 12h2"/><path d="M16 16h2"/><path d="M6 8h6v8H6z"/></svg>
        Blog Posts
      </a>
      <a href="/" target="_blank" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
        View Website
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/logout" class="nav-item logout-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="admin-main">
    <header class="admin-topbar">
      <h2>Global Site Settings</h2>
      <div class="user-badge">
        <span>Logged in as: <strong>admin</strong></span>
      </div>
    </header>

    <div class="admin-content-box">
      <!-- Alerts -->
      <?php if (!empty($error)): ?>
        <div class="error-banner"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="success-banner"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <!-- ── SECTION: SEO ASSETS GENERATOR ── -->
      <div class="crud-card" style="margin-bottom: 2rem;">
        <div class="card-header">
          <h3>SEO Crawling Assets Generator</h3>
        </div>
        <p class="settings-desc">Build optimized index directories robots.txt and sitemap.xml to allow Google, Bing, and other search engines to crawls all your published cases and chapters.</p>
        <form action="" method="POST">
          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
          <input type="hidden" name="action" value="generate_assets">
          <button type="submit" class="btn btn-primary">Generate Website Assets</button>
        </form>
      </div>

      <!-- ── GLOBAL CONFIGURATION FORM ── -->
      <div class="crud-card">
        <div class="card-header">
          <h3>Global Portal Settings</h3>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="crud-form">
          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
          <input type="hidden" name="action" value="save_settings">

          <div class="form-grid">
            <div class="form-group col-6">
              <label for="site_title" class="form-label">Site Title *</label>
              <input type="text" name="site_title" id="site_title" class="form-input" value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
            </div>

            <div class="form-group col-6">
              <label for="production_domain" class="form-label">Production Domain * (no protocol)</label>
              <input type="text" name="production_domain" id="production_domain" class="form-input" value="<?php echo htmlspecialchars($settings['production_domain']); ?>" placeholder="e.g. mangadandadan.online" required>
            </div>

            <div class="form-group col-12">
              <label for="site_description" class="form-label">Global Site Description</label>
              <input type="text" name="site_description" id="site_description" class="form-input" value="<?php echo htmlspecialchars($settings['site_description']); ?>">
            </div>

            <!-- Website modes -->
            <div class="form-group col-6">
              <label for="website_mode" class="form-label">Website Operating Mode</label>
              <select name="website_mode" id="website_mode" class="form-select">
                <option value="general" <?php echo $settings['website_mode'] === 'general' ? 'selected' : ''; ?>>General Manga Portal (Directory list)</option>
                <option value="single" <?php echo $settings['website_mode'] === 'single' ? 'selected' : ''; ?>>Single-Series Micro-Niche (Landing homepage)</option>
              </select>
            </div>

            <div class="form-group col-6">
              <label for="primary_manga_id" class="form-label">Spotlight Manga Series (Hero Showcase / Single-Series Mode)</label>
              <select name="primary_manga_id" id="primary_manga_id" class="form-select">
                <option value="">-- Choose Series --</option>
                <?php foreach ($mangas as $m): ?>
                  <option value="<?php echo $m['id']; ?>" <?php echo $settings['primary_manga_id'] === $m['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($m['title']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Theme managed in Theme Studio page -->
            <input type="hidden" name="current_theme" value="<?php echo htmlspecialchars($settings['current_theme']); ?>">
            <div class="form-group col-4">
              <label for="logo" class="form-label">Site Logo (Custom branding upload)</label>
              <input type="file" name="logo" id="logo" class="form-input" accept="image/*">
            </div>

            <div class="form-group col-4">
              <label for="favicon" class="form-label">Site Favicon (Custom brand bookmark)</label>
              <input type="file" name="favicon" id="favicon" class="form-input" accept="image/*">
            </div>

            <!-- Custom Hero Showcase override -->
            <div class="form-group col-12" style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem; margin-top: 1rem;">
              <div class="sub-section-title" style="margin-bottom: 0.25rem;">Custom Homepage Hero Override (Spotlight Customization)</div>
              <span class="zinc-text" style="display: block; margin-bottom: 1rem; font-size: 0.725rem; color: var(--theme-text-muted);">Configure manual spotlight content. If "Custom Hero Title" is specified, it overrides the auto-selected manga details in the main homepage hero section.</span>
            </div>

            <div class="form-group col-6">
              <label for="custom_hero_title" class="form-label">Custom Hero Title (Leave blank to use selected manga)</label>
              <input type="text" name="custom_hero_title" id="custom_hero_title" class="form-input" value="<?php echo htmlspecialchars($settings['custom_hero_title'] ?? ''); ?>" placeholder="e.g. Solo Leveling Season 2">
            </div>

            <div class="form-group col-6">
              <label for="custom_hero_btn_text" class="form-label">Custom Hero Button Text</label>
              <input type="text" name="custom_hero_btn_text" id="custom_hero_btn_text" class="form-input" value="<?php echo htmlspecialchars($settings['custom_hero_btn_text'] ?? ''); ?>" placeholder="e.g. READ NOW">
            </div>

            <div class="form-group col-12">
              <label for="custom_hero_desc" class="form-label">Custom Hero Description / Synopsis</label>
              <textarea name="custom_hero_desc" id="custom_hero_desc" class="form-textarea" rows="3" placeholder="Enter custom spotlight overview or synopsis..."><?php echo htmlspecialchars($settings['custom_hero_desc'] ?? ''); ?></textarea>
            </div>

            <div class="form-group col-6">
              <label for="custom_hero_image_url" class="form-label">Custom Hero Image URL (Absolute path or external URL)</label>
              <input type="text" name="custom_hero_image_url" id="custom_hero_image_url" class="form-input" value="<?php echo htmlspecialchars($settings['custom_hero_image'] ?? ''); ?>" placeholder="e.g. /uploads/custom_hero.webp">
            </div>

            <div class="form-group col-6">
              <label for="custom_hero_image_file" class="form-label">Or Upload Custom Hero Background Cover</label>
              <input type="file" name="custom_hero_image_file" id="custom_hero_image_file" class="form-input" accept="image/*">
            </div>

            <div class="form-group col-12" style="margin-bottom: 1.5rem;">
              <label for="custom_hero_link" class="form-label">Custom Hero Link Target (Target landing page link)</label>
              <input type="text" name="custom_hero_link" id="custom_hero_link" class="form-input" value="<?php echo htmlspecialchars($settings['custom_hero_link'] ?? ''); ?>" placeholder="e.g. /manga/solo-leveling or https://...">
            </div>

            <!-- Credentials and Slugs -->
            <div class="form-group col-12">
              <div class="sub-section-title">Credentials & Access Protection</div>
            </div>

            <div class="form-group col-4">
              <label for="admin_username" class="form-label">Admin Username *</label>
              <input type="text" name="admin_username" id="admin_username" class="form-input" value="<?php echo htmlspecialchars($settings['admin_username']); ?>" required autocomplete="username">
            </div>

            <div class="form-group col-4">
              <label for="admin_password" class="form-label">Admin Password (leave blank to keep current)</label>
              <input type="password" name="admin_password" id="admin_password" class="form-input" placeholder="••••••••" autocomplete="new-password">
            </div>

            <div class="form-group col-4">
              <label for="admin_slug" class="form-label">Obfuscated Admin Path Slug *</label>
              <input type="text" name="admin_slug" id="admin_slug" class="form-input" value="<?php echo htmlspecialchars($settings['admin_slug']); ?>" required>
            </div>

            <!-- Layout configurations -->
            <div class="form-group col-12">
              <div class="sub-section-title">Shelves & Localizations (JSON Structures)</div>
            </div>

            <div class="form-group col-6">
              <label for="homepage_categories" class="form-label">Homepage Categories shelving (JSON format)</label>
              <textarea name="homepage_categories" id="homepage_categories" class="form-textarea code-area" rows="3"><?php echo htmlspecialchars($settings['homepage_categories'] ?? ''); ?></textarea>
            </div>

            <div class="form-group col-6">
              <label for="geo_config" class="form-label">GEO Lang Settings (JSON hreflang map)</label>
              <textarea name="geo_config" id="geo_config" class="form-textarea code-area" rows="3"><?php echo htmlspecialchars($settings['geo_config'] ?? ''); ?></textarea>
            </div>

            <!-- Content SEO -->
            <div class="form-group col-12">
              <div class="sub-section-title">Homepage SEO & JSON-LD schema blocks</div>
            </div>

            <div class="form-group col-12">
              <label for="homepage_blog_articles" class="form-label">Homepage SEO Blog Articles (HTML markup block editor)</label>
              <textarea name="homepage_blog_articles" id="homepage_blog_articles" class="form-textarea" rows="8"><?php echo htmlspecialchars($settings['homepage_blog_articles'] ?? ''); ?></textarea>
            </div>

            <div class="form-group col-12">
              <label for="homepage_schema" class="form-label">Homepage JSON-LD SEO Schema markup (Raw script body)</label>
              <textarea name="homepage_schema" id="homepage_schema" class="form-textarea code-area" rows="5"><?php echo htmlspecialchars($settings['homepage_schema'] ?? ''); ?></textarea>
            </div>

            <!-- Social Links settings -->
            <div class="form-group col-12">
              <div class="sub-section-title">Social Settings (Footer Links)</div>
            </div>

            <div class="form-group col-4">
              <label for="social_facebook" class="form-label">Facebook URL</label>
              <input type="url" name="social_facebook" id="social_facebook" class="form-input" value="<?php echo htmlspecialchars($socials['facebook'] ?? ''); ?>" placeholder="https://facebook.com/yourpage">
            </div>

            <div class="form-group col-4">
              <label for="social_twitter" class="form-label">Twitter / X URL</label>
              <input type="url" name="social_twitter" id="social_twitter" class="form-input" value="<?php echo htmlspecialchars($socials['twitter'] ?? ''); ?>" placeholder="https://twitter.com/yourprofile">
            </div>

            <div class="form-group col-4">
              <label for="social_linkedin" class="form-label">LinkedIn URL</label>
              <input type="url" name="social_linkedin" id="social_linkedin" class="form-input" value="<?php echo htmlspecialchars($socials['linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/yourprofile">
            </div>

            <div class="form-group col-3">
              <label for="social_tumblr" class="form-label">Tumblr URL</label>
              <input type="url" name="social_tumblr" id="social_tumblr" class="form-input" value="<?php echo htmlspecialchars($socials['tumblr'] ?? ''); ?>" placeholder="https://yourblog.tumblr.com">
            </div>

            <div class="form-group col-3">
              <label for="social_pinterest" class="form-label">Pinterest URL</label>
              <input type="url" name="social_pinterest" id="social_pinterest" class="form-input" value="<?php echo htmlspecialchars($socials['pinterest'] ?? ''); ?>" placeholder="https://pinterest.com/yourprofile">
            </div>

            <div class="form-group col-3">
              <label for="social_youtube" class="form-label">YouTube URL</label>
              <input type="url" name="social_youtube" id="social_youtube" class="form-input" value="<?php echo htmlspecialchars($socials['youtube'] ?? ''); ?>" placeholder="https://youtube.com/c/yourchannel">
            </div>

            <div class="form-group col-3">
              <label for="social_discord" class="form-label">Discord Invite URL</label>
              <input type="url" name="social_discord" id="social_discord" class="form-input" value="<?php echo htmlspecialchars($socials['discord'] ?? ''); ?>" placeholder="https://discord.gg/yourinvite">
            </div>

            <!-- Analytics tracking -->
            <div class="form-group col-12">
              <div class="sub-section-title">Analytics Tracking</div>
            </div>

            <div class="form-group col-12">
              <label for="google_analytics_id" class="form-label">Google Analytics Measurement ID (Google Tag)</label>
              <input type="text" name="google_analytics_id" id="google_analytics_id" class="form-input" value="<?php echo htmlspecialchars($settings['google_analytics_id'] ?? ''); ?>" placeholder="e.g., G-XXXXXXXXXX">
              <span class="zinc-text">Enter your Google Tag / GA4 measurement ID (e.g. <code>G-XXXXXXXXXX</code>) or paste your entire <code>&lt;script&gt;</code> tracking tag block directly. It will be loaded publicly in your page headers.</span>
            </div>

            <!-- AI Settings -->
            <div class="form-group col-12">
              <div class="sub-section-title">Generative AI Studio & Multi-API Integrations</div>
            </div>

            <div class="form-group col-4">
              <label for="preferred_ai_provider" class="form-label">Preferred AI Provider *</label>
              <select name="preferred_ai_provider" id="preferred_ai_provider" class="form-select">
                <option value="gemini" <?php echo ($settings['preferred_ai_provider'] ?? 'gemini') === 'gemini' ? 'selected' : ''; ?>>Google Gemini (Flash 2.5)</option>
                <option value="openai" <?php echo ($settings['preferred_ai_provider'] ?? 'gemini') === 'openai' ? 'selected' : ''; ?>>OpenAI ChatGPT</option>
                <option value="ollama" <?php echo ($settings['preferred_ai_provider'] ?? 'gemini') === 'ollama' ? 'selected' : ''; ?>>Ollama (Local/Self-hosted)</option>
              </select>
              <span class="zinc-text">Select which active AI provider to use for automated SEO metadata & blog generation.</span>
            </div>

            <div class="form-group col-8">
              <label for="gemini_api_key" class="form-label">Google Gemini API Key</label>
              <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-input" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AIzaSy...">
              <span class="zinc-text">Enter your Google Gemini API key to enable Gemini features.</span>
            </div>

            <div class="form-group col-6">
              <label for="openai_api_key" class="form-label">OpenAI API Key (ChatGPT)</label>
              <input type="password" name="openai_api_key" id="openai_api_key" class="form-input" value="<?php echo htmlspecialchars($settings['openai_api_key'] ?? ''); ?>" placeholder="sk-proj-...">
              <span class="zinc-text">Enter your OpenAI API key to enable ChatGPT SEO automation.</span>
            </div>

            <div class="form-group col-6">
              <label for="openai_model" class="form-label">OpenAI Model Choice</label>
              <select name="openai_model" id="openai_model" class="form-select">
                <option value="gpt-4o-mini" <?php echo ($settings['openai_model'] ?? 'gpt-4o-mini') === 'gpt-4o-mini' ? 'selected' : ''; ?>>gpt-4o-mini (Recommended for speed & low cost)</option>
                <option value="gpt-4o" <?php echo ($settings['openai_model'] ?? 'gpt-4o-mini') === 'gpt-4o' ? 'selected' : ''; ?>>gpt-4o (High quality)</option>
                <option value="o1-mini" <?php echo ($settings['openai_model'] ?? 'gpt-4o-mini') === 'o1-mini' ? 'selected' : ''; ?>>o1-mini (Reasoning model)</option>
              </select>
              <span class="zinc-text">Select which GPT model option to use for text generation.</span>
            </div>

            <div class="form-group col-6">
              <label for="ollama_api_url" class="form-label">Ollama API URL Endpoint</label>
              <input type="text" name="ollama_api_url" id="ollama_api_url" class="form-input" value="<?php echo htmlspecialchars($settings['ollama_api_url'] ?? 'http://localhost:11434'); ?>" placeholder="e.g. http://localhost:11434">
              <span class="zinc-text">Local/self-hosted or cloud-hosted Ollama server endpoint.</span>
            </div>

            <div class="form-group col-6">
              <label for="ollama_model" class="form-label">Ollama Model Choice</label>
              <input type="text" name="ollama_model" id="ollama_model" class="form-input" value="<?php echo htmlspecialchars($settings['ollama_model'] ?? 'llama3'); ?>" placeholder="e.g. llama3, mistral, gemma">
              <span class="zinc-text">Enter the model name currently downloaded in your local Ollama server (e.g. <code>llama3</code>, <code>mistral</code>).</span>
            </div>

            <!-- Custom AI Blog Generator Prompt Box -->
            <div class="form-group col-12" style="margin-top: 1rem;">
              <label for="blog_ai_prompt" class="form-label">Custom AI Blog Generator Prompt</label>
              <textarea name="blog_ai_prompt" id="blog_ai_prompt" class="form-textarea code-area" rows="6" placeholder="Generate a comprehensive 2000-3500-word blog post for a manga/manhwa/scanlation blog named '{$title}'..."><?php echo htmlspecialchars($settings['blog_ai_prompt'] ?? ''); ?></textarea>
              <span class="zinc-text">Enter the customized system prompt for AI blog post generation. Use <code>{$title}</code> to dynamically substitute the article title during generation. Keep in mind that the AI output MUST strictly match a valid JSON format with keys: <code>title</code>, <code>slug</code>, <code>excerpt</code>, and <code>content</code> (containing HTML markup).</span>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Save Portal Settings</button>
        </form>
      </div>

      <!-- ── SECTION: DOMAIN MIGRATION STUDIO ── -->
      <div class="crud-card" style="margin-top: 2rem;">
        <div class="card-header">
          <h3>Domain Migration Studio (Advanced)</h3>
        </div>
        <p class="settings-desc">Migrate your entire MangaNexus project under a new domain name instantly. This action updates the database, re-writes the <code>production_domain</code> config, updates absolute URLs in sitemaps & robots.txt, and scans/replaces occurrences in all code files.</p>
        
        <form method="POST" action="" class="migration-form" style="max-width: 600px;">
          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
          <input type="hidden" name="action" value="migrate_domain">
          
          <div class="form-group" style="margin-bottom: 1.25rem;">
            <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: var(--theme-text-muted);">Current Configured Domain:</label>
            <div style="font-family: 'JetBrains Mono', monospace; background: rgba(0,0,0,0.35); padding: 0.9rem 1.1rem; border-radius: 0.85rem; border: 1px solid rgba(255,255,255,0.06); font-size: 0.875rem; color: #a1a1aa; box-shadow: inset 0 2px 6px rgba(0,0,0,0.2);">
              <?php echo htmlspecialchars($settings['production_domain']); ?>
            </div>
          </div>
          
          <div class="form-group" style="margin-bottom: 1.5rem;">
            <label for="new_domain_settings" class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: var(--theme-text-muted);">New Domain Name *</label>
            <input type="text" name="new_domain" id="new_domain_settings" class="form-input" placeholder="e.g. newdomain.com" required style="width: 100%; padding: 0.9rem 1.1rem; border-radius: 0.85rem; font-size: 0.875rem;">
            <span class="zinc-text" style="font-size: 0.72rem; color: #94a3b8; opacity: 0.8; display: block; margin-top: 0.4rem; line-height: 1.5;">
              Do not include <code>http://</code> or <code>https://</code> or trailing slashes. E.g., enter <code>newwebsite.com</code>.
            </span>
          </div>
          
          <button type="submit" class="btn btn-primary" onclick="return confirm('⚠️ WARNING: This will replace the old domain name across all database tables, columns, and source code files recursively. Make sure you have a backup of the database and files before continuing! Proceed?');" style="padding: 0.95rem 2rem; border-radius: 1rem; font-size: 0.8125rem; font-weight: 900;">
            🚀 Migrate to New Domain
          </button>
        </form>
      </div>

      <!-- ── SECTION: AI TRANSLATION STUDIO ── -->
      <div class="crud-card" style="margin-top: 2rem;">
        <div class="card-header">
          <h3>Generative AI Translation Studio</h3>
        </div>
        <p class="settings-desc">Bulk translate all Manga descriptions, HTML blog posts, and SEO metadata into your target language instantly using the active preferred AI engine. Original Manga/Manhwa names are preserved to protect your brand identity across directories.</p>
        
        <div style="max-width: 600px;">
          <div class="form-group" style="margin-bottom: 1.5rem;">
            <label for="target_language_select" class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: var(--theme-text-muted);">Select Target Translation Language</label>
            <select id="target_language_select" class="form-select" style="width: 100%; padding: 0.9rem 1.1rem; border-radius: 0.85rem; font-size: 0.875rem;">
              <option value="ar">Arabic (العربية)</option>
              <option value="fr">French (Français)</option>
              <option value="de">German (Deutsch)</option>
              <option value="es">Spanish (Español)</option>
              <option value="en">English</option>
              <option value="it">Italian (Italiano)</option>
              <option value="pt">Portuguese (Português)</option>
              <option value="ru">Russian (Русский)</option>
              <option value="zh">Chinese (中文)</option>
              <option value="tr">Turkish (Türkçe)</option>
              <option value="ja">Japanese (日本語)</option>
              <option value="ko">Korean (한국어)</option>
              <option value="id">Indonesian (Bahasa Indonesia)</option>
              <option value="vi">Vietnamese (Tiếng Việt)</option>
            </select>
          </div>
          
          <button type="button" id="start-translation-btn" class="btn btn-primary" onclick="launchTranslationStudio()" style="padding: 0.95rem 2rem; border-radius: 1rem; font-size: 0.8125rem; font-weight: 900; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
            ✨ Launch Auto-Translation Sequence
          </button>
          
          <!-- PROGRESS BOARD -->
          <div id="translation-progress-board" style="display: none; margin-top: 1.5rem; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.08); padding: 1.5rem; border-radius: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem;">
              <span id="progress-status" style="font-size: 0.85rem; font-weight: 700; color: #60a5fa;">Initializing...</span>
              <span id="progress-percentage" style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; font-weight: bold; color: #a1a1aa;">0%</span>
            </div>
            
            <!-- Progress Bar -->
            <div style="width: 100%; height: 8px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; overflow: hidden; margin-bottom: 1rem; border: 1px solid rgba(255, 255, 255, 0.02);">
              <div id="progress-bar-fill" style="width: 0%; height: 100%; background: linear-gradient(90deg, #3b82f6, #8b5cf6); transition: width 0.3s ease; border-radius: 10px;"></div>
            </div>
            
            <div style="display: flex; gap: 0.75rem; margin-bottom: 1.25rem;">
              <button type="button" id="pause-translation-btn" class="btn btn-secondary" onclick="toggleTranslationPause()" style="padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.7rem; font-weight: bold;">Pause</button>
              <button type="button" id="cancel-translation-btn" class="btn btn-danger" onclick="stopTranslationStudio()" style="padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.7rem; font-weight: bold; background: #ef4444; border: none; color: white;">Stop Sequence</button>
            </div>
            
            <!-- Live translation console logs -->
            <div id="translation-console" style="max-height: 120px; overflow-y: auto; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.04); border-radius: 0.5rem; padding: 0.75rem; font-family: 'JetBrains Mono', monospace; font-size: 0.68rem; color: #cbd5e1; line-height: 1.5;">
              <div style="color: #64748b;">&gt; Ready to receive tasks...</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
    let translationQueue = [];
    let currentTranslationIndex = 0;
    let isTranslationPaused = false;
    let isTranslationStopped = false;
    
    async function launchTranslationStudio() {
      const startBtn = document.getElementById('start-translation-btn');
      const langSelect = document.getElementById('target_language_select');
      const progressBoard = document.getElementById('translation-progress-board');
      const statusText = document.getElementById('progress-status');
      const percentText = document.getElementById('progress-percentage');
      const barFill = document.getElementById('progress-bar-fill');
      const consoleLog = document.getElementById('translation-console');
      
      if (!confirm('⚠️ Are you sure you want to translate your entire database content? This will send each manga and blog row sequentially to your configured AI, which could take a while and incur API costs.')) {
        return;
      }
      
      startBtn.disabled = true;
      startBtn.style.opacity = '0.5';
      progressBoard.style.display = 'block';
      consoleLog.innerHTML = '<div style="color: #60a5fa;">&gt; Connecting to Database & retrieving items...</div>';
      
      isTranslationPaused = false;
      isTranslationStopped = false;
      document.getElementById('pause-translation-btn').innerText = 'Pause';
      
      try {
        const res = await fetch('?action=get_translatable_items');
        const data = await res.json();
        
        if (!data.success) {
          throw new Error(data.error || 'Failed to fetch items');
        }
        
        translationQueue = [];
        (data.mangas || []).forEach(item => {
          translationQueue.push({ type: 'manga', id: item.id, title: item.title });
        });
        (data.blogs || []).forEach(item => {
          translationQueue.push({ type: 'blog', id: item.id, title: item.title });
        });
        
        currentTranslationIndex = 0;
        
        if (translationQueue.length === 0) {
          consoleLog.innerHTML += '<div style="color: #ef4444;">&gt; Error: No mangas or blog posts found to translate.</div>';
          statusText.innerText = 'Completed (0 items)';
          startBtn.disabled = false;
          startBtn.style.opacity = '1';
          return;
        }
        
        consoleLog.innerHTML += `<div style="color: #10b981;">&gt; Found ${translationQueue.length} translatable items in the database. Starting...</div>`;
        runTranslationLoop(langSelect.value);
        
      } catch (err) {
        consoleLog.innerHTML += `<div style="color: #ef4444;">&gt; Error: ${err.message}</div>`;
        statusText.innerText = 'Initialization Failed';
        startBtn.disabled = false;
        startBtn.style.opacity = '1';
      }
    }
    
    async function runTranslationLoop(lang) {
      const statusText = document.getElementById('progress-status');
      const percentText = document.getElementById('progress-percentage');
      const barFill = document.getElementById('progress-bar-fill');
      const consoleLog = document.getElementById('translation-console');
      const startBtn = document.getElementById('start-translation-btn');
      
      while (currentTranslationIndex < translationQueue.length) {
        if (isTranslationStopped) {
          consoleLog.innerHTML += '<div style="color: #ef4444;">&gt; Translation loop stopped by administrator.</div>';
          statusText.innerText = 'Sequence Stopped';
          startBtn.disabled = false;
          startBtn.style.opacity = '1';
          return;
        }
        
        if (isTranslationPaused) {
          statusText.innerText = 'Paused';
          consoleLog.innerHTML += '<div style="color: #eab308;">&gt; Sequence paused. Click resume to continue.</div>';
          return;
        }
        
        const item = translationQueue[currentTranslationIndex];
        statusText.innerText = `Translating ${item.type === 'manga' ? 'Manga' : 'Blog'} (${currentTranslationIndex + 1}/${translationQueue.length})`;
        
        const logDiv = document.createElement('div');
        logDiv.style.marginBottom = '0.3rem';
        logDiv.innerText = `&gt; [${item.type.toUpperCase()}] Sending "${item.title}" to AI Studio...`;
        consoleLog.appendChild(logDiv);
        consoleLog.scrollTop = consoleLog.scrollHeight;
        
        // Call translate API
        const formData = new FormData();
        formData.append('type', item.type);
        formData.append('id', item.id);
        formData.append('lang', lang);
        
        try {
          const response = await fetch('?action=translate_item_ajax', {
            method: 'POST',
            body: formData
          });
          const result = await response.json();
          
          if (result.success) {
            logDiv.innerHTML = `<span style="color: #10b981;">✓ [${item.type.toUpperCase()}] "${item.title}" translated successfully.</span>`;
          } else {
            logDiv.innerHTML = `<span style="color: #ef4444;">✗ [${item.type.toUpperCase()}] "${item.title}" failed: ${result.error || 'Unknown error'}</span>`;
            if (result.details) {
              consoleLog.innerHTML += `<div style="color: #ef4444; padding-left: 1rem; font-size: 0.6rem;">Details: ${JSON.stringify(result.details)}</div>`;
            }
          }
        } catch (err) {
          logDiv.innerHTML = `<span style="color: #ef4444;">✗ [${item.type.toUpperCase()}] "${item.title}" request error: ${err.message}</span>`;
        }
        
        currentTranslationIndex++;
        
        const progress = Math.round((currentTranslationIndex / translationQueue.length) * 100);
        percentText.innerText = `${progress}%`;
        barFill.style.width = `${progress}%`;
        consoleLog.scrollTop = consoleLog.scrollHeight;
      }
      
      consoleLog.innerHTML += '<div style="color: #10b981; font-weight: bold; margin-top: 0.5rem;">&gt; SUCCESS: Entire database translation sequence completed successfully!</div>';
      statusText.innerText = 'Completed Successfully!';
      startBtn.disabled = false;
      startBtn.style.opacity = '1';
    }
    
    function toggleTranslationPause() {
      const pauseBtn = document.getElementById('pause-translation-btn');
      const langSelect = document.getElementById('target_language_select');
      
      if (isTranslationPaused) {
        isTranslationPaused = false;
        pauseBtn.innerText = 'Pause';
        document.getElementById('progress-status').innerText = 'Resuming...';
        runTranslationLoop(langSelect.value);
      } else {
        isTranslationPaused = true;
        pauseBtn.innerText = 'Resume';
      }
    }
    
    function stopTranslationStudio() {
      if (confirm('Are you sure you want to stop the translation sequence? Unfinished items will not be translated.')) {
        isTranslationStopped = true;
      }
    }
    </script>

    <!-- Non-Removable Developer Footer -->
    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

</body>
</html>

<!-- CSS settings specifics -->
<style>
.settings-desc {
  font-size: 0.75rem;
  color: var(--theme-text-muted);
  line-height: 1.5;
  margin-bottom: 1.5rem;
}
</style>
