<?php
/**
 * admin_dashboard.php — Administrative Panel Dashboard Home (PHP Version)
 */

$error = '';
$success = '';

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

// Query stats
$total_mangas = db_fetch("SELECT COUNT(*) as count FROM mangas")['count'];
$total_chapters = db_fetch("SELECT COUNT(*) as count FROM chapters")['count'];
$total_visitors = db_fetch("SELECT COUNT(*) as count FROM users")['count'];

// Fetch all mangas for the library listing
$mangas = db_fetch_all("SELECT * FROM mangas ORDER BY created_at DESC");
$manga_list = [];
foreach ($mangas as $m) {
    $m['chapter_count'] = db_fetch("SELECT COUNT(*) as count FROM chapters WHERE manga_id = ?", [$m['id']])['count'];
    $manga_list[] = $m;
}

$cache_data = json_decode($settings['license_verify_cache'] ?? '', true) ?: [];
$license_expires_display = isset($cache_data['expires_at']) ? date('M d, H:i', strtotime($cache_data['expires_at'])) : 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - MangaNexus</title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?> admin-layout">

  <!-- Admin Sidebar Navigation -->
  <aside class="admin-sidebar">
    <div class="sidebar-brand">
      <div class="logo-box-mini">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <span>MangaNexus Panel</span>
    </div>
    
    <nav class="sidebar-nav">
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/" class="nav-item active">
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

  <!-- Main Content Wrapper -->
  <main class="admin-main">
    <!-- Topbar info -->
    <header class="admin-topbar">
      <h2>Dashboard Overview</h2>
      <div class="user-badge">
        <div class="status-indicator active"></div>
        <span>Username: <strong><?php echo htmlspecialchars($settings['admin_username']); ?></strong></span>
      </div>
    </header>

    <div class="admin-content-box">
      <!-- Alerts -->
      <?php if (!empty($error)): ?>
        <div class="error-banner" style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 1rem; border-radius: 1rem; margin-bottom: 2rem; font-size: 0.875rem; font-weight: 700;"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="success-banner" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981; padding: 1rem; border-radius: 1rem; margin-bottom: 2rem; font-size: 0.875rem; font-weight: 700;"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <!-- 1. Stats Counter Row -->
      <section class="stats-row">
        <div class="stat-card">
          <div class="stat-icon magenta">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
          </div>
          <div class="stat-info">
            <span class="stat-val"><?php echo $total_mangas; ?></span>
            <span class="stat-label">Total Manga Series</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon cyan">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
          </div>
          <div class="stat-info">
            <span class="stat-val"><?php echo $total_chapters; ?></span>
            <span class="stat-label">Chapters Uploaded</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon magenta">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="stat-info">
            <span class="stat-val"><?php echo $total_visitors; ?></span>
            <span class="stat-label">Registered Visitors</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <div class="stat-info">
            <span class="stat-val verified-text">Verified</span>
            <span class="stat-label">Cache Expires: <?php echo htmlspecialchars($license_expires_display); ?></span>
          </div>
        </div>
      </section>

      <!-- 2. Library listing -->
      <section class="admin-mangas-section">
        <div class="section-header-row">
          <h3>Active Manga Cases</h3>
          <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=create" class="btn btn-primary btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create Manga
          </a>
        </div>

        <?php if (empty($manga_list)): ?>
          <div class="empty-state">
            <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
            <p>No manga cases recorded. Click "Create Manga" to start.</p>
          </div>
        <?php else: ?>
          <div class="table-container">
            <table class="manga-table">
              <thead>
                <tr>
                  <th>Cover</th>
                  <th>Manga Details</th>
                  <th>Status</th>
                  <th>Chapters</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($manga_list as $m): ?>
                  <tr>
                    <td class="manga-td-cover">
                      <?php if (!empty($m['cover_url'])): ?>
                        <img src="<?php echo htmlspecialchars($m['cover_url']); ?>" alt="" class="td-cover-img">
                      <?php else: ?>
                        <div class="td-no-cover">NO ART</div>
                      <?php endif; ?>
                    </td>
                    <td class="manga-td-info">
                      <span class="manga-td-title"><?php echo htmlspecialchars($m['title']); ?></span>
                      <span class="manga-td-slug">/manga/<?php echo htmlspecialchars($m['slug']); ?></span>
                    </td>
                    <td>
                      <span class="badge badge-<?php echo htmlspecialchars($m['status']); ?>">
                        <?php echo htmlspecialchars($m['status']); ?>
                      </span>
                    </td>
                    <td>
                      <span class="td-ch-count"><?php echo $m['chapter_count']; ?> chapters</span>
                    </td>
                    <td class="manga-td-actions">
                      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=edit&id=<?php echo $m['id']; ?>" class="action-btn edit-btn" title="Edit Metadata">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Edit
                      </a>
                      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/chapters/<?php echo $m['id']; ?>" class="action-btn upload-btn" title="Manage Chapters & Pages">
                        Chapters
                      </a>
                      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga?action=delete&id=<?php echo $m['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this manga and all its chapters?');" title="Delete Series">
                        Delete
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <!-- 3. Domain Migration Studio (Advanced Tools) -->
      <section class="admin-mangas-section" style="margin-top: 2.5rem; background-color: rgba(var(--theme-card-rgb), 0.3); backdrop-filter: blur(16px);">
        <div class="section-header-row">
          <h3>Domain Migration Studio</h3>
        </div>
        <p class="settings-desc" style="margin-bottom: 1.5rem; font-size: 0.8125rem; color: var(--theme-text-muted); line-height: 1.6;">
          Migrate your entire MangaNexus project under a new domain name instantly. This action updates the database, re-writes the <code>production_domain</code> config, updates absolute URLs in sitemaps & robots.txt, and scans/replaces occurrences in all code files.
        </p>
        
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
            <label for="new_domain" class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; color: var(--theme-text-muted);">New Domain Name *</label>
            <input type="text" name="new_domain" id="new_domain" class="form-input" placeholder="e.g. newdomain.com" required style="width: 100%; padding: 0.9rem 1.1rem; border-radius: 0.85rem; font-size: 0.875rem;">
            <span class="zinc-text" style="font-size: 0.72rem; color: #94a3b8; opacity: 0.8; display: block; margin-top: 0.4rem; line-height: 1.5;">
              Do not include <code>http://</code> or <code>https://</code> or trailing slashes. E.g., enter <code>newwebsite.com</code>.
            </span>
          </div>
          
          <button type="submit" class="btn btn-primary" onclick="return confirm('⚠️ WARNING: This will replace the old domain name across all database tables, columns, and source code files recursively. Make sure you have a backup of the database and files before continuing! Proceed?');" style="padding: 0.95rem 2rem; border-radius: 1rem; font-size: 0.8125rem; font-weight: 900;">
            🚀 Migrate to New Domain
          </button>
        </form>
      </section>
    </div>

    <!-- Non-Removable Developer Footer Credits -->
    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

</body>
</html>

<!-- Admin Panel Specific Layout Styles -->
<style>

/* Stats counter */
.stats-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
  margin-bottom: 2.5rem;
}

@media(min-width: 768px) {
  .stats-row {
    grid-template-columns: repeat(4, 1fr);
  }
}

.stat-card {
  background-color: #090b11;
  border: 1px solid var(--theme-border);
  border-radius: 1.25rem;
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.stat-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon.magenta {
  background-color: rgba(139, 92, 246, 0.1);
  color: var(--theme-primary);
}

.stat-icon.cyan {
  background-color: rgba(6, 182, 212, 0.1);
  color: var(--theme-secondary);
}

.stat-icon.green {
  background-color: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-val {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--theme-text);
  line-height: 1.2;
}

.verified-text {
  color: #10b981;
}

.stat-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--theme-text-muted);
}

/* Active mangas index section */
.admin-mangas-section {
  background-color: #090b11;
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  padding: 1.5rem;
}

.section-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.section-header-row h3 {
  font-size: 0.875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--theme-text);
}

</style>
