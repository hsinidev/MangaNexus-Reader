<?php
/**
 * home.php — MangaNexus Portal Homepage Template
 * Full-width cinematic hero + manga catalog
 */

$settings = get_settings();
$theme = !empty($settings['current_theme']) ? $settings['current_theme'] : 'midnight-dark';
$mode  = !empty($settings['website_mode'])  ? $settings['website_mode']  : 'general';

// ── Query database ──
if ($mode === 'single') {
    $primary_id = !empty($settings['primary_manga_id']) ? $settings['primary_manga_id'] : '';
    $manga = null;
    if ($primary_id) {
        $manga = db_fetch("SELECT * FROM mangas WHERE id = ?", [$primary_id]);
    }
    if (!$manga) {
        $manga = db_fetch("SELECT * FROM mangas ORDER BY sort_order ASC, created_at DESC LIMIT 1");
    }
    if ($manga) {
        $chapters = db_fetch_all("SELECT * FROM chapters WHERE manga_id = ? ORDER BY number DESC", [$manga['id']]);
    } else {
        $chapters = [];
    }
} else {
    $mangas = db_fetch_all("SELECT * FROM mangas ORDER BY sort_order ASC, created_at DESC");
    $mangas_data = [];
    foreach ($mangas as $m) {
        $m['chapters'] = db_fetch_all("SELECT * FROM chapters WHERE manga_id = ? ORDER BY number DESC LIMIT 3", [$m['id']]);
        $mangas_data[] = $m;
    }
}

// ── Hero spotlight manga ──
$hero_manga    = null;
$hero_chapters = [];
if ($mode === 'single' && !empty($manga)) {
    $hero_manga    = $manga;
    $hero_chapters = $chapters;
} else {
    $primary_id = !empty($settings['primary_manga_id']) ? $settings['primary_manga_id'] : '';
    if ($primary_id) {
        $hero_manga = db_fetch("SELECT * FROM mangas WHERE id = ?", [$primary_id]);
        if ($hero_manga) {
            $hero_chapters = db_fetch_all("SELECT * FROM chapters WHERE manga_id = ? ORDER BY number DESC LIMIT 5", [$hero_manga['id']]);
        }
    }
    if (!$hero_manga && !empty($mangas_data)) {
        $hero_manga    = $mangas_data[0];
        $hero_chapters = $mangas_data[0]['chapters'] ?? [];
    }
}

// ── Theme → hero image map (default bundled images) ──
$hero_images_default = [
    'midnight-dark'         => '/images/hero_midnight.webp',
    'madara'                => '/images/hero_midnight.webp',
    'otaku-crimson'         => '/images/hero_crimson.webp',
    'minimalist-scanlation' => '/images/hero_minimalist.webp',
    'manga-classic'         => '/images/hero_classic.webp',
    'cyberpunk-district'    => '/images/hero_cyberpunk.webp',
    'shonen-punch'          => '/images/hero_shonen.webp',
    'amethyst-fantasy'      => '/images/hero_amethyst.webp',
    'solarized-novel'       => '/images/hero_solarized.webp',
    'e-reader-mono'         => '/images/hero_e_reader.webp',
    'deep-ocean'            => '/images/hero_deep_ocean.webp',
    'light-scarlet'         => '/images/hero_light_scarlet.webp',
    'light-emerald'         => '/images/hero_light_emerald.webp',
    'light-amber'           => '/images/hero_light_amber.webp',
    'light-sapphire'        => '/images/hero_light_art.webp',
    'light-orange'          => '/images/hero_light_orange.webp',
    'light-teal'            => '/images/hero_light_teal.webp',
    'light-sakura'          => '/images/hero_light_sakura.webp',
    'light-lime'            => '/images/hero_light_lime.webp',
    'light-lavender'        => '/images/hero_light_lavender.webp',
    'light-cyan'            => '/images/hero_light_cyan.webp',
];
// Merge with admin-uploaded images stored in DB
$hero_images_db  = json_decode($settings['theme_hero_images'] ?? '{}', true) ?: [];
$hero_images_all = array_merge($hero_images_default, $hero_images_db);
$hero_bg_url     = $hero_images_all[$theme] ?? '/images/hero_midnight.webp';

// ── Hero Style from Theme Studio ──
$hero_style = json_decode($settings['hero_style'] ?? '{}', true) ?: [];
$hs = [
    'title_color'     => $hero_style['title_color']     ?? '#ffffff',
    'glow_color'      => $hero_style['glow_color']      ?? '#facc15',
    'glow_intensity'  => $hero_style['glow_intensity']  ?? 'high',
    'font_size'       => $hero_style['font_size']        ?? '12',
    'letter_spacing'  => $hero_style['letter_spacing']  ?? '-0.04',
    'text_transform'  => $hero_style['text_transform']  ?? 'uppercase',
    'animation_style' => $hero_style['animation_style'] ?? 'flicker',
    'bg_blur'         => $hero_style['bg_blur']         ?? '0',
    'hero_lighting'   => $hero_style['hero_lighting']   ?? 'dark',
];
// Build glow text-shadow string
$glow_radii = ['none'=>'','low'=>'0 0 20px','medium'=>'0 0 40px','high'=>'0 0 70px','ultra'=>'0 0 120px'];
$gr = $glow_radii[$hs['glow_intensity']] ?? '0 0 70px';
$glow_shadow = $hs['glow_intensity'] === 'none'
    ? 'none'
    : "$gr {$hs['glow_color']}, $gr {$hs['glow_color']}, 0 0 200px {$hs['glow_color']}80, 0 4px 40px rgba(0,0,0,.7)";
// Build CSS animation name
$anim_name = ['flicker'=>'title-flicker','pulse'=>'title-pulse-glow','wave'=>'title-wave','none'=>'none'][$hs['animation_style']] ?? 'title-flicker';

// Overrides for Single Mode from Spotlight Manga
if ($mode === 'single' && $hero_manga) {
    if (!empty($hero_manga['hero_bg_url'])) {
        $hero_bg_url = $hero_manga['hero_bg_url'];
    }
    
    // Merge hero style settings specific to this manga
    if (!empty($hero_manga['custom_hero_style']) && !empty($focused_manga_style = json_decode($hero_manga['custom_hero_style'], true))) {
        foreach ($focused_manga_style as $k => $v) {
            $hs[$k] = $v;
        }
        
        // Re-evaluate dependent visual variables
        $gr = $glow_radii[$hs['glow_intensity']] ?? '0 0 70px';
        $glow_shadow = $hs['glow_intensity'] === 'none'
            ? 'none'
            : "$gr {$hs['glow_color']}, $gr {$hs['glow_color']}, 0 0 200px {$hs['glow_color']}80, 0 4px 40px rgba(0,0,0,.7)";
        $anim_name = ['flicker'=>'title-flicker','pulse'=>'title-pulse-glow','wave'=>'title-wave','none'=>'none'][$hs['animation_style']] ?? 'title-flicker';
    }
}

// ── Resolve Custom Hero Overrides ──
$hero_title = '';
$hero_desc  = '';
$hero_link  = '';
$hero_btn_text = 'READ NOW';

if (!empty($settings['custom_hero_title'])) {
    $hero_title = $settings['custom_hero_title'];
    $hero_desc  = $settings['custom_hero_desc'] ?? '';
    if (!empty($settings['custom_hero_image'])) {
        $hero_bg_url = $settings['custom_hero_image'];
    }
    $hero_link  = $settings['custom_hero_link'] ?? '#';
    $hero_btn_text = !empty($settings['custom_hero_btn_text']) ? $settings['custom_hero_btn_text'] : 'READ NOW';
} elseif ($hero_manga) {
    $hero_title = $hero_manga['title'];
    $hero_desc  = mb_strimwidth($hero_manga['description'] ?: '', 0, 200, '…');
    $hero_link  = "/manga/" . $hero_manga['slug'];
    if (!empty($hero_chapters)) {
        $hero_btn_text = "READ CH." . $hero_chapters[0]['number'];
        $hero_link  = "/manga/" . $hero_manga['slug'] . "/chapter/" . $hero_chapters[0]['number'];
    } else {
        $hero_btn_text = "VIEW SERIES";
    }
} else {
    $hero_title = $site_title;
    $hero_desc  = $site_description;
    $hero_link  = '#';
}

// ALWAYS override the hero link target if a custom hero link is explicitly configured in settings
if (!empty($settings['custom_hero_link'])) {
    $hero_link = $settings['custom_hero_link'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($settings['meta_title'] ?? $site_title); ?></title>
  <?php if (!empty($settings['site_favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($settings['site_favicon']); ?>">
  <?php else: ?>
    <link rel="icon" href="/favicon.ico">
  <?php endif; ?>
  <meta name="description" content="<?php echo htmlspecialchars($site_description); ?>">
  <!-- LCP preload: fetch hero background before render -->
  <link rel="preload" as="image" href="<?php echo htmlspecialchars($hero_bg_url); ?>" fetchpriority="high">
  <!-- Preconnect to GSAP CDN for faster script fetch -->
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
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
  <?php 
    echo show_geo_hreflang_tags(); 
    echo show_social_seo_tags(); 
  ?>
  <!-- Hero Style CSS Variables from Theme Studio -->
  <style id="hero-style-vars">
    :root {
      --hs-title-color:    <?php echo htmlspecialchars($hs['title_color']); ?>;
      --hs-glow-color:     <?php echo htmlspecialchars($hs['glow_color']); ?>;
      --hs-glow-shadow:    <?php echo $glow_shadow; ?>;
      --hs-font-size:      clamp(3rem, <?php echo htmlspecialchars($hs['font_size']); ?>vw, 11rem);
      --hs-letter-spacing: <?php echo htmlspecialchars($hs['letter_spacing']); ?>em;
      --hs-text-transform: <?php echo htmlspecialchars($hs['text_transform']); ?>;
      --hs-anim-name:      <?php echo $anim_name; ?>;
      --hs-bg-blur:        <?php echo floatval($hs['bg_blur']); ?>px;
      
      <?php if (($hs['hero_lighting'] ?? 'dark') === 'clear'): ?>
        --hs-hero-brightness: 0.95;
        --hs-gradient-radial: radial-gradient(circle at 50% 30%, rgba(12, 12, 24, 0.15) 0%, rgba(0,0,0,0.55) 75%);
        --hs-gradient-linear-top: linear-gradient(180deg, rgba(0, 0, 0, 0.2) 0%, transparent 100%);
        --hs-gradient-linear-bottom: linear-gradient(to top, var(--theme-bg) 0%, transparent 60%);
      <?php else: ?>
        --hs-hero-brightness: 0.65;
        --hs-gradient-radial: radial-gradient(circle at 10% 30%, rgba(12, 12, 24, 0.45) 0%, rgba(0,0,0,0.85) 70%);
        --hs-gradient-linear-top: linear-gradient(180deg, rgba(0, 0, 0, 0.5) 0%, transparent 100%);
        --hs-gradient-linear-bottom: linear-gradient(to top, var(--theme-bg) 0%, transparent 40%);
      <?php endif; ?>
      
      <?php if ($mode === 'single' && $hero_manga): ?>
        <?php if (!empty($hero_manga['custom_accent_color'])): ?>
          --theme-primary: <?php echo htmlspecialchars($hero_manga['custom_accent_color']); ?>;
          --theme-primary-rgb: <?php echo hexToRgb($hero_manga['custom_accent_color']); ?>;
        <?php endif; ?>
        <?php if (!empty($hero_manga['custom_secondary_color'])): ?>
          --theme-secondary: <?php echo htmlspecialchars($hero_manga['custom_secondary_color']); ?>;
        <?php endif; ?>
      <?php endif; ?>
    }
  </style>
  <?php if (!empty($settings['homepage_schema'])): ?>
    <script type="application/ld+json"><?php echo $settings['homepage_schema']; ?></script>
  <?php endif; ?>
  <?php if ($mode === 'single' && !empty($manga['seo_schema'])): ?>
    <script type="application/ld+json"><?php echo $manga['seo_schema']; ?></script>
  <?php endif; ?>
  <?php echo show_google_analytics_tag(); ?>
<noscript>
  <style>
    #hero-eyebrow, #hero-mega-title, #hero-synopsis, #hero-cta-row, #hero-ch-strip, #catalog-bar, .grid-card {
      opacity: 1 !important;
    }
  </style>
</noscript>
<style>
/* Initial invisible state for layout-stable cinematic load elements (prevents Cumulative Layout Shift) */
#hero-eyebrow, #hero-mega-title, #hero-synopsis, #hero-cta-row, #hero-ch-strip, #catalog-bar, .grid-card {
  opacity: 0;
  will-change: transform, opacity;
}
/* ── Particle canvas ── */
#particle-canvas {
  position: fixed; inset: 0;
  width: 100%; height: 100%;
  z-index: -10; pointer-events: none;
}

/* ── Immersive Floating Hero Header ── */
.hero-top-nav {
  position: absolute;
  top: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 1200px;
  padding: 0 2.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 100;
}
@media (max-width: 800px) {
  .hero-top-nav {
    padding: 0 1.5rem;
  }
}
.hero-brand {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  text-decoration: none;
  transition: transform 0.2s ease;
}
.hero-brand:hover {
  transform: scale(1.03);
}
.hero-logo-box {
  width: 2rem;
  height: 2rem;
  background: var(--theme-gradient);
  border-radius: 0.65rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb), 0.25);
}
.hero-brand-text {
  font-weight: 900;
  font-size: 1rem;
  letter-spacing: -0.02em;
  color: #ffffff;
}
.hero-auth-group {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.hero-nav-link {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  font-size: 0.72rem;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.75);
  text-decoration: none;
  border-radius: 0.5rem;
  transition: all 0.2s ease;
}
.hero-nav-link:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.08);
}
.hero-visitor-username {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  font-size: 0.72rem;
  font-weight: 800;
  color: #ffffff;
}
.hero-auth-btn-ghost {
  display: inline-flex;
  align-items: center;
  padding: 0.45rem 0.95rem;
  font-size: 0.72rem;
  font-weight: 800;
  color: #ffffff;
  text-decoration: none;
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 9999px;
  transition: all 0.2s ease;
}
.hero-auth-btn-ghost:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.4);
}
.hero-auth-btn-primary {
  display: inline-flex;
  align-items: center;
  padding: 0.45rem 0.95rem;
  font-size: 0.72rem;
  font-weight: 800;
  color: #ffffff !important;
  background: var(--theme-primary);
  text-decoration: none;
  border-radius: 9999px;
  box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb), 0.25);
  transition: all 0.2s ease;
}
.hero-auth-btn-primary:hover {
  background: var(--theme-primary-hover);
  box-shadow: 0 6px 16px rgba(var(--theme-primary-rgb), 0.4);
  transform: translateY(-1px);
}

/* ════════════════════════════════════════════
   CYBER DECORATIVE GRID OVERLAY
   ════════════════════════════════════════════ */
.hero-cyber-grid {
  position: absolute;
  inset: 0;
  background-image: 
    linear-gradient(to bottom, rgba(0,0,0,0) 40%, var(--theme-bg) 100%),
    linear-gradient(rgba(255, 255, 255, 0.005) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.005) 1px, transparent 1px);
  background-size: 100% 100%, 45px 45px, 45px 45px;
  background-position: center;
  z-index: 1;
  pointer-events: none;
  opacity: 0.85;
}

/* ════════════════════════════════════════════
   SITE-HERO — Immersive Full-Bleed Spotlight
   ════════════════════════════════════════════ */
.site-hero {
  position: relative;
  width: 100vw;
  margin-left: calc(50% - 50vw);
  min-height: 95vh;
  display: flex;
  align-items: center;
  overflow: hidden;
  padding-top: 0;
  contain: layout style;
}

.hero-bg-layer {
  position: absolute; inset: -5%;
  background-size: cover;
  background-position: center top;
  will-change: transform;
  z-index: 0;
  filter: blur(var(--hs-bg-blur, 0px)) brightness(var(--hs-hero-brightness, 0.65));
  transform: scale(1.08);
}

.hero-atmos-blur {
  position: absolute; inset: -10%;
  background-size: cover;
  background-position: center;
  opacity: .18;
  filter: blur(80px) saturate(1.8);
  will-change: transform;
  z-index: 2;
}

.hero-gradient-overlay {
  position: absolute; inset: 0;
  background:
    var(--hs-gradient-radial, radial-gradient(circle at 10% 30%, rgba(12, 12, 24, 0.45) 0%, rgba(0,0,0,0.85) 70%)),
    var(--hs-gradient-linear-bottom, linear-gradient(to top, var(--theme-bg) 0%, transparent 40%)),
    var(--hs-gradient-linear-top, linear-gradient(180deg, rgba(0, 0, 0, 0.5) 0%, transparent 100%));
  z-index: 3;
}

.hero-center {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 8rem 2.5rem 6rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1.75rem;
}

@media (max-width: 800px) {
  .site-hero { min-height: 100svh; padding-top: 0; }
  .hero-center {
    padding: 4rem 1.5rem 5rem;
    align-items: center;
    text-align: center;
  }
}

.hero-watermark {
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  font-size: clamp(20rem, 45vw, 42rem);
  font-weight: 900;
  color: rgba(255,255,255,.015);
  line-height: 1;
  pointer-events: none;
  z-index: 4;
}

.hero-eyebrow {
  display: inline-flex; align-items: center; gap: .7rem;
  font-size: .72rem; font-weight: 900;
  letter-spacing: .2em; text-transform: uppercase;
  color: var(--theme-primary);
  padding: .45rem 1.35rem;
  background: rgba(var(--theme-primary-rgb), .08);
  border: 1px solid rgba(var(--theme-primary-rgb), .22);
  border-radius: 9999px;
  box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb), 0.1);
  backdrop-filter: blur(8px);
}

.hero-live-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--theme-primary);
  box-shadow: 0 0 12px var(--theme-primary);
  animation: live-pulse 2s ease infinite;
}

@keyframes live-pulse {
  0%,100% { transform: scale(1); opacity: 1; }
  50%      { transform: scale(.7); opacity: .45; }
}

.hero-mega-title {
  font-family: 'Outfit', 'Inter', system-ui, sans-serif;
  font-size: var(--hs-font-size, clamp(3.8rem, 11vw, 10rem));
  font-weight: 900;
  line-height: .9;
  letter-spacing: var(--hs-letter-spacing, -.04em);
  margin: 0;
  position: relative;
  text-transform: var(--hs-text-transform, uppercase);
  z-index: 15;
}

.hero-mega-title a {
  text-decoration: none;
  color: inherit;
  display: inline-block;
  transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.hero-mega-title a:hover {
  transform: scale(1.02);
}

.hero-title-main {
  color: var(--hs-title-color, #ffffff);
  display: inline;
  text-shadow: 0 4px 40px rgba(0,0,0,.7);
}

.hero-title-accent {
  color: var(--hs-title-color, #ffffff);
  display: inline;
  position: relative;
  text-shadow: var(--hs-glow-shadow, 0 0 50px rgba(255,255,255,.5));
  animation: var(--hs-anim-name, title-flicker) 6s ease infinite;
}

.hero-title-glow-line {
  display: block;
  width: 140px;
  height: 4px;
  margin-top: 1rem;
  background: linear-gradient(90deg, var(--hs-glow-color, var(--theme-primary)), transparent);
  border-radius: 2px;
  box-shadow: 0 0 12px var(--hs-glow-color, var(--theme-primary));
}

.hero-synopsis {
  font-size: clamp(.95rem, 1.4vw, 1.15rem);
  color: rgba(255, 255, 255, 0.72);
  line-height: 1.8;
  margin: 0;
  max-width: 620px;
  border-left: 3px solid var(--theme-primary);
  padding-left: 1.5rem;
  text-shadow: 0 2px 8px rgba(0,0,0,0.5);
}

@media (max-width: 800px) {
  .hero-synopsis { border-left: none; padding-left: 0; }
}

.hero-cta-row {
  display: flex; gap: 1.25rem; flex-wrap: wrap;
  margin-top: .5rem;
}
@media (max-width: 800px) {
  .hero-cta-row { justify-content: center; }
}

.hero-btn {
  display: inline-flex; align-items: center; gap: .7rem;
  padding: 1.1rem 2.5rem;
  border-radius: 0.85rem;
  font-size: .8rem; font-weight: 800;
  text-decoration: none; border: none; cursor: pointer;
  letter-spacing: .08em; text-transform: uppercase;
  transition: all .3s cubic-bezier(.16, 1, .3, 1);
  white-space: nowrap;
}

.hero-btn-primary {
  background: var(--theme-gradient);
  color: #fff;
  box-shadow: 0 6px 20px rgba(var(--theme-primary-rgb), .35);
}
.hero-btn-primary:hover {
  transform: translateY(-4px) scale(1.02);
  box-shadow: 0 12px 30px rgba(var(--theme-primary-rgb), .5);
}
.hero-btn-primary:active {
  transform: translateY(-1px) scale(0.98);
}

.hero-btn-ghost {
  background: rgba(255, 255, 255, 0.03);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
}
.hero-btn-ghost:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: var(--theme-primary);
  transform: translateY(-4px);
}

.hero-btn-red {
  background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 6px 20px rgba(220, 38, 38, .35);
  backdrop-filter: blur(10px);
}
.hero-btn-red:hover {
  background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
  border-color: rgba(255, 255, 255, 0.3);
  box-shadow: 0 12px 30px rgba(220, 38, 38, .55);
  transform: translateY(-4px) scale(1.02);
}
.hero-btn-red:active {
  transform: translateY(-1px) scale(0.98);
}

.hero-ch-strip {
  display: flex; flex-direction: column; gap: .6rem;
  width: 100%;
}

.hero-strip-label {
  font-size: .65rem; font-weight: 900; letter-spacing: .12em;
  text-transform: uppercase; color: var(--theme-text-muted);
}

.hero-ch-pills {
  display: flex; gap: .6rem; flex-wrap: wrap;
}

.hero-ch-pill {
  display: inline-flex; align-items: center; gap: .5rem;
  padding: .5rem 1.1rem;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 0.75rem;
  font-size: .8rem; font-weight: 700;
  color: rgba(255, 255, 255, 0.85);
  text-decoration: none;
  backdrop-filter: blur(10px);
  transition: all .25s ease;
}
.hero-ch-pill:hover {
  background: rgba(var(--theme-primary-rgb), .15);
  border-color: var(--theme-primary);
  color: #fff;
  transform: translateY(-2px);
}

.hero-thumb-row {
  display: flex; gap: .75rem;
  flex-wrap: wrap;
}

.hero-thumb {
  width: 62px; aspect-ratio: 2/3;
  border-radius: 8px; overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.12);
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.3);
  transition: all .28s cubic-bezier(.16, 1, .3, 1);
}
.hero-thumb img { width: 100%; height: 100%; object-fit: cover; }
.hero-thumb:hover {
  border-color: var(--theme-primary);
  transform: translateY(-4px) scale(1.08);
  box-shadow: 0 8px 20px rgba(var(--theme-primary-rgb), 0.3);
}

.thumb-fallback {
  width: 100%; height: 100%;
  background: rgba(255,255,255,.05);
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,.3); font-size: 1.1rem; font-weight: 700;
}

.hero-scroll-cue {
  position: absolute; bottom: 2rem; left: 50%;
  transform: translateX(-50%);
  z-index: 20; opacity: .4;
}
.scroll-mouse {
  width: 22px; height: 35px;
  border: 2px solid rgba(255,255,255,.3);
  border-radius: 11px;
  display: flex; justify-content: center; padding-top: 5px;
}
.scroll-wheel {
  width: 4px; height: 7px;
  background: #fff;
  border-radius: 2px;
}

/* ════════════════════════════════════════════
   SPOTLIGHT / TRENDING SECTION
   ════════════════════════════════════════════ */
.trending-section {
  width: 100%;
  margin-bottom: 4rem;
  content-visibility: auto;
  contain-intrinsic-size: 0 400px;
}

.section-heading-wrapper {
  margin-bottom: 2.25rem;
}
.section-heading-wrapper.center {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.heading-badge {
  display: inline-block;
  font-size: 0.6rem;
  font-weight: 900;
  color: var(--theme-primary);
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
  padding: 0.3rem 0.8rem;
  background: rgba(var(--theme-primary-rgb), 0.08);
  border-radius: 4px;
}

.section-title-new {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--theme-text);
  margin: 0;
}

.section-subtitle-new {
  font-size: 0.9rem;
  color: var(--theme-text-muted);
  margin-top: 0.35rem;
  margin-bottom: 0;
  max-width: 500px;
}

.trending-rail {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 1.5rem;
}
@media (min-width: 640px) { .trending-rail { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .trending-rail { grid-template-columns: repeat(4, 1fr); } }

.trending-card {
  position: relative;
  border-radius: 1.5rem;
  overflow: hidden;
  aspect-ratio: 16/10;
  background-color: var(--theme-card);
  border: 1px solid var(--theme-border);
  box-shadow: var(--theme-shadow-md);
  transition: transform 0.4s cubic-bezier(.16, 1, .3, 1), border-color 0.4s;
}

.trending-card-bg {
  position: absolute; inset: 0;
  background-size: cover;
  background-position: center;
  transition: transform 0.5s ease;
  z-index: 0;
}

.trending-card-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.92) 0%, rgba(0,0,0,0.5) 50%, rgba(0,0,0,0.1) 100%);
  z-index: 1;
}

.trending-card-glow {
  position: absolute; inset: 0;
  opacity: 0;
  box-shadow: inset 0 0 25px rgba(var(--theme-primary-rgb), 0.5);
  transition: opacity 0.4s;
  pointer-events: none;
  z-index: 2;
}

.trending-card:hover {
  transform: translateY(-6px);
  border-color: var(--theme-card-hover-border);
  box-shadow: var(--theme-shadow-lg), 0 0 20px rgba(var(--theme-primary-rgb), 0.15);
}
.trending-card:hover .trending-card-bg {
  transform: scale(1.08);
}
.trending-card:hover .trending-card-glow {
  opacity: 1;
}

.trending-card-content {
  position: absolute; inset: 0;
  z-index: 3;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  text-decoration: none;
}

.trending-card-badge {
  align-self: flex-start;
  font-size: 0.58rem;
  font-weight: 800;
  color: #fff;
  background: var(--theme-gradient);
  padding: 0.25rem 0.6rem;
  border-radius: 0.5rem;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  box-shadow: 0 4px 10px rgba(var(--theme-primary-rgb), 0.35);
}

.trending-card-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 0.35rem 0;
  line-height: 1.25;
}

.trending-card-desc {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.7);
  line-height: 1.4;
  margin: 0 0 1rem 0;
  height: 2.1rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.trending-card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  padding-top: 0.75rem;
}

.trending-card-status {
  font-size: 0.7rem;
  font-weight: 700;
  color: rgba(255,255,255,0.5);
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.live-dot-mini {
  width: 6px; height: 6px; border-radius: 50%;
}
.live-dot-mini.ongoing { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; }
.live-dot-mini.completed { background: #10b981; box-shadow: 0 0 6px #10b981; }

.trending-card-action {
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--theme-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* ════════════════════════════════════════════
   MAIN LAYOUT & GRID
   ════════════════════════════════════════════ */
.page-container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 4rem 2.5rem 6rem;
}

@media (max-width: 767px) {
  .page-container { padding: 3rem 1.5rem 4rem; }
}

.catalog-bar {
  display: flex; flex-direction: column; gap: 1.25rem;
  margin-bottom: 3rem;
  background: rgba(var(--theme-card-rgb), 0.35);
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  padding: 1.25rem;
  backdrop-filter: blur(15px);
}
@media(min-width: 768px) {
  .catalog-bar { flex-direction: row; align-items: center; justify-content: space-between; }
}

.search-wrapper { position: relative; max-width: 380px; width: 100%; }
.search-icon {
  position: absolute; left: 1.15rem; top: 50%; transform: translateY(-50%);
  color: var(--theme-text-muted); pointer-events: none;
}
.filter-search-input {
  width: 100%; padding: .85rem 1.15rem .85rem 3rem;
  background: var(--theme-input-bg);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 1rem; color: var(--theme-text);
  font-family: inherit; font-size: .875rem; outline: none;
  transition: all .25s ease;
}
.filter-search-input:focus {
  border-color: rgba(var(--theme-primary-rgb), 0.5);
  box-shadow: 0 0 0 3px rgba(var(--theme-primary-rgb),.15), inset 0 2px 4px rgba(0,0,0,0.1);
  background: rgba(var(--theme-primary-rgb),0.02);
}

.filter-buttons { display: flex; gap: .5rem; flex-wrap: wrap; }
.filter-btn {
  background: transparent;
  border: 1px solid rgba(255,255,255,0.06);
  color: var(--theme-text-muted);
  padding: .6rem 1.35rem; border-radius: 1rem;
  font-size: .75rem; font-weight: 700; cursor: pointer;
  transition: all .22s ease;
}
.filter-btn:hover { background: rgba(255,255,255,0.03); color: var(--theme-text); }
.filter-btn.active {
  border-color: var(--theme-primary);
  color: #fff;
  background: var(--theme-gradient);
  box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb), 0.3);
}

/* ── Manga Grid — content-visibility for faster render ── */
.manga-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
  margin-bottom: 5rem;
  content-visibility: auto;
  contain-intrinsic-size: 0 800px;
}
@media(min-width: 540px)  { .manga-grid { grid-template-columns: repeat(3, 1fr); } }
@media(min-width: 768px)  { .manga-grid { grid-template-columns: repeat(4, 1fr); } }
@media(min-width: 1024px) { .manga-grid { grid-template-columns: repeat(5, 1fr); gap: 1.75rem; } }
@media(min-width: 1200px) { .manga-grid { grid-template-columns: repeat(5, 1fr); } }

.grid-card {
  display: flex; flex-direction: column;
  background: rgba(var(--theme-card-rgb), .3);
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem; overflow: hidden;
  box-shadow: var(--theme-shadow-sm);
  transition: transform .4s cubic-bezier(.16, 1, .3, 1),
              border-color .4s, background .4s;
  will-change: transform;
}
.grid-card:hover {
  transform: translateY(-6px);
  border-color: var(--theme-card-hover-border);
  background: rgba(var(--theme-card-rgb), .65);
  box-shadow: var(--theme-shadow-md), 0 10px 25px rgba(var(--theme-primary-rgb), 0.1);
}

.card-cover-link {
  display: block; width: 100%; aspect-ratio: 3/4;
  position: relative; overflow: hidden;
  background: rgba(20,20,32,.8);
  border-bottom: 1px solid var(--theme-border);
  transform-style: preserve-3d;
}
.card-image {
  width: 100%; height: 100%; object-fit: cover;
  filter: grayscale(45%) brightness(.85);
  transition: filter .5s cubic-bezier(.16, 1, .3, 1), transform .5s cubic-bezier(.16, 1, .3, 1);
}
.grid-card:hover .card-image { filter: grayscale(0%) brightness(1); transform: scale(1.05); }

.no-card-cover {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: .5rem; color: var(--theme-text-muted);
}
.no-card-cover span { font-size: .65rem; font-weight: 800; letter-spacing: .05em; }

.card-badge {
  position: absolute; top: .85rem; right: .85rem;
  padding: .25rem .6rem; font-size: .6rem; font-weight: 800;
  background: rgba(0,0,0,.7);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: .5rem; color: #fff; z-index: 10;
  text-transform: uppercase;
  backdrop-filter: blur(4px);
}

.card-hover-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.2) 60%, transparent 100%);
  display: flex; align-items: flex-end; justify-content: center;
  padding: 1.15rem; opacity: 0; z-index: 15; transition: opacity .35s;
}
.grid-card:hover .card-hover-overlay { opacity: 1; }

.hover-btn {
  font-size: .72rem; font-weight: 800; color: #fff;
  background: var(--theme-gradient);
  padding: .5rem .9rem; border-radius: .75rem;
  width: 100%; text-align: center;
  transform: translateY(12px);
  transition: transform .35s cubic-bezier(.16, 1, .3, 1);
  box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb), 0.35);
}
.grid-card:hover .hover-btn { transform: translateY(0); }

.card-content {
  padding: 1.15rem; display: flex; flex-direction: column;
  flex: 1; justify-content: space-between;
}
.card-title-link {
  display: block; font-size: .95rem; font-weight: 800;
  color: var(--theme-text); text-decoration: none;
  line-height: 1.3; margin-bottom: .35rem; transition: color .2s;
}
.card-title-link:hover { color: var(--theme-primary); }
.card-desc {
  font-size: .78rem; color: var(--theme-text-muted); line-height: 1.45;
  height: 2.6rem; display: -webkit-box; -webkit-line-clamp: 2;
  -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 1rem;
}
.card-chapters { border-top: 1px solid rgba(255,255,255,.05); padding-top: .9rem; }
.quick-title { display: block; font-size: .6rem; font-weight: 800; text-transform: uppercase; color: var(--theme-text-muted); margin-bottom: .6rem; letter-spacing: 0.05em; }
.no-updates { font-size: .78rem; color: var(--theme-text-muted); font-style: italic; }
.quick-links-list { display: flex; flex-direction: column; gap: .4rem; }
.quick-link {
  display: flex; justify-content: space-between; align-items: center;
  background: rgba(255,255,255,.015); border: 1px solid rgba(255,255,255,.04);
  border-radius: .65rem; padding: .4rem .75rem; text-decoration: none;
  font-size: .78rem; font-weight: 600; color: var(--theme-text);
  transition: all .2s;
}
.quick-link:hover { border-color: rgba(var(--theme-primary-rgb), 0.5); background: rgba(var(--theme-primary-rgb),.08); }
.q-ch { color: var(--theme-text); }
.q-action { font-size: .58rem; font-weight: 800; text-transform: uppercase; color: var(--theme-secondary); letter-spacing: .05em; }

/* ── Portal Features Grid ── */
.portal-features-section {
  width: 100%;
  margin-bottom: 4rem;
  border-top: 1px solid var(--theme-border);
  padding-top: 4rem;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr);
  gap: 2rem;
}
@media (min-width: 768px) { .features-grid { grid-template-columns: repeat(3, 1fr); } }

.feature-card {
  background: rgba(var(--theme-card-rgb), .25);
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  padding: 2.25rem;
  text-align: center;
  transition: transform 0.4s cubic-bezier(.16, 1, .3, 1), border-color 0.4s;
}
.feature-card:hover {
  transform: translateY(-5px);
  border-color: var(--theme-card-hover-border);
  box-shadow: var(--theme-shadow-md), 0 8px 24px rgba(var(--theme-primary-rgb), 0.05);
}

.feature-icon-wrapper {
  width: 3rem;
  height: 3rem;
  border-radius: 1rem;
  background: rgba(var(--theme-primary-rgb), 0.08);
  border: 1px solid rgba(var(--theme-primary-rgb), 0.2);
  color: var(--theme-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 14px rgba(var(--theme-primary-rgb), 0.15);
}

.feature-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--theme-text);
  margin: 0 0 0.75rem 0;
}

.feature-desc {
  font-size: 0.85rem;
  color: var(--theme-text-muted);
  line-height: 1.6;
  margin: 0;
}

/* ── Single-mode chapters ── */
.chapters-section {
  background: rgba(var(--theme-card-rgb), .25);
  border: 1px solid var(--theme-border);
  border-radius: 2rem; padding: 2.25rem;
  backdrop-filter: blur(10px);
  box-shadow: var(--theme-shadow-md);
}
.section-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -.02em; margin-bottom: 1.75rem; color: var(--theme-text); }
.no-chapters { font-size: .9rem; color: var(--theme-text-muted); text-align: center; padding: 3rem; border: 1px dashed var(--theme-border); border-radius: 1.25rem; background: rgba(0,0,0,0.1); }
.chapters-grid { display: flex; flex-direction: column; gap: .85rem; }
.chapter-card {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.15rem 1.5rem; background: rgba(255,255,255,.015);
  border: 1px solid var(--theme-border);
  border-radius: 1.15rem; text-decoration: none;
  transition: all .28s cubic-bezier(.16, 1, .3, 1);
}
.chapter-card:hover {
  border-color: var(--theme-primary);
  transform: translateX(6px);
  background: rgba(var(--theme-primary-rgb), .05);
  box-shadow: 0 4px 16px rgba(var(--theme-primary-rgb), .15);
}
.chapter-num { font-size: .95rem; font-weight: 800; color: var(--theme-text); }
.chapter-title { font-size: .85rem; color: var(--theme-text-muted); margin-left: .35rem; }
.chapter-action { display: flex; align-items: center; gap: .4rem; font-size: .75rem; font-weight: 800; text-transform: uppercase; color: var(--theme-secondary); }

/* Blog + SEO blocks */
.blog-section, .home-seo-blog {
  margin-top: 3.5rem;
  background: rgba(var(--theme-card-rgb), .25);
  border: 1px solid var(--theme-border);
  border-radius: 2rem; padding: 2.25rem;
  box-shadow: var(--theme-shadow-md);
}

/* Empty state */
.empty-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 5rem 2rem; border: 1px dashed var(--theme-border);
  border-radius: 2rem; text-align: center;
  background: rgba(var(--theme-card-rgb), .15);
}
.empty-state h2 { font-size: 1.3rem; font-weight: 800; margin: 1.25rem 0 .5rem; color: var(--theme-text); }
.empty-state p  { font-size: .9rem; color: var(--theme-text-muted); max-width: 320px; }
</style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">

<canvas id="particle-canvas"></canvas>

<!-- Header removed to allow full screen immersive hero spotlight -->

<!-- ════════════════════════════════════════════════════════════
     FULL-WIDTH CINEMATIC HERO SECTION
════════════════════════════════════════════════════════════ -->
<section class="site-hero" id="site-hero">
  <!-- Immersive Floating Hero Header -->
  <div class="hero-top-nav">
    <a href="/" class="hero-brand">
      <div class="hero-logo-box">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: #ffffff;"><path d="M2 20h20"/><path d="M5 17V5a3 3 0 0 1 3-3h14"/><path d="M22 17H8a3 3 0 0 0-3 3"/></svg>
      </div>
      <span class="hero-brand-text">MangaNexus</span>
    </a>
    <div class="hero-auth-group">
      <a href="/" class="hero-nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-10 9h3v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8h3L12 3z"/></svg>
        Library
      </a>
      <a href="/#catalog-bar" class="hero-nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        Search
      </a>
      <a href="/blog" class="hero-nav-link">
        Blog
      </a>
      <?php if (\MangaNexus\Security\Auth::isVisitorLoggedIn()): ?>
        <?php $visitor = \MangaNexus\Security\Auth::getVisitorUser(); ?>
        <span class="hero-visitor-username">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?php echo htmlspecialchars($visitor['username'] ?? 'User'); ?>
        </span>
        <a href="/logout" class="hero-auth-btn-ghost">Sign out</a>
      <?php else: ?>
        <a href="/login" class="hero-auth-btn-ghost">Sign In</a>
        <a href="/signup" class="hero-auth-btn-primary">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Cyber-grid overlay -->
  <div class="hero-cyber-grid"></div>

  <!-- Layer 1: theme hero background image (parallax) -->
  <div class="hero-bg-layer" style="background-image: url('<?php echo htmlspecialchars(cache_bust($hero_bg_url)); ?>')"></div>

  <!-- Layer 2: manga cover blurred as full-bleed atmosphere -->
  <?php 
    $atmos_bg = '';
    if (!empty($settings['custom_hero_image'])) {
        $atmos_bg = $settings['custom_hero_image'];
    } elseif ($hero_manga && !empty($hero_manga['cover_url'])) {
        $atmos_bg = $hero_manga['cover_url'];
    }
    if (!empty($atmos_bg)):
  ?>
  <div class="hero-atmos-blur" style="background-image: url('<?php echo htmlspecialchars(cache_bust($atmos_bg)); ?>')"></div>
  <?php endif; ?>

  <!-- Layer 3: vignette + bottom fade gradient -->
  <div class="hero-gradient-overlay"></div>



  <!-- ── Centered hero body ── -->
  <div class="hero-center" id="hero-center">

    <!-- Status pill -->
    <div class="hero-eyebrow" id="hero-eyebrow">
      <span class="hero-live-dot"></span>
      <?php if (!empty($settings['custom_hero_title'])): ?>
        SPOTLIGHT &nbsp;·&nbsp; FEATURED
      <?php elseif ($hero_manga): ?>
        <?php echo strtoupper(htmlspecialchars($hero_manga['status'] ?? 'ONGOING')); ?>
        &nbsp;·&nbsp;
        <?php echo count($hero_chapters); ?> CHAPTERS
      <?php else: ?>
        <?php echo strtoupper(htmlspecialchars($site_title)); ?> &nbsp;·&nbsp; <?php echo count($mangas_data ?? []); ?> SERIES
      <?php endif; ?>
    </div>

    <!-- ── THE BIG TITLE ── -->
    <?php
      $words = explode(' ', htmlspecialchars($hero_title));
      $last  = array_pop($words);
      $first = implode(' ', $words);
    ?>
    <h1 class="hero-mega-title" id="hero-mega-title">
      <a href="<?php echo htmlspecialchars($hero_link); ?>" style="text-decoration: none; color: inherit; display: inline-block;">
        <?php if ($first): ?>
          <span class="hero-title-main"><?php echo $first; ?></span><br>
        <?php endif; ?>
        <span class="hero-title-accent"><?php echo $last; ?></span>
        <span class="hero-title-glow-line" aria-hidden="true"></span>
      </a>
    </h1>

    <!-- Synopsis -->
    <?php if (!empty($hero_desc)): ?>
    <p class="hero-synopsis" id="hero-synopsis"><?php echo htmlspecialchars($hero_desc); ?></p>
    <?php endif; ?>

    <!-- CTAs -->
    <div class="hero-cta-row" id="hero-cta-row">
      <a href="<?php echo htmlspecialchars($hero_link); ?>" class="hero-btn hero-btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        <?php echo strtoupper(htmlspecialchars($hero_btn_text)); ?>
      </a>
      <?php if (!empty($settings['custom_hero_title'])): ?>
        <a href="#catalog-bar" class="hero-btn hero-btn-ghost">
          BROWSE ALL &nbsp;&#8595;
        </a>
      <?php else: ?>
        <?php if ($mode === 'general'): ?>
          <a href="#catalog-bar" class="hero-btn hero-btn-ghost">
            BROWSE ALL &nbsp;&#8595;
          </a>
        <?php elseif ($hero_manga): ?>
          <a href="/manga/<?php echo $hero_manga['slug']; ?>" class="hero-btn hero-btn-ghost">ALL CHAPTERS</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Thumbnail strip OR chapter pills -->
    <?php if ($mode === 'single' && !empty($hero_chapters)): ?>
      <div class="hero-ch-strip" id="hero-ch-strip">
        <span class="hero-strip-label">Latest Releases</span>
        <div class="hero-ch-pills">
          <?php foreach (array_slice($hero_chapters, 0, 5) as $ch): ?>
          <a href="/manga/<?php echo $hero_manga['slug']; ?>/chapter/<?php echo $ch['number']; ?>" class="hero-ch-pill">
            Ch.<?php echo $ch['number']; ?>
            <?php if (!empty($ch['title'])): ?><span class="pill-sub"><?php echo htmlspecialchars($ch['title']); ?></span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php elseif ($mode === 'general' && count($mangas_data ?? []) > 1): ?>
      <div class="hero-ch-strip" id="hero-ch-strip">
        <span class="hero-strip-label">More Series</span>
        <div class="hero-thumb-row">
          <?php foreach (array_slice($mangas_data, 1, 7) as $sm): ?>
          <a href="/manga/<?php echo $sm['slug']; ?>" class="hero-thumb" title="<?php echo htmlspecialchars($sm['title']); ?>">
            <?php if (!empty($sm['cover_url'])): ?>
              <img src="<?php echo htmlspecialchars(cache_bust($sm['cover_url'])); ?>" alt="<?php echo htmlspecialchars($sm['title']); ?>"
                   width="80" height="107" loading="lazy" decoding="async">
            <?php else: ?>
              <span class="thumb-fallback">?</span>
            <?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div><!-- /hero-center -->

  <!-- Scroll cue -->
  <div class="hero-scroll-cue" id="hero-scroll-cue">
    <div class="scroll-mouse"><div class="scroll-wheel"></div></div>
  </div>
</section>
<!-- ════════════════════════════════════════════════════════════ -->


<!-- ── BELOW HERO: Main content ── -->
<main class="page-container">

  <?php if ($mode === 'single'): ?>
    <?php if (!$manga): ?>
      <div class="empty-state" style="margin-top:4rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
        <h2>No Manga Series Found</h2>
        <p>Go to the admin panel and create your first series.</p>
        <a href="/<?php echo htmlspecialchars($admin_slug); ?>" class="btn btn-primary">Admin Panel</a>
      </div>
    <?php else: ?>
      <?php echo show_ad('home_top'); ?>

      <section class="chapters-section" id="all-chapters">
        <h2 class="section-title">All Chapters</h2>
        <?php if (empty($chapters)): ?>
          <p class="no-chapters">No chapters uploaded yet.</p>
        <?php else: ?>
          <div class="chapters-grid">
            <?php foreach ($chapters as $ch): ?>
              <a href="/manga/<?php echo $manga['slug']; ?>/chapter/<?php echo $ch['number']; ?>" class="chapter-card">
                <div class="chapter-info">
                  <span class="chapter-num">Chapter <?php echo $ch['number']; ?></span>
                  <?php if (!empty($ch['title'])): ?>
                    <span class="chapter-title">— <?php echo htmlspecialchars($ch['title']); ?></span>
                  <?php endif; ?>
                </div>
                <div class="chapter-action">
                  <span>Read</span>
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <?php if (!empty($manga['blog_content'])): ?>
        <section class="blog-section">
          <div class="blog-article-collapsed-wrapper">
            <article class="manga-blog-article"><?php echo $manga['blog_content']; ?></article>
          </div>
          <div class="blog-read-more-container">
            <button class="blog-read-more-btn" onclick="toggleBlogArticle(this)">
              Read More
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.25rem;"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
          </div>
        </section>
      <?php endif; ?>

      <?php echo show_ad('home_bottom'); ?>
    <?php endif; ?>

  <?php else: /* general mode */ ?>

    <?php echo show_ad('home_top'); ?>

    <!-- Spotlight/Trending Showcase -->
    <section class="trending-section">
      <div class="section-heading-wrapper">
        <div class="heading-badge">POPULAR TITLES</div>
        <h2 class="section-title-new">Spotlight Showcase</h2>
        <p class="section-subtitle-new">Handpicked legendary titles trending across the Nexus network.</p>
      </div>
      
      <div class="trending-rail">
        <?php foreach (array_slice($mangas_data, 0, 4) as $index => $tm): ?>
          <div class="trending-card" style="--card-index: <?php echo $index; ?>;">
            <img class="trending-card-bg" src="<?php echo htmlspecialchars(cache_bust($tm['cover_url'] ?? '')); ?>" alt="<?php echo htmlspecialchars($tm['title']); ?>"
                 width="280" height="175" loading="lazy" decoding="async" style="object-fit: cover;">
            <div class="trending-card-overlay"></div>
            <div class="trending-card-glow"></div>
            <div class="trending-card-content">
              <span class="trending-card-badge">#<?php echo $index + 1; ?> Spot</span>
              <h3 class="trending-card-title"><?php echo htmlspecialchars($tm['title']); ?></h3>
              <p class="trending-card-desc"><?php echo htmlspecialchars(mb_strimwidth($tm['description'] ?: '', 0, 85, '…')); ?></p>
              <div class="trending-card-footer">
                <span class="trending-card-status">
                  <span class="live-dot-mini <?php echo $tm['status'] === 'ongoing' ? 'ongoing' : 'completed'; ?>"></span>
                  <?php echo ucfirst(htmlspecialchars($tm['status'])); ?>
                </span>
                <a href="/manga/<?php echo $tm['slug']; ?>" class="trending-card-action">Read Now ›</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Search + filter bar -->
    <div class="catalog-bar" id="catalog-bar">
      <div class="search-wrapper">
        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="manga-search" placeholder="Search series…" class="filter-search-input">
      </div>
      <div class="filter-buttons">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="ongoing">Ongoing</button>
        <button class="filter-btn" data-filter="completed">Completed</button>
      </div>
    </div>

    <!-- Manga grid -->
    <?php if (empty($mangas_data)): ?>
      <div class="empty-state">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
        <h2>Archive Empty</h2>
        <p>No series yet. Add your first manga in the admin panel.</p>
      </div>
    <?php else: ?>
      <div class="manga-grid" id="manga-grid">
        <?php foreach ($mangas_data as $m): ?>
          <div class="grid-card"
               data-title="<?php echo htmlspecialchars(strtolower($m['title'])); ?>"
               data-desc="<?php echo htmlspecialchars(strtolower($m['description'] ?: '')); ?>"
               data-status="<?php echo htmlspecialchars($m['status']); ?>">

            <a href="/manga/<?php echo $m['slug']; ?>" class="card-cover-link">
              <?php if (!empty($m['cover_url'])): ?>
                <img src="<?php echo htmlspecialchars(cache_bust($m['cover_url'])); ?>"
                     alt="<?php echo htmlspecialchars($m['title']); ?>"
                     class="card-image" loading="lazy"
                     width="300" height="400"
                     decoding="async">
              <?php else: ?>
                <div class="no-card-cover">
                  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
                  <span>NO IMAGE</span>
                </div>
              <?php endif; ?>
              <span class="card-badge"><?php echo htmlspecialchars($m['status']); ?></span>
              <div class="card-hover-overlay">
                <span class="hover-btn">Read Now ›</span>
              </div>
            </a>

            <div class="card-content">
              <div>
                <a href="/manga/<?php echo $m['slug']; ?>" class="card-title-link"><?php echo htmlspecialchars($m['title']); ?></a>
                <p class="card-desc"><?php echo htmlspecialchars($m['description'] ?: 'No description available.'); ?></p>
              </div>
              <div class="card-chapters">
                <span class="quick-title">Latest:</span>
                <?php if (empty($m['chapters'])): ?>
                  <span class="no-updates">No updates.</span>
                <?php else: ?>
                  <div class="quick-links-list">
                    <?php foreach ($m['chapters'] as $ch): ?>
                      <a href="/manga/<?php echo $m['slug']; ?>/chapter/<?php echo $ch['number']; ?>" class="quick-link">
                        <span class="q-ch">Ch. <?php echo $ch['number']; ?></span>
                        <span class="q-action">Read</span>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($mangas_data) > 10): ?>
        <div class="load-more-container" id="load-more-container" style="display: flex; justify-content: center; margin-top: 3rem; margin-bottom: 2rem;">
          <button id="load-more-btn" class="hero-btn hero-btn-red load-more-btn">
            READ MORE &nbsp;·&nbsp; <span id="load-more-count"><?php echo count($mangas_data) - 10; ?> Remaining</span>
          </button>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- Portal Features Showcase -->
    <section class="portal-features-section">
      <div class="section-heading-wrapper center">
        <div class="heading-badge">SYSTEM STABILITY</div>
        <h2 class="section-title-new">MangaNexus Portal Core</h2>
        <p class="section-subtitle-new">Engineered for extreme performance, security, and read immersion.</p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.886L5 9l4.912 1.114L12 16l1.912-5.886L19 9l-4.912-1.114Z"/><path d="M5 21h14"/><path d="M12 16v5"/></svg>
          </div>
          <h3 class="feature-title">Ultra HD Scanlation</h3>
          <p class="feature-desc">Read manga in pristine quality with crystal clear text and responsive image processing optimized for all device screens.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          </div>
          <h3 class="feature-title">Lightning-Fast Load</h3>
          <p class="feature-desc">Highly optimized database querying and automated caching allow for microsecond loading times without lag.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 17V7h5a3 3 0 0 1 0 6H9"/></svg>
          </div>
          <h3 class="feature-title">Self-Hosted Freedom</h3>
          <p class="feature-desc">MangaNexus is built with maximum flexibility, offering complete configuration controls for ads, domains, and styling panels.</p>
        </div>
      </div>
    </section>

    <!-- SEO blog block -->
    <?php if (!empty($settings['homepage_blog_articles'])): ?>
      <section class="home-seo-blog">
        <div class="blog-article-collapsed-wrapper">
          <div class="manga-blog-article"><?php echo $settings['homepage_blog_articles']; ?></div>
        </div>
        <div class="blog-read-more-container">
          <button class="blog-read-more-btn" onclick="toggleBlogArticle(this)">
            Read More
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.25rem;"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
        </div>
      </section>
    <?php endif; ?>

    <?php echo show_ad('home_bottom'); ?>

  <?php endif; ?>

</main>

<?php require_once BASE_PATH . '/templates/footer.php'; ?>

<script defer>
// ── Particle canvas (deferred — does not block TBT) ──
window.addEventListener('load', function () {
  const canvas = document.getElementById('particle-canvas');
  if (!canvas) return;
  const ctx    = canvas.getContext('2d');
  const colors = ['#8b5cf6', '#06b6d4', '#4c1d95', '#164e63'];
  let   pts    = [];

  function resize() { canvas.width = innerWidth; canvas.height = innerHeight; }
  resize();
  addEventListener('resize', resize);

  function Pt() { this.r(); }
  Pt.prototype.r = function() {
    this.x  = Math.random() * canvas.width;
    this.y  = Math.random() * canvas.height;
    this.vx = (Math.random() - .5) * .22;
    this.vy = (Math.random() - .5) * .22;
    this.rad = Math.random() * 1.4 + .4;
    this.col = colors[0 | Math.random() * colors.length];
    this.a   = Math.random() * .5 + .1;
  };
  Pt.prototype.tick = function() {
    this.x += this.vx; this.y += this.vy;
    if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) this.r();
  };
  Pt.prototype.draw = function() {
    ctx.save(); ctx.globalAlpha = this.a;
    ctx.beginPath(); ctx.arc(this.x, this.y, this.rad, 0, Math.PI * 2);
    ctx.fillStyle = this.col; ctx.fill(); ctx.restore();
  };
  for (var i = 0; i < 50; i++) pts.push(new Pt());
  (function loop() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    pts.forEach(function(p) { p.tick(); p.draw(); });
    requestAnimationFrame(loop);
  })();
});

// ── 2. GSAP entry animations ──
document.addEventListener('DOMContentLoaded', () => {
  const tl = gsap.timeline({ defaults: { ease: 'power4.out' } });

  // Eyebrow pill fades in first
  tl.fromTo('#hero-eyebrow',    { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: .8, delay: .2 })
  // Big title: rises from below + scales up (cinematic)
    .fromTo('#hero-mega-title', { opacity: 0, y: 80, scale: .95 }, { opacity: 1, y: 0, scale: 1, duration: 1.8 }, '-=.5')
  // Synopsis slides up
    .fromTo('#hero-synopsis',   { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 1 }, '-=1.3')
  // CTA row
    .fromTo('#hero-cta-row',    { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: .8 }, '-=.9')
  // Thumbnail strip
    .fromTo('#hero-ch-strip',   { opacity: 0, y: 15 }, { opacity: 1, y: 0, duration: .7 }, '-=.7');

  // Scroll-wheel bounce
  gsap.to('.scroll-wheel', { y: 7, repeat: -1, yoyo: true, duration: .9, ease: 'power1.inOut' });

  // Catalog / grid stagger - explicit fromTo prevents snaps and reflows
  gsap.fromTo('#catalog-bar', { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: .8, ease: 'power3.out', delay: .4  });

  // Apply initial limit filters
  applyFilters();

  // Stagger only the visible cards
  const visibleCards = Array.from(cards).filter(c => c.style.display !== 'none');
  gsap.fromTo(visibleCards,   { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: .7, stagger: .06, ease: 'power3.out', delay: .5 });
});

// ── Hero parallax on scroll ──
const heroEl  = document.getElementById('site-hero');
const heroBG  = heroEl ? heroEl.querySelector('.hero-bg-layer')    : null;
const heroAtm = heroEl ? heroEl.querySelector('.hero-atmos-blur')  : null;
if (heroEl) {
  addEventListener('scroll', () => {
    const sy = scrollY;
    if (heroBG)  heroBG.style.transform  = `translateY(${sy * .22}px)`;
    if (heroAtm) heroAtm.style.transform = `translateY(${sy * .08}px) scale(1.06)`;
  }, { passive: true });
}

// ── Search + filter ──
let mangasLimit = 10;

const searchEl = document.getElementById('manga-search');
const filterBtns = document.querySelectorAll('.filter-btn');
const cards = document.querySelectorAll('.grid-card');
const loadMoreContainer = document.getElementById('load-more-container');
const loadMoreBtn = document.getElementById('load-more-btn');
const loadMoreCount = document.getElementById('load-more-count');

if (searchEl) searchEl.addEventListener('input', () => {
  mangasLimit = 10;
  applyFilters();
});

filterBtns.forEach(b => b.addEventListener('click', e => {
  filterBtns.forEach(x => x.classList.remove('active'));
  e.currentTarget.classList.add('active');
  mangasLimit = 10;
  applyFilters();
}));

if (loadMoreBtn) {
  loadMoreBtn.addEventListener('click', () => {
    mangasLimit += 10;
    applyFilters();
  });
}

function applyFilters() {
  const q   = searchEl ? searchEl.value.toLowerCase().trim() : '';
  const fv  = document.querySelector('.filter-btn.active')?.dataset.filter || 'all';
  
  let matches = [];
  
  cards.forEach(c => {
    const ok = (q === '' || (c.dataset.title||'').includes(q) || (c.dataset.desc||'').includes(q))
            && (fv === 'all' || c.dataset.status === fv);
    if (ok) {
      matches.push(c);
    } else {
      gsap.to(c, { opacity: 0, scale: .95, duration: .2, onComplete: () => c.style.display = 'none' });
    }
  });

  matches.forEach((c, idx) => {
    if (idx < mangasLimit) {
      c.style.display = 'flex';
      gsap.to(c, { opacity: 1, scale: 1, duration: .3 });
    } else {
      c.style.display = 'none';
      c.style.opacity = '0';
    }
  });

  if (loadMoreContainer) {
    if (matches.length > mangasLimit) {
      loadMoreContainer.style.display = 'flex';
      if (loadMoreCount) {
        loadMoreCount.textContent = (matches.length - mangasLimit) + ' Remaining';
      }
    } else {
      loadMoreContainer.style.display = 'none';
    }
  }
}

// ── 3-D card tilt ──
cards.forEach(card => {
  const cover = card.querySelector('.card-cover-link');
  if (!cover) return;
  cover.addEventListener('mousemove', e => {
    const r  = cover.getBoundingClientRect();
    const ry = ((e.clientX - r.left  - r.width  / 2) / (r.width  / 2)) * 8;
    const rx = -((e.clientY - r.top  - r.height / 2) / (r.height / 2)) * 8;
    gsap.to(cover, { rotateX: rx, rotateY: ry, transformPerspective: 600, ease: 'power1.out', duration: .3 });
  });
  cover.addEventListener('mouseleave', () => {
    gsap.to(cover, { rotateX: 0, rotateY: 0, ease: 'power2.out', duration: .5 });
  });
});
</script>
</body>
</html>

<!-- ════════════════════════════════════════════════════════════
     HOME PAGE STYLES
════════════════════════════════════════════════════════════ -->
