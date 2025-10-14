const { ethers } = require("hardhat");
const hre = require("hardhat");

async function main() {
    console.log("🚀 Deploying PARTIVOX Diamond Token Contract...");
    console.log("=" .repeat(50));
    
    // Get the deployer account
    const [deployer] = await ethers.getSigners();
    console.log("Deploying contracts with account:", deployer.address);
    console.log("Account balance:", (await deployer.getBalance()).toString());
    
    // Contract addresses (update these for your target network)
    const USDT_ADDRESS = {
        // Ethereum Mainnet
        mainnet: "0xdAC17F958D2ee523a2206206994597C13D831ec7",
        // Polygon Mainnet
        polygon: "0xc2132D05D31c914a87C6611C10748AEb04B58e8F",
        // BSC Mainnet
        bsc: "0x55d398326f99059fF775485246999027B3197955",
        // Ethereum Sepolia Testnet
        sepolia: "0x7169D38820dfd117C3FA1f22a697dBA58d90BA06",
        // Polygon Mumbai Testnet
        mumbai: "0x326C977E6efc84E512bB9C30f76E30c160eD06FB",
        // BSC Testnet
        bscTestnet: "0x337610d27c682E347C9cD60BD4b3b107C9d34dDd"
    };
    
    // Get network name
    const network = await ethers.provider.getNetwork();
    const networkName = network.name === "unknown" ? "localhost" : network.name;
    
    console.log("Network:", networkName);
    
    // Select USDT address based on network
    let usdtAddress;
    if (networkName === "localhost" || networkName === "hardhat") {
        // For local testing, deploy a mock USDT token
        console.log("📄 Deploying Mock USDT for local testing...");
        const MockUSDT = await ethers.getContractFactory("MockUSDT");
        const mockUSDT = await MockUSDT.deploy();
        await mockUSDT.waitForDeployment();
        usdtAddress = await mockUSDT.getAddress();
        console.log("✅ Mock USDT deployed to:", usdtAddress);
    } else {
        usdtAddress = USDT_ADDRESS[networkName];
        if (!usdtAddress) {
            throw new Error(`USDT address not configured for network: ${networkName}`);
        }
    }
    
    // Treasury address (you should change this to your actual treasury)
    const treasuryAddress = deployer.address; // Using deployer as treasury for now
    
    // Deploy DiamondToken contract
    console.log("💎 Deploying DiamondToken contract...");
    const DiamondToken = await ethers.getContractFactory("DiamondToken");
    const diamondToken = await DiamondToken.deploy(usdtAddress, treasuryAddress);
    
    await diamondToken.waitForDeployment();
    const diamondTokenAddress = await diamondToken.getAddress();
    
    console.log("✅ DiamondToken deployed to:", diamondTokenAddress);
    console.log("🏦 USDT Token address:", usdtAddress);
    console.log("💰 Treasury address:", treasuryAddress);
    
    // Save deployment info
    const deploymentInfo = {
        network: networkName,
        chainId: network.chainId.toString(),
        diamondToken: diamondTokenAddress,
        usdtToken: usdtAddress,
        treasury: treasuryAddress,
        deployer: deployer.address,
        deployedAt: new Date().toISOString(),
        blockNumber: await ethers.provider.getBlockNumber()
    };
    
    console.log("\n" + "=".repeat(50));
    console.log("🎉 DEPLOYMENT SUMMARY");
    console.log("=".repeat(50));
    console.log("📊 Network:", deploymentInfo.network);
    console.log("🔗 Chain ID:", deploymentInfo.chainId);
    console.log("💎 DiamondToken:", deploymentInfo.diamondToken);
    console.log("🏦 USDT Token:", deploymentInfo.usdtToken);
    console.log("💰 Treasury:", deploymentInfo.treasury);
    console.log("👤 Deployer:", deploymentInfo.deployer);
    console.log("📅 Deployed At:", deploymentInfo.deployedAt);
    console.log("📦 Block Number:", deploymentInfo.blockNumber);
    console.log("=".repeat(50));
    
    // Verify contract on Etherscan (if not local network)
    if (networkName !== "localhost" && networkName !== "hardhat") {
        console.log("\n🔍 Waiting for block confirmations...");
        const deployTx = diamondToken.deploymentTransaction();
        if (deployTx) {
            await deployTx.wait(5);
        }
        
        console.log("🔍 Verifying contract on Etherscan...");
        try {
            await hre.run("verify:verify", {
                address: diamondTokenAddress,
                constructorArguments: [usdtAddress, treasuryAddress],
            });
            console.log("✅ Contract verified successfully!");
        } catch (error) {
            console.log("❌ Verification failed:", error.message);
        }
    }
    
    // Save deployment info to file
    const fs = require("fs");
    const path = require("path");
    
    try {
        const deploymentsDir = path.join(__dirname, "deployments");
        if (!fs.existsSync(deploymentsDir)) {
            fs.mkdirSync(deploymentsDir, { recursive: true });
        }
        
        const deploymentFile = path.join(deploymentsDir, `${networkName}.json`);
        fs.writeFileSync(deploymentFile, JSON.stringify(deploymentInfo, null, 2));
        
        console.log(`\n💾 Deployment info saved to: deployments/${networkName}.json`);
        console.log("📄 File contents:");
        console.log(JSON.stringify(deploymentInfo, null, 2));
        
        // Also save a copy with timestamp for history
        const timestampFile = path.join(deploymentsDir, `${networkName}-${Date.now()}.json`);
        fs.writeFileSync(timestampFile, JSON.stringify(deploymentInfo, null, 2));
        console.log(`📄 Backup saved to: deployments/${networkName}-${Date.now()}.json`);
        
    } catch (error) {
        console.error("❌ Failed to save deployment info:", error.message);
        console.log("📄 Deployment info (manual save required):");
        console.log(JSON.stringify(deploymentInfo, null, 2));
    }
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error(error);
        process.exit(1);
    });
