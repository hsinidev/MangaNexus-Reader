<?php
/**
 * header.php — Public Page Header Navigation
 */
?>
<header class="public-header">
  <div class="header-container">
    <!-- Brand Logo -->
    <a href="/" class="brand-logo">
      <div class="logo-box">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="logo-icon"><path d="M2 20h20"/><path d="M5 17V5a3 3 0 0 1 3-3h14"/><path d="M22 17H8a3 3 0 0 0-3 3"/></svg>
      </div>
      <span class="brand-text">MangaNexus</span>
    </a>

    <!-- Navigation Links -->
    <nav class="nav-links">
      <a href="/" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-10 9h3v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8h3L12 3z"/></svg>
        Manga Library
      </a>
      
      <a href="/#catalog-bar" class="nav-link">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        Advanced Search
      </a>

      <?php if (\MangaNexus\Security\Auth::isVisitorLoggedIn()): ?>
        <?php $visitor = \MangaNexus\Security\Auth::getVisitorUser(); ?>
        <span class="nav-link visitor-username">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?php echo htmlspecialchars($visitor['username'] ?? 'User'); ?>
        </span>
        <a href="/logout" class="nav-link logout-btn auth-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign out
        </a>
      <?php else: ?>
        <a href="/login" class="nav-link auth-btn">Sign in</a>
        <a href="/signup" class="nav-link auth-btn signup-accent">Sign up</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<style>
.public-header {
  position: fixed;
  top: 1rem;
  left: 50%;
  transform: translateX(-50%);
  width: calc(100% - 2rem);
  max-width: 1100px;
  height: 4rem;
  background-color: rgba(24, 24, 27, 0.65);
  border: 1px solid var(--theme-border);
  border-radius: 1rem;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  z-index: 100;
  padding: 0 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
  transition: border-color 0.3s ease;
}

.public-header:hover {
  border-color: rgba(255, 255, 255, 0.15);
}

.header-container {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
}

.logo-box {
  width: 2.25rem;
  height: 2.25rem;
  background: linear-gradient(to top right, var(--theme-primary), var(--theme-secondary));
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
  transition: transform 0.2s ease;
}

.brand-logo:hover .logo-box {
  transform: scale(1.05);
}

.logo-icon {
  color: #ffffff;
}

.brand-text {
  font-weight: 800;
  font-size: 1.125rem;
  letter-spacing: -0.025em;
  background: linear-gradient(to right, #ffffff, var(--theme-text-muted));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--theme-text-muted);
  text-decoration: none;
  border: 1px solid transparent;
  transition: all 0.2s ease;
}

.nav-link:hover {
  color: var(--theme-text);
  background-color: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.03);
}

.visitor-username {
  cursor: default;
  color: var(--theme-text) !important;
  font-weight: 700;
}

.signup-accent {
  background-color: var(--theme-primary) !important;
  color: #ffffff !important;
  box-shadow: 0 4px 12px rgba(var(--theme-primary-rgb), 0.25);
}

.signup-accent:hover {
  background-color: var(--theme-primary-hover) !important;
  color: #ffffff !important;
  box-shadow: 0 6px 16px rgba(var(--theme-primary-rgb), 0.4);
  transform: translateY(-1px);
}

.nav-links .auth-btn {
  border-radius: 9999px !important;
}
</style>

<?php $header_ad = show_ad('header'); if (!empty($header_ad)): ?>
  <div class="ad-container-header" style="max-width: 1100px; margin: 6rem auto 1rem auto; padding: 0 1.5rem; text-align: center; width: calc(100% - 3rem); box-sizing: border-box; overflow: hidden; z-index: 10;">
    <?php echo $header_ad; ?>
  </div>
<?php endif; ?>
