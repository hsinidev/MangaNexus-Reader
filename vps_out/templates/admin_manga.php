<?php
/**
 * admin_manga.php — Manga CRUD Panel (PHP Version)
 */

$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

$error = '';
$success = '';

// Handle actions
if ($action === 'gemini_generate') {
    header('Content-Type: application/json');
    $title = trim($_POST['title'] ?? '');
    
    $settings = get_settings();
    if (empty($title)) {
        echo json_encode(['error' => 'Manga title is required.']);
        exit;
    }

    $prompt = "Generate full SEO data and a comprehensive 2000-3500-word blog post for a manga named '{$title}'. Output must be strictly valid JSON matching this schema:
{
  \"title\": \"...\",
  \"slug\": \"...\",
  \"author\": \"...\",
  \"status\": \"ongoing\",
  \"description\": \"...\",
  \"blog_content\": \"<p>HTML formatted blog content</p>\",
  \"meta_title\": \"...\",
  \"geo_targeting\": \"en-US\",
  \"meta_description\": \"...\",
  \"meta_keywords\": \"...\",
  \"meta_tags\": \"<meta ...>\",
  \"seo_schema\": { \"@context\": \"...\" }
}
Do not use markdown code blocks around the JSON output, just pure JSON.";

    $response = dispatch_ai_prompt($prompt, $settings);
    if (is_array($response) && isset($response['error'])) {
        echo json_encode(['error' => $response['error'], 'details' => $response['details'] ?? '']);
        exit;
    }

    $json_out = json_decode($response, true);
    if (!$json_out) {
        echo json_encode(['error' => 'Failed to parse JSON from AI response', 'raw' => $response]);
        exit;
    }
    
    // Dynamically calculate website properties for absolute URL replacements
    $domain = !empty($settings['production_domain']) ? trim($settings['production_domain']) : ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $protocol = 'https://';
    if ($domain === 'localhost' || str_starts_with($domain, '127.0.0.1') || preg_match('/^localhost:\d+$/', $domain)) {
        $protocol = 'http://';
    }
    $domain = preg_replace('/^https?:\/\//i', '', $domain);
    $domain = rtrim($domain, '/');
    $base_url = $protocol . $domain;
    
    $logo_url = !empty($settings['site_logo']) ? $base_url . '/' . ltrim($settings['site_logo'], '/') : $base_url . '/images/logo.png';
    $manga_slug = $json_out['slug'] ?? sanitize_slug($title);
    $manga_url = $base_url . '/manga/' . $manga_slug;

    if (isset($json_out['seo_schema'])) {
        $schema_data = $json_out['seo_schema'];
        if (is_string($schema_data)) {
            $schema_data = json_decode($schema_data, true) ?: $schema_data;
        }
        if (is_array($schema_data)) {
            $schema_data = fix_schema_placeholders($schema_data, $base_url, $logo_url, $manga_url);
            $json_out['seo_schema'] = json_encode($schema_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            // String fallback
            $schema_data = str_replace(
                ['example.com', 'http://example.com', 'https://example.com'], 
                [$domain, $base_url, $base_url], 
                $schema_data
            );
            $json_out['seo_schema'] = $schema_data;
        }
    }
    echo json_encode(['success' => true, 'data' => $json_out]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && !empty($id)) {
    try {
        $manga = db_fetch("SELECT * FROM mangas WHERE id = ?", [$id]);
        if ($manga) {
            // Delete cover file
            if (!empty($manga['cover_url'])) {
                $cover_file = BASE_PATH . $manga['cover_url'];
                if (file_exists($cover_file)) {
                    unlink($cover_file);
                }
            }
            
            // Delete page folders
            $manga_pages_dir = PAGES_DIR . '/' . $manga['slug'];
            if (is_dir($manga_pages_dir)) {
                // Recursive delete helper
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($manga_pages_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($manga_pages_dir);
            }

            // Delete from database
            db_query("DELETE FROM mangas WHERE id = ?", [$id]);
            try {
                generate_seo_assets();
            } catch (Exception $seo_ex) {
                \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on manga delete: " . $seo_ex->getMessage());
            }
            header("Location: /" . $admin_slug . "/manga");
            exit;
        }
    } catch (PDOException $e) {
        $error = 'Database delete failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reorder' && !empty($id)) {
    $direction = $_GET['direction'] ?? '';
    if ($direction === 'up' || $direction === 'down') {
        try {
            $pdo = \MangaNexus\Database\Database::getConnection();
            $all_mangas = db_fetch_all("SELECT id, sort_order, created_at FROM mangas ORDER BY sort_order ASC, created_at DESC");
            
            $target_index = -1;
            foreach ($all_mangas as $index => $m) {
                if ($m['id'] === $id) {
                    $target_index = $index;
                    break;
                }
            }
            
            if ($target_index !== -1) {
                $swap_index = -1;
                if ($direction === 'up' && $target_index > 0) {
                    $swap_index = $target_index - 1;
                } elseif ($direction === 'down' && $target_index < count($all_mangas) - 1) {
                    $swap_index = $target_index + 1;
                }
                
                if ($swap_index !== -1) {
                    $temp = $all_mangas[$target_index];
                    $all_mangas[$target_index] = $all_mangas[$swap_index];
                    $all_mangas[$swap_index] = $temp;
                    
                    $pdo->beginTransaction();
                    foreach ($all_mangas as $new_order => $m) {
                        db_query("UPDATE mangas SET sort_order = ? WHERE id = ?", [$new_order, $m['id']]);
                    }
                    $pdo->commit();
                    
                    try {
                        generate_seo_assets();
                    } catch (Exception $seo_ex) {
                        \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on manga reorder: " . $seo_ex->getMessage());
                    }
                }
            }
            header("Location: /" . $admin_slug . "/manga");
            exit;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to reorder: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reorder_all') {
    $ordered_ids = isset($_POST['ordered_ids']) ? explode(',', $_POST['ordered_ids']) : [];
    $ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $success_status = false;
    $err_msg = '';
    
    if (!empty($ordered_ids)) {
        try {
            $pdo = \MangaNexus\Database\Database::getConnection();
            $pdo->beginTransaction();
            foreach ($ordered_ids as $index => $manga_id) {
                $sort_order = $index + 1;
                db_query("UPDATE mangas SET sort_order = ? WHERE id = ?", [$sort_order, $manga_id]);
            }
            $pdo->commit();
            
            try {
                generate_seo_assets();
            } catch (Exception $seo_ex) {
                \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on bulk manga reorder: " . $seo_ex->getMessage());
            }
            $success_status = true;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err_msg = $e->getMessage();
        }
    }
    
    if ($ajax) {
        header('Content-Type: application/json');
        if ($success_status) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $err_msg ?: 'No IDs provided']);
        }
        exit;
    }
    
    if (!$success_status && !empty($err_msg)) {
        $error = 'Failed to save new sorting order: ' . $err_msg;
    } else {
        $success = 'Manga order saved successfully.';
    }
    header("Location: /" . $admin_slug . "/manga");
    exit;
}

// Handle Form Submission for Create or Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    if (empty($slug)) {
        $slug = sanitize_slug($title);
    } else {
        $slug = sanitize_slug($slug);
    }
    
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    $author = trim($_POST['author']) ?: 'Unknown';
    $blog_content = $_POST['blog_content'];
    $seo_schema = $_POST['seo_schema'];
    $meta_keywords = trim($_POST['meta_keywords']);
    $meta_tags = trim($_POST['meta_tags']);
    $meta_title = trim($_POST['meta_title']);
    $meta_description = trim($_POST['meta_description']);
    $geo_targeting = trim($_POST['geo_targeting']);

    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        // Validate slug uniqueness
        $dup = db_fetch("SELECT id FROM mangas WHERE slug = ? AND id != ?", [$slug, $id]);
        if ($dup) {
            $error = 'A series with this slug already exists.';
        } else {
            // Process Cover Image Upload
            $cover_url = isset($_POST['existing_cover']) ? $_POST['existing_cover'] : '';
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $allowed_img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'ico'];
                $cover_ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                if (in_array($cover_ext, $allowed_img_exts)) {
                    $tmp_name = $_FILES['cover']['tmp_name'];
                    
                    // Delete old cover if exists
                    if ($action === 'edit' && !empty($cover_url)) {
                        $old_file = BASE_PATH . $cover_url;
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }

                    $cover_name = $slug . '.webp';
                    $dest_file = COVERS_DIR . '/' . $cover_name;

                    if (optimize_image($tmp_name, $dest_file, 60, 350)) {
                        $cover_url = '/uploads/covers/' . $cover_name;
                    } else {
                        $error = 'Failed to process and optimize cover image.';
                    }
                } else {
                    $error = 'Cover image file type is not allowed. Only JPG, PNG, GIF, WebP, AVIF, and ICO are permitted.';
                }
            }

            if (empty($error)) {
                try {
                    if ($action === 'create') {
                        $new_id = uuid();
                        db_query(
                            "INSERT INTO mangas (id, title, slug, description, cover_url, status, blog_content, seo_schema, author, meta_keywords, meta_tags, meta_title, meta_description, geo_targeting, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                            [$new_id, $title, $slug, $description, $cover_url, $status, $blog_content, $seo_schema, $author, $meta_keywords, $meta_tags, $meta_title, $meta_description, $geo_targeting]
                        );
                        $success = 'Manga series created successfully.';
                        try {
                            generate_seo_assets();
                        } catch (Exception $seo_ex) {
                            \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on manga create: " . $seo_ex->getMessage());
                        }
                        header("Location: /" . $admin_slug . "/manga");
                        exit;
                    } else if ($action === 'edit') {
                        db_query(
                            "UPDATE mangas SET title = ?, slug = ?, description = ?, cover_url = ?, status = ?, blog_content = ?, seo_schema = ?, author = ?, meta_keywords = ?, meta_tags = ?, meta_title = ?, meta_description = ?, geo_targeting = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                            [$title, $slug, $description, $cover_url, $status, $blog_content, $seo_schema, $author, $meta_keywords, $meta_tags, $meta_title, $meta_description, $geo_targeting, $id]
                        );
                        $success = 'Manga series updated successfully.';
                        try {
                            generate_seo_assets();
                        } catch (Exception $seo_ex) {
                            \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on manga edit: " . $seo_ex->getMessage());
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database save failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch record for editing
$manga_data = null;
if ($action === 'edit' && !empty($id)) {
    $manga_data = db_fetch("SELECT * FROM mangas WHERE id = ?", [$id]);
}

// Fetch all mangas for list display
$mangas = db_fetch_all("SELECT * FROM mangas ORDER BY sort_order ASC, created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manga Management - MangaNexus</title>
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
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Dashboard
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        Manga CRUD
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/ads" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 18H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h9M12 6l7-4v20l-7-4M19 12h3M19 8h2M19 16h2"/></svg>
        Ads Manager
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/settings" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
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
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/pages" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Pages Manager
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/blog" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M16 8h2"/><path d="M16 12h2"/><path d="M16 16h2"/><path d="M6 8h6v8H6z"/></svg>
        Blog Posts
      </a>
      <a href="/" target="_blank" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
        View Website
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/logout" class="nav-item logout-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="admin-main">
    <header class="admin-topbar">
      <h2>Manga Management</h2>
      <div class="user-badge">
        <span>Logged in as: <strong>admin</strong></span>
      </div>
    </header>

    <div class="admin-content-box">
      <!-- Error and Success Banners -->
      <?php if (!empty($error)): ?>
        <div class="error-banner"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="success-banner"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($action === 'create' || $action === 'edit'): ?>
        <!-- ── CREATE OR EDIT FORM ── -->
        <div class="crud-card">
          <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <h3><?php echo $action === 'create' ? 'Create New Manga Series' : 'Edit Manga Series Settings'; ?></h3>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <label class="btn btn-primary btn-sm" style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; margin: 0; background: linear-gradient(to right, #0284c7, #0369a1); border: 1px solid rgba(255,255,255,0.05);">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>Import JSON</span>
                <input type="file" id="seo-json-file" accept=".json" style="display: none;" onchange="handleJSONImport(this)">
              </label>
              <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga" class="btn btn-secondary btn-sm">Cancel</a>
            </div>
          </div>

          <form action="" method="POST" enctype="multipart/form-data" class="crud-form">
            <?php echo \MangaNexus\Security\Csrf::getField(); ?>
            <input type="hidden" name="existing_cover" value="<?php echo htmlspecialchars($manga_data ? $manga_data['cover_url'] : ''); ?>">

            <div class="form-grid">
              <div class="form-group col-6">
                <label for="title" class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                  Manga Title *
                  <button type="button" class="btn btn-secondary btn-sm" onclick="generateGeminiSEO()" id="gemini-btn" style="padding: 0.2rem 0.5rem; font-size: 0.7rem; background: linear-gradient(to right, #8b5cf6, #6d28d9); border: none; color: white;">✨ AI Generate</button>
                </label>
                <input type="text" name="title" id="title" class="form-input" value="<?php echo htmlspecialchars($manga_data ? $manga_data['title'] : ''); ?>" required>
              </div>

              <div class="form-group col-6">
                <label for="slug" class="form-label">URL Slug (leave empty to auto-generate)</label>
                <input type="text" name="slug" id="slug" class="form-input" value="<?php echo htmlspecialchars($manga_data ? $manga_data['slug'] : ''); ?>" placeholder="e.g. one-piece">
              </div>

              <div class="form-group col-4">
                <label for="author" class="form-label">Author</label>
                <input type="text" name="author" id="author" class="form-input" value="<?php echo htmlspecialchars($manga_data ? $manga_data['author'] : 'Unknown'); ?>">
              </div>

              <div class="form-group col-4">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                  <option value="ongoing" <?php echo ($manga_data && $manga_data['status'] === 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                  <option value="completed" <?php echo ($manga_data && $manga_data['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                </select>
              </div>

              <div class="form-group col-4">
                <label for="cover" class="form-label">Cover Art Image (WebP auto-optimized)</label>
                <input type="file" name="cover" id="cover" class="form-input" accept="image/*">
              </div>

              <div class="form-group col-12">
                <label for="description" class="form-label">Synopsis / Description</label>
                <textarea name="description" id="description" class="form-textarea" rows="4"><?php echo htmlspecialchars($manga_data ? $manga_data['description'] : ''); ?></textarea>
              </div>

              <div class="form-group col-12">
                <label for="blog_content" class="form-label">Blog Post Content (HTML supported for SEO articles)</label>
                <textarea name="blog_content" id="blog_content" class="form-textarea" rows="8" placeholder="<p>Read manga online free...</p>"><?php echo htmlspecialchars($manga_data ? $manga_data['blog_content'] : ''); ?></textarea>
              </div>

              <!-- Meta and Schema -->
              <div class="form-group col-12">
                <div class="sub-section-title">SEO Configurations</div>
              </div>

              <div class="form-group col-6">
                <label for="meta_title" class="form-label">Meta Title Tag</label>
                <input type="text" name="meta_title" id="meta_title" class="form-input" value="<?php echo htmlspecialchars($manga_data ? $manga_data['meta_title'] : ''); ?>">
              </div>

              <div class="form-group col-6">
                <label for="geo_targeting" class="form-label">Target Locale / Geolocation Code</label>
                <input type="text" name="geo_targeting" id="geo_targeting" class="form-input" value="<?php echo htmlspecialchars($manga_data ? $manga_data['geo_targeting'] : 'en-US'); ?>">
              </div>

              <div class="form-group col-12">
                <label for="meta_description" class="form-label">Meta Description Tag</label>
                <input type="text" name="meta_description" id="meta_description" class="form-input" value="<?php echo htmlspecialchars($manga_data ? $manga_data['meta_description'] : ''); ?>">
              </div>

              <div class="form-group col-6">
                <label for="meta_keywords" class="form-label">Meta Keywords</label>
                <textarea name="meta_keywords" id="meta_keywords" class="form-textarea" rows="3"><?php echo htmlspecialchars($manga_data ? $manga_data['meta_keywords'] : ''); ?></textarea>
              </div>

              <div class="form-group col-6">
                <label for="meta_tags" class="form-label">Custom Meta Tags (Full HTML tags)</label>
                <textarea name="meta_tags" id="meta_tags" class="form-textarea" rows="3" placeholder="<meta name='robots' content='index, follow'>"><?php echo htmlspecialchars($manga_data ? $manga_data['meta_tags'] : ''); ?></textarea>
              </div>

              <div class="form-group col-12">
                <label for="seo_schema" class="form-label">JSON-LD Schema Markup (Raw script body)</label>
                <textarea name="seo_schema" id="seo_schema" class="form-textarea code-area" rows="5" placeholder='{ "@context": "https://schema.org", "@type": "Book" }'><?php echo htmlspecialchars($manga_data ? $manga_data['seo_schema'] : ''); ?></textarea>
              </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Manga Metadata</button>
          </form>
        </div>

      <?php else: ?>
        <!-- ── MANGA DIRECTORY INDEX LIST ── -->
        <div class="crud-card">
          <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <h3>Registered Manga Series</h3>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
              <div class="view-toggle-group" style="display: flex; background: rgba(0,0,0,0.2); border: 1px solid var(--theme-border); border-radius: 0.5rem; padding: 2px;">
                <button type="button" class="view-toggle-btn active" id="toggle-list-view" onclick="setViewMode('list')" style="background: none; border: none; padding: 0.4rem 0.8rem; font-size: 0.75rem; font-weight: 700; color: var(--theme-text-muted); cursor: pointer; border-radius: 0.35rem; display: flex; align-items: center; gap: 0.25rem; transition: all 0.2s;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                  List
                </button>
                <button type="button" class="view-toggle-btn" id="toggle-block-view" onclick="setViewMode('block')" style="background: none; border: none; padding: 0.4rem 0.8rem; font-size: 0.75rem; font-weight: 700; color: var(--theme-text-muted); cursor: pointer; border-radius: 0.35rem; display: flex; align-items: center; gap: 0.25rem; transition: all 0.2s;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                  Blocks
                </button>
              </div>
              <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=create" class="btn btn-primary btn-sm">Create New Manga</a>
            </div>
          </div>

          <?php if (empty($mangas)): ?>
            <div class="empty-state">
              <p>No manga records found. Create one above to begin.</p>
            </div>
          <?php else: ?>
            <div id="manga-list-container" class="view-mode-list">
              <!-- 1. Table/List View -->
              <div id="list-view-wrapper" class="table-container">
                <table class="manga-table">
                  <thead>
                    <tr>
                      <th>Cover</th>
                      <th>Title & Path</th>
                      <th style="text-align: center;">Sort</th>
                      <th>Status</th>
                      <th>Author</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    $total_mangas = count($mangas);
                    foreach ($mangas as $index => $m): 
                    ?>
                      <tr class="draggable-row" draggable="true" data-id="<?php echo htmlspecialchars($m['id']); ?>">
                        <td style="width:70px;">
                          <?php if (!empty($m['cover_url'])): ?>
                            <img src="<?php echo htmlspecialchars(cache_bust($m['cover_url'])); ?>" alt="" style="width:45px; height:60px; object-fit:cover; border-radius:0.35rem;">
                          <?php else: ?>
                            <div class="td-no-cover">NO ART</div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="manga-td-title"><?php echo htmlspecialchars($m['title']); ?></span>
                          <span class="manga-td-slug">/manga/<?php echo htmlspecialchars($m['slug']); ?></span>
                        </td>
                        <td style="text-align: center; width: 85px; vertical-align: middle;">
                          <div style="display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                            <span class="drag-handle" style="cursor: grab; font-size: 1.25rem; color: var(--theme-primary); padding: 0.15rem; user-select: none; transition: transform 0.2s; display: inline-block;" title="Drag to reorder" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">☰</span>
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 0.05rem; justify-content: center; line-height: 1;">
                              <?php if ($index > 0): ?>
                                <form action="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=reorder&id=<?php echo $m['id']; ?>&direction=up" method="POST" style="display: inline; line-height: 1;">
                                  <?php echo \MangaNexus\Security\Csrf::getField(); ?>
                                  <button type="submit" class="sort-arrow-btn" aria-label="Move Up" style="background: none; border: none; color: #10b981; cursor: pointer; font-size: 1.1rem; font-weight: bold; padding: 0; transition: transform 0.2s; line-height: 1;" onmouseover="this.style.transform='scale(1.3)'" onmouseout="this.style.transform='scale(1)'">▲</button>
                                </form>
                              <?php else: ?>
                                <span style="font-size: 1.1rem; opacity: 0; line-height: 1; pointer-events: none;">▲</span>
                              <?php endif; ?>
                              
                              <?php if ($index < $total_mangas - 1): ?>
                                <form action="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=reorder&id=<?php echo $m['id']; ?>&direction=down" method="POST" style="display: inline; line-height: 1;">
                                  <?php echo \MangaNexus\Security\Csrf::getField(); ?>
                                  <button type="submit" class="sort-arrow-btn" aria-label="Move Down" style="background: none; border: none; color: #10b981; cursor: pointer; font-size: 1.1rem; font-weight: bold; padding: 0; transition: transform 0.2s; line-height: 1;" onmouseover="this.style.transform='scale(1.3)'" onmouseout="this.style.transform='scale(1)'">▼</button>
                                </form>
                              <?php else: ?>
                                <span style="font-size: 1.1rem; opacity: 0; line-height: 1; pointer-events: none;">▼</span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </td>
                        <td>
                          <span class="badge badge-<?php echo htmlspecialchars($m['status']); ?>">
                            <?php echo htmlspecialchars($m['status']); ?>
                          </span>
                        </td>
                        <td>
                          <span style="font-size:0.8125rem; font-weight:600; color:var(--theme-text-muted);">
                            <?php echo htmlspecialchars($m['author']); ?>
                          </span>
                        </td>
                        <td class="manga-td-actions">
                          <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=edit&id=<?php echo $m['id']; ?>" class="action-btn edit-btn">Edit</a>
                          <a href="/<?php echo htmlspecialchars($admin_slug); ?>/chapters/<?php echo $m['id']; ?>" class="action-btn upload-btn">Chapters</a>
                          <form action="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=delete&id=<?php echo $m['id']; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this manga and all its chapters?');">
                            <?php echo \MangaNexus\Security\Csrf::getField(); ?>
                            <button type="submit" class="action-btn delete-btn" style="background:var(--theme-card); border:1px solid rgba(239, 68, 68, 0.2); cursor:pointer; font:inherit; color:#f87171;">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- 2. Block/Grid View -->
              <div id="block-view-wrapper" class="block-view-container" style="display: none;">
                <div class="manga-grid" id="manga-drag-grid">
                  <?php foreach ($mangas as $m): ?>
                    <div class="manga-block-card draggable-card" draggable="true" data-id="<?php echo htmlspecialchars($m['id']); ?>">
                      <div class="card-cover-container">
                        <?php if (!empty($m['cover_url'])): ?>
                          <img src="<?php echo htmlspecialchars(cache_bust($m['cover_url'])); ?>" alt="" class="card-cover-img">
                        <?php else: ?>
                          <div class="card-no-cover">NO ART</div>
                        <?php endif; ?>
                        
                        <!-- Drag Handle Overlay -->
                        <div class="card-drag-handle" title="Drag to reorder">
                          ☰
                        </div>
                        
                        <!-- Status Badge -->
                        <span class="card-badge badge-<?php echo htmlspecialchars($m['status']); ?>">
                          <?php echo htmlspecialchars($m['status']); ?>
                        </span>
                      </div>
                      
                      <div class="card-info">
                        <h4 class="card-title" title="<?php echo htmlspecialchars($m['title']); ?>"><?php echo htmlspecialchars($m['title']); ?></h4>
                        <span class="card-author"><?php echo htmlspecialchars($m['author']); ?></span>
                      </div>
                      
                      <div class="card-actions">
                        <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=edit&id=<?php echo $m['id']; ?>" class="action-btn edit-btn">Edit</a>
                        <a href="/<?php echo htmlspecialchars($admin_slug); ?>/chapters/<?php echo $m['id']; ?>" class="action-btn upload-btn">Chapters</a>
                        <form action="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=delete&id=<?php echo $m['id']; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this manga and all its chapters?');">
                          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
                          <button type="submit" class="action-btn delete-btn" style="background:var(--theme-card); border:1px solid rgba(239, 68, 68, 0.2); cursor:pointer; font:inherit; color:#f87171;">Delete</button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Hidden form for drag-and-drop ordering saving -->
      <form action="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=reorder_all" method="POST" id="reorder-all-form" style="display:none;">
        <?php echo \MangaNexus\Security\Csrf::getField(); ?>
        <input type="hidden" name="ordered_ids" id="ordered-ids-input" value="">
      </form>

    </div>

    <!-- Non-Removable Developer Footer -->
    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

  <script>
    function handleJSONImport(input) {
      if (!input.files || !input.files[0]) return;
      
      const file = input.files[0];
      const reader = new FileReader();
      
      reader.onload = function(e) {
        try {
          const data = JSON.parse(e.target.result);
          
          // Helper to populate fields if they exist in JSON
          const fields = [
            'title', 'slug', 'author', 'status', 'description', 
            'blog_content', 'meta_title', 'geo_targeting', 
            'meta_description', 'meta_keywords', 'meta_tags', 'seo_schema'
          ];
          
          let count = 0;
          fields.forEach(field => {
            if (data[field] !== undefined) {
              const el = document.getElementById(field);
              if (el) {
                el.value = data[field];
                count++;
              }
            }
          });
          
          alert(`🎉 SEO Metadata successfully loaded into ${count} form boxes! Please review the populated inputs and click "Save Manga Metadata" below to save.`);
        } catch (err) {
          alert('❌ Error: Invalid JSON file structure. Please make sure the JSON format is correct.');
          console.error(err);
        }
      };
      
      reader.readAsText(file);
    }

    async function generateGeminiSEO() {
      const title = document.getElementById('title').value;
      if (!title) {
        alert("Please enter a manga title first before generating AI metadata.");
        return;
      }
      
      const btn = document.getElementById('gemini-btn');
      const originalText = btn.innerHTML;
      btn.innerHTML = "✨ Generating...";
      btn.disabled = true;
      
      const formData = new FormData();
      formData.append('title', title);
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      if (csrfInput) {
          formData.append('csrf_token', csrfInput.value);
      }
      
      try {
        const res = await fetch('/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=gemini_generate', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        
        if (result.error) {
            alert("Error: " + result.error);
        } else if (result.success) {
            const data = result.data;
            const fields = [
                'title', 'slug', 'author', 'status', 'description', 
                'blog_content', 'meta_title', 'geo_targeting', 
                'meta_description', 'meta_keywords', 'meta_tags', 'seo_schema'
            ];
            
            let count = 0;
            fields.forEach(field => {
                if (data[field] !== undefined) {
                    const el = document.getElementById(field);
                    if (el) {
                        el.value = data[field];
                        count++;
                    }
                }
            });
            alert(`🎉 AI generation complete! Form populated successfully.`);
        }
      } catch(e) {
          alert("Request failed. " + e.message);
      }
      
      btn.innerHTML = originalText;
      btn.disabled = false;
    }

    // Drag and Drop Sorting for Table (List) and Grid (Blocks) Views
    document.addEventListener("DOMContentLoaded", function() {
      // Toggle view initialization
      const savedView = localStorage.getItem("manga_view_mode") || "list";
      setViewMode(savedView);
      
      initializeDragAndDrop();
    });

    function setViewMode(mode) {
      const container = document.getElementById("manga-list-container");
      const listWrapper = document.getElementById("list-view-wrapper");
      const blockWrapper = document.getElementById("block-view-wrapper");
      const listBtn = document.getElementById("toggle-list-view");
      const blockBtn = document.getElementById("toggle-block-view");
      
      if (!container) return;
      
      if (mode === "block") {
        container.className = "view-mode-block";
        if (listWrapper) listWrapper.style.display = "none";
        if (blockWrapper) blockWrapper.style.display = "block";
        if (listBtn) listBtn.classList.remove("active");
        if (blockBtn) blockBtn.classList.add("active");
        localStorage.setItem("manga_view_mode", "block");
      } else {
        container.className = "view-mode-list";
        if (listWrapper) listWrapper.style.display = "block";
        if (blockWrapper) blockWrapper.style.display = "none";
        if (listBtn) listBtn.classList.add("active");
        if (blockBtn) blockBtn.classList.remove("active");
        localStorage.setItem("manga_view_mode", "list");
      }
    }

    function initializeDragAndDrop() {
      // 1. Table Drag & Drop
      const tbody = document.querySelector(".manga-table tbody");
      if (tbody) {
        setupDragEvents(tbody, "tr.draggable-row");
      }
      
      // 2. Grid Drag & Drop
      const grid = document.getElementById("manga-drag-grid");
      if (grid) {
        setupDragEvents(grid, ".manga-block-card");
      }
    }

    function setupDragEvents(container, itemSelector) {
      let dragEl = null;
      
      container.addEventListener("dragstart", function(e) {
        const target = e.target.closest(itemSelector);
        if (!target) return;
        
        dragEl = target;
        dragEl.classList.add("dragging");
        e.dataTransfer.effectAllowed = "move";
        setTimeout(() => dragEl.style.opacity = '0.4', 0);
      });
      
      container.addEventListener("dragend", function(e) {
        const target = e.target.closest(itemSelector);
        if (!target) return;
        
        target.classList.remove("dragging");
        target.style.opacity = '1';
        
        // Retrieve current order of IDs
        const items = container.querySelectorAll(itemSelector);
        const ids = [];
        items.forEach(item => {
          if (item.dataset.id) {
            ids.push(item.dataset.id);
          }
        });
        
        saveNewOrder(ids);
        dragEl = null;
      });
      
      container.addEventListener("dragover", function(e) {
        e.preventDefault();
        const draggingEl = container.querySelector(".dragging");
        if (!draggingEl) return;
        
        const target = e.target.closest(itemSelector);
        if (!target || target === draggingEl) return;
        
        const rect = target.getBoundingClientRect();
        const isGrid = target.classList.contains("manga-block-card");
        let next;
        
        if (isGrid) {
          const midpointX = rect.left + rect.width / 2;
          const midpointY = rect.top + rect.height / 2;
          next = (e.clientX > midpointX) || (e.clientY > midpointY && e.clientX > rect.left);
        } else {
          next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
        }
        
        container.insertBefore(draggingEl, next ? target.nextSibling : target);
      });
    }

    let saveTimeout = null;
    function saveNewOrder(ids) {
      if (ids.length === 0) return;
      
      // Sync the DOM of the other view
      syncDOMOrder(ids);
      
      // Debounce saving if they are dragging rapidly
      if (saveTimeout) clearTimeout(saveTimeout);
      
      showToast('Saving new order...', 'info');
      
      saveTimeout = setTimeout(() => {
        const formData = new FormData();
        formData.append('ordered_ids', ids.join(','));
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
          formData.append('csrf_token', csrfInput.value);
        }
        
        fetch('/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=reorder_all', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showToast('Order saved successfully!', 'success');
          } else {
            showToast('Failed to save order: ' + (data.error || 'Unknown error'), 'error');
          }
        })
        .catch(err => {
          showToast('Network error while saving order.', 'error');
          console.error(err);
        });
      }, 400); // 400ms debounce
    }

    function syncDOMOrder(orderedIds) {
      // Sync Table
      const tbody = document.querySelector(".manga-table tbody");
      if (tbody) {
        const rows = Array.from(tbody.querySelectorAll("tr.draggable-row"));
        orderedIds.forEach(id => {
          const row = rows.find(r => r.dataset.id === id);
          if (row) tbody.appendChild(row);
        });
      }
      
      // Sync Grid
      const grid = document.getElementById("manga-drag-grid");
      if (grid) {
        const cards = Array.from(grid.querySelectorAll(".manga-block-card"));
        orderedIds.forEach(id => {
          const card = cards.find(c => c.dataset.id === id);
          if (card) grid.appendChild(card);
        });
      }
    }

    // Modern floating Toast notification system
    function showToast(message, type = 'info') {
      let container = document.getElementById('toast-container');
      if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '2rem';
        container.style.right = '2rem';
        container.style.zIndex = '9999';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '0.75rem';
        container.style.pointerEvents = 'none';
        document.body.appendChild(container);
      }
      
      const toast = document.createElement('div');
      toast.style.pointerEvents = 'auto';
      toast.style.padding = '0.75rem 1.25rem';
      toast.style.borderRadius = '0.75rem';
      toast.style.fontSize = '0.8125rem';
      toast.style.fontWeight = '700';
      toast.style.color = '#fff';
      toast.style.boxShadow = 'var(--theme-shadow-md)';
      toast.style.backdropFilter = 'blur(8px)';
      toast.style.display = 'flex';
      toast.style.alignItems = 'center';
      toast.style.gap = '0.5rem';
      toast.style.transition = 'all 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
      
      let bg = 'rgba(11, 17, 30, 0.9)';
      let border = '1px solid var(--theme-border)';
      let icon = 'ℹ️';
      
      if (type === 'success') {
        bg = 'rgba(16, 185, 129, 0.95)';
        border = '1px solid rgba(16, 185, 129, 0.2)';
        icon = '✅';
      } else if (type === 'error') {
        bg = 'rgba(239, 68, 68, 0.95)';
        border = '1px solid rgba(239, 68, 68, 0.2)';
        icon = '❌';
      } else if (type === 'info') {
        bg = 'rgba(139, 92, 246, 0.95)';
        border = '1px solid rgba(139, 92, 246, 0.2)';
        icon = '✨';
      }
      
      toast.style.background = bg;
      toast.style.border = border;
      toast.innerHTML = `<span style="font-size: 1rem;">${icon}</span><span>${message}</span>`;
      
      if (type === 'success' || type === 'error') {
        const activeToasts = container.querySelectorAll('.toast-saving');
        activeToasts.forEach(t => {
          t.style.opacity = '0';
          t.style.transform = 'translateY(-20px)';
          setTimeout(() => t.remove(), 300);
        });
      }
      
      if (type === 'info') {
        toast.classList.add('toast-saving');
      }
      
      container.appendChild(toast);
      
      setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
      }, 10);
      
      if (type !== 'info') {
        setTimeout(() => {
          toast.style.opacity = '0';
          toast.style.transform = 'translateY(-20px)';
          setTimeout(() => toast.remove(), 300);
        }, 3000);
      }
    }
  </script>
</body>
</html>

<!-- Styles for Manga CRUD Forms -->
<style>
.success-banner {
  background-color: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #10b981;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  margin-bottom: 1.5rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.crud-card {
  background-color: #090b11;
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  padding: 2rem;
  margin-bottom: 2.5rem;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--theme-border);
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;
}

.card-header h3 {
  font-size: 1.125rem;
  font-weight: 800;
  color: var(--theme-text);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.col-12 { grid-column: span 12; }
.col-6  { grid-column: span 12; }
.col-4  { grid-column: span 12; }

@media(min-width: 768px) {
  .col-6 { grid-column: span 6; }
  .col-4 { grid-column: span 4; }
}

.sub-section-title {
  font-size: 0.8125rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--theme-primary);
  margin-top: 1rem;
  border-bottom: 1px solid var(--theme-border);
  padding-bottom: 0.5rem;
}

.code-area {
  font-family: monospace;
  font-size: 0.8125rem;
  background-color: #040508 !important;
}

/* View Toggle styling */
.view-toggle-btn.active {
  background: var(--theme-primary) !important;
  color: #fff !important;
  box-shadow: var(--theme-shadow-sm);
}
.view-toggle-btn:hover:not(.active) {
  color: var(--theme-text) !important;
  background: rgba(255, 255, 255, 0.05);
}

/* Drag and Drop & Grid styling */
.manga-table tr.draggable-row {
  cursor: move;
  cursor: grab;
  transition: opacity 0.2s ease, background-color 0.2s ease;
}
.manga-table tr.dragging {
  background-color: rgba(var(--theme-primary-rgb), 0.12) !important;
  opacity: 0.5;
  outline: 2px dashed var(--theme-primary);
}

.manga-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1.5rem;
  padding: 0.5rem 0;
}

.manga-block-card {
  background-color: #090b11;
  border: 1px solid var(--theme-border);
  border-radius: 1rem;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
  transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
  user-select: none;
}
.manga-block-card:hover {
  border-color: var(--theme-card-hover-border);
  box-shadow: 0 0 15px rgba(var(--theme-primary-rgb), 0.15);
}

.card-cover-container {
  height: 240px;
  width: 100%;
  position: relative;
  background-color: #040508;
  overflow: hidden;
}
.card-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.3s ease;
}
.manga-block-card:hover .card-cover-img {
  transform: scale(1.05);
}
.card-no-cover {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--theme-text-muted);
}

.card-drag-handle {
  position: absolute;
  top: 0.5rem;
  left: 0.5rem;
  background: rgba(0, 0, 0, 0.65);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #fff;
  border-radius: 0.35rem;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  font-size: 1.1rem;
  backdrop-filter: blur(4px);
  z-index: 10;
  transition: background 0.2s, transform 0.2s;
}
.card-drag-handle:hover {
  background: var(--theme-primary);
  transform: scale(1.1);
}
.card-drag-handle:active {
  cursor: grabbing;
}

.card-badge {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  z-index: 10;
  font-size: 0.65rem;
  padding: 0.2rem 0.5rem;
  border-radius: 0.35rem;
  text-transform: uppercase;
  font-weight: 700;
}

.card-info {
  padding: 1rem 1rem 0.5rem 1rem;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.card-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--theme-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.card-author {
  font-size: 0.75rem;
  color: var(--theme-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-actions {
  padding: 0.75rem 1rem 1rem 1rem;
  border-top: 1px solid var(--theme-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
}
.card-actions .action-btn {
  font-size: 0.7rem;
  padding: 0.3rem 0.5rem;
  border-radius: 0.35rem;
  text-decoration: none;
  text-align: center;
  flex-grow: 1;
}
.card-actions form {
  flex-grow: 1;
  display: flex;
}
.card-actions .delete-btn {
  width: 100%;
}

.manga-grid .manga-block-card.dragging {
  opacity: 0.4;
  outline: 2px dashed var(--theme-primary);
  transform: scale(0.98);
}
</style>
