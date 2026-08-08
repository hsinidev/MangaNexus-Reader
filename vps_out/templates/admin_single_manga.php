<?php
/**
 * admin_single_manga.php — Micro-Niche Single Manga Studio: customize color, heroes, and fonts for a specific series.
 */

$error   = '';
$success = '';

// Fetch all mangas for the dropdown
$mangas = db_fetch_all("SELECT * FROM mangas ORDER BY title ASC");

// Retrieve global site settings
$settings = get_settings();
$primary_id = !empty($settings['primary_manga_id']) ? $settings['primary_manga_id'] : '';

// Handle forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\MangaNexus\Security\Csrf::validate($_POST['csrf_token'] ?? '')) {
        die('Error: Invalid CSRF Token.');
    }
    $action = $_POST['action'] ?? '';

    /* ── 1. Update Global Primary Manga ──────────────────────── */
    if ($action === 'save_primary_manga') {
        $new_primary_id = trim($_POST['primary_manga_id']) ?: null;
        try {
            db_query("UPDATE site_settings SET primary_manga_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 'global'", [$new_primary_id]);
            $primary_id = $new_primary_id;
            $settings = get_settings(); // Refresh local cache
            $success = 'Spotlight single-series primary manga updated successfully.';
            try {
                generate_seo_assets();
            } catch (Exception $seo_ex) {
                \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on primary spotlight change: " . $seo_ex->getMessage());
            }
        } catch (PDOException $e) {
            $error = 'Failed to update spotlight manga: ' . $e->getMessage();
        }
    }

    /* ── 2. Save Custom Styling for Selected Manga ─────────────── */
    if ($action === 'save_manga_style') {
        $target_manga_id = trim($_POST['target_manga_id']);
        $custom_accent_color = trim($_POST['custom_accent_color']) ?: null;
        $custom_secondary_color = trim($_POST['custom_secondary_color']) ?: null;

        $custom_hero_style = [
            'title_color'     => $_POST['title_color']     ?? '#ffffff',
            'glow_color'      => $_POST['glow_color']      ?? '#facc15',
            'glow_intensity'  => $_POST['glow_intensity']  ?? 'high',
            'font_size'       => $_POST['font_size']        ?? '12',
            'letter_spacing'  => $_POST['letter_spacing']  ?? '-0.04',
            'text_transform'  => $_POST['text_transform']  ?? 'uppercase',
            'animation_style' => $_POST['animation_style'] ?? 'flicker',
            'bg_blur'         => (string)max(0, min(20, floatval($_POST['bg_blur'] ?? 0))),
            'hero_lighting'   => $_POST['hero_lighting']   ?? 'dark',
        ];

        // Fetch target manga to get original cover/hero image
        $target_manga = db_fetch("SELECT * FROM mangas WHERE id = ?", [$target_manga_id]);
        
        if ($target_manga) {
            $hero_bg_url = $target_manga['hero_bg_url'];

            // Handle Custom Hero Background Cover Upload
            if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
                $tmp      = $_FILES['hero_image']['tmp_name'];
                $orig_ext = strtolower(pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION));
                $allowed  = ['jpg','jpeg','png','gif','webp','avif'];

                if (in_array($orig_ext, $allowed)) {
                    // Delete old hero bg if exists
                    if (!empty($hero_bg_url)) {
                        $old_file = BASE_PATH . $hero_bg_url;
                        if (file_exists($old_file) && str_contains($hero_bg_url, '/uploads/manga_hero_')) {
                            @unlink($old_file);
                        }
                    }

                    $webp_name = 'manga_hero_' . $target_manga_id . '_' . time() . '.webp';
                    $dest_path = UPLOAD_DIR . '/' . $webp_name;

                    if (optimize_image($tmp, $dest_path, 60, 1200)) {
                        $hero_bg_url = '/uploads/' . $webp_name;
                    }
                }
            }

            try {
                db_query(
                    "UPDATE mangas SET 
                        custom_accent_color = ?, 
                        custom_secondary_color = ?, 
                        custom_hero_style = ?, 
                        hero_bg_url = ?, 
                        updated_at = CURRENT_TIMESTAMP 
                     WHERE id = ?",
                    [
                        $custom_accent_color, 
                        $custom_secondary_color, 
                        json_encode($custom_hero_style), 
                        $hero_bg_url,
                        $target_manga_id
                    ]
                );
                $success = 'Micro-Niche Studio customization saved successfully for: ' . htmlspecialchars($target_manga['title']);
                try {
                    generate_seo_assets();
                } catch (Exception $seo_ex) {
                    \MangaNexus\Logging\Logger::error("Failed to automatically regenerate SEO assets on manga styling custom save: " . $seo_ex->getMessage());
                }
            } catch (PDOException $e) {
                $error = 'Failed to save styling custom configurations: ' . $e->getMessage();
            }
        } else {
            $error = 'Target manga series not found.';
        }
    }
}

// Fetch currently selected primary manga styling data
$focused_manga = null;
if (!empty($primary_id)) {
    $focused_manga = db_fetch("SELECT * FROM mangas WHERE id = ?", [$primary_id]);
}
if (!$focused_manga && !empty($mangas)) {
    $focused_manga = $mangas[0]; // Fallback to first
}

// Set up visual variables
$hero_style = [];
if ($focused_manga && !empty($focused_manga['custom_hero_style'])) {
    $hero_style = json_decode($focused_manga['custom_hero_style'], true) ?: [];
}

// Default settings values
$hs_defaults = [
    'title_color'      => '#ffffff',
    'glow_color'       => '#a855f7',
    'glow_intensity'   => 'high',
    'font_size'        => '12',
    'letter_spacing'   => '-0.04',
    'text_transform'   => 'uppercase',
    'animation_style'  => 'flicker',
    'bg_blur'          => '0',
    'hero_lighting'    => 'dark',
];
foreach ($hs_defaults as $k => $v) {
    if (!isset($hero_style[$k])) {
        $hero_style[$k] = $v;
    }
}

$active_accent = $focused_manga['custom_accent_color'] ?? '#8b5cf6';
$active_secondary = $focused_manga['custom_secondary_color'] ?? '#06b6d4';
$active_hero_bg = $focused_manga['hero_bg_url'] ?? $focused_manga['cover_url'] ?? '/images/hero_midnight.webp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Micro-Niche Single Studio - MangaNexus</title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
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
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/settings" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Site Settings
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/visitors" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Registered Visitors
      </a>
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/single-manga" class="nav-item active">
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
      <h2>🚀 Micro-Niche Single Manga Studio</h2>
      <div class="user-badge">
        <span>Operating mode: <strong style="color:var(--theme-primary);"><?php echo htmlspecialchars(strtoupper($settings['website_mode'])); ?></strong></span>
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

      <?php if ($settings['website_mode'] !== 'single'): ?>
        <div class="warning-banner" style="background-color: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.2); color: #eab308; margin-bottom: 2rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.5rem;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span><strong>Notice:</strong> Your website mode is currently set to <strong>"General Portal"</strong>. Micro-niche visual branding overrides will apply only when you set Website Operating Mode to <strong>"Single-Series Mode"</strong> in <a href="/<?php echo htmlspecialchars($admin_slug); ?>/settings" style="color:#eab308; text-decoration:underline; font-weight:700;">Global Settings</a>.</span>
        </div>
      <?php endif; ?>

      <!-- ══════════════════════════════════════════════════
           SECTION 1 — Select Spotlight Primary Manga
      ══════════════════════════════════════════════════ -->
      <div class="crud-card">
        <div class="card-header">
          <h3>Spotlight Series Choice</h3>
        </div>
        <p class="settings-desc">Choose which primary manga series focus acts as the dynamic landing homepage of your website.</p>
        
        <form method="POST" class="crud-form">
          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
          <input type="hidden" name="action" value="save_primary_manga">
          <div class="form-group" style="max-width: 500px; display: flex; gap: 1rem; align-items: center;">
            <select name="primary_manga_id" class="form-select" style="flex: 1; height: 2.75rem;" required>
              <option value="" disabled>-- Select Manga --</option>
              <?php foreach ($mangas as $m): ?>
                <option value="<?php echo $m['id']; ?>" <?php echo $primary_id === $m['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($m['title']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="height: 2.75rem;">Set Primary Manga</button>
          </div>
        </form>
      </div>

      <!-- ══════════════════════════════════════════════════
           SECTION 2 — Custom Theme overrides for this manga
      ══════════════════════════════════════════════════ -->
      <?php if ($focused_manga): ?>
        <div class="crud-card">
          <div class="card-header">
            <h3>Visual Style Customizer — <?php echo htmlspecialchars($focused_manga['title']); ?></h3>
          </div>
          <p class="settings-desc">Fine-tune dynamic accent colors, backgrounds, and hero neon styles strictly for this manga series homepage landing layout.</p>

          <!-- Live Studio Preview -->
          <div class="ts-title-preview" id="ts-preview-box" style="margin-bottom: 2rem;">
            <div class="ts-preview-label">LIVE PREVIEW</div>
            <div class="ts-preview-title" id="ts-preview-title">
              <span id="pv-main"><?php echo htmlspecialchars($focused_manga['title']); ?></span>
            </div>
            <div class="ts-preview-line" id="pv-line"></div>
          </div>

          <form method="POST" enctype="multipart/form-data" class="crud-form ts-style-form" id="ts-style-form">
            <?php echo \MangaNexus\Security\Csrf::getField(); ?>
            <input type="hidden" name="action" value="save_manga_style">
            <input type="hidden" name="target_manga_id" value="<?php echo $focused_manga['id']; ?>">

            <div class="form-grid">
              
              <!-- Core UI Branding Colors -->
              <div class="form-group col-12">
                <div class="sub-section-title">Branding Color System</div>
              </div>

              <!-- Accent Color -->
              <div class="form-group col-6">
                <label class="form-label">Primary UI Accent Color (Hex Picker)</label>
                <div class="ts-color-row">
                  <input type="color" name="custom_accent_color" id="custom_accent_color"
                         value="<?php echo htmlspecialchars($active_accent); ?>"
                         class="ts-color-picker" oninput="updatePreview()">
                  <input type="text" class="form-input ts-color-hex" id="custom_accent_color_hex"
                         value="<?php echo htmlspecialchars($active_accent); ?>"
                         oninput="document.getElementById('custom_accent_color').value=this.value; updatePreview()">
                </div>
              </div>

              <!-- Secondary Color -->
              <div class="form-group col-6">
                <label class="form-label">Secondary Highlights Color (Hex Picker)</label>
                <div class="ts-color-row">
                  <input type="color" name="custom_secondary_color" id="custom_secondary_color"
                         value="<?php echo htmlspecialchars($active_secondary); ?>"
                         class="ts-color-picker" oninput="updatePreview()">
                  <input type="text" class="form-input ts-color-hex" id="custom_secondary_color_hex"
                         value="<?php echo htmlspecialchars($active_secondary); ?>"
                         oninput="document.getElementById('custom_secondary_color').value=this.value; updatePreview()">
                </div>
              </div>

              <!-- Hero Branding overrides -->
              <div class="form-group col-12" style="margin-top: 1.5rem;">
                <div class="sub-section-title">Giant Hero Title Customizer</div>
              </div>

              <!-- Title Color -->
              <div class="form-group col-3">
                <label class="form-label">Title Text Color</label>
                <div class="ts-color-row">
                  <input type="color" name="title_color" id="title_color"
                         value="<?php echo htmlspecialchars($hero_style['title_color']); ?>"
                         class="ts-color-picker" oninput="updatePreview()">
                  <input type="text" class="form-input ts-color-hex" id="title_color_hex"
                         value="<?php echo htmlspecialchars($hero_style['title_color']); ?>"
                         oninput="document.getElementById('title_color').value=this.value; updatePreview()">
                </div>
              </div>

              <!-- Glow Color -->
              <div class="form-group col-3">
                <label class="form-label">Glow / Neon Light Color</label>
                <div class="ts-color-row">
                  <input type="color" name="glow_color" id="glow_color"
                         value="<?php echo htmlspecialchars($hero_style['glow_color']); ?>"
                         class="ts-color-picker" oninput="updatePreview()">
                  <input type="text" class="form-input ts-color-hex" id="glow_color_hex"
                         value="<?php echo htmlspecialchars($hero_style['glow_color']); ?>"
                         oninput="document.getElementById('glow_color').value=this.value; updatePreview()">
                </div>
              </div>

              <!-- Glow Intensity -->
              <div class="form-group col-3">
                <label class="form-label">Glow Intensity</label>
                <select name="glow_intensity" id="glow_intensity" class="form-select" onchange="updatePreview()">
                  <?php foreach (['none'=>'None','low'=>'Low','medium'=>'Medium','high'=>'High (default)','ultra'=>'Ultra Neon'] as $k=>$v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $hero_style['glow_intensity']===$k?'selected':''; ?>><?php echo $v; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Animation Style -->
              <div class="form-group col-3">
                <label class="form-label">Animation Style</label>
                <select name="animation_style" id="animation_style" class="form-select" onchange="updatePreview()">
                  <?php foreach (['none'=>'None (static)','flicker'=>'Flicker (default)','pulse'=>'Pulse / Breathe','wave'=>'Wave Sweep'] as $k=>$v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $hero_style['animation_style']===$k?'selected':''; ?>><?php echo $v; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Font Size -->
              <div class="form-group col-4">
                <label class="form-label">Font Size (vw) — desktop</label>
                <div class="ts-range-row">
                  <input type="range" name="font_size" id="font_size" min="4" max="18" step="0.5"
                         value="<?php echo htmlspecialchars($hero_style['font_size']); ?>"
                         oninput="document.getElementById('fs_val').textContent=this.value+'vw'; updatePreview()">
                  <span id="fs_val" class="ts-range-val"><?php echo $hero_style['font_size']; ?>vw</span>
                </div>
              </div>

              <!-- Letter Spacing -->
              <div class="form-group col-4">
                <label class="form-label">Letter Spacing (em)</label>
                <div class="ts-range-row">
                  <input type="range" name="letter_spacing" id="letter_spacing" min="-0.1" max="0.2" step="0.005"
                         value="<?php echo htmlspecialchars($hero_style['letter_spacing']); ?>"
                         oninput="document.getElementById('ls_val').textContent=this.value+'em'; updatePreview()">
                  <span id="ls_val" class="ts-range-val"><?php echo $hero_style['letter_spacing']; ?>em</span>
                </div>
              </div>

              <!-- Text Transform -->
              <div class="form-group col-4">
                <label class="form-label">Text Transform</label>
                <select name="text_transform" id="text_transform" class="form-select" onchange="updatePreview()">
                  <?php foreach (['uppercase'=>'UPPERCASE','capitalize'=>'Capitalize','none'=>'None (as-is)'] as $k=>$v): ?>
                    <option value="<?php echo $k; ?>" <?php echo $hero_style['text_transform']===$k?'selected':''; ?>><?php echo $v; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Hero Background cover section -->
              <div class="form-group col-12" style="margin-top: 1.5rem;">
                <div class="sub-section-title">Cinema Hero Artwork & Blur</div>
              </div>

              <!-- File upload -->
              <div class="form-group col-4">
                <label for="hero_image" class="form-label">Custom Background Image</label>
                <input type="file" name="hero_image" id="hero_image" class="form-input" accept="image/*" onchange="previewHeroBgFile(this)">
                <span class="zinc-text" style="margin-top: 0.35rem; display: block; font-size: 0.65rem;">Upload custom panoramic artwork (best size: 1920 × 1080px). Defaults to cover art if empty.</span>
              </div>

              <!-- Hero Lighting -->
              <div class="form-group col-4">
                <label class="form-label">Hero Lighting Atmosphere</label>
                <select name="hero_lighting" id="hero_lighting" class="form-select" onchange="updatePreview()">
                  <option value="dark" <?php echo ($hero_style['hero_lighting'] ?? 'dark') === 'dark' ? 'selected' : ''; ?>>Moody Dark (Dramatic focus, high text contrast)</option>
                  <option value="clear" <?php echo ($hero_style['hero_lighting'] ?? 'dark') === 'clear' ? 'selected' : ''; ?>>Clear / Vibrant (Bright image, light overlays)</option>
                </select>
                <span class="zinc-text" style="margin-top: 0.35rem; display: block; font-size: 0.65rem;">Choose "Clear" to let the original background artwork colors shine through brightly.</span>
              </div>

              <!-- Image Thumbnail Preview -->
              <div class="form-group col-4" style="display: flex; flex-direction: column;">
                <label class="form-label">Active Background Artwork Preview</label>
                <div class="active-hero-preview-box" style="border: 2px solid var(--theme-border); border-radius: 12px; height: 50px; overflow: hidden; position: relative;">
                  <img id="active-hero-preview-img" src="<?php echo htmlspecialchars(cache_bust($active_hero_bg)); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
              </div>

              <!-- Background Blur -->
              <div class="form-group col-12" style="margin-top: 0.5rem;">
                <label class="form-label ts-blur-label">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                  Hero Background Image Blur
                </label>
                <div class="ts-blur-track">
                  <span class="ts-blur-icon ts-blur-crisp" title="Crisp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
                  </span>
                  <input type="range" name="bg_blur" id="bg_blur"
                         min="0" max="20" step="0.5"
                         value="<?php echo htmlspecialchars($hero_style['bg_blur']); ?>"
                         class="ts-blur-slider"
                         oninput="document.getElementById('blur_val').textContent=this.value+'px'; updatePreview()">
                  <span class="ts-blur-icon ts-blur-blurry" title="Blurry">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="3" stroke-dasharray="2 2"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10" stroke-dasharray="3 3"/><path d="M22 12c0-5.52-4.48-10-10-10" stroke-dasharray="3 3"/></svg>
                  </span>
                  <span id="blur_val" class="ts-range-val ts-blur-val"><?php echo $hero_style['bg_blur']; ?>px</span>
                </div>
                <div class="ts-blur-labels">
                  <span>0px — Crystal clear</span>
                  <span>5px</span>
                  <span>10px — Soft focus</span>
                  <span>15px</span>
                  <span>20px — Heavily blurred</span>
                </div>
              </div>

            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Save Micro-Niche Branding overrides</button>
          </form>
        </div>
      <?php endif; ?>

    </div>
    
    <!-- Non-Removable Credits Footer -->
    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

  <!-- Live customizer script scripts -->
  <script>
    function updatePreview() {
      const tc = document.getElementById('title_color').value;
      const gc = document.getElementById('glow_color').value;
      const gi = document.getElementById('glow_intensity').value;
      const fs = document.getElementById('font_size').value;
      const ls = document.getElementById('letter_spacing').value;
      const tt = document.getElementById('text_transform').value;
      const ani = document.getElementById('animation_style').value;
      const bl = parseFloat(document.getElementById('bg_blur').value) || 0;

      // Sync hex inputs
      document.getElementById('title_color_hex').value = tc;
      document.getElementById('glow_color_hex').value = gc;
      
      const primaryColorHex = document.getElementById('custom_accent_color').value;
      document.getElementById('custom_accent_color_hex').value = primaryColorHex;
      
      const secondaryColorHex = document.getElementById('custom_secondary_color').value;
      document.getElementById('custom_secondary_color_hex').value = secondaryColorHex;

      const title = document.getElementById('ts-preview-title');
      const line = document.getElementById('pv-line');
      const preview = document.getElementById('ts-preview-box');

      title.style.fontSize = 'clamp(1.5rem,' + fs + 'vw,4.5rem)';
      title.style.letterSpacing = ls + 'em';
      title.style.textTransform = tt;

      title.style.color = tc;

      const radii = { none:'', low:'0 0 20px', medium:'0 0 40px', high:'0 0 70px', ultra:'0 0 120px' };
      let glow = '';
      if (gi !== 'none') {
        glow = `${radii[gi]} ${gc}, ${radii[gi]} ${gc}, 0 4px 30px rgba(0,0,0,.6)`;
      }
      title.style.textShadow = glow;
      line.style.background = `linear-gradient(90deg, ${primaryColorHex}, rgba(0,0,0,0))`;
      line.style.boxShadow = gi !== 'none' ? `0 0 16px ${primaryColorHex}` : 'none';

      title.style.animation = ani === 'flicker' ? 'pv-flicker 4s ease infinite'
                           : ani === 'pulse'   ? 'pv-pulse 2.5s ease infinite'
                           : ani === 'wave'    ? 'pv-wave 3s ease infinite'
                           : 'none';

      preview.style.setProperty('--pv-blur', bl + 'px');
      preview.style.setProperty('--pv-accent', primaryColorHex);
    }

    function previewHeroBgFile(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('active-hero-preview-img').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
      }
    }

    // Init on load
    document.addEventListener('DOMContentLoaded', updatePreview);
  </script>

  <!-- Embed specific visual styles locally for live customizer page -->
  <style>
  .settings-desc {
    font-size: 0.75rem;
    color: var(--theme-text-muted);
    line-height: 1.5;
    margin-bottom: 1.5rem;
  }
  .sub-section-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--theme-text);
    border-left: 3px solid var(--theme-primary);
    padding-left: 0.75rem;
    margin-top: 1rem;
    margin-bottom: 0.75rem;
    letter-spacing: -0.01em;
  }
  .ts-title-preview {
    border-radius: 12px; padding: 2.5rem 2rem;
    text-align: center; position: relative;
    border: 1px solid var(--theme-border);
    overflow: hidden;
    isolation: isolate;
    --pv-blur: 0px;
    --pv-accent: var(--theme-primary);
  }
  .ts-title-preview::before {
    content: '';
    position: absolute; inset: 0; z-index: -1;
    background: linear-gradient(135deg, #090b11 0%, #11131e 100%);
    filter: blur(var(--pv-blur, 0px));
    transform: scale(1.05);
    transition: filter .25s ease;
  }
  .ts-preview-label {
    position: absolute; top: .7rem; left: 1rem;
    font-size: .58rem; letter-spacing: .14em; color: rgba(255,255,255,.25); font-weight: 700;
  }
  .ts-preview-title {
    font-family: 'Outfit', 'Inter', system-ui, sans-serif;
    font-size: clamp(1.8rem, 8vw, 4.5rem);
    font-weight: 900; line-height: .9;
    letter-spacing: -.04em; text-transform: uppercase;
    margin: 0; transition: all .3s;
  }
  .ts-preview-line {
    width: 40%; height: 3px; margin: .8rem auto 0;
    background: linear-gradient(90deg, var(--pv-accent), rgba(250,204,21,.15));
    border-radius: 2px; transition: all .3s;
  }
  .ts-color-row { display: flex; align-items: center; gap: .6rem; }
  .ts-color-picker {
    width: 44px; height: 44px; border-radius: 8px; cursor: pointer;
    border: 2px solid rgba(255,255,255,.15); background: none; padding: 2px;
  }
  .ts-color-hex { flex: 1; font-family: monospace; font-size: .82rem; }
  .ts-range-row { display: flex; align-items: center; gap: .8rem; }
  .ts-range-row input[type="range"] { flex: 1; accent-color: var(--theme-primary); }
  .ts-range-val { font-size: .75rem; font-weight: 700; color: var(--theme-primary); min-width: 56px; text-align: right; }
  .ts-blur-label {
    display: inline-flex; align-items: center; gap: .5rem;
    font-size: .78rem; margin-bottom: .6rem;
  }
  .ts-blur-track {
    display: flex; align-items: center; gap: .9rem;
  }
  .ts-blur-icon { color: rgba(255,255,255,.35); flex-shrink: 0; display: flex; }
  .ts-blur-slider {
    flex: 1;
    -webkit-appearance: none; appearance: none;
    height: 6px; border-radius: 9999px;
    background: linear-gradient(90deg,
      rgba(139,92,246,.7) calc(var(--slider-pct,0) * 1%),
      rgba(255,255,255,.12) calc(var(--slider-pct,0) * 1%));
    outline: none; cursor: pointer;
    accent-color: var(--theme-primary);
  }
  .ts-blur-slider::-webkit-slider-thumb {
    -webkit-appearance: none; appearance: none;
    width: 20px; height: 20px; border-radius: 50%;
    background: radial-gradient(circle at 35% 35%, #c4b5fd, #7c3aed);
    border: 2px solid rgba(255,255,255,.3);
    box-shadow: 0 0 10px rgba(139,92,246,.6);
    cursor: grab;
    transition: box-shadow .15s;
  }
  .ts-blur-slider::-webkit-slider-thumb:active { cursor: grabbing; box-shadow: 0 0 18px rgba(139,92,246,.9); }
  .ts-blur-val { min-width: 52px; }
  .ts-blur-labels {
    display: flex; justify-content: space-between;
    font-size: .6rem; color: rgba(255,255,255,.25);
    margin-top: .4rem; padding: 0 .2rem;
  }
  @keyframes pv-flicker {
    0%,100% { opacity:1; } 44% { opacity:.7; } 46% { opacity:1; }
  }
  @keyframes pv-pulse {
    0%,100% { opacity:1; } 50% { opacity:.55; }
  }
  @keyframes pv-wave {
    0%   { text-shadow: inherit; }
    50%  { letter-spacing: .02em; }
    100% { text-shadow: inherit; }
  }
  </style>
</body>
</html>
