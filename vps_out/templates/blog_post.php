<?php
/**
 * blog_post.php — Public Single Blog Post Article View
 */

if (empty($blog_slug)) {
    http_response_code(404);
    die("Post not found.");
}

try {
    $post = db_fetch("SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1", [$blog_slug]);
} catch (PDOException $e) {
    $post = null;
}

if (!$post) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>404 Not Found - <?php echo htmlspecialchars($site_title); ?></title>
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
        <style>
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background-color: #09090b; color: #fafafa; font-family: system-ui, sans-serif; }
            .container { text-align: center; max-width: 400px; padding: 2rem; border: 1px solid #27272a; border-radius: 1rem; background-color: #121214; }
            h1 { font-size: 3rem; margin: 0 0 1rem 0; background: linear-gradient(to right, #8b5cf6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            p { color: #a1a1aa; margin-bottom: 2rem; }
            a { display: inline-block; padding: 0.75rem 1.5rem; background: linear-gradient(to right, #8b5cf6, #06b6d4); color: white; text-decoration: none; border-radius: 0.5rem; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>404</h1>
            <p>The blog article you are looking for does not exist or has been removed.</p>
            <a href="/blog">Back to Blog</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($post['meta_title'] ?: $post['title'] . ' - ' . $site_title); ?></title>
  <?php if (!empty($settings['site_favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($settings['site_favicon']); ?>">
  <?php else: ?>
    <link rel="icon" href="/favicon.ico">
  <?php endif; ?>
  <meta name="description" content="<?php echo htmlspecialchars($post['meta_description'] ?: $post['excerpt']); ?>">
  <!-- Google Fonts: non-blocking async delivery -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap">
  </noscript>
  <?php 
    echo show_geo_hreflang_tags(); 
    echo show_social_seo_tags(
        $post['meta_title'] ?: $post['title'] . ' - ' . $site_title,
        $post['meta_description'] ?: $post['excerpt'],
        $post['thumbnail_url'],
        'article'
    ); 
  ?>
  <?php if (!empty($post['seo_schema'])): ?>
    <!-- Blog Article Custom JSON-LD SEO Schema -->
    <?php 
      $schema_content = trim($post['seo_schema']);
      if (stripos($schema_content, '<script') === false) {
          echo '<script type="application/ld+json">' . $schema_content . '</script>';
      } else {
          echo $schema_content;
      }
    ?>
  <?php endif; ?>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <style>
    .post-back-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--theme-text-muted);
      text-decoration: none;
      font-size: 0.8125rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 2rem;
      transition: color 0.2s ease;
    }
    .post-back-link:hover {
      color: var(--theme-primary);
    }
    .post-header {
      margin-bottom: 3rem;
    }
    .post-date {
      font-size: 0.8125rem;
      font-weight: 700;
      color: var(--theme-secondary);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 1rem;
    }
    .post-title {
      font-size: 2.75rem;
      font-weight: 900;
      line-height: 1.2;
      letter-spacing: -0.03em;
      color: var(--theme-text);
      margin-bottom: 1.5rem;
    }
    .post-excerpt {
      font-size: 1.125rem;
      color: var(--theme-text-muted);
      line-height: 1.6;
      border-left: 3px solid var(--theme-primary);
      padding-left: 1.5rem;
      margin-bottom: 2rem;
    }
    .post-hero-image {
      width: 100%;
      aspect-ratio: 21/9;
      border-radius: 1.5rem;
      overflow: hidden;
      border: 1px solid var(--theme-border);
      background-color: #121214;
      margin-bottom: 3rem;
      box-shadow: var(--theme-shadow-lg);
    }
    .post-hero-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .post-content {
      font-size: 1.05rem;
      line-height: 1.8;
      color: var(--theme-text);
    }
    .post-content p {
      margin-bottom: 1.75rem;
    }
    .post-content h2 {
      font-size: 1.75rem;
      font-weight: 800;
      color: var(--theme-text);
      margin: 3rem 0 1.25rem 0;
      letter-spacing: -0.02em;
    }
    .post-content h3 {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--theme-text);
      margin: 2rem 0 1rem 0;
      letter-spacing: -0.02em;
    }
    .post-content ul, .post-content ol {
      margin-bottom: 1.75rem;
      padding-left: 1.5rem;
    }
    .post-content li {
      margin-bottom: 0.5rem;
    }
    .post-content img {
      max-width: 100%;
      border-radius: 1rem;
      border: 1px solid var(--theme-border);
      margin: 2rem 0;
    }
    .post-content blockquote {
      border-left: 4px solid var(--theme-secondary);
      background-color: rgba(var(--theme-card-rgb), 0.3);
      padding: 1.5rem;
      border-radius: 0 1rem 1rem 0;
      font-style: italic;
      margin: 2rem 0;
    }
    .post-footer-nav {
      margin-top: 5rem;
      padding-top: 2rem;
      border-top: 1px solid var(--theme-border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    /* Premium Glassmorphic Manga CTA Card */
    .manga-cta-container {
      margin: 4rem 0 2rem 0;
      position: relative;
    }
    .manga-cta-card {
      position: relative;
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
      gap: 2rem;
      padding: 2.25rem;
      background: rgba(var(--theme-card-rgb, 18, 18, 20), 0.45);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 1.5rem;
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
      overflow: hidden;
    }
    .manga-cta-glow {
      position: absolute;
      top: -50%;
      left: -20%;
      width: 140%;
      height: 200%;
      background: radial-gradient(circle, rgba(var(--theme-primary-rgb, 96, 165, 250), 0.15) 0%, transparent 60%);
      pointer-events: none;
      z-index: 0;
    }
    .manga-cta-content {
      position: relative;
      z-index: 1;
      flex: 1;
    }
    .manga-cta-content h3 {
      font-size: 1.25rem;
      font-weight: 800;
      margin: 0 0 0.5rem 0 !important;
      color: #fff;
      letter-spacing: -0.02em;
    }
    .manga-cta-content p {
      font-size: 0.9rem;
      color: var(--theme-text-muted);
      margin: 0 !important;
      line-height: 1.5;
    }
    .btn-manga-cta {
      position: relative;
      z-index: 1;
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem 2rem;
      background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-secondary) 100%);
      color: #fff;
      text-decoration: none;
      border-radius: 1rem;
      font-weight: 900;
      font-size: 0.95rem;
      letter-spacing: 0.02em;
      box-shadow: 0 4px 20px rgba(var(--theme-primary-rgb, 96, 165, 250), 0.35);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-manga-cta:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 6px 24px rgba(var(--theme-primary-rgb, 96, 165, 250), 0.5);
      filter: brightness(1.1);
    }
    .btn-manga-cta svg {
      transition: transform 0.2s ease;
    }
    .btn-manga-cta:hover svg {
      transform: translateX(4px);
    }

    .post-header-cta-link:hover {
      background: var(--theme-primary) !important;
      color: #fff !important;
      box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb, 96, 165, 250), 0.25);
      transform: translateY(-1px);
    }

    @media (max-width: 768px) {
      .manga-cta-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
        padding: 1.75rem;
      }
      .btn-manga-cta {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">

  <!-- Header -->
  <?php require_once BASE_PATH . '/templates/header.php'; ?>

  <!-- Main Wrapper -->
  <div class="app-container">
    <a href="/blog" class="post-back-link">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Back to Blog
    </a>

    <article class="blog-post-article">
      <header class="post-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
          <div class="post-date" style="margin-bottom: 0;">Published <?php echo format_manga_date($post['created_at']); ?></div>
        </div>
        <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
        <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
      </header>

      <?php if (!empty($post['thumbnail_url'])): ?>
        <div class="post-hero-image">
          <img src="<?php echo htmlspecialchars($post['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
          <?php if (!empty($post['read_manga_url'])): ?>
            <div class="post-hero-image-overlay">
              <a href="<?php echo htmlspecialchars($post['read_manga_url']); ?>" class="btn-hero-read-manga">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.25rem;"><path d="M8 5v14l11-7z"/></svg>
                <span>Read Manga Now</span>
              </a>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php if (!empty($post['read_manga_url'])): ?>
          <div class="post-hero-image fallback-hero">
            <div class="post-hero-image-overlay">
              <a href="<?php echo htmlspecialchars($post['read_manga_url']); ?>" class="btn-hero-read-manga">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.25rem;"><path d="M8 5v14l11-7z"/></svg>
                <span>Read Manga Now</span>
              </a>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="post-content">
        <!-- Render Raw HTML Body Content -->
        <?php echo $post['content']; ?>
      </div>

      <?php if (!empty($post['read_manga_url'])): ?>
        <!-- Premium Glassmorphic Manga CTA Card -->
        <div class="manga-cta-container">
          <div class="manga-cta-card">
            <div class="manga-cta-glow"></div>
            <div class="manga-cta-content">
              <h3>Enjoyed this post? Read the full series now!</h3>
              <p>Follow the story and chapters directly in our high-speed scanlation reader.</p>
            </div>
            <a href="<?php echo htmlspecialchars($post['read_manga_url']); ?>" class="btn-manga-cta">
              <span>Read Manga</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
          </div>
        </div>
      <?php endif; ?>
    </article>

    <div class="post-footer-nav">
      <a href="/blog" class="post-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Blog Listing
      </a>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once BASE_PATH . '/templates/footer.php'; ?>

</body>
</html>
