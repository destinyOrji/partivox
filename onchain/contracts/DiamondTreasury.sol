// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

interface IERC20 {
    function totalSupply() external view returns (uint256);
    function balanceOf(address account) external view returns (uint256);
    function transfer(address recipient, uint256 amount) external returns (bool);
    function allowance(address owner, address spender) external view returns (uint256);
    function approve(address spender, uint256 amount) external returns (bool);
    function transferFrom(address sender, address recipient, uint256 amount) external returns (bool);
    function decimals() external view returns (uint8);
}

contract DiamondTreasury {
    event Purchase(address indexed buyer, uint256 usdtAmount, uint256 diamonds);
    event Withdraw(address indexed to, uint256 usdtAmount);
    event RateUpdated(uint256 newRate);
    event OwnerTransferred(address indexed previousOwner, address indexed newOwner);

    IERC20 public immutable usdt;
    uint8 public immutable usdtDecimals;

    // diamonds per 1 USDT (e.g., 5 => 1 USDT = 5 diamonds)
    uint256 public diamondsPerUsdt;

    address public owner;

    modifier onlyOwner() {
        require(msg.sender == owner, "not owner");
        _;
    }

    constructor(address _usdt, uint256 _diamondsPerUsdt, address _owner) {
        require(_usdt != address(0), "USDT zero");
        require(_owner != address(0), "owner zero");
        usdt = IERC20(_usdt);
        usdtDecimals = IERC20(_usdt).decimals();
        diamondsPerUsdt = _diamondsPerUsdt; // e.g., 5
        owner = _owner;
        emit RateUpdated(_diamondsPerUsdt);
        emit OwnerTransferred(address(0), _owner);
    }

    function setRate(uint256 _diamondsPerUsdt) external onlyOwner {
        require(_diamondsPerUsdt > 0, "rate 0");
        diamondsPerUsdt = _diamondsPerUsdt;
        emit RateUpdated(_diamondsPerUsdt);
    }

    // Buyer must approve this contract for 'usdtAmount' before calling
    function buyWithUSDT(uint256 usdtAmount) external returns (bool) {
        require(usdtAmount > 0, "amount 0");
        // pull USDT in
        require(usdt.transferFrom(msg.sender, address(this), usdtAmount), "transferFrom failed");
        // compute diamonds: (amount / 10^decimals) * rate
        uint256 diamonds = (usdtAmount * diamondsPerUsdt) / (10 ** usdtDecimals);
        require(diamonds > 0, "diamonds 0");
        emit Purchase(msg.sender, usdtAmount, diamonds);
        return true;
    }

    function withdrawUSDT(address to, uint256 amount) external onlyOwner {
        require(to != address(0), "to zero");
        require(usdt.transfer(to, amount), "withdraw failed");
        emit Withdraw(to, amount);
    }

    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "owner zero");
        emit OwnerTransferred(owner, newOwner);
        owner = newOwner;
    }
}
