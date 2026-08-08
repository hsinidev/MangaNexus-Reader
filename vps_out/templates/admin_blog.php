<?php
/**
 * admin_blog.php — Blog Articles Manager (CRUD + Image Upload)
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
        echo json_encode(['error' => 'Article title is required.']);
        exit;
    }

    $custom_prompt = $settings['blog_ai_prompt'] ?? '';
    if (!empty($custom_prompt)) {
        $prompt = str_replace('{$title}', $title, $custom_prompt);
    } else {
        $prompt = "Generate a comprehensive 2000-3500-word blog post for a manga/manhwa/scanlation blog named '{$title}'. Output must be strictly valid JSON matching this schema:
{
  \"title\": \"...\",
  \"slug\": \"...\",
  \"excerpt\": \"...\",
  \"content\": \"<p>HTML formatted blog content</p>\",
  \"seo_schema\": \"{ \\\"@context\\\": \\\"https://schema.org\\\", \\\"@type\\\": \\\"BlogPosting\\\", \\\"headline\\\": \\\"...\\\", \\\"description\\\": \\\"...\\\" }\"
}
Do not use markdown code blocks around the JSON output, just pure JSON.";
    }

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
    echo json_encode(['success' => true, 'data' => $json_out]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && !empty($id)) {
    try {
        $post = db_fetch("SELECT * FROM blog_posts WHERE id = ?", [$id]);
        if ($post) {
            // Delete cover/thumbnail if exists
            if (!empty($post['thumbnail_url'])) {
                $thumb_file = BASE_PATH . $post['thumbnail_url'];
                if (file_exists($thumb_file)) {
                    unlink($thumb_file);
                }
            }
            db_query("DELETE FROM blog_posts WHERE id = ?", [$id]);
            try {
                generate_seo_assets();
            } catch (Exception $seo_ex) {
                \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on blog delete: " . $seo_ex->getMessage());
            }
            header("Location: /" . $admin_slug . "/blog");
            exit;
        }
    } catch (PDOException $e) {
        $error = 'Database delete failed: ' . $e->getMessage();
    }
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
    
    $excerpt = trim($_POST['excerpt']);
    $content = $_POST['content'];
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $read_manga_url = trim($_POST['read_manga_url'] ?? '');
    $seo_schema = trim($_POST['seo_schema'] ?? '');

    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        // Validate slug uniqueness
        $dup = db_fetch("SELECT id FROM blog_posts WHERE slug = ? AND id != ?", [$slug, $id]);
        if ($dup) {
            $error = 'A blog post with this slug already exists.';
        } else {
            // Process Thumbnail Upload
            $thumbnail_url = isset($_POST['existing_thumbnail']) ? $_POST['existing_thumbnail'] : '';
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $allowed_img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'ico'];
                $thumb_ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
                if (in_array($thumb_ext, $allowed_img_exts)) {
                    $tmp_name = $_FILES['thumbnail']['tmp_name'];
                    
                    // Delete old thumbnail if editing
                    if ($action === 'edit' && !empty($thumbnail_url)) {
                        $old_file = BASE_PATH . $thumbnail_url;
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }

                    $thumb_name = 'blog_' . $slug . '_' . time() . '.webp';
                    $dest_file = BLOG_DIR . '/' . $thumb_name;

                    // Max width 800px, quality 60 for optimal loading speed under 50KB!
                    if (optimize_image($tmp_name, $dest_file, 60, 800)) {
                        $thumbnail_url = '/uploads/blog/' . $thumb_name;
                    } else {
                        $error = 'Failed to process and optimize thumbnail image.';
                    }
                } else {
                    $error = 'Thumbnail file type is not allowed. Only JPG, PNG, GIF, WebP, AVIF, and ICO are permitted.';
                }
            }

            if (empty($error)) {
                try {
                    if ($action === 'create') {
                        $new_id = uuid();
                        db_query(
                            "INSERT INTO blog_posts (id, title, slug, excerpt, content, thumbnail_url, is_published, read_manga_url, seo_schema, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                            [$new_id, $title, $slug, $excerpt, $content, $thumbnail_url, $is_published, $read_manga_url, $seo_schema]
                        );
                        $success = 'Blog post published successfully.';
                        try {
                            generate_seo_assets();
                        } catch (Exception $seo_ex) {
                            \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on blog create: " . $seo_ex->getMessage());
                        }
                        header("Location: /" . $admin_slug . "/blog");
                        exit;
                    } else if ($action === 'edit') {
                        db_query(
                            "UPDATE blog_posts SET title = ?, slug = ?, excerpt = ?, content = ?, thumbnail_url = ?, is_published = ?, read_manga_url = ?, seo_schema = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                            [$title, $slug, $excerpt, $content, $thumbnail_url, $is_published, $read_manga_url, $seo_schema, $id]
                        );
                        $success = 'Blog post updated successfully.';
                        try {
                            generate_seo_assets();
                        } catch (Exception $seo_ex) {
                            \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on blog edit: " . $seo_ex->getMessage());
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
$post_data = null;
if ($action === 'edit' && !empty($id)) {
    $post_data = db_fetch("SELECT * FROM blog_posts WHERE id = ?", [$id]);
}

// Fetch all posts for listing
$posts_list = db_fetch_all("SELECT * FROM blog_posts ORDER BY created_at DESC");
// Fetch all mangas for selections mapping
$all_mangas = db_fetch_all("SELECT id, title, slug FROM mangas ORDER BY title ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog Management - MangaNexus</title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <style>
    .form-textarea.html-editor {
      font-family: 'Courier New', Courier, monospace;
      font-size: 0.85rem !important;
      line-height: 1.5;
      background-color: #03050a !important;
      color: #38bdf8 !important;
      resize: vertical;
      min-height: 350px;
    }
    .thumb-preview-box {
      width: 120px;
      aspect-ratio: 16/10;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--theme-border);
      background-color: #121214;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 0.75rem;
    }
    .thumb-preview-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .thumb-preview-fallback {
      font-size: 0.65rem;
      color: var(--theme-text-muted);
      text-transform: uppercase;
      font-weight: 700;
    }
  </style>
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
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga" class="nav-item">
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
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/blog" class="nav-item active">
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
      <h2>Blog Management</h2>
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

      <?php if ($action === 'create' || $action === 'edit'): ?>
        <!-- ── CREATE OR EDIT FORM ── -->
        <div class="crud-card">
          <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h3><?php echo $action === 'create' ? 'Create New Blog Post' : 'Edit Blog Post Settings'; ?></h3>
            <a href="/<?php echo htmlspecialchars($admin_slug); ?>/blog" class="btn btn-secondary btn-sm">Cancel</a>
          </div>

          <form action="" method="POST" enctype="multipart/form-data" class="crud-form">
            <?php echo \MangaNexus\Security\Csrf::getField(); ?>
            <input type="hidden" name="existing_thumbnail" value="<?php echo htmlspecialchars($post_data ? $post_data['thumbnail_url'] : ''); ?>">

            <div class="form-grid">
              <div class="form-group col-6">
                <label for="title" class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                  <span>Article Title *</span>
                  <button type="button" id="gemini-btn" onclick="generateGeminiBlog()" class="btn btn-secondary btn-sm" style="padding: 0.2rem 0.6rem; font-size: 0.75rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; color: #fff;">✨ AI Generate</button>
                </label>
                <input type="text" name="title" id="title" class="form-input" value="<?php echo htmlspecialchars($post_data ? $post_data['title'] : ''); ?>" required>
              </div>

              <div class="form-group col-6">
                <label for="slug" class="form-label">URL Slug (leave empty to auto-generate)</label>
                <input type="text" name="slug" id="slug" class="form-input" value="<?php echo htmlspecialchars($post_data ? $post_data['slug'] : ''); ?>" placeholder="e.g. dynamic-layouts-performance">
              </div>

              <div class="form-group col-8">
                <label for="excerpt" class="form-label">Excerpt / Short Summary *</label>
                <input type="text" name="excerpt" id="excerpt" class="form-input" value="<?php echo htmlspecialchars($post_data ? $post_data['excerpt'] : ''); ?>" placeholder="Brief one-sentence summary of the post..." required>
              </div>

              <div class="form-group col-4">
                <label for="thumbnail" class="form-label">Cover Thumbnail Image</label>
                <div class="thumb-preview-box">
                  <?php if ($post_data && !empty($post_data['thumbnail_url'])): ?>
                    <img src="<?php echo htmlspecialchars($post_data['thumbnail_url']); ?>?v=<?php echo time(); ?>" alt="Thumbnail">
                  <?php else: ?>
                    <span class="thumb-preview-fallback">No Cover</span>
                  <?php endif; ?>
                </div>
                <input type="file" name="thumbnail" id="thumbnail" class="form-input" accept="image/*">
                <span class="zinc-text">Auto-converted to highly optimized WebP format (max 800px width).</span>
              </div>

              <div class="form-group col-6" style="margin-top: 0.5rem;">
                <label for="associated_manga_select" class="form-label">Associate Manga Series</label>
                <select id="associated_manga_select" class="form-select" onchange="updateMangaReadUrl(this.value)">
                  <option value="">-- Choose Manga Series --</option>
                  <?php foreach ($all_mangas as $m): ?>
                    <option value="<?php echo htmlspecialchars($m['slug']); ?>">
                      <?php echo htmlspecialchars($m['title']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="zinc-text">Optionally select a manga to auto-fill the Read Manga button URL.</span>
              </div>

              <div class="form-group col-6" style="margin-top: 0.5rem;">
                <label for="read_manga_url" class="form-label">Manga Read URL / Custom CTA Link</label>
                <input type="text" name="read_manga_url" id="read_manga_url" class="form-input" value="<?php echo htmlspecialchars($post_data ? $post_data['read_manga_url'] : ''); ?>" placeholder="e.g. /manga/one-piece or https://...">
                <span class="zinc-text">The landing URL visitors are directed to when clicking the "Read Manga" button.</span>
              </div>

              <!-- JSON SEO Schema -->
              <div class="form-group col-12" style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                <label for="seo_schema" class="form-label">Blog Article JSON-LD SEO Schema</label>
                <textarea name="seo_schema" id="seo_schema" class="form-textarea" style="font-family: monospace; font-size: 0.8rem; min-height: 120px;" placeholder='{ "@context": "https://schema.org", "@type": "BlogPosting", ... }'><?php echo htmlspecialchars($post_data ? $post_data['seo_schema'] : ''); ?></textarea>
                <span class="zinc-text">Enter custom JSON-LD SEO Schema markup. It will be outputted in the page &lt;head&gt;.</span>
              </div>

              <div class="form-group col-12" style="display: flex; align-items: center; user-select: none;">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                  <input type="checkbox" name="is_published" value="1" <?php echo (!$post_data || $post_data['is_published'] == 1) ? 'checked' : ''; ?> style="width: 1.15rem; height: 1.15rem; accent-color: var(--theme-primary);">
                  <span style="font-size: 0.85rem; font-weight: 600; color: #fff;">Publish Article (Make visible to readers)</span>
                </label>
              </div>

              <div class="form-group col-12" style="margin-top: 1rem;">
                <label for="content" class="form-label">Article Body Content (HTML Editor) *</label>
                <textarea name="content" id="content" class="form-textarea html-editor" placeholder="<p>Insert your article copy here...</p>" required><?php echo htmlspecialchars($post_data ? $post_data['content'] : ''); ?></textarea>
                <span class="zinc-text" style="display: block; margin-top: 0.5rem; color: var(--theme-text-muted); font-size: 0.725rem;">
                  HTML markup is fully supported. Use structural tags like <code>&lt;p&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;img&gt;, &lt;iframe&gt;</code> to style the article content.
                </span>
              </div>
            </div>

            <div style="margin-top: 2rem;">
              <button type="submit" class="btn btn-primary">Publish Article</button>
            </div>
          </form>
        </div>

      <?php else: ?>
        <!-- ── LIST OF ARTICLES ── -->
        <section class="admin-mangas-section">
          <div class="section-header-row" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Published Blog Articles</h3>
            <a href="/<?php echo htmlspecialchars($admin_slug); ?>/blog?action=create" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Create Article
            </a>
          </div>

          <?php if (empty($posts_list)): ?>
            <div class="empty-state">
              <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M16 8h2"/><path d="M16 12h2"/><path d="M16 16h2"/><path d="M6 8h6v8H6z"/></svg>
              <p>No blog articles found in the database. Write your first article today!</p>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table class="manga-table">
                <thead>
                  <tr>
                    <th>Thumbnail</th>
                    <th>Article Title</th>
                    <th>Slug Path</th>
                    <th>Status</th>
                    <th>Date Published</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($posts_list as $post): ?>
                    <tr>
                      <td>
                        <div style="width: 50px; aspect-ratio: 16/10; border-radius: 4px; overflow:hidden; border:1px solid var(--theme-border); background-color: #18181b;">
                          <?php if (!empty($post['thumbnail_url'])): ?>
                            <img src="<?php echo htmlspecialchars($post['thumbnail_url']); ?>?v=<?php echo time(); ?>" alt="Thumb" style="width:100%; height:100%; object-fit:cover;">
                          <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:0.5rem; font-weight:700; color:var(--theme-text-muted);">NONE</div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="manga-td-info">
                        <span class="manga-td-title"><?php echo htmlspecialchars($post['title']); ?></span>
                      </td>
                      <td style="font-family: monospace; color: var(--theme-secondary);">
                        /blog/<?php echo htmlspecialchars($post['slug']); ?>
                      </td>
                      <td>
                        <span class="status-pill <?php echo $post['is_published'] ? 'ongoing' : 'completed'; ?>" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">
                          <?php echo $post['is_published'] ? 'Published' : 'Draft'; ?>
                        </span>
                      </td>
                      <td style="font-size:0.75rem; color:var(--theme-text-muted);">
                        <?php echo format_manga_date($post['created_at']); ?>
                      </td>
                      <td class="manga-td-actions">
                        <a href="/<?php echo htmlspecialchars($admin_slug); ?>/blog?action=edit&id=<?php echo htmlspecialchars($post['id']); ?>" class="action-btn edit-btn">Edit</a>
                        <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" target="_blank" class="action-btn edit-btn" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #10b981;">View</a>
                        <form action="/<?php echo htmlspecialchars($admin_slug); ?>/blog?action=delete&id=<?php echo htmlspecialchars($post['id']); ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this blog post?');">
                          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
                          <button type="submit" class="action-btn delete-btn" style="background:var(--theme-card); border:1px solid rgba(239, 68, 68, 0.2); cursor:pointer; font:inherit; color:#f87171;">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>

    <!-- Non-Removable Developer Footer Credits -->
    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

  <script>
    async function generateGeminiBlog() {
      const title = document.getElementById('title').value;
      if (!title) {
        alert("Please enter a blog title first before generating AI content.");
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
        const res = await fetch('/<?php echo htmlspecialchars($admin_slug); ?>/blog?action=gemini_generate', {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        
        if (result.error) {
            alert("Error: " + result.error);
        } else if (result.success) {
            const data = result.data;
            const fields = ['title', 'slug', 'excerpt', 'content', 'seo_schema'];
            
            let count = 0;
            fields.forEach(field => {
                if (data[field] !== undefined) {
                    const el = document.getElementById(field);
                    if (el) {
                        if (field === 'seo_schema' && typeof data[field] === 'object') {
                            el.value = JSON.stringify(data[field], null, 2);
                        } else {
                            el.value = data[field];
                        }
                        count++;
                    }
                }
            });
            alert(`🎉 AI generation complete! Blog content populated successfully.`);
        }
      } catch(e) {
          alert("Request failed. " + e.message);
      }
      
      btn.innerHTML = originalText;
      btn.disabled = false;
    }

    function updateMangaReadUrl(slug) {
      if (slug) {
        document.getElementById('read_manga_url').value = '/manga/' + slug;
      }
    }
  </script>
</body>
</html>
