<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.5" />
    <title>5G Election Guru · Login</title>
    <!-- Google Fonts + Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
</head>
<body>

<!-- ===== PRELOADER ===== -->
<div class="preloader" id="preloader">
    <div class="loader-ring"></div>
</div>

<!-- ===== LOGIN ===== -->
<div class="login-wrapper">
    <div class="login-card">
        <!-- Logo -->
        <div class="login-logo">
            <a href="#">
                <i class="fas fa-bolt"></i>
                5G Election Guru
            </a>
            <p>Enterprise Election Management Platform</p>
        </div>

        <!-- Form -->
        <form class="login-form" onsubmit="return false;">
            <h2>Welcome Back</h2>
            <p class="subtitle">Sign in to access your dashboard and manage elections.</p>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" placeholder="admin@organization.ng" required />
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" placeholder="••••••••" required />
                </div>
            </div>

            <div class="form-options">
                <label>
                    <input type="checkbox" /> Remember me
                </label>
                <a href="#">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-arrow-right-to-bracket"></i>
                Sign In
            </button>
        </form>

        <!-- Divider -->
        <div class="divider">or continue with</div>

        <!-- Social Buttons -->
        <div class="social-buttons">
            <button class="social-btn google">
                <i class="fab fa-google"></i> Google
            </button>
            <button class="social-btn microsoft">
                <i class="fab fa-microsoft"></i> Microsoft
            </button>
        </div>

        <!-- Register Link -->
        <div class="register-link">
            Don't have an account? <a href="#">Request Access</a>
        </div>

        <!-- Back to Home -->
        <div class="back-home">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i>
                Back to Homepage
            </a>
        </div>
    </div>
</div>

<!-- ===== JS ===== -->
<script src="assets/js/login.js"></script>

</body>
</html>