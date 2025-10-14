<?php
/**
 * PARTIVOX Image Path Fix Script
 * This script fixes all image path references in HTML files
 */

echo "🔧 PARTIVOX Image Path Fix Script\n";
echo "==================================\n\n";

// Define directories to scan
$directories = [
    __DIR__ . '/pages',
    __DIR__ . '/'
];

// Define path replacements
$pathReplacements = [
    // Fix relative paths from pages directory
    '../images/' => '../images/',
    '/assets/images/logo.png' => '../images/IconDiamond.png',
    '/images/' => '../images/',
    
    // Fix absolute paths
    'src="/assets/images/logo.png"' => 'src="../images/IconDiamond.png"',
    'src="/images/' => 'src="../images/',
];

// Additional missing images to create
$additionalImages = [
    'walletIcon.png' => ['👛', 40, 40, '#4f46e5', '#ffffff'],
    'upArrow.png' => ['↑', 24, 24, '#10b981', '#ffffff'],
    'bottom1.jpg' => ['IMG', 200, 200, '#6b7280', '#ffffff'],
];

// Create missing images
$imagesDir = __DIR__ . '/images';
foreach ($additionalImages as $filename => $config) {
    $filepath = $imagesDir . '/' . $filename;
    if (!file_exists($filepath)) {
        $svg = createSVGPlaceholder($filename, $config[0], $config[1], $config[2], $config[3], $config[4]);
        file_put_contents($filepath, $svg);
        echo "🎨 Created missing image: $filename\n";
    }
}

function createSVGPlaceholder($filename, $text, $width = 100, $height = 100, $bgColor = '#e0e0e0', $textColor = '#666') {
    $svg = <<<SVG
<svg width="$width" height="$height" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="$bgColor"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="12" fill="$textColor" text-anchor="middle" dominant-baseline="middle">$text</text>
</svg>
SVG;
    return $svg;
}

// Function to fix image paths in HTML files
function fixImagePaths($filePath) {
    global $pathReplacements;
    
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $changes = 0;
    
    // Apply path replacements
    foreach ($pathReplacements as $search => $replace) {
        $newContent = str_replace($search, $replace, $content);
        if ($newContent !== $content) {
            $changes += substr_count($content, $search);
            $content = $newContent;
        }
    }
    
    // Special fixes for specific patterns
    $patterns = [
        // Fix /assets/images/logo.png to ../images/IconDiamond.png
        '/\/assets\/images\/logo\.png/' => '../images/IconDiamond.png',
        // Fix /images/ to ../images/ for pages
        '/src="\/images\//' => 'src="../images/',
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $newContent = preg_replace($pattern, $replacement, $content);
        if ($newContent !== $content) {
            $changes++;
            $content = $newContent;
        }
    }
    
    // Write back if changes were made
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return $changes;
    }
    
    return 0;
}

// Scan and fix files
$totalFiles = 0;
$totalChanges = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'html') {
            $filePath = $file->getPathname();
            $relativePath = str_replace(__DIR__ . '/', '', $filePath);
            
            $changes = fixImagePaths($filePath);
            if ($changes > 0) {
                echo "🔧 Fixed $changes image paths in: $relativePath\n";
                $totalChanges += $changes;
            }
            $totalFiles++;
        }
    }
}

echo "\n📊 SUMMARY\n";
echo "==========\n";
echo "📁 Files scanned: $totalFiles\n";
echo "🔧 Total fixes applied: $totalChanges\n";

// Create a comprehensive image reference guide
$imageGuide = <<<GUIDE
# PARTIVOX Image Reference Guide

## Current Image Structure
```
/images/                    # Root images directory (for index.html)
├── IconDiamond.png        # Main PARTIVOX logo
├── blackDiamond.png       # Black diamond icon
├── diamond.png            # Diamond section icon
├── wallet.png             # Generic wallet icon
├── walletIcon.png         # Wallet icon for stats
├── metamaskIcon.png       # MetaMask wallet
├── walletconnectIcon.png  # WalletConnect
├── ethereumIcon.png       # Ethereum blockchain
├── coinLogo.png           # Coinbase wallet
├── phantom.png            # Phantom wallet
├── xLogo.png              # X (Twitter) logo
├── google.png             # Google icon
├── infinity.png           # Infinity feature icon
├── cube.png               # Cube feature icon
├── coin.png               # Coin feature icon
├── speakIcon.png          # Campaign/speaking icon
├── checkIcon.png          # Check/completion icon
├── upArrow.png            # Up arrow icon
├── avartar1.jpg           # User avatar 1
├── avarter2.png           # User avatar 2
├── avarter3.png           # User avatar 3
├── avarter4.jpg           # User avatar 4
├── avarter5.jpg           # User avatar 5
└── bottom1.jpg            # Task image placeholder
```

## Path Usage by Location

### Root Files (index.html)
Use: `images/filename.ext`
Example: `<img src="images/IconDiamond.png" alt="Logo">`

### Pages Directory (/pages/*.html)
Use: `../images/filename.ext`
Example: `<img src="../images/IconDiamond.png" alt="Logo">`

### Admin Pages (/pages/admin_dashboard/*.html)
Use: `../../images/filename.ext`
Example: `<img src="../../images/IconDiamond.png" alt="Logo">`

## Recommended Replacements

### Brand Consistency
- Replace all logo references with `IconDiamond.png`
- Use `blackDiamond.png` for dark theme elements
- Use `diamond.png` for section headers

### Wallet Icons
- MetaMask: `metamaskIcon.png`
- WalletConnect: `walletconnectIcon.png`
- Ethereum: `ethereumIcon.png`
- Coinbase: `coinLogo.png`
- Phantom: `phantom.png`
- Generic: `wallet.png` or `walletIcon.png`

### Social Media
- Twitter/X: `xLogo.png`
- Google: `google.png`

### UI Elements
- Campaign: `speakIcon.png`
- Completed: `checkIcon.png`
- Upload/Withdraw: `upArrow.png`

## Next Steps
1. Replace placeholder images with actual branded assets
2. Optimize all images for web (compress, resize)
3. Consider using WebP format for better performance
4. Add proper alt text for accessibility
5. Test all pages to ensure images load correctly
GUIDE;

file_put_contents(__DIR__ . '/IMAGE_REFERENCE_GUIDE.md', $imageGuide);
echo "\n📖 Created IMAGE_REFERENCE_GUIDE.md\n";

echo "\n✅ IMAGE PATH FIX COMPLETE!\n";
echo "============================\n";
echo "All image paths have been standardized.\n";
echo "Check the IMAGE_REFERENCE_GUIDE.md for detailed information.\n";

?>
