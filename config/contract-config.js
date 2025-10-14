// PARTIVOX Smart Contract Configuration
// This file contains all the important addresses and settings for your app

const CONTRACT_CONFIG = {
    // 🏦 TREASURY WALLET - This is where all your revenue goes!
    // ⚠️ IMPORTANT: Replace with your actual wallet address
    TREASURY_WALLET: "0x742d35Cc6634C0532925a3b8D0C9e3e0C8b0e4c8", // 🔴 REPLACE WITH YOUR WALLET
    
    // 📄 Smart Contract Addresses (set after deployment)
    DIAMOND_TOKEN_ADDRESS: "", // Will be set after deployment
    USDT_TOKEN_ADDRESS: "0xdAC17F958D2ee523a2206206994597C13D831ec7", // Mainnet USDT
    
    // 💰 Revenue Settings (configured in smart contract)
    REVENUE_SETTINGS: {
        DEPOSIT_REVENUE_PERCENTAGE: 20,    // 20% of all USDT deposits
        WITHDRAWAL_FEE_PERCENTAGE: 5,      // 5% of all USDT withdrawals
        MINIMUM_WITHDRAWAL_DIAMONDS: 3,    // Minimum 3 diamonds to withdraw
    },
    
    // 💎 Exchange Rate Settings
    EXCHANGE_RATE: {
        DIAMONDS_PER_USDT: 200,           // 1 USDT = 200 Diamonds
        USDT_PER_DIAMOND: 0.005,          // 1 Diamond = 0.005 USDT
    },
    
    // 🌐 Network Configuration
    NETWORKS: {
        mainnet: {
            name: "Ethereum Mainnet",
            chainId: 1,
            rpcUrl: "https://mainnet.infura.io/v3/YOUR_INFURA_KEY",
            usdtAddress: "0xdAC17F958D2ee523a2206206994597C13D831ec7"
        },
        sepolia: {
            name: "Sepolia Testnet",
            chainId: 11155111,
            rpcUrl: "https://sepolia.infura.io/v3/YOUR_INFURA_KEY",
            usdtAddress: "0x..." // Testnet USDT address
        },
        localhost: {
            name: "Local Development",
            chainId: 31337,
            rpcUrl: "http://127.0.0.1:8545",
            usdtAddress: "0x..." // Local MockUSDT address
        }
    }
};

// 🔧 Helper Functions
const ContractHelpers = {
    // Get current network config
    getCurrentNetwork: () => {
        const chainId = window.ethereum?.chainId;
        return Object.values(CONTRACT_CONFIG.NETWORKS).find(n => n.chainId === parseInt(chainId)) || CONTRACT_CONFIG.NETWORKS.localhost;
    },
    
    // Format treasury address for display
    formatTreasuryAddress: (address) => {
        if (!address) return "Not Set";
        return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
    },
    
    // Calculate revenue from amount
    calculateDepositRevenue: (usdtAmount) => {
        return usdtAmount * (CONTRACT_CONFIG.REVENUE_SETTINGS.DEPOSIT_REVENUE_PERCENTAGE / 100);
    },
    
    calculateWithdrawalFee: (usdtAmount) => {
        return usdtAmount * (CONTRACT_CONFIG.REVENUE_SETTINGS.WITHDRAWAL_FEE_PERCENTAGE / 100);
    },
    
    // Convert between diamonds and USDT
    diamondsToUSDT: (diamonds) => {
        return diamonds * CONTRACT_CONFIG.EXCHANGE_RATE.USDT_PER_DIAMOND;
    },
    
    usdtToDiamonds: (usdt) => {
        return usdt * CONTRACT_CONFIG.EXCHANGE_RATE.DIAMONDS_PER_USDT;
    }
};

// 📋 Deployment Checklist
const DEPLOYMENT_CHECKLIST = {
    "✅ Set Treasury Wallet Address": "Update TREASURY_WALLET with your actual wallet address",
    "✅ Deploy MockUSDT (testnet only)": "Deploy MockUSDT contract for testing",
    "✅ Deploy DiamondToken Contract": "Deploy main contract with treasury and USDT addresses",
    "✅ Update Contract Addresses": "Set DIAMOND_TOKEN_ADDRESS after deployment",
    "✅ Test Revenue Flow": "Verify deposits and withdrawals send revenue to treasury",
    "✅ Update Frontend Config": "Update contracts.js with deployed addresses",
    "✅ Test All Functions": "Test buy, convert, withdraw with real transactions"
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CONTRACT_CONFIG, ContractHelpers, DEPLOYMENT_CHECKLIST };
}

// Make available globally in browser
if (typeof window !== 'undefined') {
    window.CONTRACT_CONFIG = CONTRACT_CONFIG;
    window.ContractHelpers = ContractHelpers;
}

console.log("📋 PARTIVOX Contract Configuration Loaded");
console.log("🏦 Treasury Wallet:", CONTRACT_CONFIG.TREASURY_WALLET);
console.log("💰 Revenue: 20% deposits + 5% withdrawals");
console.log("💎 Exchange Rate: 1 USDT = 200 Diamonds");
