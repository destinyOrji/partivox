const { ethers } = require("hardhat");

async function main() {
    console.log("🚀 Deploying PARTIVOX Diamond Token Contract...");
    
    // Get the deployer account
    const [deployer] = await ethers.getSigners();
    console.log("📝 Deploying with account:", deployer.address);
    console.log("💰 Account balance:", ethers.formatEther(await deployer.provider.getBalance(deployer.address)), "ETH");
    
    // ⚠️ IMPORTANT: Set your app owner wallet address here
    // This is where all revenue (20% deposits + 5% withdrawals) will be sent
    const APP_OWNER_WALLET = "0x742d35Cc6634C0532925a3b8D0C9e3e0C8b0e4c8"; // 🔴 REPLACE WITH YOUR WALLET
    
    // Mock USDT contract address (replace with real USDT on mainnet)
    // Mainnet USDT: 0xdAC17F958D2ee523a2206206994597C13D831ec7
    // Testnet: Deploy MockUSDT first or use existing testnet USDT
    const USDT_CONTRACT_ADDRESS = "0x1234567890123456789012345678901234567890"; // 🔴 REPLACE WITH USDT ADDRESS
    
    console.log("🏦 App Owner Treasury:", APP_OWNER_WALLET);
    console.log("💵 USDT Contract:", USDT_CONTRACT_ADDRESS);
    
    // Deploy the Diamond Token contract
    const DiamondToken = await ethers.getContractFactory("DiamondToken");
    const diamondToken = await DiamondToken.deploy(
        USDT_CONTRACT_ADDRESS,  // USDT token address
        APP_OWNER_WALLET        // Treasury address (your wallet)
    );
    
    await diamondToken.waitForDeployment();
    const contractAddress = await diamondToken.getAddress();
    
    console.log("✅ DiamondToken deployed to:", contractAddress);
    console.log("💎 Token Name:", await diamondToken.name());
    console.log("🔤 Token Symbol:", await diamondToken.symbol());
    console.log("🏦 Treasury Address:", await diamondToken.treasury());
    
    // Verify the configuration
    console.log("\n📊 Contract Configuration:");
    console.log("- Exchange Rate: 1 USDT = 200 Diamonds (0.005 USDT per Diamond)");
    console.log("- Deposit Revenue: 20% to treasury");
    console.log("- Withdrawal Fee: 5% to treasury");
    console.log("- Minimum Withdrawal: 3 Diamonds");
    
    // Save deployment info
    const deploymentInfo = {
        network: "localhost", // Change based on your network
        contractAddress: contractAddress,
        treasuryAddress: APP_OWNER_WALLET,
        usdtAddress: USDT_CONTRACT_ADDRESS,
        deployedAt: new Date().toISOString(),
        deployer: deployer.address
    };
    
    console.log("\n💾 Save this deployment info:");
    console.log(JSON.stringify(deploymentInfo, null, 2));
    
    return {
        diamondToken,
        contractAddress,
        treasuryAddress: APP_OWNER_WALLET
    };
}

// Handle deployment
main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("❌ Deployment failed:", error);
        process.exit(1);
    });

module.exports = { main };
