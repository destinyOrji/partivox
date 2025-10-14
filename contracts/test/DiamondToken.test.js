const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("DiamondToken", function () {
  let diamondToken;
  let mockUSDT;
  let owner;
  let user1;
  let user2;
  let treasury;

  beforeEach(async function () {
    [owner, user1, user2, treasury] = await ethers.getSigners();

    // Deploy Mock USDT
    const MockUSDT = await ethers.getContractFactory("MockUSDT");
    mockUSDT = await MockUSDT.deploy();
    await mockUSDT.deployed();

    // Deploy DiamondToken
    const DiamondToken = await ethers.getContractFactory("DiamondToken");
    diamondToken = await DiamondToken.deploy(mockUSDT.address, treasury.address);
    await diamondToken.deployed();

    // Give user1 some USDT
    await mockUSDT.faucet(user1.address, ethers.utils.parseUnits("1000", 6)); // 1000 USDT
  });

  describe("Deployment", function () {
    it("Should set the right owner", async function () {
      expect(await diamondToken.owner()).to.equal(owner.address);
    });

    it("Should set the right USDT token", async function () {
      expect(await diamondToken.usdtToken()).to.equal(mockUSDT.address);
    });

    it("Should set the right treasury", async function () {
      expect(await diamondToken.treasury()).to.equal(treasury.address);
    });

    it("Should have correct exchange rate", async function () {
      expect(await diamondToken.DIAMONDS_PER_USDT()).to.equal(20);
    });
  });

  describe("Deposit", function () {
    it("Should allow users to deposit USDT and receive diamonds", async function () {
      const usdtAmount = ethers.utils.parseUnits("100", 6); // 100 USDT
      
      // Approve USDT spending
      await mockUSDT.connect(user1).approve(diamondToken.address, usdtAmount);
      
      // Deposit USDT
      await diamondToken.connect(user1).deposit(usdtAmount);
      
      // Check diamond balance (100 USDT * 20 = 2000 diamonds, minus 2.5% fee)
      const expectedDiamonds = ethers.utils.parseEther("1950"); // 2000 - 50 (2.5% fee)
      const balance = await diamondToken.balanceOf(user1.address);
      
      expect(balance).to.be.closeTo(expectedDiamonds, ethers.utils.parseEther("1"));
    });

    it("Should charge deposit fee to treasury", async function () {
      const usdtAmount = ethers.utils.parseUnits("100", 6);
      
      await mockUSDT.connect(user1).approve(diamondToken.address, usdtAmount);
      await diamondToken.connect(user1).deposit(usdtAmount);
      
      // Treasury should receive fee (50 diamonds)
      const treasuryBalance = await diamondToken.balanceOf(treasury.address);
      const expectedFee = ethers.utils.parseEther("50");
      
      expect(treasuryBalance).to.be.closeTo(expectedFee, ethers.utils.parseEther("1"));
    });

    it("Should fail if insufficient USDT balance", async function () {
      const usdtAmount = ethers.utils.parseUnits("2000", 6); // More than user has
      
      await mockUSDT.connect(user1).approve(diamondToken.address, usdtAmount);
      
      await expect(
        diamondToken.connect(user1).deposit(usdtAmount)
      ).to.be.revertedWith("USDT transfer failed");
    });
  });

  describe("Withdraw", function () {
    beforeEach(async function () {
      // Give user1 some diamonds first
      const usdtAmount = ethers.utils.parseUnits("100", 6);
      await mockUSDT.connect(user1).approve(diamondToken.address, usdtAmount);
      await diamondToken.connect(user1).deposit(usdtAmount);
    });

    it("Should allow users to withdraw diamonds for USDT", async function () {
      const diamondAmount = ethers.utils.parseEther("1000"); // 1000 diamonds
      
      const initialUSDTBalance = await mockUSDT.balanceOf(user1.address);
      
      // Withdraw diamonds
      await diamondToken.connect(user1).withdraw(diamondAmount);
      
      // Check USDT received (1000 diamonds / 20 = 50 USDT, minus 3% fee)
      const finalUSDTBalance = await mockUSDT.balanceOf(user1.address);
      const usdtReceived = finalUSDTBalance.sub(initialUSDTBalance);
      
      // Expected: 50 USDT - 1.5 USDT fee = 48.5 USDT
      const expectedUSDT = ethers.utils.parseUnits("48.5", 6);
      expect(usdtReceived).to.be.closeTo(expectedUSDT, ethers.utils.parseUnits("0.1", 6));
    });

    it("Should burn user's diamonds on withdrawal", async function () {
      const diamondAmount = ethers.utils.parseEther("1000");
      const initialBalance = await diamondToken.balanceOf(user1.address);
      
      await diamondToken.connect(user1).withdraw(diamondAmount);
      
      const finalBalance = await diamondToken.balanceOf(user1.address);
      expect(finalBalance).to.equal(initialBalance.sub(diamondAmount));
    });

    it("Should fail if insufficient diamond balance", async function () {
      const diamondAmount = ethers.utils.parseEther("10000"); // More than user has
      
      await expect(
        diamondToken.connect(user1).withdraw(diamondAmount)
      ).to.be.revertedWith("Insufficient Diamond balance");
    });
  });

  describe("Task Engagement", function () {
    beforeEach(async function () {
      // Give user1 some diamonds
      const usdtAmount = ethers.utils.parseUnits("100", 6);
      await mockUSDT.connect(user1).approve(diamondToken.address, usdtAmount);
      await diamondToken.connect(user1).deposit(usdtAmount);
    });

    it("Should allow users to engage tasks", async function () {
      const taskId = ethers.utils.formatBytes32String("task_123");
      const diamondAmount = ethers.utils.parseEther("100");
      
      const initialBalance = await diamondToken.balanceOf(user1.address);
      
      await diamondToken.connect(user1).engageTask(taskId, diamondAmount);
      
      const finalBalance = await diamondToken.balanceOf(user1.address);
      expect(finalBalance).to.equal(initialBalance.sub(diamondAmount));
    });

    it("Should prevent duplicate task engagement", async function () {
      const taskId = ethers.utils.formatBytes32String("task_123");
      const diamondAmount = ethers.utils.parseEther("100");
      
      await diamondToken.connect(user1).engageTask(taskId, diamondAmount);
      
      await expect(
        diamondToken.connect(user1).engageTask(taskId, diamondAmount)
      ).to.be.revertedWith("Task already completed");
    });
  });

  describe("Rewards", function () {
    it("Should allow authorized rewarders to give rewards", async function () {
      const taskId = ethers.utils.formatBytes32String("reward_task");
      const diamondAmount = ethers.utils.parseEther("200");
      
      const initialBalance = await diamondToken.balanceOf(user1.address);
      
      // Owner is authorized by default
      await diamondToken.connect(owner).rewardUser(user1.address, taskId, diamondAmount);
      
      const finalBalance = await diamondToken.balanceOf(user1.address);
      expect(finalBalance).to.equal(initialBalance.add(diamondAmount));
    });

    it("Should fail if unauthorized user tries to give rewards", async function () {
      const taskId = ethers.utils.formatBytes32String("reward_task");
      const diamondAmount = ethers.utils.parseEther("200");
      
      await expect(
        diamondToken.connect(user1).rewardUser(user2.address, taskId, diamondAmount)
      ).to.be.revertedWith("Not authorized to give rewards");
    });
  });

  describe("User Statistics", function () {
    it("Should track user statistics correctly", async function () {
      // Deposit
      const usdtAmount = ethers.utils.parseUnits("100", 6);
      await mockUSDT.connect(user1).approve(diamondToken.address, usdtAmount);
      await diamondToken.connect(user1).deposit(usdtAmount);
      
      // Engage task
      const taskId = ethers.utils.formatBytes32String("task_123");
      const spentAmount = ethers.utils.parseEther("100");
      await diamondToken.connect(user1).engageTask(taskId, spentAmount);
      
      // Reward
      const rewardAmount = ethers.utils.parseEther("50");
      const rewardTaskId = ethers.utils.formatBytes32String("reward_task");
      await diamondToken.connect(owner).rewardUser(user1.address, rewardTaskId, rewardAmount);
      
      // Withdraw
      const withdrawAmount = ethers.utils.parseEther("500");
      await diamondToken.connect(user1).withdraw(withdrawAmount);
      
      // Check stats
      const stats = await diamondToken.getUserStats(user1.address);
      
      expect(stats.deposited).to.equal(usdtAmount);
      expect(stats.spent).to.equal(spentAmount);
      expect(stats.earned).to.equal(rewardAmount);
      // Withdrawn amount should be close to expected (accounting for fees)
      expect(stats.withdrawn).to.be.gt(0);
    });
  });

  describe("Fee Calculations", function () {
    it("Should calculate deposit preview correctly", async function () {
      const usdtAmount = ethers.utils.parseUnits("100", 6);
      const preview = await diamondToken.calculateDeposit(usdtAmount);
      
      // 100 USDT * 20 = 2000 diamonds
      const expectedGross = ethers.utils.parseEther("2000");
      const expectedFee = expectedGross.mul(250).div(10000); // 2.5%
      const expectedNet = expectedGross.sub(expectedFee);
      
      expect(preview.diamondAmount).to.equal(expectedNet);
      expect(preview.fee).to.equal(expectedFee);
    });

    it("Should calculate withdrawal preview correctly", async function () {
      const diamondAmount = ethers.utils.parseEther("1000");
      const preview = await diamondToken.calculateWithdrawal(diamondAmount);
      
      // 1000 diamonds / 20 = 50 USDT
      const expectedGross = ethers.utils.parseUnits("50", 6);
      const expectedFee = expectedGross.mul(300).div(10000); // 3%
      const expectedNet = expectedGross.sub(expectedFee);
      
      expect(preview.usdtAmount).to.equal(expectedNet);
      expect(preview.fee).to.equal(expectedFee);
    });
  });

  describe("Admin Functions", function () {
    it("Should allow owner to update fees", async function () {
      await diamondToken.connect(owner).setDepositFee(500); // 5%
      expect(await diamondToken.depositFee()).to.equal(500);
      
      await diamondToken.connect(owner).setWithdrawFee(400); // 4%
      expect(await diamondToken.withdrawFee()).to.equal(400);
    });

    it("Should prevent setting fees too high", async function () {
      await expect(
        diamondToken.connect(owner).setDepositFee(1500) // 15% > 10% max
      ).to.be.revertedWith("Fee too high");
    });

    it("Should allow owner to authorize rewarders", async function () {
      await diamondToken.connect(owner).setRewarder(user1.address, true);
      expect(await diamondToken.authorizedRewarders(user1.address)).to.be.true;
      
      // Now user1 can give rewards
      const taskId = ethers.utils.formatBytes32String("reward_task");
      const diamondAmount = ethers.utils.parseEther("100");
      
      await expect(
        diamondToken.connect(user1).rewardUser(user2.address, taskId, diamondAmount)
      ).to.not.be.reverted;
    });

    it("Should allow owner to pause/unpause", async function () {
      await diamondToken.connect(owner).pause();
      
      const usdtAmount = ethers.utils.parseUnits("100", 6);
      await mockUSDT.connect(user1).approve(diamondToken.address, usdtAmount);
      
      await expect(
        diamondToken.connect(user1).deposit(usdtAmount)
      ).to.be.revertedWith("Pausable: paused");
      
      await diamondToken.connect(owner).unpause();
      
      await expect(
        diamondToken.connect(user1).deposit(usdtAmount)
      ).to.not.be.reverted;
    });
  });
});
