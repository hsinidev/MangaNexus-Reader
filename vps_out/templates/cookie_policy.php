<?php
/**
 * cookie_policy.php — Public Cookie Policy page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cookie Policy — <?php echo htmlspecialchars($site_title); ?></title>
  <?php 
    echo show_geo_hreflang_tags(); 
    echo show_social_seo_tags(
        "Cookie Policy - " . $site_title,
        "Read the official cookie consent and tracking policy for " . $site_title
    ); 
  ?>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">

  <!-- Header -->
  <?php require_once BASE_PATH . '/templates/header.php'; ?>

  <!-- Main Wrapper -->
  <div class="app-container">
    <div class="manga-card" style="margin-bottom: 2rem; padding: 3rem;">
      <h1 class="text-primary-gradient" style="font-size: 2rem; font-weight: 900; margin-bottom: 1.5rem; text-transform: uppercase;">Cookie Policy</h1>
      
      <p style="margin-bottom: 1.5rem; color: var(--theme-text);">This Cookie Policy explains how <strong><?php echo htmlspecialchars($site_title); ?></strong> uses cookies and similar technologies to recognize you when you visit our website. It explains what these technologies are and why we use them, as well as your rights to control our use of them.</p>

      <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--theme-text); margin: 2rem 0 1rem 0; text-transform: uppercase;">What are cookies?</h2>
      <p style="margin-bottom: 1.5rem; color: var(--theme-text-muted);">Cookies are small data files that are placed on your computer or mobile device when you visit a website. Cookies are widely used by website owners in order to make their websites work, or to work more efficiently, as well as to provide reporting information.</p>

      <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--theme-text); margin: 2rem 0 1rem 0; text-transform: uppercase;">Why do we use cookies?</h2>
      <p style="margin-bottom: 1.5rem; color: var(--theme-text-muted);">We use first-party and third-party cookies for several reasons. Some cookies are required for technical reasons in order for our Website to operate, and we refer to these as "essential" or "strictly necessary" cookies. Other cookies enable us to track and target the interests of our users to enhance the experience on our Online Properties.</p>

      <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--theme-text); margin: 2rem 0 1rem 0; text-transform: uppercase;">Cookies used on our site</h2>
      <table class="manga-table" style="margin: 1.5rem 0; width: 100%;">
        <thead>
          <tr>
            <th>Cookie Name</th>
            <th>Type</th>
            <th>Purpose</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><code>PHPSESSID</code></td>
            <td>Essential First-Party</td>
            <td>Keeps user and administrator session state active.</td>
            <td>Session</td>
          </tr>
          <tr>
            <td><code>csrf_token</code></td>
            <td>Essential First-Party</td>
            <td>Protects administrators and visitors against CSRF attacks.</td>
            <td>Session</td>
          </tr>
          <tr>
            <td><code>manganexus_cookie_consent</code></td>
            <td>First-Party Functionality</td>
            <td>Stores your choice regarding the cookie consent banner.</td>
            <td>30 days</td>
          </tr>
        </tbody>
      </table>

      <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--theme-text); margin: 2rem 0 1rem 0; text-transform: uppercase;">How can I control cookies?</h2>
      <p style="margin-bottom: 1.5rem; color: var(--theme-text-muted);">You have the right to decide whether to accept or reject cookies. You can exercise your cookie rights by setting your preferences in the Cookie Consent Banner or by adjusting your web browser controls to accept or refuse cookies.</p>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once BASE_PATH . '/templates/footer.php'; ?>

</body>
</html>
