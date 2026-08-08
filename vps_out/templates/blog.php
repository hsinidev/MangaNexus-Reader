<?php
/**
 * blog.php — Public Blog Articles Directory
 */

// Fetch all published posts
try {
    $blog_posts = db_fetch_all("SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY created_at DESC");
} catch (PDOException $e) {
    $blog_posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog & Scanlation Updates — <?php echo htmlspecialchars($site_title); ?></title>
  <?php if (!empty($settings['site_favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($settings['site_favicon']); ?>">
  <?php else: ?>
    <link rel="icon" href="/favicon.ico">
  <?php endif; ?>
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
        "Blog & Scanlation Updates - " . $site_title,
        "Read latest scanlation news, releases, manga updates and micro-blogs online at " . $site_title
    ); 
  ?>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <style>
    .blog-header {
      text-align: center;
      margin-bottom: 4rem;
      position: relative;
    }
    .blog-header h1 {
      font-size: 3rem;
      font-weight: 900;
      letter-spacing: -0.04em;
      text-transform: uppercase;
      margin-bottom: 0.75rem;
    }
    .blog-header p {
      color: var(--theme-text-muted);
      font-size: 1rem;
      max-width: 600px;
      margin: 0 auto;
    }
    .blog-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 2rem;
      margin-bottom: 4rem;
    }
    .blog-card {
      background-color: rgba(var(--theme-card-rgb), 0.45);
      border: 1px solid var(--theme-border);
      border-radius: 1.25rem;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      height: 100%;
      box-shadow: var(--theme-shadow-md);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .blog-card:hover {
      transform: translateY(-5px);
      border-color: var(--theme-card-hover-border);
      box-shadow: var(--theme-shadow-lg);
    }
    .blog-card-thumb {
      width: 100%;
      aspect-ratio: 16/9;
      overflow: hidden;
      position: relative;
      background-color: #121214;
      border-bottom: 1px solid var(--theme-border);
    }
    .blog-card-thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s ease;
    }
    .blog-card:hover .blog-card-thumb img {
      transform: scale(1.04);
    }
    .blog-card-no-thumb {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(var(--theme-primary-rgb), 0.1), rgba(var(--theme-secondary-rgb), 0.1));
    }
    .blog-card-no-thumb svg {
      color: var(--theme-primary);
      opacity: 0.6;
    }
    .blog-card-body {
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      flex: 1;
    }
    .blog-card-date {
      font-size: 0.725rem;
      font-weight: 700;
      color: var(--theme-secondary);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.75rem;
    }
    .blog-card-title {
      font-size: 1.15rem;
      font-weight: 800;
      line-height: 1.4;
      color: var(--theme-text);
      margin-bottom: 0.75rem;
      text-decoration: none;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      transition: color 0.2s ease;
    }
    .blog-card-title:hover {
      color: var(--theme-primary);
    }
    .blog-card-excerpt {
      font-size: 0.85rem;
      color: var(--theme-text-muted);
      line-height: 1.6;
      margin-bottom: 1.5rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .blog-card-footer {
      margin-top: auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-top: 1px solid rgba(255, 255, 255, 0.04);
      padding-top: 1rem;
    }
    .blog-card-more {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--theme-primary);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      transition: gap 0.2s;
    }
    .blog-card:hover .blog-card-more {
      gap: 0.5rem;
    }
  </style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">

  <!-- Header -->
  <?php require_once BASE_PATH . '/templates/header.php'; ?>

  <!-- Main Wrapper -->
  <div class="app-container">
    <header class="blog-header">
      <h1 class="text-primary-gradient">Scanlation Blog</h1>
      <p>Stay updated with our latest scanlation project news, release plans, announcements, and deep dives.</p>
    </header>

    <?php if (empty($blog_posts)): ?>
      <div class="manga-card" style="padding: 4rem; text-align: center; max-width: 600px; margin: 0 auto 4rem auto;">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--theme-primary); margin-bottom: 1.5rem; opacity: 0.7;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M16 8h2"/><path d="M16 12h2"/><path d="M16 16h2"/><path d="M6 8h6v8H6z"/></svg>
        <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--theme-text); margin-bottom: 0.5rem;">No blog posts available yet</h3>
        <p style="color: var(--theme-text-muted); font-size: 0.875rem;">Check back later! We will publish news and update schedules here soon.</p>
      </div>
    <?php else: ?>
      <div class="blog-grid">
        <?php foreach ($blog_posts as $post): ?>
          <article class="blog-card">
            <div class="blog-card-thumb">
              <?php if (!empty($post['thumbnail_url'])): ?>
                <img src="<?php echo htmlspecialchars($post['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy">
              <?php else: ?>
                <div class="blog-card-no-thumb">
                  <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M16 8h2"/><path d="M16 12h2"/><path d="M16 16h2"/><path d="M6 8h6v8H6z"/></svg>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="blog-card-body">
              <div class="blog-card-date"><?php echo format_manga_date($post['created_at']); ?></div>
              <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" class="blog-card-title"><?php echo htmlspecialchars($post['title']); ?></a>
              <p class="blog-card-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
              
              <div class="blog-card-footer">
                <a href="/blog/<?php echo htmlspecialchars($post['slug']); ?>" class="blog-card-more">
                  Read Article
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Footer -->
  <?php require_once BASE_PATH . '/templates/footer.php'; ?>

</body>
</html>
