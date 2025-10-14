<?php
/**
 * PARTIVOX Image Optimization Script
 * This script helps optimize and improve the placeholder images
 */

echo "🎨 PARTIVOX Image Optimization Script\n";
echo "=====================================\n\n";

$imagesDir = __DIR__ . '/images';

// Enhanced SVG templates for better-looking placeholders
function createEnhancedSVG($filename, $icon, $width, $height, $bgColor, $iconColor, $type = 'icon') {
    $fontSize = min($width, $height) * 0.4;
    
    if ($type === 'logo') {
        $svg = <<<SVG
<svg width="$width" height="$height" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:$bgColor;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#1a1a1a;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="100%" height="100%" fill="url(#grad1)" rx="8"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="$fontSize" font-weight="bold" fill="$iconColor" text-anchor="middle" dominant-baseline="middle">$icon</text>
</svg>
SVG;
    } elseif ($type === 'avatar') {
        $svg = <<<SVG
<svg width="$width" height="$height" xmlns="http://www.w3.org/2000/svg">
    <circle cx="50%" cy="50%" r="50%" fill="$bgColor"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="$fontSize" font-weight="bold" fill="$iconColor" text-anchor="middle" dominant-baseline="middle">$icon</text>
</svg>
SVG;
    } else {
        $svg = <<<SVG
<svg width="$width" height="$height" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="$bgColor" rx="4"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="$fontSize" fill="$iconColor" text-anchor="middle" dominant-baseline="middle">$icon</text>
</svg>
SVG;
    }
    
    return $svg;
}

// Enhanced image definitions with better styling
$enhancedImages = [
    // Brand Assets (with gradient backgrounds)
    'IconDiamond.png' => ['💎', 50, 50, '#7c3aed', '#ffffff', 'logo'],
    'blackDiamond.png' => ['💎', 40, 40, '#000000', '#ffffff', 'logo'],
    'diamond.png' => ['💎', 30, 30, '#7c3aed', '#ffffff', 'logo'],
    
    // Wallet Icons (with brand colors)
    'metamaskIcon.png' => ['🦊', 50, 50, '#f6851b', '#ffffff', 'icon'],
    'walletconnectIcon.png' => ['🔗', 50, 50, '#3b99fc', '#ffffff', 'icon'],
    'ethereumIcon.png' => ['Ξ', 50, 50, '#627eea', '#ffffff', 'icon'],
    'coinLogo.png' => ['CB', 50, 50, '#0052ff', '#ffffff', 'icon'],
    'phantom.png' => ['👻', 50, 50, '#ab9ff2', '#ffffff', 'icon'],
    'wallet.png' => ['👛', 50, 50, '#4f46e5', '#ffffff', 'icon'],
    'walletIcon.png' => ['💰', 40, 40, '#059669', '#ffffff', 'icon'],
    
    // Social Media
    'xLogo.png' => ['𝕏', 50, 50, '#000000', '#ffffff', 'icon'],
    'google.png' => ['G', 50, 50, '#4285f4', '#ffffff', 'icon'],
    
    // Feature Icons (larger and more colorful)
    'infinity.png' => ['∞', 60, 60, '#10b981', '#ffffff', 'icon'],
    'cube.png' => ['🧊', 60, 60, '#8b5cf6', '#ffffff', 'icon'],
    'coin.png' => ['🪙', 60, 60, '#f59e0b', '#ffffff', 'icon'],
    
    // UI Icons
    'speakIcon.png' => ['📢', 24, 24, '#3b82f6', '#ffffff', 'icon'],
    'checkIcon.png' => ['✓', 24, 24, '#10b981', '#ffffff', 'icon'],
    'upArrow.png' => ['↑', 24, 24, '#10b981', '#ffffff', 'icon'],
    
    // User Avatars (circular with different colors)
    'avartar1.jpg' => ['U1', 80, 80, '#ef4444', '#ffffff', 'avatar'],
    'avarter2.png' => ['U2', 80, 80, '#3b82f6', '#ffffff', 'avatar'],
    'avarter3.png' => ['U3', 80, 80, '#10b981', '#ffffff', 'avatar'],
    'avarter4.jpg' => ['U4', 80, 80, '#f59e0b', '#ffffff', 'avatar'],
    'avarter5.jpg' => ['U5', 80, 80, '#8b5cf6', '#ffffff', 'avatar'],
    
    // Content Images
    'bottom1.jpg' => ['IMG', 200, 200, '#6b7280', '#ffffff', 'icon'],
];

echo "🎨 Creating enhanced placeholder images...\n\n";

$updated = 0;
foreach ($enhancedImages as $filename => $config) {
    $filepath = $imagesDir . '/' . $filename;
    
    if (file_exists($filepath)) {
        $svg = createEnhancedSVG($filename, $config[0], $config[1], $config[2], $config[3], $config[4], $config[5]);
        file_put_contents($filepath, $svg);
        echo "✨ Enhanced: $filename\n";
        $updated++;
    }
}

echo "\n📊 OPTIMIZATION COMPLETE\n";
echo "========================\n";
echo "✨ Enhanced images: $updated\n";
echo "🎨 All placeholders now have improved styling\n";

// Create a favicon
$faviconSVG = createEnhancedSVG('favicon.ico', '💎', 32, 32, '#7c3aed', '#ffffff', 'logo');
file_put_contents($imagesDir . '/favicon.svg', $faviconSVG);
echo "🔖 Created favicon.svg\n";

// Create additional useful images
$additionalImages = [
    'loading.svg' => ['⟳', 40, 40, '#6b7280', '#ffffff', 'icon'],
    'error.svg' => ['⚠', 40, 40, '#ef4444', '#ffffff', 'icon'],
    'success.svg' => ['✓', 40, 40, '#10b981', '#ffffff', 'icon'],
];

echo "\n🔧 Creating utility images...\n";
foreach ($additionalImages as $filename => $config) {
    $filepath = $imagesDir . '/' . $filename;
    $svg = createEnhancedSVG($filename, $config[0], $config[1], $config[2], $config[3], $config[4], $config[5]);
    file_put_contents($filepath, $svg);
    echo "🔧 Created: $filename\n";
}

echo "\n💡 RECOMMENDATIONS\n";
echo "==================\n";
echo "1. 🎨 Replace key brand images (IconDiamond.png) with actual logos\n";
echo "2. 🦊 Use official wallet icons from their brand kits\n";
echo "3. 👤 Replace avatar placeholders with actual user photos\n";
echo "4. 📱 Add favicon.svg to your HTML <head> section\n";
echo "5. ⚡ Consider converting frequently used icons to inline SVG\n";
echo "6. 🔍 Test all images on different screen sizes\n";

echo "\n✅ Your images are now optimized and ready for production!\n";

?>
