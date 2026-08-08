<?php
/**
 * admin_theme.php — Theme Studio: theme selection, hero image upload, title style
 */

$error   = '';
$success = '';

// ── Theme definitions ────────────────────────────────────────────────────────
$themes = [
    'midnight-dark'         => ['label' => 'Midnight Dark (Default Pro)',      'hero_file' => 'hero_midnight.webp',   'accent' => '#8b5cf6'],
    'madara'                => ['label' => 'Madara Red Style',                  'hero_file' => 'hero_madara.webp',     'accent' => '#ff3a3a'],
    'otaku-crimson'         => ['label' => 'Otaku Crimson (Gothic Noir)',       'hero_file' => 'hero_crimson.webp',    'accent' => '#e11d48'],
    'minimalist-scanlation' => ['label' => 'Minimalist Scanlation (Grid)',      'hero_file' => 'hero_minimalist.webp', 'accent' => '#3b82f6'],
    'manga-classic'         => ['label' => 'Manga Classic (Traditional Ink)',   'hero_file' => 'hero_classic.webp',    'accent' => '#92400e'],
    'cyberpunk-district'    => ['label' => 'Cyberpunk District',                'hero_file' => 'hero_cyberpunk.webp',  'accent' => '#06b6d4'],
    'shonen-punch'          => ['label' => 'Otaku Shōnen Punch',                'hero_file' => 'hero_shonen.webp',     'accent' => '#ef4444'],
    'amethyst-fantasy'      => ['label' => 'Amethyst Fantasy',                  'hero_file' => 'hero_amethyst.webp',   'accent' => '#a855f7'],
    'solarized-novel'       => ['label' => 'Solarized Novel',                   'hero_file' => 'hero_solarized.webp',  'accent' => '#0d9488'],
    'e-reader-mono'         => ['label' => 'E-Reader Mono',                     'hero_file' => 'hero_e_reader.webp',   'accent' => '#64748b'],
    'deep-ocean'            => ['label' => 'Deep Ocean Abyssal',                'hero_file' => 'hero_deep_ocean.webp', 'accent' => '#0891b2'],
    'light-scarlet'         => ['label' => 'Scarlet Red Light',                 'hero_file' => 'hero_light_scarlet.webp','accent' => '#ef4444'],
    'light-emerald'         => ['label' => 'Emerald Green Light',               'hero_file' => 'hero_light_emerald.webp','accent' => '#10b981'],
    'light-amber'           => ['label' => 'Amber Gold Light',                  'hero_file' => 'hero_light_amber.webp','accent' => '#f59e0b'],
    'light-sapphire'        => ['label' => 'Sapphire Blue Light',               'hero_file' => 'hero_light_art.webp',  'accent' => '#3b82f6'],
    'light-orange'          => ['label' => 'Tokyo Orange Light',                'hero_file' => 'hero_light_orange.webp','accent' => '#f97316'],
    'light-teal'            => ['label' => 'Clinical Teal Light',               'hero_file' => 'hero_light_teal.webp',  'accent' => '#14b8a6'],
    'light-sakura'          => ['label' => 'Sakura Pink Light',                 'hero_file' => 'hero_light_sakura.webp','accent' => '#ec4899'],
    'light-lime'            => ['label' => 'Volt Lime Light',                   'hero_file' => 'hero_light_lime.webp',  'accent' => '#84cc16'],
    'light-lavender'        => ['label' => 'Lavender Fields Light',             'hero_file' => 'hero_light_lavender.webp','accent' => '#8b5cf6'],
    'light-cyan'            => ['label' => 'Vivid Cyan Light',                  'hero_file' => 'hero_light_cyan.webp',  'accent' => '#06b6d4'],
];

// Load stored settings
$hero_images = json_decode($settings['theme_hero_images'] ?? '{}', true) ?: [];
$hero_style  = json_decode($settings['hero_style']        ?? '{}', true) ?: [];

// Default hero style values
$hs_defaults = [
    'title_color'      => '#ffffff',
    'glow_color'       => '#facc15',
    'glow_intensity'   => 'high',
    'font_size'        => '12',        // vw number only
    'letter_spacing'   => '-0.04',     // em
    'text_transform'   => 'uppercase',
    'animation_style'  => 'flicker',
    'bg_blur'          => '0',         // px — 0 = crisp, 20 = very blurry
    'hero_lighting'    => 'dark',
];
foreach ($hs_defaults as $k => $v) {
    if (!isset($hero_style[$k])) $hero_style[$k] = $v;
}

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\MangaNexus\Security\Csrf::validate($_POST['csrf_token'] ?? '')) {
        die('Error: Invalid CSRF Token.');
    }
    $action = $_POST['action'] ?? '';

    /* ── 1. Save active theme ─────────────────────────────── */
    if ($action === 'save_theme') {
        $new_theme = $_POST['current_theme'] ?? $settings['current_theme'];
        if (!array_key_exists($new_theme, $themes)) {
            $error = 'Invalid theme selected.';
        } else {
            db_query("UPDATE site_settings SET current_theme = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 'global'", [$new_theme]);
            $settings['current_theme'] = $new_theme;
            $theme = $new_theme;
            $success = 'Active theme updated to: ' . $themes[$new_theme]['label'];
        }
    }

    /* ── 2. Upload hero image for a theme ─────────────────── */
    if ($action === 'upload_hero') {
        $target_theme = $_POST['target_theme'] ?? '';
        if (!array_key_exists($target_theme, $themes)) {
            $error = 'Invalid theme for hero upload.';
        } elseif (!isset($_FILES['hero_image']) || $_FILES['hero_image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'No file uploaded or upload error.';
        } else {
            $tmp      = $_FILES['hero_image']['tmp_name'];
            $orig_ext = strtolower(pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','gif','webp','avif'];

            if (!in_array($orig_ext, $allowed)) {
                $error = 'Only image files allowed (jpg, png, gif, webp, avif).';
            } else {
                // Convert/save as WebP for best performance if GD supports it
                $webp_name = 'hero_' . $target_theme . '_' . time() . '.webp';
                $dest_path = BASE_PATH . '/images/' . $webp_name;

                $converted = false;
                if (function_exists('imagecreatefromstring')) {
                    $img_data = file_get_contents($tmp);
                    $src_img  = @imagecreatefromstring($img_data);
                    if ($src_img) {
                        // Resize to max 1920px wide for optimal hero quality
                        $ow = imagesx($src_img);
                        $oh = imagesy($src_img);
                        $max_w = 1920;
                        if ($ow > $max_w) {
                            $nw = $max_w;
                            $nh = (int)round($oh * $max_w / $ow);
                            $dst_img = imagecreatetruecolor($nw, $nh);
                            imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh,$ow,$oh);
                            imagedestroy($src_img);
                            $src_img = $dst_img;
                        }
                        if (imagewebp($src_img, $dest_path, 88)) {
                            $converted = true;
                        }
                        imagedestroy($src_img);
                    }
                }

                // Fallback: copy original if WebP conversion failed
                if (!$converted) {
                    $webp_name = 'hero_' . $target_theme . '_' . time() . '.' . $orig_ext;
                    $dest_path = BASE_PATH . '/images/' . $webp_name;
                    move_uploaded_file($tmp, $dest_path);
                }

                // Save path in theme_hero_images JSON
                $hero_images[$target_theme] = '/images/' . $webp_name;
                db_query(
                    "UPDATE site_settings SET theme_hero_images = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 'global'",
                    [json_encode($hero_images)]
                );
                $settings['theme_hero_images'] = json_encode($hero_images);
                $success = 'Hero image uploaded and saved for: ' . $themes[$target_theme]['label'];
            }
        }
    }

    /* ── 3. Save hero title style ─────────────────────────── */
    if ($action === 'save_hero_style') {
        $bg_blur_raw = floatval($_POST['bg_blur'] ?? 0);
        $bg_blur_raw = max(0, min(20, $bg_blur_raw)); // clamp 0-20
        $hero_style = [
            'title_color'     => $_POST['title_color']     ?? '#ffffff',
            'glow_color'      => $_POST['glow_color']      ?? '#facc15',
            'glow_intensity'  => $_POST['glow_intensity']  ?? 'high',
            'font_size'       => $_POST['font_size']        ?? '12',
            'letter_spacing'  => $_POST['letter_spacing']  ?? '-0.04',
            'text_transform'  => $_POST['text_transform']  ?? 'uppercase',
            'animation_style' => $_POST['animation_style'] ?? 'flicker',
            'bg_blur'         => (string)$bg_blur_raw,
            'hero_lighting'   => $_POST['hero_lighting']   ?? 'dark',
        ];
        db_query(
            "UPDATE site_settings SET hero_style = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 'global'",
            [json_encode($hero_style)]
        );
        $settings['hero_style'] = json_encode($hero_style);
        $success = 'Hero title style saved.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Theme Studio - MangaNexus</title>
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
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/settings" class="nav-item">
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
      <a href="/<?php echo htmlspecialchars($admin_slug); ?>/theme" class="nav-item active">
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
      <h2>🎨 Theme Studio</h2>
      <div class="user-badge">
        <span>Active theme: <strong><?php echo htmlspecialchars($themes[$settings['current_theme']]['label'] ?? $settings['current_theme']); ?></strong></span>
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

      <!-- ══════════════════════════════════════════════════
           SECTION 1 — Active Theme Selector
      ══════════════════════════════════════════════════ -->
      <div class="crud-card ts-card">
        <div class="card-header">
          <h3>Active Theme</h3>
          <span class="ts-badge">Global</span>
        </div>
        <p class="ts-desc">Choose which theme the entire website uses. Changes apply instantly.</p>

        <form method="POST" class="crud-form">
          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
          <input type="hidden" name="action" value="save_theme">
          <div class="ts-theme-grid">
            <?php foreach ($themes as $slug => $info): ?>
              <?php $is_active = ($settings['current_theme'] === $slug); ?>
              <label class="ts-theme-card <?php echo $is_active ? 'ts-theme-active' : ''; ?>" style="--ta:<?php echo $info['accent']; ?>">
                <input type="radio" name="current_theme" value="<?php echo $slug; ?>" <?php echo $is_active ? 'checked' : ''; ?>>
                <div class="ts-theme-swatch" style="background: linear-gradient(135deg, <?php echo $info['accent']; ?> 0%, #0c0c18 70%);">
                  <?php if (!empty($hero_images[$slug])): ?>
                    <img src="<?php echo htmlspecialchars($hero_images[$slug]); ?>" alt="" class="ts-theme-preview-img">
                  <?php else: ?>
                    <?php $default = '/images/' . $info['hero_file']; ?>
                    <img src="<?php echo htmlspecialchars($default); ?>" alt="" class="ts-theme-preview-img" onerror="this.style.display='none'">
                  <?php endif; ?>
                  <div class="ts-swatch-overlay"></div>
                </div>
                <div class="ts-theme-info">
                  <span class="ts-theme-dot" style="background:<?php echo $info['accent']; ?>"></span>
                  <span class="ts-theme-name"><?php echo $info['label']; ?></span>
                  <?php if ($is_active): ?><span class="ts-active-badge">ACTIVE</span><?php endif; ?>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-primary ts-btn-save">Apply Theme</button>
        </form>
      </div>

      <!-- ══════════════════════════════════════════════════
           SECTION 2 — Hero Image Upload per Theme
      ══════════════════════════════════════════════════ -->
      <div class="crud-card ts-card">
        <div class="card-header">
          <h3>Hero Images</h3>
          <span class="ts-badge">Per Theme</span>
        </div>
        <p class="ts-desc">Upload a custom hero background image for each theme. Images are automatically converted to WebP at 1920px max width for the best quality/performance ratio. Supported inputs: JPG, PNG, GIF, WebP, AVIF.</p>

        <div class="ts-hero-grid">
          <?php foreach ($themes as $slug => $info): ?>
            <?php
              $custom_img  = $hero_images[$slug] ?? null;
              $default_img = '/images/' . $info['hero_file'];
              $preview_src = $custom_img ?: $default_img;
              $is_active   = ($settings['current_theme'] === $slug);
            ?>
            <div class="ts-hero-row <?php echo $is_active ? 'ts-hero-active-row' : ''; ?>">
              <!-- Preview thumbnail -->
              <div class="ts-hero-thumb-wrap" style="--ta:<?php echo $info['accent']; ?>">
                <img src="<?php echo htmlspecialchars($preview_src); ?>"
                     alt="<?php echo htmlspecialchars($info['label']); ?>"
                     class="ts-hero-thumb-img"
                     onerror="this.src=''; this.alt='No image';">
                <?php if ($is_active): ?>
                  <div class="ts-hero-active-tag">ACTIVE</div>
                <?php endif; ?>
                <?php if ($custom_img): ?>
                  <div class="ts-hero-custom-tag">Custom</div>
                <?php endif; ?>
              </div>

              <!-- Info + upload form -->
              <div class="ts-hero-info">
                <div class="ts-hero-label" style="color:<?php echo $info['accent']; ?>"><?php echo $info['label']; ?></div>
                <?php if ($custom_img): ?>
                  <div class="ts-hero-path">📁 <?php echo htmlspecialchars($custom_img); ?></div>
                <?php else: ?>
                  <div class="ts-hero-path ts-hero-default">📁 Default: <?php echo $default_img; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="ts-upload-form">
                  <?php echo \MangaNexus\Security\Csrf::getField(); ?>
                  <input type="hidden" name="action" value="upload_hero">
                  <input type="hidden" name="target_theme" value="<?php echo $slug; ?>">
                  <div class="ts-file-row">
                    <label class="ts-file-label" for="hero_upload_<?php echo $slug; ?>">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                      Choose Image
                    </label>
                    <input type="file" name="hero_image" id="hero_upload_<?php echo $slug; ?>"
                           class="ts-file-input" accept="image/*"
                           onchange="document.getElementById('fn_<?php echo $slug; ?>').textContent = this.files[0]?.name || 'No file'">
                    <span id="fn_<?php echo $slug; ?>" class="ts-file-name">No file chosen</span>
                    <button type="submit" class="btn btn-sm ts-btn-upload" style="--ta:<?php echo $info['accent']; ?>">Upload WebP</button>
                  </div>
                  <div class="ts-hint">Best size: 1920 × 1080 px · auto-converted to WebP · quality 88</div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════
           SECTION 3 — Hero Title Style Customizer
      ══════════════════════════════════════════════════ -->
      <div class="crud-card ts-card">
        <div class="card-header">
          <h3>Hero Title Style</h3>
          <span class="ts-badge">Global</span>
        </div>
        <p class="ts-desc">Customize the mega-title displayed in the hero section. Changes apply globally across all themes.</p>

        <!-- Live Preview -->
        <div class="ts-title-preview" id="ts-preview-box">
          <div class="ts-preview-label">LIVE PREVIEW</div>
          <div class="ts-preview-title" id="ts-preview-title">
            <span id="pv-main">MANGA</span><br>
            <span id="pv-accent">NEXUS</span>
          </div>
          <div class="ts-preview-line" id="pv-line"></div>
        </div>

        <form method="POST" class="crud-form ts-style-form" id="ts-style-form">
          <?php echo \MangaNexus\Security\Csrf::getField(); ?>
          <input type="hidden" name="action" value="save_hero_style">

          <div class="form-grid">

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
              <label class="form-label">Glow / Lightning Color</label>
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
                <input type="range" name="font_size" id="font_size" min="5" max="20" step="0.5"
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

            <!-- Background Blur Slider & Hero Lighting atmosphere -->
            <div class="form-group col-12">
              <div class="sub-section-title">Cinema Background Image & Atmosphere</div>
            </div>
            
            <div class="form-group col-6">
              <label class="form-label ts-blur-label">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                Background Image Blur
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

            <div class="form-group col-6">
              <label class="form-label">Hero Lighting Atmosphere</label>
              <select name="hero_lighting" id="hero_lighting" class="form-select" onchange="updatePreview()">
                <option value="dark" <?php echo ($hero_style['hero_lighting'] ?? 'dark') === 'dark' ? 'selected' : ''; ?>>Moody Dark (Dramatic focus, high text contrast)</option>
                <option value="clear" <?php echo ($hero_style['hero_lighting'] ?? 'dark') === 'clear' ? 'selected' : ''; ?>>Clear / Vibrant (Bright image, light overlays)</option>
              </select>
              <span class="zinc-text" style="font-size: 0.6875rem; color: rgba(255,255,255,0.25); display: block; margin-top: 0.4rem;">Select "Clear" to let the original background image details shine through vibrantly without heavy dark shading overlays.</span>
            </div>

          </div>

          <button type="submit" class="btn btn-primary ts-btn-save">Save Title Style</button>
        </form>
      </div>

    </div><!-- /admin-content-box -->

    <?php require_once BASE_PATH . '/templates/footer.php'; ?>
  </main>

</body>
</html>

<!-- ══ Theme Studio CSS ══ -->
<style>
/* ── Cards ── */
.ts-card { margin-bottom: 2rem; }
.ts-badge {
  display: inline-block; padding: .2rem .7rem;
  background: rgba(139,92,246,.15); border: 1px solid rgba(139,92,246,.3);
  border-radius: 9999px; font-size: .62rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase; color: #a78bfa;
}
.ts-desc { font-size: .78rem; color: var(--theme-text-muted,#aaa); margin-bottom: 1.75rem; line-height: 1.6; }

/* ── Theme Selector Grid ── */
.ts-theme-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 1rem; margin-bottom: 1.5rem;
}
.ts-theme-card {
  cursor: pointer; border-radius: 10px; overflow: hidden;
  border: 2px solid rgba(255,255,255,.07);
  transition: border-color .2s, transform .2s;
}
.ts-theme-card input[type="radio"] { display: none; }
.ts-theme-card:hover { border-color: var(--ta); transform: translateY(-3px); }
.ts-theme-active, .ts-theme-card:has(input:checked) {
  border-color: var(--ta);
  box-shadow: 0 0 16px color-mix(in srgb, var(--ta) 40%, transparent);
}
.ts-theme-swatch { position: relative; width: 100%; aspect-ratio: 16/9; overflow: hidden; }
.ts-theme-preview-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ts-swatch-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.5), transparent); }
.ts-theme-info {
  display: flex; align-items: center; gap: .45rem;
  padding: .55rem .7rem; background: rgba(255,255,255,.03);
}
.ts-theme-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ts-theme-name { font-size: .68rem; font-weight: 600; flex: 1; }
.ts-active-badge {
  font-size: .55rem; font-weight: 800; letter-spacing: .08em;
  background: var(--ta); color: #fff;
  padding: .1rem .4rem; border-radius: 4px;
}

/* ── Hero Grid ── */
.ts-hero-grid { display: flex; flex-direction: column; gap: 1.1rem; }
.ts-hero-row {
  display: grid; grid-template-columns: 200px 1fr;
  gap: 1.25rem; align-items: start;
  padding: 1.1rem; border-radius: 10px;
  background: rgba(255,255,255,.025);
  border: 1px solid rgba(255,255,255,.07);
  transition: border-color .2s;
}
.ts-hero-active-row { border-color: rgba(139,92,246,.35); background: rgba(139,92,246,.06); }
.ts-hero-thumb-wrap { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 16/9; background: rgba(255,255,255,.04); border: 2px solid var(--ta); }
.ts-hero-thumb-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ts-hero-active-tag, .ts-hero-custom-tag {
  position: absolute; top: .4rem; left: .4rem;
  font-size: .55rem; font-weight: 800; letter-spacing: .07em;
  padding: .18rem .5rem; border-radius: 4px;
}
.ts-hero-active-tag { background: #8b5cf6; color: #fff; }
.ts-hero-custom-tag { top: .4rem; left: auto; right: .4rem; background: #10b981; color: #fff; }
.ts-hero-label { font-size: .82rem; font-weight: 700; margin-bottom: .3rem; }
.ts-hero-path { font-size: .68rem; color: rgba(255,255,255,.4); margin-bottom: .8rem; word-break: break-all; }
.ts-hero-default { color: rgba(255,255,255,.25); }
.ts-upload-form { margin: 0; }
.ts-file-row { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; }
.ts-file-input { display: none; }
.ts-file-label {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .5rem 1rem; border-radius: 6px; cursor: pointer;
  background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.15);
  font-size: .75rem; font-weight: 600; transition: background .2s;
}
.ts-file-label:hover { background: rgba(255,255,255,.12); }
.ts-file-name { font-size: .72rem; color: rgba(255,255,255,.45); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ts-btn-upload {
  background: linear-gradient(135deg, var(--ta, #8b5cf6), color-mix(in srgb, var(--ta, #8b5cf6) 60%, #06b6d4));
  color: #fff; font-size: .72rem; padding: .45rem .95rem;
  border-radius: 6px; border: none; cursor: pointer; font-weight: 700;
  transition: opacity .2s, transform .15s;
}
.ts-btn-upload:hover { opacity: .85; transform: translateY(-1px); }
.ts-hint { font-size: .62rem; color: rgba(255,255,255,.3); margin-top: .45rem; }

/* ── Style Customizer ── */
.ts-title-preview {
  border-radius: 12px; padding: 2.5rem 2rem;
  margin-bottom: 2rem; text-align: center; position: relative;
  border: 1px solid rgba(255,255,255,.06);
  overflow: hidden;
  isolation: isolate;
  --pv-blur: 0px;
}
.ts-title-preview::before {
  content: '';
  position: absolute; inset: 0; z-index: -1;
  background: linear-gradient(135deg, #0c0c18 0%, #151530 100%);
  filter: blur(var(--pv-blur, 0px));
  transform: scale(1.05); /* prevent blur edge clipping */
  transition: filter .25s ease;
}

/* Blur slider */
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
  accent-color: #8b5cf6;
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
.ts-preview-label {
  position: absolute; top: .7rem; left: 1rem;
  font-size: .58rem; letter-spacing: .14em; color: rgba(255,255,255,.25); font-weight: 700;
}
.ts-preview-title {
  font-family: 'Epilogue', 'Inter', system-ui, sans-serif;
  font-size: clamp(2rem, 8vw, 5rem);
  font-weight: 900; line-height: .9;
  letter-spacing: -.04em; text-transform: uppercase;
  margin: 0; transition: all .3s;
}
#pv-main { color: #fff; display: inline; }
#pv-accent { color: #fff; display: inline; }
.ts-preview-line {
  width: 40%; height: 3px; margin: .8rem auto 0;
  background: linear-gradient(90deg, #facc15, rgba(250,204,21,.15));
  border-radius: 2px; transition: all .3s;
}

/* Color picker row */
.ts-color-row { display: flex; align-items: center; gap: .6rem; }
.ts-color-picker {
  width: 44px; height: 44px; border-radius: 8px; cursor: pointer;
  border: 2px solid rgba(255,255,255,.15); background: none; padding: 2px;
}
.ts-color-hex { flex: 1; font-family: monospace; font-size: .82rem; }

/* Range slider */
.ts-range-row { display: flex; align-items: center; gap: .8rem; }
.ts-range-row input[type="range"] { flex: 1; accent-color: #8b5cf6; }
.ts-range-val { font-size: .75rem; font-weight: 700; color: #a78bfa; min-width: 56px; text-align: right; }

/* Save button */
.ts-btn-save { margin-top: 1.5rem; }

/* Style form grid */
.ts-style-form .form-grid { margin-bottom: 0; }

@media (max-width: 768px) {
  .ts-hero-row { grid-template-columns: 1fr; }
  .ts-theme-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<script>
/* Live preview updater */
const glowMap = {
  none:   'none',
  low:    'blur(8px) brightness(1.2)',
  medium: 'blur(20px) brightness(1.4)',
  high:   'blur(35px) brightness(1.6)',
  ultra:  'blur(60px) brightness(2)',
};
const tsGlowRadius = { none:'0,0,0', low:'0 0 20px', medium:'0 0 40px', high:'0 0 70px', ultra:'0 0 120px' };

function updatePreview() {
  const tc  = document.getElementById('title_color').value;
  const gc  = document.getElementById('glow_color').value;
  const gi  = document.getElementById('glow_intensity').value;
  const fs  = document.getElementById('font_size').value;
  const ls  = document.getElementById('letter_spacing').value;
  const tt  = document.getElementById('text_transform').value;
  const ani = document.getElementById('animation_style').value;
  const bl  = parseFloat(document.getElementById('bg_blur').value) || 0;

  // Sync hex fields
  document.getElementById('title_color_hex').value = tc;
  document.getElementById('glow_color_hex').value  = gc;

  const title   = document.getElementById('ts-preview-title');
  const acc     = document.getElementById('pv-accent');
  const line    = document.getElementById('pv-line');
  const preview = document.getElementById('ts-preview-box');

  title.style.fontSize       = 'clamp(1.8rem,' + fs + 'vw,4.5rem)';
  title.style.letterSpacing  = ls + 'em';
  title.style.textTransform  = tt;

  document.getElementById('pv-main').style.color = tc;
  acc.style.color = tc;

  // Glow text-shadow
  const radii = { none:'', low:'0 0 20px', medium:'0 0 40px', high:'0 0 70px', ultra:'0 0 120px' };
  let glow = '';
  if (gi !== 'none') {
    glow = `${radii[gi]} ${gc}, ${radii[gi]} ${gc}, 0 4px 30px rgba(0,0,0,.6)`;
  }
  acc.style.textShadow = glow;
  line.style.background = `linear-gradient(90deg, ${gc}, rgba(0,0,0,0))`;
  line.style.boxShadow  = gi !== 'none' ? `0 0 16px ${gc}` : 'none';

  // Animation
  acc.style.animation = ani === 'flicker' ? 'pv-flicker 4s ease infinite'
                       : ani === 'pulse'   ? 'pv-pulse 2.5s ease infinite'
                       : ani === 'wave'    ? 'pv-wave 3s ease infinite'
                       : 'none';

  // Live blur on preview pseudo-background
  preview.style.setProperty('--pv-blur', bl + 'px');
}

// Init on load
document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<style>
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
