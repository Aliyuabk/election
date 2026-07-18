<?php
// ============================================================
// CAPTCHA - Generate and refresh CAPTCHA
// ============================================================
require_once '../config/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

SessionManager::start();

// Handle JSON request for refreshing CAPTCHA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $captcha = generateCaptcha(6);
    $_SESSION['captcha_text'] = $captcha;
    
    echo json_encode(['success' => true, 'captcha' => $captcha]);
    exit();
}

// Generate CAPTCHA image
$captcha = generateCaptcha(6);
$_SESSION['captcha_text'] = $captcha;

// Create image
$width = 150;
$height = 50;
$image = imagecreatetruecolor($width, $height);

// Colors
$bg = imagecolorallocate($image, 248, 250, 252);
$text_color = imagecolorallocate($image, 15, 76, 129);
$line_color = imagecolorallocate($image, 37, 99, 235);
$noise_color = imagecolorallocate($image, 100, 116, 139);

// Fill background
imagefilledrectangle($image, 0, 0, $width, $height, $bg);

// Add grid pattern
for ($x = 0; $x < $width; $x += 10) {
    for ($y = 0; $y < $height; $y += 10) {
        if (rand(0, 1)) {
            imagesetpixel($image, $x, $y, $noise_color);
        }
    }
}

// Add random lines
for ($i = 0; $i < 5; $i++) {
    imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $line_color);
}

// Add random arcs
for ($i = 0; $i < 3; $i++) {
    imagearc($image, rand(0, $width), rand(0, $height), rand(20, 40), rand(20, 40), 0, 360, $line_color);
}

// Add text with random rotation and positioning
$font_size = 5;
$text_width = imagefontwidth($font_size) * strlen($captcha);
$text_x = ($width - $text_width) / 2 + rand(-5, 5);
$text_y = ($height - imagefontheight($font_size)) / 2 + rand(-3, 3);

// Draw each character with slight variation
for ($i = 0; $i < strlen($captcha); $i++) {
    $char_x = $text_x + ($i * imagefontwidth($font_size)) + rand(-2, 2);
    $char_y = $text_y + rand(-2, 2);
    imagestring($image, $font_size, $char_x, $char_y, $captcha[$i], $text_color);
}

// Output image
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

imagepng($image);
imagedestroy($image);
exit();
?>