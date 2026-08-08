<?php
/**
 * admin_login.php — Admin Login Gate (username + password only)
 */

$error_msg = '';

if (\MangaNexus\Security\Auth::isLockedOut()) {
    $remaining = ceil(\MangaNexus\Security\Auth::getLockoutRemaining() / 60);
    $error_msg = "Too many failed attempts. Login locked out. Try again in $remaining minutes.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error_msg = 'Please enter your username and password.';
    } elseif (\MangaNexus\Security\Auth::verify($username, $password)) {
        header("Location: /" . $admin_slug);
        exit;
    } else {
        if (\MangaNexus\Security\Auth::isLockedOut()) {
            $remaining = ceil(\MangaNexus\Security\Auth::getLockoutRemaining() / 60);
            $error_msg = "Too many failed attempts. Login locked out. Try again in $remaining minutes.";
        } else {
            $error_msg = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — <?php echo htmlspecialchars($site_title); ?></title>
  <link rel="stylesheet" href="/theme.css">
  <link rel="stylesheet" href="/themes/theme-<?php echo htmlspecialchars($theme); ?>.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?> login-body">

  <canvas id="particle-canvas"></canvas>

  <div class="login-wrapper">
    <div class="login-card animate-scale-in">
      <div class="login-header">
        <div class="logo-box">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="logo-icon"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <h2>Admin Login</h2>
        <p>Enter your credentials to access the dashboard.</p>
      </div>

      <?php if (!empty($error_msg)): ?>
        <div class="error-banner">
          <svg class="error-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
      <?php endif; ?>

      <form action="" method="POST" class="login-form">
        <?php echo \MangaNexus\Security\Csrf::getField(); ?>
        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <input type="text" name="username" id="username" class="form-input" placeholder="admin" required autocomplete="username">
        </div>

        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <input type="password" name="password" id="password" class="form-input" placeholder="•••••" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary login-btn">
          Sign In
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </button>
      </form>
    </div>
  </div>

  <!-- Persistent Developer Footer inside Login Gate -->
  <?php require_once BASE_PATH . '/templates/footer.php'; ?>

  <!-- Scripts -->
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
        this.vx = (Math.random() - 0.5) * 0.15;
        this.vy = (Math.random() - 0.5) * 0.15;
        this.alpha = Math.random() * 0.4 + 0.1;
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

    for (let i = 0; i < 30; i++) {
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

    // GSAP entry on Login panel
    document.addEventListener('DOMContentLoaded', () => {
      gsap.from('.animate-scale-in', {
        opacity: 0,
        scale: 0.95,
        duration: 0.6,
        ease: 'back.out(1.4)'
      });
    });
  </script>
</body>
</html>

<!-- CSS Styles for Login page -->
<style>
.login-body {
  background-color: #02040a !important;
}

.login-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 1.5rem 2rem 1.5rem;
}

.login-card {
  width: 100%;
  max-width: 440px;
  background-color: rgba(18, 18, 20, 0.75);
  border: 1px solid var(--theme-border);
  border-radius: 1.5rem;
  padding: 2.5rem;
  box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  position: relative;
  overflow: hidden;
}

.login-header {
  text-align: center;
  margin-bottom: 2rem;
}

.login-header h2 {
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin-bottom: 0.35rem;
  color: #fff;
  text-transform: uppercase;
}

.login-header p {
  font-size: 0.75rem;
  color: var(--theme-text-muted);
}

.logo-box {
  width: 2.75rem;
  height: 2.75rem;
  background: linear-gradient(to top right, var(--theme-primary), var(--theme-secondary));
  border-radius: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem auto;
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.25);
}

.logo-icon {
  color: #fff;
}

.error-banner {
  background-color: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 0.75rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #ef4444;
  font-size: 0.75rem;
  font-weight: 600;
}

.error-icon {
  flex-shrink: 0;
}

.login-form .form-group {
  margin-bottom: 1.5rem;
}

.license-input {
  font-family: monospace;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.login-btn {
  width: 100%;
  justify-content: center;
  padding: 0.85rem !important;
  margin-top: 1rem;
}

.license-hint {
  font-size: 0.7rem;
  color: var(--theme-text-muted);
  margin-top: 0.4rem;
}

.license-status-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  margin-top: 1.25rem;
  font-size: 0.7rem;
  font-weight: 700;
  color: #22c55e;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
</style>
