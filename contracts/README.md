# PARTIVOX Diamond Token Smart Contract System

A comprehensive smart contract system for the PARTIVOX platform that enables users to:
- **Deposit USDT** to buy Diamonds
- **Engage in tasks** by spending Diamonds
- **Earn rewards** in Diamonds
- **Withdraw Diamonds** back to USDT

## 🏗️ Architecture Overview

### Smart Contracts
- **DiamondToken.sol** - Main ERC20 token contract with deposit/withdraw functionality
- **MockUSDT.sol** - Mock USDT token for testing

### Key Features
- **1 USDT = 20 Diamonds** (0.05 USDT per Diamond)
- **Deposit Fee**: 2.5% (configurable)
- **Withdrawal Fee**: 3.0% (configurable)
- **Task Engagement**: Spend diamonds on platform activities
- **Reward System**: Earn diamonds for completing tasks
- **Multi-Network Support**: Ethereum, Polygon, BSC

## 🚀 Quick Start

### Prerequisites
```bash
npm install -g hardhat
```

### Installation
```bash
cd contracts
npm install
```

### Environment Setup
```bash
cp .env.example .env
# Edit .env with your configuration
```

### Compile Contracts
```bash
npm run compile
```

### Deploy to Local Network
```bash
# Terminal 1: Start local blockchain
npm run node

# Terminal 2: Deploy contracts
npm run deploy:local
```

### Deploy to Testnet
```bash
# Sepolia Testnet
npm run deploy:sepolia

# Polygon Mumbai
npm run deploy:mumbai

# BSC Testnet
npm run deploy:bsc-testnet
```

## 📋 Contract Functions

### User Functions

#### Deposit USDT → Get Diamonds
```solidity
function deposit(uint256 usdtAmount) external
```
- Converts USDT to Diamonds at 1:20 ratio
- Charges 2.5% deposit fee
- Requires USDT approval first

#### Withdraw Diamonds → Get USDT
```solidity
function withdraw(uint256 diamondAmount) external
```
- Converts Diamonds back to USDT
- Charges 3.0% withdrawal fee
- Burns user's diamonds

#### Engage in Tasks
```solidity
function engageTask(bytes32 taskId, uint256 diamondAmount) external
```
- Spend diamonds on platform tasks
- Burns diamonds from user balance
- Records task completion

### View Functions

#### Get User Statistics
```solidity
function getUserStats(address user) external view returns (
    uint256 balance,
    uint256 deposited,
    uint256 withdrawn,
    uint256 earned,
    uint256 spent
)
```

#### Calculate Fees
```solidity
function calculateDeposit(uint256 usdtAmount) external view returns (uint256 diamondAmount, uint256 fee)
function calculateWithdrawal(uint256 diamondAmount) external view returns (uint256 usdtAmount, uint256 fee)
```

### Admin Functions

#### Reward Users
```solidity
function rewardUser(address user, bytes32 taskId, uint256 diamondAmount) external
```
- Only authorized rewarders can call
- Mints new diamonds to user
- Records reward in user stats

#### Update Fees
```solidity
function setDepositFee(uint256 _fee) external onlyOwner
function setWithdrawFee(uint256 _fee) external onlyOwner
```

## 🌐 Network Configuration

### Supported Networks

| Network | Chain ID | USDT Address |
|---------|----------|--------------|
| Ethereum Mainnet | 1 | 0xdAC17F958D2ee523a2206206994597C13D831ec7 |
| Ethereum Sepolia | 11155111 | 0x7169D38820dfd117C3FA1f22a697dBA58d90BA06 |
| Polygon Mainnet | 137 | 0xc2132D05D31c914a87C6611C10748AEb04B58e8F |
| Polygon Mumbai | 80001 | 0x326C977E6efc84E512bB9C30f76E30c160eD06FB |
| BSC Mainnet | 56 | 0x55d398326f99059fF775485246999027B3197955 |
| BSC Testnet | 97 | 0x337610d27c682E347C9cD60BD4b3b107C9d34dDd |

### Update Contract Addresses
After deployment, update the contract addresses in:
- `js/contracts.js` - Frontend configuration
- Backend API configuration

## 💻 Frontend Integration

### Initialize Contract
```javascript
// Initialize the diamond contract
await window.diamondContract.initialize();

// Get user balances
const balances = await window.diamondContract.getUserBalances();
console.log('Diamond Balance:', balances.diamondBalance);
console.log('USDT Balance:', balances.usdtBalance);
```

### Buy Diamonds (Deposit USDT)
```javascript
// Calculate how many diamonds user will get
const preview = await window.diamondContract.calculateDepositPreview(100); // 100 USDT

// Deposit USDT to get diamonds
const result = await window.diamondContract.depositUSDT(100, (progress) => {
    console.log('Progress:', progress);
});
```

### Convert Diamonds to USDT
```javascript
// Calculate how much USDT user will get
const preview = await window.diamondContract.calculateWithdrawalPreview(1000); // 1000 diamonds

// Withdraw diamonds to get USDT
const result = await window.diamondContract.withdrawDiamonds(1000, (progress) => {
    console.log('Progress:', progress);
});
```

### Engage in Tasks
```javascript
// Spend diamonds on a task
const result = await window.diamondContract.engageTask('task_123', 50, (progress) => {
    console.log('Progress:', progress);
});
```

### Buy USDT
```javascript
// Opens DEX for USDT purchase
await window.diamondContract.buyUSDT();
```

## 🔧 Backend Integration

### Record Smart Contract Transactions
```php
// Record a transaction
POST /api/smartcontract/record-transaction
{
    "transaction_hash": "0x...",
    "transaction_type": "deposit",
    "amount": 1000,
    "currency": "DIAMOND",
    "contract_address": "0x...",
    "network_id": 1
}
```

### Sync User Balance
```php
// Sync blockchain balance with database
POST /api/smartcontract/sync-balance
{
    "diamondBalance": "1000.0",
    "usdtBalance": "50.0"
}
```

### Get User Transactions
```php
// Get user's smart contract transactions
GET /api/smartcontract/transactions?type=deposit&page=1&limit=10
```

## 🧪 Testing

### Local Testing
```bash
# Start local blockchain
npm run node

# Deploy contracts
npm run deploy:local

# Run tests
npm test
```

### Testnet Testing
1. Get testnet tokens from faucets
2. Deploy to testnet
3. Test all functions through frontend
4. Verify transactions on block explorer

## 🔒 Security Features

- **ReentrancyGuard**: Prevents reentrancy attacks
- **Pausable**: Emergency pause functionality
- **Ownable**: Admin controls with ownership transfer
- **Fee Limits**: Maximum 10% fee cap
- **Input Validation**: Comprehensive parameter checking

## 📊 Monitoring & Analytics

### Transaction Tracking
- All transactions recorded in database
- Real-time status updates
- Comprehensive transaction history

### Network Statistics
- Total volume by network
- Transaction counts by type
- Average transaction amounts

### User Analytics
- Individual user statistics
- Deposit/withdrawal patterns
- Task engagement metrics

## 🚨 Emergency Procedures

### Pause Contract
```solidity
function pause() external onlyOwner
```

### Emergency USDT Withdrawal
```solidity
function emergencyWithdrawUSDT(uint256 amount) external onlyOwner
```

### Update Treasury
```solidity
function setTreasury(address _treasury) external onlyOwner
```

## 📈 Upgrade Path

1. **Phase 1**: Basic deposit/withdraw functionality ✅
2. **Phase 2**: Task engagement system ✅
3. **Phase 3**: Advanced reward mechanisms
4. **Phase 4**: Cross-chain functionality
5. **Phase 5**: Governance token integration

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Add tests for new functionality
4. Submit pull request

## 📄 License

MIT License - see LICENSE file for details

## 🆘 Support

For technical support:
- Check the troubleshooting guide
- Review transaction logs
- Contact development team

---

**⚠️ Important Notes:**
- Always test on testnets before mainnet deployment
- Keep private keys secure
- Monitor gas prices for optimal transaction timing
- Regularly backup deployment configurations
