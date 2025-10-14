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