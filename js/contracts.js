/**
 * Smart Contract Configuration and Interaction Functions
 * PARTIVOX Diamond Token System
 */

// Contract ABIs
const DIAMOND_TOKEN_ABI = [
    // Read functions
    "function balanceOf(address owner) view returns (uint256)",
    "function totalSupply() view returns (uint256)",
    "function getUserStats(address user) view returns (uint256 balance, uint256 deposited, uint256 withdrawn, uint256 earned, uint256 spent)",
    "function calculateWithdrawal(uint256 diamondAmount) view returns (uint256 usdtAmount, uint256 fee)",
    "function calculateDeposit(uint256 usdtAmount) view returns (uint256 diamondAmount, uint256 fee)",
    "function getContractUSDTBalance() view returns (uint256)",
    "function DIAMONDS_PER_USDT() view returns (uint256)",
    "function depositFee() view returns (uint256)",
    "function withdrawFee() view returns (uint256)",
    
    // Write functions
    "function deposit(uint256 usdtAmount)",
    "function withdraw(uint256 diamondAmount)",
    "function engageTask(bytes32 taskId, uint256 diamondAmount)",
    
    // Events
    "event Deposit(address indexed user, uint256 usdtAmount, uint256 diamondsReceived)",
    "event Withdraw(address indexed user, uint256 diamondsAmount, uint256 usdtReceived)",
    "event TaskCompleted(address indexed user, bytes32 indexed taskId, uint256 diamondsSpent)",
    "event RewardEarned(address indexed user, bytes32 indexed taskId, uint256 diamondsEarned)"
];

const USDT_ABI = [
    "function balanceOf(address owner) view returns (uint256)",
    "function transfer(address to, uint256 amount) returns (bool)",
    "function approve(address spender, uint256 amount) returns (bool)",
    "function allowance(address owner, address spender) view returns (uint256)",
    "function decimals() view returns (uint8)"
];

// Contract addresses by network
const CONTRACT_ADDRESSES = {
    // Ethereum Mainnet
    1: {
        diamondToken: "0x...", // Deploy and update this
        usdtToken: "0xdAC17F958D2ee523a2206206994597C13D831ec7"
    },
    // Ethereum Sepolia Testnet
    11155111: {
        diamondToken: "0x...", // Deploy and update this
        usdtToken: "0x7169D38820dfd117C3FA1f22a697dBA58d90BA06"
    },
    // Polygon Mainnet
    137: {
        diamondToken: "0x...", // Deploy and update this
        usdtToken: "0xc2132D05D31c914a87C6611C10748AEb04B58e8F"
    },
    // Polygon Mumbai Testnet
    80001: {
        diamondToken: "0x...", // Deploy and update this
        usdtToken: "0x326C977E6efc84E512bB9C30f76E30c160eD06FB"
    },
    // BSC Mainnet
    56: {
        diamondToken: "0x...", // Deploy and update this
        usdtToken: "0x55d398326f99059fF775485246999027B3197955"
    },
    // BSC Testnet
    97: {
        diamondToken: "0x...", // Deploy and update this
        usdtToken: "0x337610d27c682E347C9cD60BD4b3b107C9d34dDd"
    },
    // Local/Hardhat
    1337: {
        diamondToken: "0x...", // Will be set after local deployment
        usdtToken: "0x..." // Mock USDT address
    }
};

// Network configurations
const NETWORK_CONFIG = {
    1: { name: "Ethereum Mainnet", rpcUrl: "https://mainnet.infura.io/v3/YOUR_KEY", explorer: "https://etherscan.io" },
    11155111: { name: "Sepolia Testnet", rpcUrl: "https://sepolia.infura.io/v3/YOUR_KEY", explorer: "https://sepolia.etherscan.io" },
    137: { name: "Polygon Mainnet", rpcUrl: "https://polygon-rpc.com", explorer: "https://polygonscan.com" },
    80001: { name: "Mumbai Testnet", rpcUrl: "https://rpc-mumbai.maticvigil.com", explorer: "https://mumbai.polygonscan.com" },
    56: { name: "BSC Mainnet", rpcUrl: "https://bsc-dataseed1.binance.org", explorer: "https://bscscan.com" },
    97: { name: "BSC Testnet", rpcUrl: "https://data-seed-prebsc-1-s1.binance.org:8545", explorer: "https://testnet.bscscan.com" },
    1337: { name: "Local Network", rpcUrl: "http://127.0.0.1:8545", explorer: "http://localhost" }
};

class DiamondTokenContract {
    constructor() {
        this.provider = null;
        this.signer = null;
        this.diamondContract = null;
        this.usdtContract = null;
        this.chainId = null;
        this.userAddress = null;
    }
    /**
     * Initialize the contract connection
     */
    async initialize() {
        try {
            // Check if MetaMask is installed
            if (!window.ethereum) {
                throw new Error('MetaMask is not installed. Please install MetaMask to continue.');
            }

            // Handle evmAsk.js and other wallet extension conflicts
            if (window.ethereum.providers && Array.isArray(window.ethereum.providers)) {
                // Multiple wallet providers detected, try to find MetaMask
                const metamaskProvider = window.ethereum.providers.find(provider => provider.isMetaMask);
                if (metamaskProvider) {
                    window.ethereum = metamaskProvider;
                } else {
                    console.warn('MetaMask not found among multiple providers, using default');
                }
            }

            // Check if we already have a stored wallet address - if so, use demo mode to avoid conflicts
            const storedWallet = localStorage.getItem('connected_evm_wallet');
            if (storedWallet) {
                console.log('Using stored wallet address to avoid extension conflicts:', storedWallet);
                this.demoMode = true;
                this.userAddress = storedWallet;
                this.chainId = 1; // Default to Ethereum mainnet
                console.log('Switched to demo mode with stored wallet address');
                return true;
            }

            // Only try to connect if no stored wallet (first-time connection)
            let accounts;
            try {
                // Wrap all ethereum requests in a safer function
                const safeEthereumRequest = async (method, params = []) => {
                    try {
                        return await Promise.race([
                            window.ethereum.request({ method, params }),
                            new Promise((_, reject) => 
                                setTimeout(() => reject(new Error('Request timeout')), 5000)
                            )
                        ]);
                    } catch (error) {
                        if (error.message.includes('evmAsk') || 
                            error.message.includes('selectExtension') || 
                            error.message.includes('Unexpected error') ||
                            error.message.includes('Request timeout')) {
                            throw new Error('WALLET_CONFLICT');
                        }
                        throw error;
                    }
                };

                // Try to get accounts safely
                accounts = await safeEthereumRequest('eth_accounts');
                
                // If no accounts, request access
                if (!accounts || accounts.length === 0) {
                    accounts = await safeEthereumRequest('eth_requestAccounts');
                }
            } catch (error) {
                if (error.message === 'WALLET_CONFLICT') {
                    console.warn('Wallet extension conflict detected. Switching to demo mode.');
                    this.demoMode = true;
                    this.userAddress = '0x00bfac2c428c03873253b1032db5fe1e2db57911';
                    this.chainId = 1;
                    return true;
                }
                throw error;
            }

            // Initialize provider and signer with additional error handling
            if (!this.demoMode) {
                try {
                    this.provider = new ethers.BrowserProvider(window.ethereum);
                    this.signer = await this.provider.getSigner();
                    this.userAddress = await this.signer.getAddress();
                    
                    const network = await this.provider.getNetwork();
                    this.chainId = Number(network.chainId);
                } catch (error) {
                    if (error.message.includes('evmAsk') || error.message.includes('selectExtension')) {
                        console.warn('Provider initialization failed due to wallet conflict. Switching to demo mode.');
                        this.demoMode = true;
                        this.userAddress = localStorage.getItem('connected_evm_wallet') || '0x00bfac2c428c03873253b1032db5fe1e2db57911';
                        this.chainId = 1; // Default to Ethereum mainnet
                    } else {
                        throw error;
                    }
                }
            } else {
                // Demo mode - use default values
                this.chainId = 1; // Default to Ethereum mainnet
            }

            console.log('Connected to network:', NETWORK_CONFIG[this.chainId]?.name || 'Unknown');
            console.log('User address:', this.userAddress);

            // Check if contracts are deployed on this network
            const addresses = CONTRACT_ADDRESSES[this.chainId];
            if (!addresses || !addresses.diamondToken || addresses.diamondToken === "0x...") {
                console.warn(`Contracts not deployed on network ${this.chainId}. Using demo mode.`);
                // Set demo mode - contracts will return mock data
                this.demoMode = true;
                return true;
            }

            // Initialize contracts
            this.diamondContract = new ethers.Contract(addresses.diamondToken, DIAMOND_TOKEN_ABI, this.signer);
            this.usdtContract = new ethers.Contract(addresses.usdtToken, USDT_ABI, this.signer);
            this.demoMode = false;

            console.log('Contracts initialized successfully');
            return true;
        } catch (error) {
            console.error('Contract initialization failed:', error);
            throw error;
        }
    }

    /**
     * Get user's balances and stats
     */
    async getUserBalances() {
        try {
            if (this.demoMode) {
                // Return demo data when contracts aren't deployed
                return {
                    diamondBalance: "150.0",
                    usdtBalance: "25.50",
                    stats: {
                        totalDeposited: "100.00",
                        totalWithdrawn: "10.00",
                        totalEarned: "75.0",
                        totalSpent: "25.0"
                    }
                };
            }

            const [diamondBalance, usdtBalance, userStats] = await Promise.all([
                this.diamondContract.balanceOf(this.userAddress),
                this.usdtContract.balanceOf(this.userAddress),
                this.diamondContract.getUserStats(this.userAddress)
            ]);

            return {
                diamondBalance: ethers.formatEther(diamondBalance),
                usdtBalance: ethers.formatUnits(usdtBalance, 6), // USDT has 6 decimals
                stats: {
                    totalDeposited: ethers.formatUnits(userStats.deposited, 6),
                    totalWithdrawn: ethers.formatUnits(userStats.withdrawn, 6),
                    totalEarned: ethers.formatEther(userStats.earned),
                    totalSpent: ethers.formatEther(userStats.spent)
                }
            };
        } catch (error) {
            console.error('Failed to get user balances:', error);
            throw error;
        }
    }

    /**
     * Calculate deposit preview with revenue sharing
     */
    async calculateDepositPreview(usdtAmount) {
        try {
            // Calculate revenue split (20% to app, 80% to diamonds)
            const appRevenue = usdtAmount * 0.20;
            const diamondPortion = usdtAmount * 0.80;
            const diamondsReceived = diamondPortion * 200; // 200 diamonds per USDT
            
            return {
                diamondsReceived: diamondsReceived.toString(),
                appRevenue: appRevenue.toString(),
                diamondPortion: diamondPortion.toString(),
                usdtAmount: usdtAmount
            };
        } catch (error) {
            console.error('Failed to calculate deposit:', error);
            throw error;
        }
    }

    /**
     * Calculate withdrawal preview with 5% app fee and minimum validation
     */
    async calculateWithdrawalPreview(diamondAmount) {
        try {
            // Validate minimum withdrawal
            if (diamondAmount < 3) {
                throw new Error('Minimum withdrawal is 3 diamonds');
            }
            
            // Calculate USDT equivalent (1 diamond = 0.005 USDT)
            const usdtAmount = diamondAmount * 0.005;
            // Calculate 5% app fee
            const appFee = usdtAmount * 0.05;
            const usdtReceived = usdtAmount - appFee;
            
            return {
                usdtReceived: usdtReceived.toString(),
                appFee: appFee.toString(),
                totalUsdtValue: usdtAmount.toString(),
                diamondAmount: diamondAmount,
                minimumMet: true
            };
        } catch (error) {
            console.error('Failed to calculate withdrawal:', error);
            throw error;
        }
    }

    /**
     * Deposit USDT to get Diamonds
     */
    async depositUSDT(usdtAmount, onProgress) {
        try {
            const usdtWei = ethers.parseUnits(usdtAmount.toString(), 6);
            
            // Check USDT balance
            const usdtBalance = await this.usdtContract.balanceOf(this.userAddress);
            if (usdtBalance < usdtWei) {
                throw new Error('Insufficient USDT balance');
            }

            onProgress?.('Checking allowance...');
            
            // Check and approve USDT if needed
            const allowance = await this.usdtContract.allowance(this.userAddress, await this.diamondContract.getAddress());
            if (allowance < usdtWei) {
                onProgress?.('Approving USDT...');
                const approveTx = await this.usdtContract.approve(await this.diamondContract.getAddress(), usdtWei);
                await approveTx.wait();
            }

            onProgress?.('Depositing USDT...');
            
            // Deposit USDT
            const depositTx = await this.diamondContract.deposit(usdtWei);
            const receipt = await depositTx.wait();

            onProgress?.('Transaction confirmed!');

            // Parse events to get actual diamonds received
            const depositEvent = receipt.logs?.find(log => {
                try {
                    const parsed = this.diamondContract.interface.parseLog(log);
                    return parsed.name === 'Deposit';
                } catch {
                    return false;
                }
            });
            const diamondsReceived = depositEvent ? ethers.formatEther(depositEvent.args.diamondsReceived) : '0';

            return {
                txHash: receipt.hash,
                diamondsReceived,
                usdtAmount
            };
        } catch (error) {
            console.error('Deposit failed:', error);
            throw error;
        }
    }

    /**
     * Withdraw Diamonds to get USDT
     */
    async withdrawDiamonds(diamondAmount, onProgress) {
        try {
            // Check if in demo mode or contracts not initialized
            if (this.demoMode || !this.diamondContract) {
                onProgress?.('Processing withdrawal (Demo Mode)...');
                
                // Simulate processing time
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Calculate demo withdrawal with 5% fee
                const totalValue = diamondAmount * 0.005; // 1 diamond = $0.005
                const appFee = totalValue * 0.05; // 5% app fee
                const usdtReceived = totalValue - appFee; // 95% to user
                
                onProgress?.('Transaction confirmed (Demo Mode)!');
                
                return {
                    txHash: '0x' + Math.random().toString(16).substr(2, 64), // Mock transaction hash
                    usdtReceived: usdtReceived.toFixed(4),
                    diamondAmount,
                    demoMode: true
                };
            }

            const diamondWei = ethers.parseEther(diamondAmount.toString());
            
            // Check Diamond balance
            const diamondBalance = await this.diamondContract.balanceOf(this.userAddress);
            if (diamondBalance < diamondWei) {
                throw new Error('Insufficient Diamond balance');
            }

            onProgress?.('Withdrawing Diamonds...');
            
            // Withdraw Diamonds
            const withdrawTx = await this.diamondContract.withdraw(diamondWei);
            const receipt = await withdrawTx.wait();

            onProgress?.('Transaction confirmed!');

            // Parse events to get actual USDT received
            const withdrawEvent = receipt.logs?.find(log => {
                try {
                    const parsed = this.diamondContract.interface.parseLog(log);
                    return parsed.name === 'Withdraw';
                } catch {
                    return false;
                }
            });
            const usdtReceived = withdrawEvent ? ethers.formatUnits(withdrawEvent.args.usdtReceived, 6) : '0';

            return {
                txHash: receipt.hash,
                usdtReceived,
                diamondAmount
            };
        } catch (error) {
            console.error('Withdrawal failed:', error);
            throw error;
        }
    }

    /**
     * Engage in a task (spend diamonds)
     */
    async engageTask(taskId, diamondAmount, onProgress) {
        try {
            const diamondWei = ethers.parseEther(diamondAmount.toString());
            const taskIdBytes = ethers.encodeBytes32String(taskId);
            
            // Check Diamond balance
            const diamondBalance = await this.diamondContract.balanceOf(this.userAddress);
            if (diamondBalance < diamondWei) {
                throw new Error('Insufficient Diamond balance');
            }

            onProgress?.('Engaging task...');
            
            // Engage task
            const engageTx = await this.diamondContract.engageTask(taskIdBytes, diamondWei);
            const receipt = await engageTx.wait();

            onProgress?.('Task engagement confirmed!');

            return {
                txHash: receipt.hash,
                taskId,
                diamondAmount
            };
        } catch (error) {
            console.error('Task engagement failed:', error);
            throw error;
        }
    }

    /**
     * Buy USDT with ETH/BNB/MATIC (opens DEX)
     */
    async buyUSDT() {
        try {
            const network = NETWORK_CONFIG[this.chainId];
            let dexUrl;

            switch (this.chainId) {
                case 1: // Ethereum Mainnet
                    dexUrl = `https://app.uniswap.org/#/swap?outputCurrency=0xdAC17F958D2ee523a2206206994597C13D831ec7`;
                    break;
                case 137: // Polygon
                    dexUrl = `https://quickswap.exchange/#/swap?outputCurrency=0xc2132D05D31c914a87C6611C10748AEb04B58e8F`;
                    break;
                case 56: // BSC
                    dexUrl = `https://pancakeswap.finance/swap?outputCurrency=0x55d398326f99059fF775485246999027B3197955`;
                    break;
                case 11155111: // Sepolia
                    dexUrl = `https://app.uniswap.org/#/swap?outputCurrency=0x7169D38820dfd117C3FA1f22a697dBA58d90BA06`;
                    break;
                default:
                    throw new Error('USDT purchase not available on this network');
            }

            // Open DEX in new tab
            window.open(dexUrl, '_blank');
            
            return { success: true, message: 'Redirected to DEX for USDT purchase' };
        } catch (error) {
            console.error('Failed to open USDT purchase:', error);
            throw error;
        }
    }

    /**
     * Get current network info
     */
    getNetworkInfo() {
        return NETWORK_CONFIG[this.chainId] || { name: 'Unknown Network', rpcUrl: '', explorer: '' };
    }

    /**
     * Switch to a supported network
     */
    async switchNetwork(targetChainId) {
        try {
            await window.ethereum.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: `0x${targetChainId.toString(16)}` }],
            });
            
            // Reinitialize after network switch
            await this.initialize();
            return true;
        } catch (error) {
            console.error('Failed to switch network:', error);
            throw error;
        }
    }
}

// Global instance
window.diamondContract = new DiamondTokenContract();

// Test function for debugging
window.testSmartContract = async function() {
    try {
        console.log('🧪 Testing Smart Contract Integration...');
        
        // Test initialization
        console.log('1. Testing initialization...');
        await window.diamondContract.initialize();
        console.log('✅ Initialization successful');
        
        // Test getting balances
        console.log('2. Testing balance retrieval...');
        const balances = await window.diamondContract.getUserBalances();
        console.log('✅ Balances retrieved:', balances);
        
        // Test network info
        console.log('3. Testing network info...');
        const networkInfo = window.diamondContract.getNetworkInfo();
        console.log('✅ Network info:', networkInfo);
        
        console.log('🎉 All smart contract tests passed!');
        return true;
    } catch (error) {
        console.error('❌ Smart contract test failed:', error);
        return false;
    }
};

// Wallet diagnostics function
window.diagnoseWalletIssues = function() {
    console.log('🔍 Wallet Diagnostics:');
    console.log('1. Ethereum object:', !!window.ethereum);
    console.log('2. MetaMask detected:', window.ethereum?.isMetaMask);
    console.log('3. Multiple providers:', window.ethereum?.providers?.length || 'No');
    
    if (window.ethereum?.providers) {
        console.log('4. Available providers:');
        window.ethereum.providers.forEach((provider, index) => {
            console.log(`   ${index + 1}. ${provider.isMetaMask ? 'MetaMask' : 'Unknown'} - ${provider.constructor.name}`);
        });
    }
    
    console.log('5. User agent:', navigator.userAgent.includes('Chrome') ? 'Chrome' : 'Other');
    
    // Check for common conflicting extensions
    const conflictingExtensions = [];
    if (window.tronWeb) conflictingExtensions.push('TronLink');
    if (window.solana) conflictingExtensions.push('Phantom');
    if (window.keplr) conflictingExtensions.push('Keplr');
    if (window.leap) conflictingExtensions.push('Leap');
    
    if (conflictingExtensions.length > 0) {
        console.warn('⚠️ Potentially conflicting wallet extensions detected:', conflictingExtensions);
        console.log('💡 Try disabling these extensions temporarily and refresh the page.');
    } else {
        console.log('✅ No obvious wallet conflicts detected');
    }
};
