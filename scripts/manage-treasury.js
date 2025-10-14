const { ethers } = require("hardhat");

// Treasury Management Script for PARTIVOX Diamond Token
async function main() {
    const action = process.argv[2];
    const contractAddress = process.argv[3];
    const newTreasuryAddress = process.argv[4];
    
    if (!action || !contractAddress) {
        console.log("📋 Treasury Management Commands:");
        console.log("node scripts/manage-treasury.js view <contract-address>");
        console.log("node scripts/manage-treasury.js update <contract-address> <new-treasury-address>");
        console.log("node scripts/manage-treasury.js balance <contract-address>");
        return;
    }
    
    // Get contract instance
    const DiamondToken = await ethers.getContractFactory("DiamondToken");
    const contract = DiamondToken.attach(contractAddress);
    
    const [signer] = await ethers.getSigners();
    console.log("🔐 Using account:", signer.address);
    
    switch (action) {
        case "view":
            await viewTreasuryInfo(contract);
            break;
            
        case "update":
            if (!newTreasuryAddress) {
                console.log("❌ Please provide new treasury address");
                return;
            }
            await updateTreasury(contract, newTreasuryAddress);
            break;
            
        case "balance":
            await checkTreasuryBalance(contract);
            break;
            
        default:
            console.log("❌ Unknown action:", action);
    }
}

async function viewTreasuryInfo(contract) {
    console.log("🏦 Treasury Information:");
    
    try {
        const treasury = await contract.treasury();
        const owner = await contract.owner();
        const usdtToken = await contract.usdtToken();
        
        console.log("📍 Current Treasury Address:", treasury);
        console.log("👑 Contract Owner:", owner);
        console.log("💵 USDT Token Address:", usdtToken);
        
        // Get USDT contract to check balance
        const USDT = await ethers.getContractAt("IERC20", usdtToken);
        const balance = await USDT.balanceOf(treasury);
        console.log("💰 Treasury USDT Balance:", ethers.formatUnits(balance, 6), "USDT");
        
        console.log("\n📊 Revenue Configuration:");
        console.log("- Deposit Revenue: 20% of all USDT deposits");
        console.log("- Withdrawal Fee: 5% of all USDT withdrawals");
        console.log("- Exchange Rate: 1 USDT = 200 Diamonds");
        
    } catch (error) {
        console.error("❌ Error viewing treasury info:", error.message);
    }
}

async function updateTreasury(contract, newAddress) {
    console.log("🔄 Updating Treasury Address...");
    
    try {
        // Validate address
        if (!ethers.isAddress(newAddress)) {
            console.log("❌ Invalid address format");
            return;
        }
        
        const currentTreasury = await contract.treasury();
        console.log("📍 Current Treasury:", currentTreasury);
        console.log("🆕 New Treasury:", newAddress);
        
        // Update treasury
        const tx = await contract.setTreasury(newAddress);
        console.log("⏳ Transaction submitted:", tx.hash);
        
        await tx.wait();
        console.log("✅ Treasury updated successfully!");
        
        // Verify update
        const updatedTreasury = await contract.treasury();
        console.log("✅ Verified New Treasury:", updatedTreasury);
        
    } catch (error) {
        console.error("❌ Error updating treasury:", error.message);
        
        if (error.message.includes("Ownable: caller is not the owner")) {
            console.log("🔐 Only the contract owner can update the treasury address");
        }
    }
}

async function checkTreasuryBalance(contract) {
    console.log("💰 Checking Treasury Balance...");
    
    try {
        const treasury = await contract.treasury();
        const usdtToken = await contract.usdtToken();
        
        // Get USDT contract
        const USDT = await ethers.getContractAt("IERC20", usdtToken);
        const balance = await USDT.balanceOf(treasury);
        
        console.log("🏦 Treasury Address:", treasury);
        console.log("💵 USDT Balance:", ethers.formatUnits(balance, 6), "USDT");
        
        // Calculate potential earnings
        const balanceInWei = parseFloat(ethers.formatUnits(balance, 6));
        console.log("\n📈 Revenue Breakdown:");
        console.log("💎 From Deposits (20%):", (balanceInWei * 5).toFixed(2), "USDT worth of deposits processed");
        console.log("🔄 From Withdrawals (5%):", (balanceInWei * 20).toFixed(2), "USDT worth of withdrawals processed");
        
    } catch (error) {
        console.error("❌ Error checking balance:", error.message);
    }
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error("❌ Script failed:", error);
        process.exit(1);
    });
