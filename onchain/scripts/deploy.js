require('dotenv').config();
const hre = require('hardhat');

async function main() {
  const [deployer] = await hre.ethers.getSigners();
  console.log('Deployer:', deployer.address);
  console.log('Network:', hre.network.name);

  // Deploy MockUSDT
  const MockUSDT = await hre.ethers.getContractFactory('MockUSDT');
  const usdt = await MockUSDT.deploy();
  await usdt.waitForDeployment();
  const usdtAddress = await usdt.getAddress();
  console.log('MockUSDT deployed at:', usdtAddress);

  // Mint some USDT to deployer for testing (1,000 USDT with 6 decimals)
  const mintAmount = hre.ethers.parseUnits('1000', 6);
  const mintTx = await usdt.mint(deployer.address, mintAmount);
  await mintTx.wait();
  console.log('Minted 1000 mUSDT to deployer');

  // Deploy DiamondTreasury with rate 5 diamonds per USDT
  const rate = 5;
  const owner = process.env.TREASURY_ADMIN_ADDRESS || deployer.address;
  const DiamondTreasury = await hre.ethers.getContractFactory('DiamondTreasury');
  const treasury = await DiamondTreasury.deploy(usdtAddress, rate, owner);
  await treasury.waitForDeployment();
  const treasuryAddress = await treasury.getAddress();
  console.log('DiamondTreasury deployed at:', treasuryAddress);

  console.log('\n=== Addresses ===');
  console.log('USDT_TOKEN_ADDRESS=', usdtAddress);
  console.log('DIAMOND_TREASURY_ADDRESS=', treasuryAddress);
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
