<?php
// ============================================================
// CAPTCHA - Generate and refresh CAPTCHA
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captcha = generateCaptcha(6);
    $_SESSION['captcha_text'] = $captcha;
    
    echo json_encode(['success' => true, 'captcha' => $captcha]);
    exit();
} else {
    // Generate CAPTCHA image (for direct image requests)
    $captcha = generateCaptcha(6);
    $_SESSION['captcha_text'] = $captcha;
    
    // Create image
    $width = 150;
    $height = 50;
    $image = imagecreate($width, $height);
    
    // Colors
    $bg = imagecolorallocate($image, 248, 250, 252);
    $text_color = imagecolorallocate($image, 15, 76, 129);
    $line_color = imagecolorallocate($image, 37, 99, 235);
    $noise_color = imagecolorallocate($image, 100, 116, 139);
    
    // Fill background
    imagefilledrectangle($image, 0, 0, $width, $height, $bg);
    
    // Add noise (dots)
    for ($i = 0; $i < 100; $i++) {
        imagesetpixel($image, rand(0, $width), rand(0, $height), $noise_color);
    }
    
    // Add random lines
    for ($i = 0; $i < 5; $i++) {
        imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $line_color);
    }
    
    // Add text with random rotation
    $font = 5;
    $text_width = imagefontwidth($font) * strlen($captcha);
    $text_x = ($width - $text_width) / 2;
    $text_y = ($height - imagefontheight($font)) / 2;
    imagestring($image, $font, $text_x, $text_y, $captcha, $text_color);
    
    // Output image
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
    exit();
}
?>