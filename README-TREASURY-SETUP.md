# 🏦 Treasury Wallet Setup Guide

## Where Your Revenue Goes

Your app generates revenue from two sources:
- **20% of all USDT deposits** (when users buy diamonds)
- **5% of all USDT withdrawals** (when users convert diamonds back)

All this revenue automatically goes to your **Treasury Wallet Address**.

## 🔧 Setting Up Your Treasury Wallet

### Step 1: Choose Your Treasury Wallet

You need a wallet address where all revenue will be sent. This should be:
- ✅ **Your personal wallet** (MetaMask, hardware wallet, etc.)
- ✅ **A secure wallet** you control
- ✅ **Same network** as your smart contract (Ethereum mainnet, testnet, etc.)

**Example Treasury Address:**
```
0x742d35Cc6634C0532925a3b8D0C9e3e0C8b0e4c8
```

### Step 2: Configure Treasury in Smart Contract

The treasury address is set when you deploy the contract:

```javascript
// In deploy-contract.js
const APP_OWNER_WALLET = "0xYOUR_WALLET_ADDRESS_HERE"; // 🔴 REPLACE THIS

const diamondToken = await DiamondToken.deploy(
    USDT_CONTRACT_ADDRESS,  // USDT token address
    APP_OWNER_WALLET        // Your treasury wallet
);
```

### Step 3: Update Configuration Files

Update your treasury address in:

**`config/contract-config.js`:**
```javascript
TREASURY_WALLET: "0xYOUR_WALLET_ADDRESS_HERE", // 🔴 REPLACE THIS
```

**`js/contracts.js`:** (if needed)
```javascript
const TREASURY_ADDRESS = "0xYOUR_WALLET_ADDRESS_HERE";
```

## 💰 How Revenue Flows to Your Wallet

### Deposit Revenue (20%)
```
User pays $100 USDT → Smart Contract
├── $20 USDT → Your Treasury Wallet (20% revenue)
└── $80 USDT → Converted to 16,000 Diamonds for user
```

### Withdrawal Revenue (5%)
```
User converts 1,000 Diamonds ($5 value) → Smart Contract
├── $0.25 USDT → Your Treasury Wallet (5% fee)
└── $4.75 USDT → Sent to user's wallet
```

## 🔧 Managing Your Treasury Address

### View Current Treasury
```bash
node scripts/manage-treasury.js view <contract-address>
```

### Update Treasury Address (Owner Only)
```bash
node scripts/manage-treasury.js update <contract-address> <new-treasury-address>
```

### Check Treasury Balance
```bash
node scripts/manage-treasury.js balance <contract-address>
```

## 🛡️ Security Best Practices

### 1. Use a Secure Wallet
- ✅ Hardware wallet (Ledger, Trezor)
- ✅ Secure MetaMask with strong password
- ❌ Don't use exchange addresses
- ❌ Don't use temporary wallets

### 2. Keep Private Keys Safe
- ✅ Store private keys securely
- ✅ Use hardware wallet for large amounts
- ✅ Consider multi-sig wallet for high volume

### 3. Monitor Your Treasury
- ✅ Check balance regularly
- ✅ Set up wallet notifications
- ✅ Track revenue vs expected amounts

## 📊 Revenue Tracking

### Expected Revenue Calculation

**Monthly Revenue Example:**
- 1,000 users buy $10 diamonds = $10,000 volume
- Your 20% cut = **$2,000 deposit revenue**
- 500 users withdraw $20 = $10,000 withdrawal volume  
- Your 5% cut = **$500 withdrawal revenue**
- **Total Monthly Revenue: $2,500**

### Monitoring Tools

1. **Blockchain Explorer**
   - View your treasury address on Etherscan
   - See all incoming USDT transactions
   - Track revenue over time

2. **Wallet Apps**
   - MetaMask shows USDT balance
   - Set up notifications for incoming transfers
   - Export transaction history

3. **Custom Dashboard** (Optional)
   - Build admin panel to track revenue
   - Show real-time treasury balance
   - Calculate revenue analytics

## 🚨 Important Notes

### Contract Deployment
- Treasury address is set **once** during deployment
- Can be updated later using `setTreasury()` function
- Only contract owner can update treasury

### Revenue is Automatic
- No manual intervention needed
- Revenue flows automatically with each transaction
- Smart contract handles all calculations

### Network Considerations
- Treasury must be on same network as contract
- Mainnet = real USDT revenue
- Testnet = test USDT (no real value)

## 🔍 Troubleshooting

### Revenue Not Appearing?
1. ✅ Check treasury address is correct
2. ✅ Verify contract is deployed properly
3. ✅ Confirm users are actually making transactions
4. ✅ Check blockchain explorer for transactions

### Need to Change Treasury?
1. ✅ Use `setTreasury()` function
2. ✅ Must be called by contract owner
3. ✅ Update all config files
4. ✅ Test with small transaction

### Lost Access to Treasury Wallet?
- ❌ **Cannot recover funds** if private keys are lost
- ✅ **Prevention:** Use secure backup methods
- ✅ **Solution:** Update treasury to new wallet ASAP

## 📞 Support

If you need help setting up your treasury wallet:
1. Check smart contract deployment logs
2. Verify treasury address in contract
3. Test with small amounts first
4. Monitor blockchain explorer for transactions

**Your treasury wallet is where your business revenue flows - keep it secure! 🔒**
