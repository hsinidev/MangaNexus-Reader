<?php
/**
 * footer.php — Reusable Public & Admin Footer displaying Developer Credits
 */

$is_admin_page = false;
if (isset($is_admin_route) && $is_admin_route === true) {
    $is_admin_page = true;
} elseif (isset($admin_slug) && str_contains($_SERVER['REQUEST_URI'], '/' . $admin_slug)) {
    $is_admin_page = true;
}
if (!$is_admin_page) {
    $footer_ad = show_ad('footer');
    if (!empty($footer_ad)) {
        echo '<div class="ad-container-footer" style="max-width: 1100px; margin: 2rem auto 2rem auto; padding: 0 1.5rem; text-align: center; width: calc(100% - 3rem); box-sizing: border-box; overflow: hidden; z-index: 10;">' . $footer_ad . '</div>';
    }

    // Dynamic Custom CSS Selector Injection (JS-Based Ad Inserter)
    try {
        $db = \MangaNexus\Database\Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM ad_blocks WHERE insertion_type = 'custom_selector' AND is_active = 1 ORDER BY block_index ASC");
        $stmt->execute();
        $custom_blocks = $stmt->fetchAll();
        
        $valid_custom_ads = [];
        $is_mobile = is_mobile_device();
        $current_page = isset($GLOBALS['page_type']) ? $GLOBALS['page_type'] : 'all';
        
        foreach ($custom_blocks as $block) {
            $device_target = $block['target_devices'] ?? 'all';
            if ($device_target === 'desktop' && $is_mobile) continue;
            if ($device_target === 'mobile' && !$is_mobile) continue;
            
            $page_target = $block['target_pages'] ?? 'all';
            if ($page_target !== 'all' && $page_target !== $current_page) continue;
            
            if (!empty($block['code']) && !empty($block['custom_selector'])) {
                $valid_custom_ads[] = [
                    'selector' => $block['custom_selector'],
                    'action' => $block['selector_action'] ?? 'before',
                    'code' => $block['code'],
                    'class' => $block['wrapper_class'] ?? 'ad-block-wrapper',
                    'style' => $block['wrapper_style'] ?? '',
                    'index' => $block['block_index']
                ];
            }
        }
        
        if (!empty($valid_custom_ads)): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const customAds = <?php echo json_encode($valid_custom_ads); ?>;
                    customAds.forEach(ad => {
                        try {
                            const elements = document.querySelectorAll(ad.selector);
                            elements.forEach(el => {
                                if (el.hasAttribute('data-ad-injected-' + ad.index)) return;
                                el.setAttribute('data-ad-injected-' + ad.index, 'true');

                                const container = document.createElement('div');
                                container.className = ad.class;
                                if (ad.style) {
                                    container.setAttribute('style', ad.style);
                                }
                                container.setAttribute('data-block-id', ad.index);
                                container.innerHTML = ad.code;
                                
                                if (ad.action === 'before') {
                                    el.parentNode.insertBefore(container, el);
                                } else if (ad.action === 'after') {
                                    el.parentNode.insertBefore(container, el.nextSibling);
                                } else if (ad.action === 'prepend') {
                                    el.insertBefore(container, el.firstChild);
                                } else if (ad.action === 'append') {
                                    el.appendChild(container);
                                }
                                
                                const scripts = container.querySelectorAll('script');
                                scripts.forEach(oldScript => {
                                    const newScript = document.createElement('script');
                                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                                    oldScript.parentNode.replaceChild(newScript, oldScript);
                                });
                            });
                        } catch (err) {
                            console.error("Ad Inserter injection failed for selector:", ad.selector, err);
                        }
                    });
                });
            </script>
        <?php endif;
    } catch (PDOException $e) {
        // Fallback
    }
}
?>
<footer class="site-footer">
  <?php if (!$is_admin_page): ?>
    <?php
      $socials = [];
      if (!empty($settings['social_links'])) {
          $socials = json_decode($settings['social_links'], true) ?: [];
      }
      $socials = array_filter($socials);
    ?>
    <?php if (!empty($socials)): ?>
      <div class="footer-social-section">
        <div class="footer-social-links">
          <?php foreach ($socials as $platform => $url): ?>
            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" class="social-icon-circle social-<?php echo $platform; ?>" title="<?php echo ucfirst($platform); ?>">
              <?php if ($platform === 'facebook'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
              <?php elseif ($platform === 'twitter'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"/></svg>
              <?php elseif ($platform === 'linkedin'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
              <?php elseif ($platform === 'tumblr'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2.5a.5.5 0 0 0-.5-.5h-2a.5.5 0 0 0-.5.5V6H7.5a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 .5.5H9v6.5a4.5 4.5 0 0 0 4.5 4.5h2a.5.5 0 0 0 .5-.5v-2a.5.5 0 0 0-.5-.5h-1a1.5 1.5 0 0 1-1.5-1.5V9h2.5a.5.5 0 0 0 .5-.5v-2a.5.5 0 0 0-.5-.5H11V2.5z"/></svg>
              <?php elseif ($platform === 'pinterest'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
              <?php elseif ($platform === 'youtube'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>
              <?php elseif ($platform === 'discord'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><path d="M12 2a10 10 0 0 0-7.38 16.75 1 1 0 0 1-.22.68L3 21a.5.5 0 0 0 .7.75l2.25-1.5a1 1 0 0 1 .63-.25 10 10 0 1 0 10.84-18H12z"/></svg>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="footer-divider"></div>
  <div class="footer-content">
    <!-- Left Section: Copyright / Branding -->
    <div class="footer-left">
      <p class="copyright">&copy; <?php echo date('Y'); ?> <strong>MangaNexus</strong>. All rights reserved.</p>
      <p class="tagline">High-performance self-hosted manga portal.</p>
      
      <?php if (!$is_admin_page): ?>
        <?php
          try {
              $footer_pages = db_fetch_all("SELECT title, slug FROM custom_pages WHERE is_published = 1 AND show_in_footer = 1 ORDER BY created_at ASC");
          } catch (PDOException $e) {
              $footer_pages = [];
          }
        ?>
        <div class="footer-menu" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
          <a href="/" style="color: var(--theme-text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-primary)'" onmouseout="this.style.color='var(--theme-text-muted)'">Home</a>
          <a href="/blog" style="color: var(--theme-text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-primary)'" onmouseout="this.style.color='var(--theme-text-muted)'">Blog</a>
          <?php foreach ($footer_pages as $fp): ?>
            <a href="/<?php echo htmlspecialchars($fp['slug']); ?>" style="color: var(--theme-text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-primary)'" onmouseout="this.style.color='var(--theme-text-muted)'"><?php echo htmlspecialchars($fp['title']); ?></a>
          <?php endforeach; ?>
          <a href="/cookie-policy" style="color: var(--theme-text-muted); text-decoration: none; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; transition: color 0.2s;" onmouseover="this.style.color='var(--theme-primary)'" onmouseout="this.style.color='var(--theme-text-muted)'">Cookie Policy</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Center/Right Section: Developer Credits (Non-Removable / Secure) -->
    <?php if ($is_admin_page): ?>
    <div class="footer-credits">
      <div class="credits-header">SYSTEM ARCHITECT</div>
      <div class="developer-profile">
        <div class="avatar-container">
          <!-- Checks for HSINI.jfif in root, falls back to inline CSS avatar -->
          <?php if (file_exists(BASE_PATH . '/HSINI.jfif')): ?>
            <img src="/HSINI.jfif" alt="hsini mohamed" class="developer-avatar" onerror="this.style.display='none'; document.getElementById('fallback-avatar').style.display='flex';">
          <?php endif; ?>
          <div id="fallback-avatar" class="avatar-fallback" style="<?php echo file_exists(BASE_PATH . '/HSINI.jfif') ? 'display:none;' : 'display:flex;'; ?>">HM</div>
        </div>
        <div class="developer-info">
          <div class="developer-name">hsini mohamed</div>
          <div class="developer-email"><a href="mailto:contact@hsini.dev">contact@hsini.dev</a></div>
          <div class="developer-links">
            <a href="https://hsini.dev" target="_blank" rel="noopener" class="dev-link">Website</a> &bull;
            <a href="https://github.com/hsinidev" target="_blank" rel="noopener" class="dev-link">GitHub</a> &bull;
            <a href="https://linkedin.com/in/hsinidev/" target="_blank" rel="noopener" class="dev-link">LinkedIn</a>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</footer>

<style>
.site-footer {
  width: 100%;
  max-width: 1100px;
  margin: 4rem auto 0 auto;
  padding: 0 1.5rem 2rem 1.5rem;
  z-index: 10;
  position: relative;
}

.footer-divider {
  width: 100%;
  height: 1px;
  background: linear-gradient(to right, transparent, var(--theme-border), transparent);
  margin-bottom: 2rem;
}

.footer-content {
  display: flex;
  flex-direction: column;
  gap: 2rem;
  align-items: center;
  text-align: center;
}

@media(min-width: 768px) {
  .footer-content {
    flex-direction: row;
    justify-content: space-between;
    text-align: left;
    align-items: flex-start;
  }
}

.footer-left .copyright {
  font-size: 0.8125rem;
  color: var(--theme-text);
  margin-bottom: 0.25rem;
}

.footer-left .tagline {
  font-size: 0.75rem;
  color: var(--theme-text-muted);
}

.footer-credits {
  background-color: rgba(var(--theme-card-rgb), 0.5);
  border: 1px solid var(--theme-border);
  border-radius: 1rem;
  padding: 1rem 1.25rem;
  max-width: 340px;
  width: 100%;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.credits-header {
  font-size: 0.625rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: var(--theme-primary);
  margin-bottom: 0.75rem;
  text-transform: uppercase;
}

.developer-profile {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.avatar-container {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid var(--theme-border);
  flex-shrink: 0;
  background-color: #1f2937;
}

.developer-avatar {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.875rem;
  color: #ffffff;
  background: linear-gradient(to bottom right, var(--theme-primary), var(--theme-secondary));
}

.developer-name {
  font-weight: 700;
  font-size: 0.875rem;
  color: var(--theme-text);
}

.developer-email {
  font-size: 0.75rem;
  margin-bottom: 0.25rem;
}

.developer-email a {
  color: var(--theme-text-muted);
  text-decoration: none;
}

.developer-email a:hover {
  color: var(--theme-primary);
  text-decoration: underline;
}

.developer-links {
  font-size: 0.6875rem;
  color: var(--theme-text-muted);
}

.dev-link {
  color: var(--theme-secondary);
  text-decoration: none;
  font-weight: 600;
}

.dev-link:hover {
  text-decoration: underline;
}
</style>

<?php if (!$is_admin_page): ?>
  <!-- Cookie Consent Banner -->
  <div id="cookie-consent" class="cookie-consent-banner">
    <div class="cookie-consent-text">
      We use cookies to optimize our library and enhance your scanlation experience. Read our <a href="/cookie-policy">Cookie Policy</a> to learn more.
    </div>
    <div class="cookie-consent-buttons">
      <button id="cookie-accept-btn" class="btn btn-primary cookie-consent-btn">Accept</button>
    </div>
  </div>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      // Check if consent cookie exists
      const getCookie = (name) => {
        const matches = document.cookie.match(new RegExp(
          "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
      };

      const setCookie = (name, value, days) => {
        let expires = "";
        if (days) {
          const date = new Date();
          date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
          expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/; SameSite=Lax";
      };

      if (!getCookie("manganexus_cookie_consent")) {
        const banner = document.getElementById("cookie-consent");
        setTimeout(() => {
          banner.classList.add("show");
        }, 1000);

        document.getElementById("cookie-accept-btn").addEventListener("click", function() {
          setCookie("manganexus_cookie_consent", "accepted", 30);
          banner.classList.remove("show");
        });
      }

      // Global Collapsible Blog Toggle
      window.toggleBlogArticle = function(btn) {
        const wrapper = btn.closest('section, div.home-seo-blog, div.blog-section').querySelector('.blog-article-collapsed-wrapper');
        if (!wrapper) return;
        const isExpanded = wrapper.classList.toggle('expanded');
        if (isExpanded) {
          wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
          btn.innerHTML = 'Read Less <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="transform: rotate(180deg); margin-left: 0.25rem;"><polyline points="6 9 12 15 18 9"/></svg>';
          btn.classList.add('active');
        } else {
          wrapper.style.maxHeight = '3.5em';
          btn.innerHTML = 'Read More <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 0.25rem;"><polyline points="6 9 12 15 18 9"/></svg>';
          btn.classList.remove('active');
          wrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      };
    });
  </script>
<?php endif; ?>
