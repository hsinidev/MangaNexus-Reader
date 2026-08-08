<?php
/**
 * login.php — Public User/Visitor Login Page
 */

$error_msg = '';

if (\MangaNexus\Security\Auth::isVisitorLoggedIn()) {
    header("Location: /");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Note: CSRF token is verified globally in index.php for POST requests (if enabled for this route)
    // Wait, the global CSRF check in index.php only checks $is_admin_route.
    // So for public visitor POST actions, we should manually validate the CSRF token!
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!\MangaNexus\Security\Csrf::validate($csrfToken)) {
        $error_msg = 'Invalid CSRF security token.';
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($email) || empty($password)) {
            $error_msg = 'Please enter your email and password.';
        } elseif (\MangaNexus\Security\Auth::verifyVisitor($email, $password)) {
            header("Location: /");
            exit;
        } else {
            $error_msg = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — <?php echo htmlspecialchars($site_title); ?></title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">

  <!-- Header -->
  <?php require_once BASE_PATH . '/templates/header.php'; ?>

  <!-- Main Wrapper -->
  <div class="app-container" style="display: flex; align-items: center; justify-content: center; min-height: 80vh;">
    <div class="manga-card" style="width: 100%; max-width: 440px; padding: 2.5rem; background-color: rgba(18, 18, 20, 0.75);">
      <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="font-size: 1.5rem; font-weight: 800; text-transform: uppercase; color: var(--theme-text); margin-bottom: 0.5rem;">Sign In</h2>
        <p style="font-size: 0.75rem; color: var(--theme-text-muted);">Access your library account</p>
      </div>

      <?php if (!empty($error_msg)): ?>
        <div class="error-banner" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 1.5rem; color: #ef4444; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
      <?php endif; ?>

      <form action="" method="POST" class="crud-form">
        <?php echo \MangaNexus\Security\Csrf::getField(); ?>

        <div class="form-group">
          <label for="email" class="form-label">Email Address</label>
          <input type="email" name="email" id="email" class="form-input" placeholder="you@domain.com" required autocomplete="email">
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; margin-top: 1rem;">
          Sign In
        </button>
      </form>

      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: var(--theme-text-muted);">
        Don't have an account? <a href="/signup" class="dev-link" style="font-weight: 700;">Sign Up</a>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <?php require_once BASE_PATH . '/templates/footer.php'; ?>

</body>
</html>
