// Global User Management System
// This script handles user authentication and display across all pages

class GlobalUserManager {
  constructor() {
    this.userInfo = null;
    this.isLoaded = false;
    this.callbacks = [];
  }

  // Load user information from API
  async loadUserInfo() {
    if (this.isLoaded && this.userInfo) {
      return this.userInfo;
    }

    try {
      console.log('Loading global user info...');
      
      const response = await fetch(`${window.location.origin}/api/auth/me`, {
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (!response.ok) {
        console.warn('User info API not available, using fallback');
        // Don't throw error, just return null to allow page to continue
        return null;
      }

      const data = await response.json();
      
      if (data.status === 'success' && data.user) {
        // Process user data
        let handle, displayName, imgUrl, provider;

        if (data.provider === 'twitter') {
          // Twitter user
          handle = data.user.twitter_handle || '@' + (data.user.name || 'user');
          displayName = data.user.name || data.user.twitter_handle || 'User';
          imgUrl = data.user.twitter_profile_image_url || data.user.avatar;
          provider = 'twitter';
        } else {
          // Email user
          const userName = data.user.name || 'User';
          handle = '@' + userName.toLowerCase().replace(/\s+/g, '');
          displayName = userName;
          imgUrl = data.user.avatar;
          provider = 'email';
        }

        this.userInfo = {
          handle,
          displayName,
          imgUrl,
          provider,
          email: data.user.email,
          id: data.user.id,
          raw: data
        };

        this.isLoaded = true;
        console.log('Global user info loaded:', this.userInfo);
        
        // Trigger callbacks
        this.callbacks.forEach(callback => callback(this.userInfo));
        
        return this.userInfo;
      } else {
        throw new Error('Invalid user data received');
      }
    } catch (error) {
      console.error('Error loading global user info:', error);
      
      // Check for stored user info as fallback
      const storedUser = localStorage.getItem('userInfo');
      if (storedUser) {
        try {
          this.userInfo = JSON.parse(storedUser);
          this.isLoaded = true;
          console.log('Using stored user info:', this.userInfo);
          return this.userInfo;
        } catch (parseError) {
          console.error('Error parsing stored user info:', parseError);
        }
      }
      
      return null;
    }
  }

  // Store user info in localStorage for offline access
  storeUserInfo() {
    if (this.userInfo) {
      localStorage.setItem('userInfo', JSON.stringify(this.userInfo));
    }
  }

  // Get user info (load if not already loaded)
  async getUserInfo() {
    if (!this.isLoaded) {
      await this.loadUserInfo();
    }
    return this.userInfo;
  }

  // Register callback for when user info is loaded
  onUserLoaded(callback) {
    if (this.isLoaded && this.userInfo) {
      callback(this.userInfo);
    } else {
      this.callbacks.push(callback);
    }
  }

  // Update user displays on the page
  updateUserDisplay() {
    if (!this.userInfo) return;

    const { handle, displayName, imgUrl } = this.userInfo;

    // Update all elements with user-handle class or id
    const handleElements = document.querySelectorAll('#user-handle, .user-handle, #userHandle, #adminName, #twitter-handle');
    handleElements.forEach(el => {
      if (el) el.textContent = handle;
    });

    // Update all elements with user-name class or id  
    const nameElements = document.querySelectorAll('#user-name, .user-name, #userName, #welcome-handle');
    nameElements.forEach(el => {
      if (el) el.textContent = displayName;
    });

    // Update avatar images with fallback to localStorage
    this.updateAvatarDisplays();

    // Trigger callbacks
    this.callbacks.forEach(callback => {
      try {
        callback(this.userInfo);
      } catch (error) {
        console.warn('User info callback error:', error);
      }
    });

    console.log('User display updated across page');
  }

  // Update avatar displays across the page
  updateAvatarDisplays() {
    // Get avatar URL from user info or localStorage
    let avatarUrl = this.userInfo?.imgUrl;
    
    // Check localStorage for updated avatar
    const storedAvatar = localStorage.getItem('userAvatar');
    if (storedAvatar) {
      avatarUrl = 'http://localhost:8000' + storedAvatar + '?t=' + Date.now();
    }

    // Update all avatar elements
    const avatarElements = document.querySelectorAll('#user-avatar, .user-avatar, #userAvatar, #profile-avatar');
    avatarElements.forEach(el => {
      if (el && avatarUrl) {
        if (el.tagName === 'IMG') {
          el.src = avatarUrl;
        } else {
          el.style.backgroundImage = `url(${avatarUrl})`;
        }
      }
    });
  }

  // Initialize user display for a page
  async initializePage() {
    try {
      await this.loadUserInfo();
      this.updateUserDisplay();
      this.storeUserInfo();
    } catch (error) {
      console.error('Error initializing user display:', error);
      
      // Fallback: try to use stored info
      const storedUser = localStorage.getItem('userInfo');
      if (storedUser) {
        try {
          this.userInfo = JSON.parse(storedUser);
          this.updateUserDisplay();
        } catch (parseError) {
          console.error('Error using stored user info:', parseError);
        }
      }
    }
  }

  // Clear user info (for logout)
  clearUserInfo() {
    this.userInfo = null;
    this.isLoaded = false;
    this.callbacks = [];
    localStorage.removeItem('userInfo');
  }
}

// Create global instance
window.globalUser = new GlobalUserManager();

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  console.log('Global user manager initializing...');
  window.globalUser.initializePage();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
  module.exports = GlobalUserManager;
}
