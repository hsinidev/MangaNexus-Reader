<?php
/**
 * manga.php — Manga Details and Chapters Index Page (PHP Version)
 */

$settings = get_settings();
$theme = !empty($settings['current_theme']) ? $settings['current_theme'] : 'midnight-dark';
$site_title = !empty($settings['site_title']) ? $settings['site_title'] : 'MangaNexus';

$manga = db_fetch("SELECT * FROM mangas WHERE slug = ?", [$manga_slug]);

if (!$manga) {
    http_response_code(404);
    require_once BASE_PATH . '/index.php'; // Renders 404 page
    exit;
}

// Fetch all chapters of this manga with their first page as thumbnail
$chapters = db_fetch_all("
    SELECT c.*, p.image_url AS first_page_url
    FROM chapters c
    LEFT JOIN (
        SELECT chapter_id, MIN(order_index) AS min_idx, image_url
        FROM pages
        GROUP BY chapter_id
    ) p ON c.id = p.chapter_id
    WHERE c.manga_id = ?
    ORDER BY c.number DESC
", [$manga['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($manga['meta_title'] ?: $manga['title'] . ' - ' . $site_title); ?></title>
  <?php if (!empty($settings['site_favicon'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($settings['site_favicon']); ?>">
  <?php else: ?>
    <link rel="icon" href="/favicon.ico">
  <?php endif; ?>
  <meta name="description" content="<?php echo htmlspecialchars($manga['meta_description'] ?: $manga['description']); ?>">
  <?php if (!empty($manga['meta_keywords'])): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($manga['meta_keywords']); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <?php if (!empty($manga['cover_url'])): ?>
  <link rel="preload" as="image" href="<?php echo htmlspecialchars($manga['cover_url']); ?>" fetchpriority="high">
  <?php endif; ?>
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
  <!-- GSAP CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>

  <!-- Dynamic Schema Injection -->
  <?php if (!empty($manga['seo_schema'])): ?>
    <script type="application/ld+json">
      <?php echo $manga['seo_schema']; ?>
    </script>
  <?php endif; ?>

  <?php 
    echo show_geo_hreflang_tags(); 
    echo show_social_seo_tags(
        $manga['meta_title'] ?: $manga['title'] . ' - ' . $site_title,
        $manga['meta_description'] ?: $manga['description'],
        $manga['cover_url'],
        'article'
    ); 
  ?>

  <?php echo show_google_analytics_tag(); ?>
<noscript>
  <style>
    .animate-fade-in, .animate-slide-up {
      opacity: 1 !important;
    }
  </style>
</noscript>
<style>
/* Initial state for layout stability during GSAP entry (prevents CLS) */
.animate-fade-in, .animate-slide-up {
  opacity: 0;
  will-change: transform, opacity;
}
.back-nav {
  margin-bottom: 2rem;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--theme-text-muted);
  text-decoration: none;
  transition: color 0.2s ease;
}

.back-link:hover {
  color: var(--theme-primary);
}

.details-blur-bg {
  position: absolute;
  top: -8rem;
  left: 0;
  width: 100%;
  height: 500px;
  opacity: 0.07;
  filter: blur(60px);
  background-size: cover;
  background-position: center;
  pointer-events: none;
  z-index: -1;
}

.manga-profile-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2rem;
  align-items: start;
}

@media(min-width: 768px) {
  .manga-profile-grid {
    grid-template-columns: 280px 1fr;
    gap: 3.5rem;
  }
}

.profile-cover-box {
  width: 100%;
  aspect-ratio: 3/4.5;
  background-color: var(--theme-card);
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  overflow: hidden;
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.6);
  position: relative;
}

.profile-cover-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.no-profile-cover {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: var(--theme-text-muted);
}

.no-profile-cover span {
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.1em;
}

.author-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--theme-border);
  background-color: rgba(255, 255, 255, 0.02);
}

.manga-title {
  font-size: 2.25rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.15;
  margin-bottom: 1.5rem;
  background: linear-gradient(to right, #ffffff, var(--theme-text-muted));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

@media(min-width: 768px) {
  .manga-title {
    font-size: 3rem;
  }
}

.manga-synopsis-card {
  background-color: rgba(var(--theme-card-rgb), 0.55);
  border: 1px solid var(--theme-border);
  border-radius: 1.25rem;
  padding: 1.5rem;
  margin-bottom: 2rem;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
}

.manga-synopsis-card h3 {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--theme-text);
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.manga-synopsis-card .synopsis-text {
  font-size: 0.875rem;
  color: var(--theme-text-muted);
  line-height: 1.6;
}

/* Chapters grid premium layout */
.chapters-section {
  margin-top: 3.5rem;
  margin-bottom: 4rem;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin-bottom: 2rem;
  color: var(--theme-text);
  border-left: 4px solid var(--theme-primary);
  padding-left: 0.85rem;
  line-height: 1.2;
}

.chapters-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.25rem;
}

@media (min-width: 640px) {
  .chapters-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 1024px) {
  .chapters-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

.chapter-card-premium {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1rem;
  border-radius: 1rem;
  background-color: rgba(var(--theme-card-rgb), 0.5);
  border: 1px solid var(--theme-border);
  text-decoration: none;
  backdrop-filter: blur(var(--theme-glass-blur));
  -webkit-backdrop-filter: blur(var(--theme-glass-blur));
  box-shadow: var(--theme-shadow-sm), inset 0 1px 0 rgba(255, 255, 255, 0.02);
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), 
              border-color 0.4s cubic-bezier(0.16, 1, 0.3, 1), 
              box-shadow 0.4s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
}

.chapter-card-premium:hover {
  transform: translateY(-4px) scale(1.02);
  border-color: var(--theme-card-hover-border);
  box-shadow: var(--theme-shadow-md), 0 0 20px var(--theme-glow);
}

.chapter-card-thumb {
  width: 70px;
  height: 95px;
  border-radius: 0.65rem;
  overflow: hidden;
  flex-shrink: 0;
  background-color: var(--theme-bg);
  border: 1px solid var(--theme-border);
  position: relative;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.chapter-card-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}

.chapter-card-premium:hover .chapter-card-thumb img {
  transform: scale(1.08);
}

.no-thumb-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--theme-text-muted);
}

.chapter-card-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.chapter-card-subtitle {
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--theme-primary);
  letter-spacing: 0.08em;
  margin-bottom: 0.35rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chapter-card-title {
  font-size: 0.9rem;
  font-weight: 800;
  color: var(--theme-text);
  line-height: 1.3;
  margin-bottom: 0.5rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: color 0.2s ease;
}

.chapter-card-premium:hover .chapter-card-title {
  color: #fff;
}

.chapter-card-link {
  font-size: 0.725rem;
  font-weight: 800;
  color: var(--theme-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  transition: color 0.2s ease;
}

.chapter-card-link svg {
  transition: transform 0.2s ease;
}

.chapter-card-premium:hover .chapter-card-link {
  color: var(--theme-secondary);
}

.chapter-card-premium:hover .chapter-card-link svg {
  transform: translateX(3px);
}
</style>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">

  <!-- Particle Background Layer -->
  <canvas id="particle-canvas"></canvas>

  <!-- Global Header Navigation -->
  <?php require_once BASE_PATH . '/templates/header.php'; ?>

  <!-- Main View -->
  <main class="app-container">
    <div class="bg-grid"></div>
    <div class="bg-radial"></div>

    <div class="manga-details-layout">
      <?php echo show_ad('manga_top'); ?>

      <!-- Back Button -->
      <div class="back-nav animate-fade-in">
        <a href="/" class="back-link">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to Library
        </a>
      </div>

      <!-- Blurred Background Cover -->
      <?php if (!empty($manga['cover_url'])): ?>
        <div class="details-blur-bg" style="background-image: url('<?php echo htmlspecialchars(cache_bust($manga['cover_url'])); ?>');"></div>
      <?php endif; ?>

      <!-- Details Header Box -->
      <div class="manga-profile-grid">
        <!-- Cover Art Column -->
        <div class="profile-cover-col animate-fade-in">
          <div class="profile-cover-box">
            <?php if (!empty($manga['cover_url'])): ?>
              <img src="<?php echo htmlspecialchars(cache_bust($manga['cover_url'])); ?>" alt="<?php echo htmlspecialchars($manga['title']); ?>" class="profile-cover-img"
                   width="280" height="400" fetchpriority="high" decoding="async">
            <?php else: ?>
              <div class="no-profile-cover">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
                <span>NO IMAGE</span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Info details column -->
        <div class="profile-details-col animate-slide-up">
          <div class="badge-row">
            <span class="badge badge-<?php echo htmlspecialchars($manga['status']); ?>">
              <?php echo ucfirst(htmlspecialchars($manga['status'])); ?>
            </span>
            <span class="badge badge-secondary">
              <?php echo count($chapters); ?> Chapters
            </span>
            <?php if (!empty($manga['author'])): ?>
              <span class="author-badge text-zinc-350">
                Author: <?php echo htmlspecialchars($manga['author']); ?>
              </span>
            <?php endif; ?>
          </div>

          <h1 class="manga-title"><?php echo htmlspecialchars($manga['title']); ?></h1>
          
          <div class="manga-synopsis-card">
            <h3>
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="synopsis-icon"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
              Synopsis
            </h3>
            <p class="synopsis-text"><?php echo nl2br(htmlspecialchars($manga['description'] ?: 'No synopsis available for this manga.')); ?></p>
          </div>

          <div class="meta-dates">
            <div class="meta-date-box">
              <span class="meta-label">First Published</span>
              <span class="meta-val"><?php echo format_manga_date($manga['created_at']); ?></span>
            </div>
            <div class="meta-date-box">
              <span class="meta-label">Last Updated</span>
              <span class="meta-val"><?php echo format_manga_date($manga['updated_at']); ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Manga Info Page Ad Slot -->
      <?php $manga_bottom_ad = show_ad('manga_bottom'); if (!empty($manga_bottom_ad)): ?>
        <div class="ad-container-manga-info animate-slide-up" style="margin: 2rem 0; text-align: center; width: 100%; overflow: hidden;">
          <?php echo $manga_bottom_ad; ?>
        </div>
      <?php endif; ?>

      <!-- Chapters index list -->
      <section class="chapters-section animate-slide-up">
        <h2 class="section-title">Chapters Index</h2>
        <?php if (empty($chapters)): ?>
          <p class="no-chapters">No chapters uploaded yet.</p>
        <?php else: ?>
          <div class="chapters-grid">
            <?php foreach ($chapters as $ch): 
              $thumb_url = !empty($ch['first_page_url']) ? htmlspecialchars(cache_bust($ch['first_page_url'])) : (!empty($manga['cover_url']) ? htmlspecialchars(cache_bust($manga['cover_url'])) : '');
              $subtitle = htmlspecialchars(strtoupper($manga['title'])) . ' CH ' . $ch['number'];
              $title = !empty($ch['title']) ? htmlspecialchars($ch['title']) : 'Chapter ' . $ch['number'];
            ?>
              <a href="/manga/<?php echo $manga['slug']; ?>/chapter/<?php echo $ch['number']; ?>" class="chapter-card-premium">
                <div class="chapter-card-thumb">
                  <?php if (!empty($thumb_url)): ?>
                    <img src="<?php echo $thumb_url; ?>" alt="<?php echo $title; ?>" loading="lazy"
                         width="120" height="80" decoding="async">
                  <?php else: ?>
                    <div class="no-thumb-placeholder">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5B2.5 2.5 0 0 1 6.5 17H20M4 19.5B2.5 2.5 0 0 0 6.5 22H20M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="chapter-card-content">
                  <span class="chapter-card-subtitle"><?php echo $subtitle; ?></span>
                  <h3 class="chapter-card-title"><?php echo $title; ?></h3>
                  <span class="chapter-card-link">
                    Read Now
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                  </span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Blog Article Section -->
      <?php if (!empty($manga['blog_content'])): ?>
        <section class="blog-section animate-slide-up">
          <div class="blog-article-collapsed-wrapper">
            <article class="manga-blog-article">
              <?php echo $manga['blog_content']; ?>
            </article>
          </div>
          <div class="blog-read-more-container">
            <button class="blog-read-more-btn" onclick="toggleBlogArticle(this)">
              Read More
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.25rem;"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
          </div>
        </section>
      <?php endif; ?>

    </div>
  </main>

  <!-- Reusable Developer footer -->
  <?php require_once BASE_PATH . '/templates/footer.php'; ?>

  <!-- Core Page Scripts & Interactive Physics Particle Canvas -->
  <script>
    // Particle Canvas System
    const canvas = document.getElementById('particle-canvas');
    const ctx = canvas.getContext('2d');
    
    let particles = [];
    const colors = ['#8b5cf6', '#06b6d4', '#4c1d95', '#164e63'];

    function resizeCanvas() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    class Particle {
      constructor() {
        this.reset();
      }
      reset() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.radius = Math.random() * 1.5 + 0.5;
        this.color = colors[Math.floor(Math.random() * colors.length)];
        this.vx = (Math.random() - 0.5) * 0.2;
        this.vy = (Math.random() - 0.5) * 0.2;
        this.alpha = Math.random() * 0.5 + 0.1;
      }
      update() {
        this.x += this.vx;
        this.y += this.vy;
        
        if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) {
          this.reset();
        }
      }
      draw() {
        ctx.save();
        ctx.globalAlpha = this.alpha;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();
        ctx.restore();
      }
    }

    // Initialize Particles
    for (let i = 0; i < 40; i++) {
      particles.push(new Particle());
    }

    function animate() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      particles.forEach(p => {
        p.update();
        p.draw();
      });
      requestAnimationFrame(animate);
    }
    animate();

    // GSAP animations
    document.addEventListener('DOMContentLoaded', () => {
      gsap.fromTo('.animate-fade-in', { opacity: 0 }, {
        opacity: 1,
        duration: 0.8,
        ease: 'power3.out'
      });

      gsap.fromTo('.animate-slide-up', { opacity: 0, y: 25 }, {
        opacity: 1,
        y: 0,
        duration: 0.7,
        stagger: 0.12,
        ease: 'power3.out'
      });
    });
  </script>
</body>
</html>

<!-- Styles specific to details page -->
