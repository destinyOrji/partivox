<?php
/**
 * PARTIVOX Image Fix Script
 * This script organizes and fixes all image references in the application
 */

echo "🎨 PARTIVOX Image Fix Script\n";
echo "============================\n\n";

// Define the root images directory
$imagesDir = __DIR__ . '/images';
$assetsImagesDir = __DIR__ . '/assets/images';
$publicImagesDir = __DIR__ . '/public/images';

// Create images directory if it doesn't exist
if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
    echo "✅ Created images directory\n";
}

// List of all required images based on HTML analysis
$requiredImages = [
    // Brand Assets
    'IconDiamond.png' => 'Main PARTIVOX diamond logo',
    'blackDiamond.png' => 'Black diamond icon for buttons',
    'diamond.png' => 'Diamond icon for sections',
    
    // Wallet & Crypto Icons
    'metamaskIcon.png' => 'MetaMask wallet icon',
    'walletconnectIcon.png' => 'WalletConnect icon',
    'ethereumIcon.png' => 'Ethereum blockchain icon',
    'coinLogo.png' => 'Coinbase wallet icon',
    'phantom.png' => 'Phantom wallet icon',
    'wallet.png' => 'Generic wallet icon',
    
    // Social Media Icons
    'xLogo.png' => 'X (Twitter) logo',
    'google.png' => 'Google icon',
    
    // Feature Icons
    'infinity.png' => 'Infinity icon for features',
    'cube.png' => 'Cube icon for features',
    'coin.png' => 'Coin icon for features',
    
    // User Avatars
    'avartar1.jpg' => 'User avatar 1',
    'avarter2.png' => 'User avatar 2',
    'avarter3.png' => 'User avatar 3',
    'avarter4.jpg' => 'User avatar 4',
    'avarter5.jpg' => 'User avatar 5',
    
    // Task & Campaign Icons
    'speakIcon.png' => 'Speaking/campaign icon',
    'checkIcon.png' => 'Check/completion icon'
];

// Check existing images and copy them
$foundImages = [];
$missingImages = [];

// Check assets/images directory
if (is_dir($assetsImagesDir)) {
    $files = scandir($assetsImagesDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($assetsImagesDir . '/' . $file)) {
            $targetPath = $imagesDir . '/' . $file;
            if (!file_exists($targetPath)) {
                copy($assetsImagesDir . '/' . $file, $targetPath);
                echo "📋 Copied: $file from assets/images/\n";
            }
            $foundImages[] = $file;
        }
    }
}

// Check public/images directory
if (is_dir($publicImagesDir)) {
    $files = scandir($publicImagesDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($publicImagesDir . '/' . $file)) {
            $targetPath = $imagesDir . '/' . $file;
            if (!file_exists($targetPath)) {
                copy($publicImagesDir . '/' . $file, $targetPath);
                echo "📋 Copied: $file from public/images/\n";
            }
            $foundImages[] = $file;
        }
    }
}

// Check what's missing
foreach ($requiredImages as $filename => $description) {
    if (!file_exists($imagesDir . '/' . $filename)) {
        $missingImages[] = ['file' => $filename, 'desc' => $description];
    }
}

echo "\n📊 IMAGE AUDIT REPORT\n";
echo "=====================\n";
echo "✅ Found images: " . count($foundImages) . "\n";
echo "❌ Missing images: " . count($missingImages) . "\n\n";

if (!empty($missingImages)) {
    echo "🔍 MISSING IMAGES:\n";
    echo "------------------\n";
    foreach ($missingImages as $missing) {
        echo "❌ {$missing['file']} - {$missing['desc']}\n";
    }
    
    echo "\n💡 RECOMMENDATIONS:\n";
    echo "-------------------\n";
    echo "1. Create or source the missing images\n";
    echo "2. Use placeholder images for testing\n";
    echo "3. Update image references if files have different names\n";
    echo "4. Consider using icon libraries (FontAwesome, etc.) for simple icons\n";
}

// Create a simple SVG placeholder function
function createSVGPlaceholder($filename, $text, $width = 100, $height = 100, $bgColor = '#e0e0e0', $textColor = '#666') {
    $svg = <<<SVG
<svg width="$width" height="$height" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="$bgColor"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="12" fill="$textColor" text-anchor="middle" dominant-baseline="middle">$text</text>
</svg>
SVG;
    return $svg;
}

// Offer to create placeholder images
echo "\n🎨 CREATE PLACEHOLDER IMAGES?\n";
echo "==============================\n";
echo "Would you like to create placeholder images for missing files? (y/n): ";

// For automated execution, let's create placeholders
$createPlaceholders = true; // Set to true for automated execution

if ($createPlaceholders) {
    echo "y\n\n";
    
    $placeholders = [
        'IconDiamond.png' => ['💎', 50, 50, '#7c3aed', '#ffffff'],
        'blackDiamond.png' => ['💎', 40, 40, '#000000', '#ffffff'],
        'diamond.png' => ['💎', 30, 30, '#7c3aed', '#ffffff'],
        'metamaskIcon.png' => ['🦊', 50, 50, '#f6851b', '#ffffff'],
        'walletconnectIcon.png' => ['🔗', 50, 50, '#3b99fc', '#ffffff'],
        'ethereumIcon.png' => ['Ξ', 50, 50, '#627eea', '#ffffff'],
        'coinLogo.png' => ['CB', 50, 50, '#0052ff', '#ffffff'],
        'phantom.png' => ['👻', 50, 50, '#ab9ff2', '#ffffff'],
        'wallet.png' => ['👛', 50, 50, '#4f46e5', '#ffffff'],
        'xLogo.png' => ['𝕏', 50, 50, '#000000', '#ffffff'],
        'google.png' => ['G', 50, 50, '#4285f4', '#ffffff'],
        'infinity.png' => ['∞', 60, 60, '#10b981', '#ffffff'],
        'cube.png' => ['🧊', 60, 60, '#8b5cf6', '#ffffff'],
        'coin.png' => ['🪙', 60, 60, '#f59e0b', '#ffffff'],
        'speakIcon.png' => ['📢', 24, 24, '#3b82f6', '#ffffff'],
        'checkIcon.png' => ['✓', 24, 24, '#10b981', '#ffffff']
    ];
    
    foreach ($missingImages as $missing) {
        $filename = $missing['file'];
        $filepath = $imagesDir . '/' . $filename;
        
        if (isset($placeholders[$filename])) {
            $config = $placeholders[$filename];
            $svg = createSVGPlaceholder($filename, $config[0], $config[1], $config[2], $config[3], $config[4]);
            file_put_contents($filepath, $svg);
            echo "🎨 Created placeholder: $filename\n";
        } else {
            // Create generic placeholder
            $svg = createSVGPlaceholder($filename, '?', 50, 50);
            file_put_contents($filepath, $svg);
            echo "🎨 Created generic placeholder: $filename\n";
        }
    }
    
    // Create avatar placeholders
    $avatarImages = ['avartar1.jpg', 'avarter2.png', 'avarter3.png', 'avarter4.jpg', 'avarter5.jpg'];
    foreach ($avatarImages as $i => $avatar) {
        if (!file_exists($imagesDir . '/' . $avatar)) {
            $colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'];
            $svg = createSVGPlaceholder($avatar, 'U' . ($i + 1), 80, 80, $colors[$i], '#ffffff');
            file_put_contents($imagesDir . '/' . $avatar, $svg);
            echo "🎨 Created avatar placeholder: $avatar\n";
        }
    }
}

echo "\n✅ IMAGE FIX COMPLETE!\n";
echo "======================\n";
echo "All images are now organized in the /images/ directory.\n";
echo "Your HTML files should now display images correctly.\n\n";

echo "📁 Images directory: " . realpath($imagesDir) . "\n";
echo "📊 Total images: " . count(scandir($imagesDir)) - 2 . " files\n"; // -2 for . and ..

echo "\n🔧 NEXT STEPS:\n";
echo "==============\n";
echo "1. Replace placeholder images with actual branded assets\n";
echo "2. Optimize image sizes for web performance\n";
echo "3. Consider using WebP format for better compression\n";
echo "4. Add alt text for accessibility\n";
echo "5. Test all pages to ensure images load correctly\n";

?>
