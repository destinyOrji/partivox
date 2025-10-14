// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";

/**
 * @title MockUSDT
 * @dev Mock USDT token for testing purposes
 */
contract MockUSDT is ERC20 {
    constructor() ERC20("Mock USDT", "USDT") {
        // Mint 1 million USDT for testing (6 decimals like real USDT)
        _mint(msg.sender, 1000000 * 10**6);
    }
    
    function decimals() public pure override returns (uint8) {
        return 6; // USDT has 6 decimals
    }
    
    // Faucet function for testing
    function faucet(address to, uint256 amount) external {
        _mint(to, amount);
    }
}
