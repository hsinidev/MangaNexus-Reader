<?php
/**
 * custom_page.php — Dynamic Custom Pages Viewer (HTML Renderer)
 */

if (!isset($custom_page)) {
    http_response_code(404);
    die("Page not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($custom_page['title']); ?> — <?php echo htmlspecialchars($site_title); ?></title>
  <?php 
    echo show_geo_hreflang_tags(); 
    echo show_social_seo_tags(
        $custom_page['title'] . " - " . $site_title,
        mb_substr(strip_tags($custom_page['content']), 0, 150) . "..."
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
      <!-- Content Rendered Directly as HTML -->
      <article class="custom-page-article">
        <?php echo $custom_page['content']; ?>
      </article>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once BASE_PATH . '/templates/footer.php'; ?>

</body>
</html>
