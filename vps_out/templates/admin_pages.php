<?php
/**
 * admin_pages.php — Custom Pages Manager (HTML Code Input)
 */

$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\MangaNexus\Security\Csrf::validate($_POST['csrf_token'] ?? '')) {
        die('Error: Invalid CSRF Token.');
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && !empty($id)) {
    // Prevent deleting default pages completely or allow it? Let's allow but warn.
    try {
        db_query("DELETE FROM custom_pages WHERE id = ?", [$id]);
        try {
            generate_seo_assets();
        } catch (Exception $seo_ex) {
            \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on custom page delete: " . $seo_ex->getMessage());
        }
        header("Location: /" . $admin_slug . "/pages");
        exit;
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
    
    $content = $_POST['content'];
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $show_in_footer = isset($_POST['show_in_footer']) ? 1 : 0;

    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        // Validate slug uniqueness
        $dup = db_fetch("SELECT id FROM custom_pages WHERE slug = ? AND id != ?", [$slug, $id]);
        if ($dup) {
            $error = 'A page with this slug already exists.';
        } else {
            try {
                if ($action === 'create') {
                    $new_id = uuid();
                    db_query(
                        "INSERT INTO custom_pages (id, title, slug, content, is_published, show_in_footer, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                        [$new_id, $title, $slug, $content, $is_published, $show_in_footer]
                    );
                    $success = 'Custom page created successfully.';
                    try {
                        generate_seo_assets();
                    } catch (Exception $seo_ex) {
                        \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on custom page create: " . $seo_ex->getMessage());
                    }
                    header("Location: /" . $admin_slug . "/pages");
                    exit;
                } else if ($action === 'edit') {
                    db_query(
                        "UPDATE custom_pages SET title = ?, slug = ?, content = ?, is_published = ?, show_in_footer = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                        [$title, $slug, $content, $is_published, $show_in_footer, $id]
                    );
                    $success = 'Custom page updated successfully.';
                    try {
                        generate_seo_assets();
                    } catch (Exception $seo_ex) {
                        \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on custom page edit: " . $seo_ex->getMessage());
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database save failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch record for editing
$page_data = null;
if ($action === 'edit' && !empty($id)) {
    $page_data = db_fetch("SELECT * FROM custom_pages WHERE id = ?", [$id]);
}

// Fetch all pages for listing
$pages_list = db_fetch_all("SELECT * FROM custom_pages ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pages Management - MangaNexus</title>
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
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/pages" class="nav-item active">
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
      <h2>Pages Management</h2>
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
            <h3><?php echo $action === 'create' ? 'Create New Custom Page' : 'Edit Custom Page Settings'; ?></h3>
            <a href="/<?php echo htmlspecialchars($admin_slug); ?>/pages" class="btn btn-secondary btn-sm">Cancel</a>
          </div>

          <form action="" method="POST" class="crud-form">
            <?php echo \MangaNexus\Security\Csrf::getField(); ?>

            <div class="form-grid">
              <div class="form-group col-6">
                <label for="title" class="form-label">Page Title *</label>
                <input type="text" name="title" id="title" class="form-input" value="<?php echo htmlspecialchars($page_data ? $page_data['title'] : ''); ?>" required>
              </div>

              <div class="form-group col-6">
                <label for="slug" class="form-label">URL Slug (e.g. privacy-policy, contact)</label>
                <input type="text" name="slug" id="slug" class="form-input" value="<?php echo htmlspecialchars($page_data ? $page_data['slug'] : ''); ?>" placeholder="e.g. about-us">
              </div>

              <div class="form-group col-6" style="display: flex; align-items: center; gap: 2rem; margin-top: 1rem;">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 0.5rem; user-select: none;">
                  <input type="checkbox" name="is_published" value="1" <?php echo (!$page_data || $page_data['is_published'] == 1) ? 'checked' : ''; ?> style="width: 1.15rem; height: 1.15rem; accent-color: var(--theme-primary);">
                  <span style="font-size: 0.85rem; font-weight: 600; color: #fff;">Publish Page</span>
                </label>
                
                <label style="cursor: pointer; display: flex; align-items: center; gap: 0.5rem; user-select: none;">
                  <input type="checkbox" name="show_in_footer" value="1" <?php echo (!$page_data || $page_data['show_in_footer'] == 1) ? 'checked' : ''; ?> style="width: 1.15rem; height: 1.15rem; accent-color: var(--theme-primary);">
                  <span style="font-size: 0.85rem; font-weight: 600; color: #fff;">Show in Footer Navigation</span>
                </label>
              </div>

              <div class="form-group col-12">
                <label for="content" class="form-label">Page Content (HTML Code Editor) *</label>
                <textarea name="content" id="content" class="form-textarea html-editor" placeholder="<h1>Page Title</h1><p>Insert your page layout and details here...</p>" required><?php echo htmlspecialchars($page_data ? $page_data['content'] : ''); ?></textarea>
                <span class="zinc-text" style="display: block; margin-top: 0.5rem; color: var(--theme-text-muted); font-size: 0.725rem;">
                  HTML markup is fully supported. Use headings <code>&lt;h1&gt;, &lt;h2&gt;</code>, paragraphs <code>&lt;p&gt;</code>, lists <code>&lt;ul&gt;, &lt;li&gt;</code>, or custom container classes for layout design.
                </span>
              </div>
            </div>

            <div style="margin-top: 2rem;">
              <button type="submit" class="btn btn-primary">Save Custom Page</button>
            </div>
          </form>
        </div>

      <?php else: ?>
        <!-- ── LIST OF CUSTOM PAGES ── -->
        <section class="admin-mangas-section">
          <div class="section-header-row" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Dynamic Pages List</h3>
            <a href="/<?php echo htmlspecialchars($admin_slug); ?>/pages?action=create" class="btn btn-primary">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Create Page
            </a>
          </div>

          <?php if (empty($pages_list)): ?>
            <div class="empty-state">
              <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
              <p>No custom pages have been configured yet.</p>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table class="manga-table">
                <thead>
                  <tr>
                    <th>Page Title</th>
                    <th>URL Slug</th>
                    <th>Status</th>
                    <th>Footer Nav</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pages_list as $page): ?>
                    <tr>
                      <td class="manga-td-info">
                        <span class="manga-td-title"><?php echo htmlspecialchars($page['title']); ?></span>
                      </td>
                      <td style="font-family: monospace; color: var(--theme-secondary);">
                        /<?php echo htmlspecialchars($page['slug']); ?>
                      </td>
                      <td>
                        <span class="status-pill <?php echo $page['is_published'] ? 'ongoing' : 'completed'; ?>" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">
                          <?php echo $page['is_published'] ? 'Published' : 'Draft'; ?>
                        </span>
                      </td>
                      <td>
                        <span style="font-size: 0.8rem; font-weight: 600; color: <?php echo $page['show_in_footer'] ? '#10b981' : '#ef4444'; ?>;">
                          <?php echo $page['show_in_footer'] ? 'Yes' : 'No'; ?>
                        </span>
                      </td>
                      <td style="font-size:0.75rem; color:var(--theme-text-muted);">
                        <?php echo htmlspecialchars($page['updated_at']); ?>
                      </td>
                      <td class="manga-td-actions">
                        <a href="/<?php echo htmlspecialchars($admin_slug); ?>/pages?action=edit&id=<?php echo htmlspecialchars($page['id']); ?>" class="action-btn edit-btn">Edit</a>
                        <a href="/<?php echo htmlspecialchars($page['slug']); ?>" target="_blank" class="action-btn edit-btn" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #10b981;">View</a>
                        <form action="/<?php echo htmlspecialchars($admin_slug); ?>/pages?action=delete&id=<?php echo htmlspecialchars($page['id']); ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this custom page?');">
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

</body>
</html>
