/**
 * Profile Utilities for Partivox Application
 * Shared functions for handling user profiles and avatars across all pages
 */

// API Base URL - dynamically set based on current origin
const API_BASE_URL = window.location.origin + "/";

/**
 * Load and update user avatar across all pages
 * @param {string} avatarElementId - ID of the avatar element (default: 'userAvatar')
 * @param {object} options - Configuration options
 */
async function loadUserAvatar(avatarElementId = 'userAvatar', options = {}) {
  try {
    const token = await getValidToken();
    if (!token) {
      console.warn('No token available for avatar loading');
      return;
    }

    const response = await fetch(`${API_BASE_URL}api/settings/profile-picture`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      const data = await response.json();
      if (data.status === 'success' && data.data.profile_picture_url) {
        const userAvatar = document.getElementById(avatarElementId);
        if (userAvatar) {
          const imageStyle = options.style || 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
          userAvatar.innerHTML = `<img src="${data.data.profile_picture_url}?t=${Date.now()}" alt="Profile" style="${imageStyle}">`;
          
          // Store avatar URL for other components
          localStorage.setItem('userAvatarUrl', data.data.profile_picture_url);
          
          // Trigger custom event for other components that might need to update
          window.dispatchEvent(new CustomEvent('avatarUpdated', { 
            detail: { avatarUrl: data.data.profile_picture_url } 
          }));
        }
      }
    }
  } catch (error) {
    console.error('Error loading user avatar:', error);
  }
}

/**
 * Load user profile information (handle, name, etc.)
 * @param {object} elementIds - Object containing element IDs for different profile fields
 */
async function loadUserProfile(elementIds = {}) {
  try {
    const token = await getValidToken();
    if (!token) {
      console.warn('No token available for profile loading');
      return;
    }

    const response = await fetch(`${API_BASE_URL}api/settings/profile`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });

    if (response.ok) {
      const data = await response.json();
      if (data.status === 'success' && data.data) {
        const profileData = data.data;
        
        // Update handle if element exists
        if (profileData.twitter_handle && elementIds.handle) {
          const handleElement = document.getElementById(elementIds.handle);
          if (handleElement) {
            const handle = `@${profileData.twitter_handle}`;
            handleElement.textContent = handle;
            localStorage.setItem('userHandle', handle);
          }
        }
        
        // Update name if element exists
        if (profileData.name && elementIds.name) {
          const nameElement = document.getElementById(elementIds.name);
          if (nameElement) {
            nameElement.textContent = profileData.name;
          }
        }
        
        // Store user ID for other components
        if (profileData.id) {
          localStorage.setItem('userId', profileData.id.toString());
        }
        
        // Trigger custom event
        window.dispatchEvent(new CustomEvent('profileUpdated', { 
          detail: { profileData } 
        }));
        
        return profileData;
      }
    }
  } catch (error) {
    console.error('Error loading user profile:', error);
  }
  return null;
}

/**
 * Update wallet address display
 * @param {string} elementId - ID of the wallet address element
 */
function updateWalletAddress(elementId = 'walletAddress') {
  const CONNECTED_EVM_KEY = 'connected_evm_wallet';
  const localWallet = localStorage.getItem(CONNECTED_EVM_KEY);
  const walletElement = document.getElementById(elementId);
  
  if (walletElement) {
    if (localWallet && localWallet.startsWith('0x')) {
      const shortAddress = `${localWallet.slice(0, 6)}...${localWallet.slice(-4)}`;
      walletElement.textContent = shortAddress;
      walletElement.title = localWallet; // Full address on hover
    } else {
      walletElement.textContent = 'Connect wallet';
      walletElement.title = '';
    }
  }
}

/**
 * Initialize profile components for a page
 * @param {object} config - Configuration object
 */
async function initializeProfile(config = {}) {
  const defaultConfig = {
    avatarElementId: 'userAvatar',
    profileElements: {
      handle: 'userHandle',
      name: 'userName'
    },
    walletElementId: 'walletAddress',
    loadAvatar: true,
    loadProfile: true,
    loadWallet: true
  };
  
  const finalConfig = { ...defaultConfig, ...config };
  
  try {
    const promises = [];
    
    if (finalConfig.loadProfile) {
      promises.push(loadUserProfile(finalConfig.profileElements));
    }
    
    if (finalConfig.loadAvatar) {
      promises.push(loadUserAvatar(finalConfig.avatarElementId));
    }
    
    // Wait for profile and avatar to load
    await Promise.all(promises);
    
    if (finalConfig.loadWallet) {
      updateWalletAddress(finalConfig.walletElementId);
    }
    
    console.log('✅ Profile initialization complete');
  } catch (error) {
    console.error('❌ Profile initialization failed:', error);
  }
}

/**
 * Refresh all profile components
 */
async function refreshProfile() {
  // Clear cached data
  localStorage.removeItem('userAvatarUrl');
  localStorage.removeItem('userHandle');
  
  // Reload profile data
  await initializeProfile();
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    loadUserAvatar,
    loadUserProfile,
    updateWalletAddress,
    initializeProfile,
    refreshProfile
  };
}

// Make functions available globally
window.ProfileUtils = {
  loadUserAvatar,
  loadUserProfile,
  updateWalletAddress,
  initializeProfile,
  refreshProfile
};
