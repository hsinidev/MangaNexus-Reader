<?php
/**
 * admin_ads.php — Ad Inserter Control Panel (PHP Version)
 * Provides 16 independent configurable ad blocks.
 */

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\MangaNexus\Security\Csrf::validate($_POST['csrf_token'] ?? '')) {
        die('Error: Invalid CSRF Token.');
    }
}

// Handle Ad Configurations Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_ads') {
    try {
        $stmt = $pdo->prepare("UPDATE ad_blocks SET 
            name = ?, 
            code = ?, 
            is_active = ?, 
            insertion_type = ?, 
            custom_selector = ?, 
            selector_action = ?, 
            between_frequency = ?, 
            target_pages = ?, 
            target_devices = ?, 
            wrapper_class = ?, 
            wrapper_style = ? 
            WHERE block_index = ?");
            
        for ($i = 1; $i <= 16; $i++) {
            $name = isset($_POST['name'][$i]) ? trim($_POST['name'][$i]) : 'Ad Block ' . $i;
            if (empty($name)) $name = 'Ad Block ' . $i;
            
            $code = isset($_POST['code'][$i]) ? $_POST['code'][$i] : '';
            $is_active = isset($_POST['is_active'][$i]) ? 1 : 0;
            $insertion_type = isset($_POST['insertion_type'][$i]) ? $_POST['insertion_type'][$i] : 'none';
            $custom_selector = isset($_POST['custom_selector'][$i]) ? trim($_POST['custom_selector'][$i]) : '';
            $selector_action = isset($_POST['selector_action'][$i]) ? $_POST['selector_action'][$i] : 'before';
            $between_frequency = isset($_POST['between_frequency'][$i]) ? (int)$_POST['between_frequency'][$i] : 5;
            $target_pages = isset($_POST['target_pages'][$i]) ? $_POST['target_pages'][$i] : 'all';
            $target_devices = isset($_POST['target_devices'][$i]) ? $_POST['target_devices'][$i] : 'all';
            $wrapper_class = isset($_POST['wrapper_class'][$i]) ? trim($_POST['wrapper_class'][$i]) : 'ad-block-wrapper';
            if (empty($wrapper_class)) $wrapper_class = 'ad-block-wrapper';
            $wrapper_style = isset($_POST['wrapper_style'][$i]) ? trim($_POST['wrapper_style'][$i]) : 'margin: 1rem auto; text-align: center;';
            
            $stmt->execute([
                $name,
                $code,
                $is_active,
                $insertion_type,
                $custom_selector,
                $selector_action,
                $between_frequency,
                $target_pages,
                $target_devices,
                $wrapper_class,
                $wrapper_style,
                $i
            ]);
        }
        $success = 'All ad blocks updated and saved successfully.';
    } catch (PDOException $e) {
        $error = 'Failed to save ad configurations: ' . $e->getMessage();
    }
}

// Fetch all ads configuration to populate form
$ad_blocks = [];
try {
    $stmt = $pdo->query("SELECT * FROM ad_blocks ORDER BY block_index ASC");
    while ($row = $stmt->fetch()) {
        $ad_blocks[$row['block_index']] = $row;
    }
} catch (PDOException $e) {
    $error = 'Failed to retrieve ad blocks: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ads Manager - MangaNexus</title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <style>
    /* Styling Specifics for Ad Inserter Controls */
    .tabs-scroll-container {
      width: 100%;
      overflow-x: auto;
      background-color: rgba(3, 7, 18, 0.4);
      border: 1px solid var(--theme-border);
      border-radius: 0.75rem;
      padding: 0.5rem;
      margin-bottom: 1.5rem;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .ad-tabs-row {
      display: flex;
      gap: 0.5rem;
      width: max-content;
    }
    
    .ad-tab-btn {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.05);
      color: var(--theme-text-muted);
      padding: 0.6rem 1.1rem;
      border-radius: 0.5rem;
      font-size: 0.8125rem;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.25s ease;
      user-select: none;
    }
    
    .ad-tab-btn:hover {
      background: rgba(255, 255, 255, 0.06);
      color: #fff;
      border-color: rgba(255, 255, 255, 0.15);
    }
    
    .ad-tab-btn.active {
      background: linear-gradient(135deg, var(--theme-primary), var(--theme-secondary));
      color: #fff;
      border-color: transparent;
      box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }
    
    .active-dot {
      width: 6px;
      height: 6px;
      background-color: #10b981;
      border-radius: 50%;
      display: inline-block;
      box-shadow: 0 0 8px #10b981;
    }
    
    .ad-pane {
      display: none;
      animation: fadeIn 0.3s ease;
    }
    
    .ad-pane.active {
      display: block;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .form-group-row {
      display: flex;
      gap: 1.5rem;
      align-items: center;
      margin-bottom: 1.25rem;
    }
    
    .form-group-grid-2 {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.5rem;
      margin-bottom: 1.25rem;
    }
    
    .form-group-grid-3 {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.5rem;
      margin-bottom: 1.25rem;
    }
    
    @media(min-width: 768px) {
      .form-group-grid-2 {
        grid-template-columns: 1fr 1fr;
      }
      .form-group-grid-3 {
        grid-template-columns: 1fr 1fr 1fr;
      }
    }
    
    .w-50 {
      width: 50%;
    }
    
    .flex-end {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      padding-top: 1.5rem;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    
    .form-label {
      font-size: 0.8125rem;
      font-weight: 700;
      color: var(--theme-text);
    }
    
    .form-input, .form-select, .form-textarea {
      background-color: var(--theme-input-bg) !important;
      border: 1px solid var(--theme-border) !important;
      color: var(--theme-text) !important;
      border-radius: 0.5rem !important;
      padding: 0.75rem 1rem !important;
      font-size: 0.8125rem !important;
      outline: none;
      transition: all 0.25s ease;
      box-sizing: border-box;
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
      border-color: var(--theme-primary) !important;
      box-shadow: 0 0 8px rgba(139, 92, 246, 0.2);
    }

    .form-select option {
      background-color: var(--theme-card) !important;
      color: var(--theme-text) !important;
    }

    
    .form-textarea.code-area {
      font-family: 'Courier New', Courier, monospace;
      font-size: 0.85rem !important;
      line-height: 1.5;
      background-color: #03050a !important;
      resize: vertical;
    }
    
    .zinc-text {
      color: var(--theme-text-muted);
      font-size: 0.725rem;
      margin-top: 0.25rem;
      line-height: 1.4;
    }
    
    .zinc-text code {
      background-color: rgba(255, 255, 255, 0.05);
      padding: 0.1rem 0.3rem;
      border-radius: 0.25rem;
      color: var(--theme-secondary);
    }
    
    /* Toggle Switch */
    .switch-container {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      cursor: pointer;
      user-select: none;
    }
    
    .switch-container input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }
    
    .switch-slider {
      position: relative;
      display: inline-block;
      width: 2.75rem;
      height: 1.5rem;
      background-color: #1f2937;
      border-radius: 9999px;
      transition: background-color 0.25s ease;
    }
    
    .switch-slider:before {
      position: absolute;
      content: "";
      height: 1.1rem;
      width: 1.1rem;
      left: 0.2rem;
      bottom: 0.2rem;
      background-color: #fff;
      border-radius: 50%;
      transition: transform 0.25s ease;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
    }
    
    .switch-container input:checked + .switch-slider {
      background-color: var(--theme-primary);
    }
    
    .switch-container input:checked + .switch-slider:before {
      transform: translateX(1.25rem);
    }
    
    .switch-label {
      font-size: 0.8125rem;
      font-weight: 700;
      color: var(--theme-text);
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
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Dashboard
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/manga" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
        Manga CRUD
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/ads" class="nav-item active">
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
      <h2>Flexible Ad Inserter Manager</h2>
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

      <div class="crud-card">
        <div class="card-header">
          <h3>Ad Inserter Configuration Panels</h3>
        </div>
        <p class="settings-desc">
          Manage advertising block placements dynamically. Switch between 16 individual blocks below. Support for targeted device routing, page whitelist exceptions, CSS selectors, and custom anti-adblock wrappers is built in.
        </p>

        <!-- Block Selection Scroll Tabs -->
        <div class="tabs-scroll-container">
          <div class="ad-tabs-row">
            <?php for ($i = 1; $i <= 16; $i++): 
              $isActiveBlock = ($ad_blocks[$i]['is_active'] ?? 0) == 1;
            ?>
              <button type="button" class="ad-tab-btn <?php echo $i === 1 ? 'active' : ''; ?>" data-block="<?php echo $i; ?>" id="tab-btn-<?php echo $i; ?>">
                <?php if ($isActiveBlock): ?>
                  <span class="active-dot"></span>
                <?php endif; ?>
                Block <?php echo $i; ?>
              </button>
            <?php endfor; ?>
          </div>
        </div>

        <form action="" method="POST" class="crud-form">
          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
          <input type="hidden" name="action" value="save_ads">

          <!-- Blocks Container -->
          <div class="ad-panes-wrapper">

            <?php for ($i = 1; $i <= 16; $i++): 
              $block = $ad_blocks[$i] ?? [];
              $is_active = (int)($block['is_active'] ?? 0);
              $ins_type = $block['insertion_type'] ?? 'none';
            ?>
              <div class="ad-pane <?php echo $i === 1 ? 'active' : ''; ?>" id="pane-block-<?php echo $i; ?>">
                
                <!-- TOP META ROW -->
                <div class="form-group-row">
                  <div class="form-group w-50">
                    <label class="form-label">Block Label / Name</label>
                    <input type="text" name="name[<?php echo $i; ?>]" class="form-input" value="<?php echo htmlspecialchars($block['name'] ?? 'Ad Block ' . $i); ?>" placeholder="e.g. Header Banner Ad">
                  </div>
                  <div class="form-group w-50 flex-end">
                    <label class="switch-container">
                      <input type="checkbox" name="is_active[<?php echo $i; ?>]" value="1" <?php echo $is_active ? 'checked' : ''; ?> onchange="updateTabIndicator(<?php echo $i; ?>, this.checked)">
                      <span class="switch-slider"></span>
                      <span class="switch-label">Enable Block</span>
                    </label>
                  </div>
                </div>

                <!-- AD CODE BLOCK -->
                <div class="form-group" style="margin-bottom: 1.25rem;">
                  <label class="form-label">Script / Banner HTML Code</label>
                  <textarea name="code[<?php echo $i; ?>]" class="form-textarea code-area" rows="8" placeholder="<!-- Paste AdSense, Adsterra, or custom HTML/JS banner code here -->"><?php echo htmlspecialchars($block['code'] ?? ''); ?></textarea>
                </div>

                <!-- TARGET PLACEMENT RULES -->
                <div class="form-group-grid-3">
                  <div class="form-group">
                    <label class="form-label">Automatic Insertion Placement</label>
                    <select name="insertion_type[<?php echo $i; ?>]" class="form-select placement-select" data-block="<?php echo $i; ?>">
                      <option value="none" <?php echo $ins_type === 'none' ? 'selected' : ''; ?>>None (Manual / Off)</option>
                      <option value="header" <?php echo $ins_type === 'header' ? 'selected' : ''; ?>>Global Header (Top of Body)</option>
                      <option value="footer" <?php echo $ins_type === 'footer' ? 'selected' : ''; ?>>Global Footer (Bottom of Body)</option>
                      <option value="sidebar" <?php echo $ins_type === 'sidebar' ? 'selected' : ''; ?>>Global Sidebar (Manga Details Sidebar)</option>
                      <option value="home_top" <?php echo $ins_type === 'home_top' ? 'selected' : ''; ?>>Home Page Top</option>
                      <option value="home_bottom" <?php echo $ins_type === 'home_bottom' ? 'selected' : ''; ?>>Home Page Bottom</option>
                      <option value="manga_top" <?php echo $ins_type === 'manga_top' ? 'selected' : ''; ?>>Manga Info Page Top</option>
                      <option value="manga_bottom" <?php echo $ins_type === 'manga_bottom' ? 'selected' : ''; ?>>Manga Info Page Bottom (Before Chapters)</option>
                      <option value="reader_top" <?php echo $ins_type === 'reader_top' ? 'selected' : ''; ?>>Chapter Reader Top</option>
                      <option value="reader_between" <?php echo $ins_type === 'reader_between' ? 'selected' : ''; ?>>Chapter Reader Between Pages</option>
                      <option value="reader_bottom" <?php echo $ins_type === 'reader_bottom' ? 'selected' : ''; ?>>Chapter Reader Bottom</option>
                      <option value="custom_selector" <?php echo $ins_type === 'custom_selector' ? 'selected' : ''; ?>>Custom CSS Selector (JS Injection)</option>
                    </select>
                  </div>

                  <!-- Conditionally visible inputs based on placement type -->
                  <div class="form-group freq-wrapper" id="freq-wrapper-<?php echo $i; ?>" style="<?php echo $ins_type === 'reader_between' ? '' : 'display: none;'; ?>">
                    <label class="form-label">Reader Insertion Frequency</label>
                    <select name="between_frequency[<?php echo $i; ?>]" class="form-select">
                      <option value="2" <?php echo ($block['between_frequency'] ?? 5) == 2 ? 'selected' : ''; ?>>Every 2 pages (After image 2, 4, 6, 8, 10...)</option>
                      <option value="3" <?php echo ($block['between_frequency'] ?? 5) == 3 ? 'selected' : ''; ?>>Every 3 pages (After image 3, 6, 9, 12, 15...)</option>
                      <option value="4" <?php echo ($block['between_frequency'] ?? 5) == 4 ? 'selected' : ''; ?>>Every 4 pages (After image 4, 8, 12, 16, 20...)</option>
                      <option value="5" <?php echo ($block['between_frequency'] ?? 5) == 5 ? 'selected' : ''; ?>>Every 5 pages (After image 5, 10, 15, 20, 25...)</option>
                      <option value="6" <?php echo ($block['between_frequency'] ?? 5) == 6 ? 'selected' : ''; ?>>Every 6 pages (After image 6, 12, 18, 24, 30...)</option>
                      <option value="8" <?php echo ($block['between_frequency'] ?? 5) == 8 ? 'selected' : ''; ?>>Every 8 pages (After image 8, 16, 24, 32, 40...)</option>
                      <option value="10" <?php echo ($block['between_frequency'] ?? 5) == 10 ? 'selected' : ''; ?>>Every 10 pages (After image 10, 20, 30, 40, 50...)</option>
                    </select>
                  </div>


                  <div class="form-group selector-wrapper" id="selector-wrapper-<?php echo $i; ?>" style="<?php echo $ins_type === 'custom_selector' ? '' : 'display: none;'; ?>">
                    <label class="form-label">CSS Element Selector</label>
                    <input type="text" name="custom_selector[<?php echo $i; ?>]" class="form-input" placeholder="e.g. .manga-profile-grid or #image-strip" value="<?php echo htmlspecialchars($block['custom_selector'] ?? ''); ?>">
                  </div>

                  <div class="form-group action-wrapper" id="action-wrapper-<?php echo $i; ?>" style="<?php echo $ins_type === 'custom_selector' ? '' : 'display: none;'; ?>">
                    <label class="form-label">Selector Insertion Action</label>
                    <select name="selector_action[<?php echo $i; ?>]" class="form-select">
                      <option value="before" <?php echo ($block['selector_action'] ?? '') === 'before' ? 'selected' : ''; ?>>Insert BEFORE target element</option>
                      <option value="after" <?php echo ($block['selector_action'] ?? '') === 'after' ? 'selected' : ''; ?>>Insert AFTER target element</option>
                      <option value="prepend" <?php echo ($block['selector_action'] ?? '') === 'prepend' ? 'selected' : ''; ?>>Prepend INSIDE target element</option>
                      <option value="append" <?php echo ($block['selector_action'] ?? '') === 'append' ? 'selected' : ''; ?>>Append INSIDE target element</option>
                    </select>
                  </div>
                </div>

                <!-- FILTER TARGETS -->
                <div class="form-group-grid-2">
                  <div class="form-group">
                    <label class="form-label">Target Page Types</label>
                    <select name="target_pages[<?php echo $i; ?>]" class="form-select">
                      <option value="all" <?php echo ($block['target_pages'] ?? '') === 'all' ? 'selected' : ''; ?>>All Pages</option>
                      <option value="home" <?php echo ($block['target_pages'] ?? '') === 'home' ? 'selected' : ''; ?>>Homepage Only</option>
                      <option value="manga" <?php echo ($block['target_pages'] ?? '') === 'manga' ? 'selected' : ''; ?>>Manga Details Page Only</option>
                      <option value="reader" <?php echo ($block['target_pages'] ?? '') === 'reader' ? 'selected' : ''; ?>>Chapter Reader Page Only</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Target Device Types</label>
                    <select name="target_devices[<?php echo $i; ?>]" class="form-select">
                      <option value="all" <?php echo ($block['target_devices'] ?? '') === 'all' ? 'selected' : ''; ?>>All Devices (Responsive)</option>
                      <option value="desktop" <?php echo ($block['target_devices'] ?? '') === 'desktop' ? 'selected' : ''; ?>>Desktop Views Only</option>
                      <option value="mobile" <?php echo ($block['target_devices'] ?? '') === 'mobile' ? 'selected' : ''; ?>>Mobile Views Only</option>
                    </select>
                  </div>
                </div>

                <!-- ANTIBLOCK WRAPPERS -->
                <div class="form-group-grid-2">
                  <div class="form-group">
                    <label class="form-label">Wrapper CSS Class Name</label>
                    <input type="text" name="wrapper_class[<?php echo $i; ?>]" class="form-input" placeholder="ad-block-wrapper" value="<?php echo htmlspecialchars($block['wrapper_class'] ?? 'ad-block-wrapper'); ?>">
                    <span class="zinc-text">Use arbitrary classes to avoid filter lists (e.g. <code>content-box-promo</code> instead of <code>ads-container</code>).</span>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Wrapper Container Style (CSS)</label>
                    <input type="text" name="wrapper_style[<?php echo $i; ?>]" class="form-input" placeholder="margin: 1rem auto; text-align: center;" value="<?php echo htmlspecialchars($block['wrapper_style'] ?? 'margin: 1rem auto; text-align: center;'); ?>">
                  </div>
                </div>

              </div>
            <?php endfor; ?>

          </div>

          <button type="submit" class="btn btn-primary" style="margin-top: 2rem; width: 100%; justify-content: center; padding: 0.95rem;">Save All Ad Blocks</button>
        </form>
      </div>

    </div>

    <!-- Dev Footer -->
    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

  <script>
    // Tab switching controller
    document.querySelectorAll('.ad-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.ad-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.ad-pane').forEach(p => p.classList.remove('active'));
        
        btn.classList.add('active');
        const blockId = btn.getAttribute('data-block');
        document.getElementById('pane-block-' + blockId).classList.add('active');
      });
    });

    // Toggle active state dots in tabs dynamically
    function updateTabIndicator(index, isChecked) {
      const tabBtn = document.getElementById('tab-btn-' + index);
      let dot = tabBtn.querySelector('.active-dot');
      if (isChecked) {
        if (!dot) {
          dot = document.createElement('span');
          dot.className = 'active-dot';
          tabBtn.insertBefore(dot, tabBtn.firstChild);
        }
      } else {
        if (dot) {
          dot.remove();
        }
      }
    }

    // Dropdown placements change controller
    document.querySelectorAll('.placement-select').forEach(select => {
      select.addEventListener('change', (e) => {
        const val = e.target.value;
        const blockIndex = e.target.getAttribute('data-block');
        
        const freqWrapper = document.getElementById('freq-wrapper-' + blockIndex);
        const selectorWrapper = document.getElementById('selector-wrapper-' + blockIndex);
        const actionWrapper = document.getElementById('action-wrapper-' + blockIndex);
        
        if (val === 'reader_between') {
          freqWrapper.style.display = 'flex';
        } else {
          freqWrapper.style.display = 'none';
        }
        
        if (val === 'custom_selector') {
          selectorWrapper.style.display = 'flex';
          actionWrapper.style.display = 'flex';
        } else {
          selectorWrapper.style.display = 'none';
          actionWrapper.style.display = 'none';
        }
      });
    });
  </script>

</body>
</html>
