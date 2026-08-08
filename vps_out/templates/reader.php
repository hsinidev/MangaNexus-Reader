<?php
/**
 * reader.php — Vertical Strip Manga Reader Page (PHP Version)
 */

// Fetch the current manga
$manga = db_fetch("SELECT * FROM mangas WHERE slug = ?", [$manga_slug]);

if (!$manga) {
    http_response_code(404);
    require_once BASE_PATH . '/index.php';
    exit;
}

// Fetch current chapter
$current_chapter = db_fetch(
    "SELECT * FROM chapters WHERE manga_id = ? AND number = ?", 
    [$manga['id'], $chapter_number]
);

if (!$current_chapter) {
    // If not found, try to redirect to manga page
    header("Location: /manga/" . $manga['slug']);
    exit;
}

// Fetch all pages of the current chapter sorted by order_index
$pages = db_fetch_all(
    "SELECT * FROM pages WHERE chapter_id = ? ORDER BY order_index ASC", 
    [$current_chapter['id']]
);

// Fetch all chapters for the dropdown list
$all_chapters = db_fetch_all(
    "SELECT * FROM chapters WHERE manga_id = ? ORDER BY number DESC", 
    [$manga['id']]
);

// Calculate Prev & Next chapters
$prev_chapter = db_fetch(
    "SELECT number FROM chapters WHERE manga_id = ? AND number < ? ORDER BY number DESC LIMIT 1",
    [$manga['id'], $chapter_number]
);
$next_chapter = db_fetch(
    "SELECT number FROM chapters WHERE manga_id = ? AND number > ? ORDER BY number ASC LIMIT 1",
    [$manga['id'], $chapter_number]
);

$prev_num = $prev_chapter ? $prev_chapter['number'] : null;
$next_num = $next_chapter ? $next_chapter['number'] : null;

// Prefetch next chapter page images
$next_pages = [];
if ($next_num !== null) {
    $next_ch = db_fetch(
        "SELECT id FROM chapters WHERE manga_id = ? AND number = ?",
        [$manga['id'], $next_num]
    );
    if ($next_ch) {
        $next_pages = db_fetch_all(
            "SELECT image_url FROM pages WHERE chapter_id = ? ORDER BY order_index ASC",
            [$next_ch['id']]
        );
    }
}

$settings = get_settings();
$theme = !empty($settings['current_theme']) ? $settings['current_theme'] : 'midnight-dark';
$site_title = !empty($settings['site_title']) ? $settings['site_title'] : 'MangaNexus';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ch. <?php echo $chapter_number; ?> - <?php echo htmlspecialchars($manga['title']); ?></title>
  <?php if (!empty($settings['site_favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($settings['site_favicon']); ?>">
  <?php else: ?>
    <link rel="icon" href="/favicon.ico">
  <?php endif; ?>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
  <!-- Google Fonts: non-blocking async delivery -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap">
  </noscript>
  <?php if (!empty($pages[0]['image_url'])): ?>
  <link rel="preload" as="image" href="<?php echo htmlspecialchars($pages[0]['image_url']); ?>" fetchpriority="high">
  <?php endif; ?>
  <!-- GSAP CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>

  <!-- 1. Auto-Prefetch Next Chapter Image Files for Fast Loading -->
  <?php foreach ($next_pages as $n_page): ?>
    <link rel="prefetch" href="<?php echo htmlspecialchars(cache_bust($n_page['image_url'])); ?>">
  <?php endforeach; ?>

  <?php 
    $first_page_image = !empty($pages[0]['image_url']) ? $pages[0]['image_url'] : $manga['cover_url'];
    echo show_geo_hreflang_tags(); 
    echo show_social_seo_tags(
        "Chapter " . $chapter_number . " - " . $manga['title'] . " - " . $site_title,
        "Read " . htmlspecialchars($manga['title']) . " Chapter " . $chapter_number . " online free. " . mb_substr(strip_tags($manga['description'] ?? ''), 0, 120),
        $first_page_image,
        'article'
    ); 
  ?>

  <?php echo show_google_analytics_tag(); ?>
<style>
.reader-body-bg {
  background-color: var(--theme-bg) !important;
}

.reader-floating-header {
  position: fixed;
  top: 1rem;
  left: 50%;
  transform: translateX(-50%) translateY(0);
  width: calc(100% - 2rem);
  max-width: 1000px;
  height: 4.5rem;
  background-color: rgba(var(--theme-card-rgb), 0.7);
  border: 1px solid var(--theme-border);
  border-radius: 1.25rem;
  backdrop-filter: blur(var(--theme-glass-blur));
  -webkit-backdrop-filter: blur(var(--theme-glass-blur));
  z-index: 100;
  padding: 0 1.25rem;
  display: flex;
  align-items: center;
  box-shadow: var(--theme-shadow-md), inset 0 1px 0 rgba(255, 255, 255, 0.03);
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), 
              opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1),
              border-color 0.3s ease;
}

.reader-floating-header:hover {
  border-color: var(--theme-card-hover-border);
}

.reader-floating-header.hidden {
  transform: translateX(-50%) translateY(-6rem);
  opacity: 0;
  pointer-events: none;
}

.reader-header-container {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.header-back-btn {
  width: 2.25rem;
  height: 2.25rem;
  background-color: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--theme-text-muted);
  text-decoration: none;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.header-back-btn:hover {
  color: #fff;
  background-color: rgba(255,255,255,0.08);
}

.header-meta {
  min-width: 0;
}

.header-manga-title {
  display: block;
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--theme-text-muted);
  text-decoration: none;
  letter-spacing: 0.05em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-chapter-num {
  font-size: 0.875rem;
  font-weight: 800;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.dropdown-wrapper {
  position: relative;
}

.dropdown-select {
  height: 2.25rem;
  padding: 0 2rem 0 1rem;
  background-color: var(--theme-input-bg);
  border: 1px solid var(--theme-border);
  border-radius: 0.75rem;
  color: var(--theme-text);
  font-size: 0.75rem;
  font-weight: 700;
  outline: none;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  transition: border-color 0.25s ease, background-color 0.25s ease;
}

.dropdown-select:focus {
  border-color: var(--theme-primary);
}

.dropdown-caret {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--theme-text-muted);
  pointer-events: none;
  border-left: 1px solid var(--theme-border);
  padding-left: 0.5rem;
  display: flex;
  align-items: center;
}

.width-controls {
  display: none;
  background-color: var(--theme-input-bg);
  border: 1px solid var(--theme-border);
  border-radius: 0.75rem;
  padding: 0.125rem;
}

@media(min-width: 640px) {
  .width-controls {
    display: flex;
    align-items: center;
  }
}

.width-btn {
  width: 2rem;
  height: 2rem;
  border-radius: 0.6rem;
  border: none;
  background-color: transparent;
  color: var(--theme-text-muted);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.width-btn:hover {
  color: #fff;
  background-color: rgba(255, 255, 255, 0.03);
}

.width-btn.active {
  background-color: var(--theme-primary);
  color: #fff;
  box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb), 0.35);
}

/* Strip layout settings */
.reader-container {
  flex: 1;
  width: 100%;
  padding-top: 6rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.reader-banner {
  text-align: center;
  padding: 3rem 1.5rem;
  max-width: 600px;
}

.banner-badge {
  display: inline-block;
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--theme-primary);
  letter-spacing: 0.1em;
  margin-bottom: 0.75rem;
}

.banner-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #fff;
  line-height: 1.25;
  margin-bottom: 0.5rem;
}

.banner-pages-count {
  font-size: 0.6875rem;
  color: var(--theme-text-muted);
}

.reader-strip {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  transition: max-width 0.3s ease;
  padding: 0 0.5rem;
}

.reader-strip.narrow {
  max-width: 540px;
}

.reader-strip.standard {
  max-width: 680px;
}

.reader-strip.wide {
  max-width: 1200px;
}

.page-container {
  width: 100%;
  position: relative;
  border-left: 1px solid #1a1a1c;
  border-right: 1px solid #1a1a1c;
  background-color: #0c0c0d;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}

.strip-image {
  width: 100%;
  height: auto;
  display: block;
  opacity: 0;
  transition: opacity 0.5s ease;
  pointer-events: none;
  user-select: none;
}

.page-container.loaded .strip-image {
  opacity: 1;
}

.page-spinner {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  z-index: 5;
  pointer-events: none;
}

.page-container.loaded .page-spinner {
  display: none;
}

.spinner-circle {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  border: 2px solid rgba(139, 92, 246, 0.15);
  border-top-color: var(--theme-primary);
  animation: spin 0.8s linear infinite;
}

.spinner-text {
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--theme-text-muted);
  letter-spacing: 0.05em;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.page-number-overlay {
  position: absolute;
  bottom: 0.5rem;
  right: 0.75rem;
  background-color: rgba(0,0,0,0.5);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.625rem;
  font-weight: 700;
  color: var(--theme-text-muted);
  opacity: 0;
  transition: opacity 0.25s ease;
  pointer-events: none;
}

.page-container:hover .page-number-overlay {
  opacity: 1;
}

/* End chapter card */
.end-chapter-section {
  width: 100%;
  max-width: 600px;
  margin: 4rem auto 0 auto;
  padding: 0 1rem;
  display: flex;
  flex-direction: column;
  gap: 3rem;
}

.end-chapter-card {
  background-color: var(--theme-card);
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  padding: 2rem;
  text-align: center;
  box-shadow: 0 20px 40px rgba(0,0,0,0.25);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.end-chapter-card h3 {
  font-size: 0.875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--theme-text);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.sparkle-icon {
  color: var(--theme-primary);
  animation: pulse 2s infinite;
}

.end-chapter-card p {
  font-size: 0.8125rem;
  color: var(--theme-text-muted);
  max-width: 320px;
  line-height: 1.5;
}

.next-chapter-btn {
  padding: 1rem 2rem !important;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.reader-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--theme-border);
  padding-top: 2rem;
}

.pag-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.2rem;
  border-radius: 0.75rem;
  background-color: rgba(255,255,255,0.03);
  border: 1px solid var(--theme-border);
  color: var(--theme-text-muted);
  text-decoration: none;
  font-size: 0.75rem;
  font-weight: 700;
  transition: all 0.2s ease;
}

.pag-btn:not(.disabled):hover {
  background-color: rgba(255,255,255,0.06);
  color: #fff;
  border-color: var(--theme-text-muted);
}

.pag-btn.disabled {
  opacity: 0.25;
  cursor: not-allowed;
}

.pag-brand {
  font-size: 0.6875rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--theme-text-muted);
}

/* Scroll top button styling */
.scroll-top-btn {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  width: 2.75rem;
  height: 2.75rem;
  background-color: var(--theme-primary);
  border: 1px solid rgba(255,255,255,0.1);
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
  z-index: 95;
  transform: translateY(4rem) scale(0.5);
  opacity: 0;
  transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
}

.scroll-top-btn.visible {
  transform: translateY(0) scale(1);
  opacity: 1;
}

.scroll-top-btn:hover {
  background-color: var(--theme-primary-hover);
  transform: scale(1.05);
}

.scroll-top-btn:active {
  transform: scale(0.95);
}
</style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?> reader-body-bg">

  <!-- 2. Floating Auto-Hiding Reader Header Menu -->
  <header class="reader-floating-header" id="reader-header">
    <div class="reader-header-container">
      <!-- Back to manga details -->
      <div class="header-left">
        <a href="/manga/<?php echo $manga['slug']; ?>" class="header-back-btn" title="Back to Details">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <div class="header-meta">
          <a href="/manga/<?php echo $manga['slug']; ?>" class="header-manga-title"><?php echo htmlspecialchars($manga['title']); ?></a>
          <h1 class="header-chapter-num">Chapter <?php echo $chapter_number; ?></h1>
        </div>
      </div>

      <!-- Dropdowns and settings -->
      <div class="header-right">
        <!-- Chapter Switcher Dropdown -->
        <div class="dropdown-wrapper">
          <select id="chapter-select" class="dropdown-select" onchange="window.location.href='/manga/<?php echo $manga['slug']; ?>/chapter/' + this.value">
            <?php foreach ($all_chapters as $ch): ?>
              <option value="<?php echo $ch['number']; ?>" <?php echo $ch['number'] == $chapter_number ? 'selected' : ''; ?>>
                Ch. <?php echo $ch['number']; ?> <?php echo !empty($ch['title']) ? '- ' . htmlspecialchars(substr($ch['title'], 0, 16)) : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="dropdown-caret">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
          </div>
        </div>

        <!-- Width Adjuster Controls -->
        <div class="width-controls">
          <button class="width-btn" data-width="narrow" title="Narrow View">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="22" y1="12" x2="18" y2="12"/><line x1="6" y1="12" x2="2" y2="12"/><line x1="12" y1="6" x2="12" y2="2"/><line x1="12" y1="22" x2="12" y2="18"/></svg>
          </button>
          <button class="width-btn" data-width="standard" title="Standard View">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
          </button>
          <button class="width-btn active" data-width="wide" title="Wide View">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="1" y1="20" x2="23" y2="20"/><line x1="1" y1="4" x2="23" y2="4"/><line x1="4" y1="22" x2="4" y2="2"/><line x1="20" y1="22" x2="20" y2="2"/></svg>
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- 3. Vertical Strip Reading Canvas -->
  <main class="reader-container">
    <!-- Chapter Title Alert Banner -->
    <div class="reader-banner">
      <span class="banner-badge">Reading Chapter <?php echo $chapter_number; ?></span>
      <h2 class="banner-title"><?php echo htmlspecialchars($current_chapter['title'] ?: $manga['title'] . ' - Chapter ' . $chapter_number); ?></h2>
      <span class="banner-pages-count"><?php echo count($pages); ?> Pages</span>
    </div>

    <!-- Long Strip Pages Box -->
    <section class="reader-strip wide" id="image-strip">
      <?php if (empty($pages)): ?>
        <div class="empty-state">
          <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
          <h2>No Pages Uploaded</h2>
          <p>Please log in to the admin settings and upload page ZIP files for this chapter.</p>
        </div>
      <?php else: ?>
        <!-- Reader Top Ad Slot -->
        <?php $reader_top_ad = show_ad('reader_top'); if (!empty($reader_top_ad)): ?>
          <div class="ad-container-reader-top" style="margin: 1.5rem auto; text-align: center; width: 100%; max-width: 728px; overflow: hidden; padding: 0 1rem;">
            <?php echo $reader_top_ad; ?>
          </div>
        <?php endif; ?>

        <?php 
        $between_ad_blocks = [];
        try {
            $stmt = $pdo->prepare("SELECT * FROM ad_blocks WHERE insertion_type = 'reader_between' AND is_active = 1 ORDER BY block_index ASC");
            $stmt->execute();
            $raw_between = $stmt->fetchAll();
            $is_mobile = is_mobile_device();
            foreach ($raw_between as $block) {
                // Device filter
                $device_target = $block['target_devices'] ?? 'all';
                if ($device_target === 'desktop' && $is_mobile) continue;
                if ($device_target === 'mobile' && !$is_mobile) continue;
                
                // Page filter
                $page_target = $block['target_pages'] ?? 'all';
                if ($page_target !== 'all' && $page_target !== 'reader') continue;
                
                if (!empty($block['code'])) {
                    $between_ad_blocks[] = $block;
                }
            }
        } catch (PDOException $e) {}
        ?>

        <?php foreach ($pages as $index => $page): ?>
          <?php
            // Get image dimensions recursively from local files or VPS mounts (mapped paths)
            $local_img_path = !empty($page['external_path']) ? $page['external_path'] : BASE_PATH . $page['image_url'];
            $aspect_ratio_style = '';
            $size = null;
            if (file_exists($local_img_path)) {
                $size = @getimagesize($local_img_path);
                if ($size && $size[0] > 0 && $size[1] > 0) {
                    $aspect_ratio_style = ' style="aspect-ratio: ' . $size[0] . ' / ' . $size[1] . '; min-height: auto;"';
                }
            }
          ?>
          <div class="page-container"<?php echo $aspect_ratio_style; ?>>
            <!-- Spinner loader showing while image loading -->
            <div class="page-spinner">
              <div class="spinner-circle"></div>
              <span class="spinner-text">Page <?php echo $page['order_index']; ?></span>
            </div>

            <!-- Page Image -->
            <?php
              $is_first_pages = $index < 3;
              $fetch_prio     = $is_first_pages ? ' fetchpriority="high"' : '';
              $loading_attr   = $is_first_pages ? '' : ' loading="lazy"';
              $img_width      = ($size && $size[0] > 0) ? ' width="' . $size[0] . '"' : '';
              $img_height     = ($size && $size[1] > 0) ? ' height="' . $size[1] . '"' : '';
              $img_src        = !empty($page['external_path']) ? '/external-media/' . $page['id'] : $page['image_url'];
            ?>
            <img src="<?php echo htmlspecialchars(cache_bust($img_src)); ?>"
                 alt="Page <?php echo $page['order_index']; ?>"
                 class="strip-image lazy-img"
                 data-index="<?php echo $page['order_index']; ?>"
                 decoding="async"<?php echo $fetch_prio; ?><?php echo $loading_attr; ?><?php echo $img_width; ?><?php echo $img_height; ?>>

            <!-- Progress tracker -->
            <div class="page-number-overlay">
              <?php echo $page['order_index']; ?> / <?php echo count($pages); ?>
            </div>
          </div>

          <!-- Reader Between Pages Ad Slot -->
          <?php 
          $page_num_1based = $index + 1;
          $rendered_ads_for_page = '';
          foreach ($between_ad_blocks as $block) {
              $freq = (int)($block['between_frequency'] ?? 5);
              if ($freq > 0 && $page_num_1based % $freq === 0 && $page_num_1based < count($pages)) {
                  $rendered_ads_for_page .= render_ad_block($block);
              }
          }
          if (!empty($rendered_ads_for_page)): ?>
            <?php echo $rendered_ads_for_page; ?>
          <?php endif; ?>
        <?php endforeach; ?>

        <!-- Reader Bottom Ad Slot -->
        <?php $reader_bottom_ad = show_ad('reader_bottom'); if (!empty($reader_bottom_ad)): ?>
          <div class="ad-container-reader-bottom" style="margin: 2rem auto; text-align: center; width: 100%; max-width: 728px; overflow: hidden; padding: 0 1rem;">
            <?php echo $reader_bottom_ad; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <!-- 4. End of Chapter Layout Card -->
    <section class="end-chapter-section">
      <div class="end-chapter-card">
        <h3>
          <svg class="sparkle-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275Z"/></svg>
          End of Chapter <?php echo $chapter_number; ?>
        </h3>

        <?php if ($next_num !== null): ?>
          <p>The next chapter is pre-loaded and ready. Continue reading instantly.</p>
          <a href="/manga/<?php echo $manga['slug']; ?>/chapter/<?php echo $next_num; ?>" class="btn btn-primary next-chapter-btn">
            Continue to Chapter <?php echo $next_num; ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </a>
        <?php else: ?>
          <p>You have caught up with the latest chapter of this series! Return to the library catalog.</p>
          <a href="/" class="btn btn-secondary">
            Back to Library Catalog
          </a>
        <?php endif; ?>
      </div>

      <!-- Pagination footer controls -->
      <div class="reader-pagination">
        <?php if ($prev_num !== null): ?>
          <a href="/manga/<?php echo $manga['slug']; ?>/chapter/<?php echo $prev_num; ?>" class="pag-btn prev-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Chapter <?php echo $prev_num; ?>
          </a>
        <?php else: ?>
          <span class="pag-btn disabled">Start</span>
        <?php endif; ?>

        <span class="pag-brand">MangaNexus</span>

        <?php if ($next_num !== null): ?>
          <a href="/manga/<?php echo $manga['slug']; ?>/chapter/<?php echo $next_num; ?>" class="pag-btn next-btn">
            Chapter <?php echo $next_num; ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </a>
        <?php else: ?>
          <span class="pag-btn disabled">End</span>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- 5. Floating Glass Scroll-to-Top Button -->
  <button id="scroll-top-btn" class="scroll-top-btn" title="Scroll to Top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
  </button>

  <!-- Reader Interactions script -->
  <script>
    // 1. Image loading listener
    const images = document.querySelectorAll('.lazy-img');
    images.forEach(img => {
      // If image is already cached/loaded
      if (img.complete) {
        img.parentElement.classList.add('loaded');
      } else {
        img.addEventListener('load', () => {
          img.parentElement.classList.add('loaded');
        });
      }
    });

    // 2. Dynamic Floating Header and Scroll to top
    const header = document.getElementById('reader-header');
    const scrollTopBtn = document.getElementById('scroll-top-btn');
    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', () => {
      const currentScrollY = window.scrollY;

      // Show/Hide Floating top menu
      if (currentScrollY > lastScrollY && currentScrollY > 100) {
        header.classList.add('hidden');
      } else {
        header.classList.remove('hidden');
      }

      // Show/Hide Scroll to top button
      if (currentScrollY > 600) {
        scrollTopBtn.classList.add('visible');
      } else {
        scrollTopBtn.classList.remove('visible');
      }

      lastScrollY = currentScrollY;
    }, { passive: true });

    // 3. Width Controller selection
    const widthButtons = document.querySelectorAll('.width-btn');
    const imageStrip = document.getElementById('image-strip');

    widthButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        widthButtons.forEach(b => b.classList.remove('active'));
        const targetBtn = e.currentTarget;
        targetBtn.classList.add('active');

        const width = targetBtn.dataset.width;
        imageStrip.className = 'reader-strip ' + width;
      });
    });
  </script>

</body>
</html>

<!-- CSS Styles for Reader page -->
