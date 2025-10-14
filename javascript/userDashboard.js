document.getElementById('copy-wallet').addEventListener('click', function() {
    var address = document.getElementById('wallet-address').textContent;
    navigator.clipboard.writeText(address).then(function() {
      // Optionally, show feedback
      const btn = document.getElementById('copy-wallet');
      btn.innerHTML = `<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-clipboard-check' viewBox='0 0 16 16'><path d='M10 1.5v1h1A1.5 1.5 0 0 1 12.5 4v9A1.5 1.5 0 0 1 11 14.5H5A1.5 1.5 0 0 1 3.5 13V4A1.5 1.5 0 0 1 5 2.5h1v-1A.5.5 0 0 1 6.5 1h3a.5.5 0 0 1 .5.5zm-3 1V1.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V2.5h1A.5.5 0 0 1 13 4v9a.5.5 0 0 1-.5.5H5A.5.5 0 0 1 4.5 13V4a.5.5 0 0 1 .5-.5h1z'/><path d='M10.97 7.97a.75.75 0 0 1 1.07 1.05l-3 3.5a.75.75 0 0 1-1.08.02l-1.5-1.5a.75.75 0 1 1 1.06-1.06l.97.97 2.48-2.98z'/></svg>`;
      setTimeout(function() {
        btn.innerHTML = `<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-clipboard' viewBox='0 0 16 16'><path d='M10 1.5v1h1A1.5 1.5 0 0 1 12.5 4v9A1.5 1.5 0 0 1 11 14.5H5A1.5 1.5 0 0 1 3.5 13V4A1.5 1.5 0 0 1 5 2.5h1v-1A.5.5 0 0 1 6.5 1h3a.5.5 0 0 1 .5.5zm-3 1V1.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V2.5h1A.5.5 0 0 1 13 4v9a.5.5 0 0 1-.5.5H5A.5.5 0 0 1 4.5 13V4a.5.5 0 0 1 .5-.5h1z'/></svg>`;
      }, 1200);
    });
  });

  function copyLink() {
  const linkText = document.getElementById('tweet-link').textContent;
  navigator.clipboard.writeText(linkText).then(() => {
    // Optional: show feedback
    alert('Link copied!');
  });
}
// Show/hide popout logic
const buyDiamondsBtn = document.getElementById('buyDiamondsBtn');
if (buyDiamondsBtn) {
  buyDiamondsBtn.addEventListener('click', function() {
    const popout = document.getElementById('buyDiamondsPopout');
    if (popout) {
      popout.style.display = popout.style.display === 'none' ? 'block' : 'none';
    }
  });
}

const cancelDiamondsPopout = document.getElementById('cancelDiamondsPopout');
if (cancelDiamondsPopout) {
  cancelDiamondsPopout.addEventListener('click', function() {
    const popout = document.getElementById('buyDiamondsPopout');
    if (popout) {
      popout.style.display = 'none';
    }
  });
}

// Show popout when button is clicked
const convertUsdtBtn = document.getElementById('convertUsdtBtn');
if (convertUsdtBtn) {
  convertUsdtBtn.addEventListener('click', function() {
    const popout = document.getElementById('convertUsdtPopout');
    if (popout) {
      popout.style.display = 'block';
    }
  });
}

// Hide popout when either cancel button is clicked
const cancelConvertUsdtPopout = document.getElementById('cancelConvertUsdtPopout');
if (cancelConvertUsdtPopout) {
  cancelConvertUsdtPopout.addEventListener('click', function() {
    const popout = document.getElementById('convertUsdtPopout');
    if (popout) {
      popout.style.display = 'none';
    }
  });
}
const cancelConvertUsdtPopout2 = document.getElementById('cancelConvertUsdtPopout2');
if (cancelConvertUsdtPopout2) {
  cancelConvertUsdtPopout2.addEventListener('click', function() {
    const popout = document.getElementById('convertUsdtPopout');
    if (popout) {
      popout.style.display = 'none';
    }
  });
}

// Optional: live USDT estimate
const diamondAmount = document.getElementById('diamondAmount');
const usdtEstimate = document.getElementById('usdtEstimate');
if (diamondAmount && usdtEstimate) {
  diamondAmount.addEventListener('input', function() {
    const rate = 0.234;
    const amount = parseFloat(this.value) || 0;
    usdtEstimate.textContent = '$' + (amount * rate).toFixed(2);
  });
}

const popoutCard = document.getElementById('launchCard');
const launchBtn = document.getElementById('launchBtn');

if (launchBtn && popoutCard) {
  launchBtn.addEventListener('click', () => {
    popoutCard.style.display = 'block';
  });
}

function hidePopout() {
  if (popoutCard) {
    popoutCard.style.display = 'none';
  }
}

// Populate user info from session via backend endpoint
document.addEventListener('DOMContentLoaded', function() {
  // Try Twitter endpoint first, then fallback to general user endpoint
  fetch('/api/twitter/me.php', { credentials: 'include', cache: 'no-store' })
    .then(function(res) {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.json().catch(function(){ throw new Error('Invalid JSON from /api/twitter/me.php'); });
    })
    .then(function(data) {
      if (!data || data.authenticated !== true) {
        // Try general user endpoint for email users
        console.log('Twitter auth failed, trying user endpoint...');
        return fetch('/api/user/me.php', { credentials: 'include', cache: 'no-store' })
          .then(res => {
            if (!res.ok) {
              throw new Error('User endpoint failed: ' + res.status);
            }
            return res.json();
          })
          .catch((err) => {
            console.error('User endpoint error:', err);
            // If both fail, redirect to login
            window.location.href = '/index.html';
            return null;
          });
      }
      return data;
    })
    .then(function(data) {
      if (!data || data.authenticated !== true) {
        // Not authenticated; send user to start page
        window.location.href = '/index.html';
        return;
      }

      var handle, displayName, imgUrl;
      
      if (data.provider === 'twitter') {
        // Twitter user
        handle = data.twitter && data.twitter.screen_name ? '@' + data.twitter.screen_name : '@username';
        displayName = (data.user && data.user.name) ? data.user.name : handle;
        
        if (data.user && data.user.twitter_profile_image_url) {
          imgUrl = data.user.twitter_profile_image_url;
        } else if (data.twitter && data.twitter.profile_image_url) {
          imgUrl = data.twitter.profile_image_url;
        }
      } else {
        // Email user
        var userName = (data.user && data.user.name) ? data.user.name : 'User';
        var userEmail = (data.user && data.user.email) ? data.user.email : '';
        
        handle = '@' + userName.toLowerCase().replace(/\s+/g, '');
        displayName = userName;
        imgUrl = data.user && data.user.avatar ? data.user.avatar : null;
      }

      // Update all the UI elements
      var elHandleHeader = document.getElementById('twitter-handle');
      var elWelcome = document.getElementById('welcome-handle');
      var elAvatar = document.getElementById('user-avatar');
      var handleTargets = [
        document.getElementById('card-handle-1'),
        document.getElementById('popout-handle-1'),
        document.getElementById('card-handle-2'),
        document.getElementById('popout-handle-2'),
        document.getElementById('card-handle-3'),
        document.getElementById('popout-handle-3')
      ];

      if (elHandleHeader) elHandleHeader.textContent = handle;
      if (elWelcome) elWelcome.textContent = displayName;
      handleTargets.forEach(function(el){ if (el) el.textContent = handle; });

      // Set profile image
      if (elAvatar && imgUrl) {
        // Upgrade Twitter's _normal to higher res if present
        elAvatar.src = imgUrl.replace('_normal', '_200x200');
      }
      
      console.log('User info loaded:', { handle, displayName, provider: data.provider });
    })
    .catch(function(err) {
      console.error('Failed to load user info', err);
      // Check if user has auth token for email login
      const authToken = localStorage.getItem('authToken');
      if (authToken) {
        // Try to get user info from token or show default
        const elWelcome = document.getElementById('welcome-handle');
        if (elWelcome) elWelcome.textContent = 'User';
        console.log('Using fallback user display');
      } else {
        // No auth at all, redirect to home
        window.location.href = '/index.html';
      }
    });
});