// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/security/Pausable.sol";

/**
 * @title DiamondToken
 * @dev Smart contract for PARTIVOX Diamond token system
 * Features: Deposit USDT → Get Diamonds, Spend Diamonds on tasks, Win Diamonds, Withdraw Diamonds → USDT
 */
contract DiamondToken is ERC20, ReentrancyGuard, Ownable, Pausable {
    
    // USDT contract interface
    IERC20 public immutable usdtToken;
    
    // Exchange rate: 1 USDT = 200 Diamonds (0.005 USDT per Diamond)
    uint256 public constant DIAMONDS_PER_USDT = 200;
    uint256 public constant USDT_DECIMALS = 6; // USDT has 6 decimals
    uint256 public constant DIAMOND_DECIMALS = 18; // Our token has 18 decimals
    
    // Revenue sharing model
    uint256 public constant APP_REVENUE_PERCENTAGE = 20; // 20% to app owner
    uint256 public constant USER_DIAMOND_PERCENTAGE = 80; // 80% converted to diamonds
    uint256 public constant WITHDRAWAL_FEE_PERCENTAGE = 5; // 5% withdrawal fee to app
    uint256 public constant MINIMUM_WITHDRAWAL_DIAMONDS = 3; // Minimum 3 diamonds to withdraw
    uint256 public withdrawFee = 500; // 5.0% (basis points) for withdrawals
    
    // Platform treasury
    address public treasury;
    
    // User balances and activity tracking
    mapping(address => uint256) public totalDeposited;
    mapping(address => uint256) public totalWithdrawn;
    mapping(address => uint256) public totalEarned;
    mapping(address => uint256) public totalSpent;
    
    // Task and reward system
    mapping(address => bool) public authorizedRewarders;
    mapping(bytes32 => bool) public completedTasks;
    
    // Events
    event Deposit(address indexed user, uint256 usdtAmount, uint256 diamondsReceived);
    event Withdraw(address indexed user, uint256 diamondsAmount, uint256 usdtReceived);
    event TaskCompleted(address indexed user, bytes32 indexed taskId, uint256 diamondsSpent);
    event RewardEarned(address indexed user, bytes32 indexed taskId, uint256 diamondsEarned);
    event FeeUpdated(string feeType, uint256 oldFee, uint256 newFee);
    event TreasuryUpdated(address oldTreasury, address newTreasury);
    event RewarderAuthorized(address indexed rewarder, bool authorized);
    
    constructor(
        address _usdtToken,
        address _treasury
    ) ERC20("PARTIVOX Diamond", "DIAMOND") {
        require(_usdtToken != address(0), "Invalid USDT token address");
        require(_treasury != address(0), "Invalid treasury address");
        
        usdtToken = IERC20(_usdtToken);
        treasury = _treasury;
        
        // Authorize owner as initial rewarder
        authorizedRewarders[msg.sender] = true;
    }
    
    /**
     * @dev Deposit USDT to buy Diamonds with revenue sharing
     * @param usdtAmount Amount of USDT to deposit (in USDT decimals)
     */
    function deposit(uint256 usdtAmount) external nonReentrant whenNotPaused {
        require(usdtAmount > 0, "Amount must be greater than 0");
        
        // Transfer USDT from user to contract first
        require(usdtToken.transferFrom(msg.sender, address(this), usdtAmount), "USDT transfer failed");
        
        // Calculate revenue split
        uint256 appRevenue = (usdtAmount * APP_REVENUE_PERCENTAGE) / 100;
        uint256 diamondPortion = (usdtAmount * USER_DIAMOND_PERCENTAGE) / 100;
        
        // Send 20% to app owner (treasury)
        require(usdtToken.transfer(treasury, appRevenue), "Revenue transfer failed");
        
        // Convert 80% to diamonds for user
        uint256 diamondsToMint = (diamondPortion * DIAMONDS_PER_USDT * (10 ** DIAMOND_DECIMALS)) / (10 ** USDT_DECIMALS);
        
        // Mint diamonds to user (no additional fees, revenue already taken)
        _mint(msg.sender, diamondsToMint);
        
        // Update user stats
        totalDeposited[msg.sender] += usdtAmount;
        
        emit Deposit(msg.sender, usdtAmount, diamondsToMint);
    }
    
    /**
     * @dev Withdraw Diamonds to get USDT back with 5% app fee
     * @param diamondAmount Amount of Diamonds to withdraw
     */
    function withdraw(uint256 diamondAmount) external nonReentrant whenNotPaused {
        require(diamondAmount > 0, "Amount must be greater than 0");
        require(diamondAmount >= MINIMUM_WITHDRAWAL_DIAMONDS * (10 ** DIAMOND_DECIMALS), "Minimum withdrawal is 3 Diamonds");
        require(balanceOf(msg.sender) >= diamondAmount, "Insufficient Diamond balance");
        
        // Calculate USDT to return (convert decimals)
        uint256 usdtAmount = (diamondAmount * (10 ** USDT_DECIMALS)) / (DIAMONDS_PER_USDT * (10 ** DIAMOND_DECIMALS));
        
        // Calculate 5% withdrawal fee for app revenue
        uint256 appFee = (usdtAmount * WITHDRAWAL_FEE_PERCENTAGE) / 100;
        uint256 usdtAfterFee = usdtAmount - appFee;
        
        // Ensure contract has enough USDT for withdrawal
        require(usdtToken.balanceOf(address(this)) >= usdtAmount, "Insufficient contract USDT balance");
        
        // Burn diamonds from user
        _burn(msg.sender, diamondAmount);
        
        // Transfer 5% fee to app treasury (your revenue)
        if (appFee > 0) {
            require(usdtToken.transfer(treasury, appFee), "App fee transfer failed");
        }
        
        // Transfer remaining USDT to user (95% of original amount)
        require(usdtToken.transfer(msg.sender, usdtAfterFee), "USDT transfer failed");
        
        // Update user stats
        totalWithdrawn[msg.sender] += usdtAmount;
        
        emit Withdraw(msg.sender, diamondAmount, usdtAfterFee);
    }
    
    /**
     * @param taskId Unique identifier for the task
     * @param diamondAmount Amount of diamonds to spend
     */
    function engageTask(bytes32 taskId, uint256 diamondAmount) external nonReentrant whenNotPaused {
        require(diamondAmount > 0, "Amount must be greater than 0");
        require(balanceOf(msg.sender) >= diamondAmount, "Insufficient Diamond balance");
        require(!completedTasks[taskId], "Task already completed");
        
        // Burn diamonds from user
        _burn(msg.sender, diamondAmount);
        
        // Mark task as completed
        completedTasks[taskId] = true;
        
        // Update user stats
        totalSpent[msg.sender] += diamondAmount;
        
        emit TaskCompleted(msg.sender, taskId, diamondAmount);
    }
    
    /**
     * @dev Reward user with diamonds for completing tasks
     * @param user Address of the user to reward
     * @param taskId Unique identifier for the task
     * @param diamondAmount Amount of diamonds to reward
     */
    function rewardUser(address user, bytes32 taskId, uint256 diamondAmount) external whenNotPaused {
        require(authorizedRewarders[msg.sender], "Not authorized to give rewards");
        require(user != address(0), "Invalid user address");
        require(diamondAmount > 0, "Amount must be greater than 0");
        
        // Mint diamonds to user
        _mint(user, diamondAmount);
        
        // Update user stats
        totalEarned[user] += diamondAmount;
        
        emit RewardEarned(user, taskId, diamondAmount);
    }
    
    /**
     * @dev Get user's complete stats
     */
    function getUserStats(address user) external view returns (
        uint256 balance,
        uint256 deposited,
        uint256 withdrawn,
        uint256 earned,
        uint256 spent
    ) {
        return (
            balanceOf(user),
            totalDeposited[user],
            totalWithdrawn[user],
            totalEarned[user],
            totalSpent[user]
        );
    }
    
    /**
     * @dev Calculate how much USDT user would get for withdrawing diamonds
     */
    function calculateWithdrawal(uint256 diamondAmount) external view returns (uint256 usdtAmount, uint256 fee) {
        uint256 grossUsdt = (diamondAmount * (10 ** USDT_DECIMALS)) / (DIAMONDS_PER_USDT * (10 ** DIAMOND_DECIMALS));
        fee = (grossUsdt * withdrawFee) / 10000;
        usdtAmount = grossUsdt - fee;
    }
    
    /**
     * @dev Calculate how many diamonds user would get for depositing USDT
     */
    function calculateDeposit(uint256 usdtAmount) external view returns (uint256 diamondAmount, uint256 fee) {
        uint256 grossDiamonds = (usdtAmount * DIAMONDS_PER_USDT * (10 ** DIAMOND_DECIMALS)) / (10 ** USDT_DECIMALS);
        fee = (grossDiamonds * depositFee) / 10000;
        diamondAmount = grossDiamonds - fee;
    }
    
    // Admin functions
    
    /**
     * @dev Update deposit fee (only owner)
     */
    function setDepositFee(uint256 _fee) external onlyOwner {
        require(_fee <= MAX_FEE, "Fee too high");
        uint256 oldFee = depositFee;
        depositFee = _fee;
        emit FeeUpdated("deposit", oldFee, _fee);
    }
    
    /**
     * @dev Update withdrawal fee (only owner)
     */
    function setWithdrawFee(uint256 _fee) external onlyOwner {
        require(_fee <= MAX_FEE, "Fee too high");
        uint256 oldFee = withdrawFee;
        withdrawFee = _fee;
        emit FeeUpdated("withdraw", oldFee, _fee);
    }
    
    /**
     * @dev Update treasury address (only owner)
     */
    function setTreasury(address _treasury) external onlyOwner {
        require(_treasury != address(0), "Invalid treasury address");
        address oldTreasury = treasury;
        treasury = _treasury;
        emit TreasuryUpdated(oldTreasury, _treasury);
    }
    
    /**
     * @dev Authorize/deauthorize rewarder (only owner)
     */
    function setRewarder(address rewarder, bool authorized) external onlyOwner {
        require(rewarder != address(0), "Invalid rewarder address");
        authorizedRewarders[rewarder] = authorized;
        emit RewarderAuthorized(rewarder, authorized);
    }
    
    /**
     * @dev Pause contract (only owner)
     */
    function pause() external onlyOwner {
        _pause();
    }
    
    /**
     * @dev Unpause contract (only owner)
     */
    function unpause() external onlyOwner {
        _unpause();
    }
    
    /**
     * @dev Emergency withdraw USDT (only owner)
     */
    function emergencyWithdrawUSDT(uint256 amount) external onlyOwner {
        require(usdtToken.transfer(owner(), amount), "Emergency withdraw failed");
    }
    
    /**
     * @dev Get contract USDT balance
     */
    function getContractUSDTBalance() external view returns (uint256) {
        return usdtToken.balanceOf(address(this));
    }
}
