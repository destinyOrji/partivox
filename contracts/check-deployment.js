const fs = require('fs');
const path = require('path');

async function checkDeployment() {
    console.log("🔍 Checking PARTIVOX Deployment Status...");
    console.log("=".repeat(50));
    
    const deploymentsDir = path.join(__dirname, 'deployments');
    
    if (!fs.existsSync(deploymentsDir)) {
        console.log("❌ No deployments directory found");
        console.log("💡 Run deployment first: npx hardhat run scripts/deploy.js");
        return;
    }
    
    const files = fs.readdirSync(deploymentsDir).filter(f => f.endsWith('.json'));
    
    if (files.length === 0) {
        console.log("❌ No deployment files found");
        console.log("💡 Run deployment first: npx hardhat run scripts/deploy.js");
        return;
    }
    
    console.log(`📄 Found ${files.length} deployment file(s):`);
    
    files.forEach(file => {
        console.log(`\n📋 ${file}:`);
        try {
            const deploymentPath = path.join(deploymentsDir, file);
            const deployment = JSON.parse(fs.readFileSync(deploymentPath, 'utf8'));
            
            console.log("  📊 Network:", deployment.network);
            console.log("  🔗 Chain ID:", deployment.chainId);
            console.log("  💎 DiamondToken:", deployment.diamondToken);
            console.log("  🏦 USDT Token:", deployment.usdtToken);
            console.log("  💰 Treasury:", deployment.treasury);
            console.log("  👤 Deployer:", deployment.deployer);
            console.log("  📅 Deployed At:", deployment.deployedAt);
            console.log("  📦 Block Number:", deployment.blockNumber);
            
        } catch (error) {
            console.log("  ❌ Error reading file:", error.message);
        }
    });
    
    console.log("\n" + "=".repeat(50));
    console.log("✅ Deployment check complete!");
}

checkDeployment().catch(console.error);
