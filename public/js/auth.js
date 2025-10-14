/**
 * Handle Google Sign-In button click
 */
async function handleGoogleSignIn() {
    try {
        // Show loading state
        const googleBtn = document.getElementById('google-signin-btn');
        const originalBtnText = googleBtn.innerHTML;
        googleBtn.disabled = true;
        googleBtn.innerHTML = 'Redirecting to Google...';
        
        // Get the Google auth URL from your API
        const response = await fetch('/api/auth/google/url');
        const data = await response.json();
        
        if (data.status === 'success' && data.auth_url) {
            // Store the current URL to redirect back after login
            localStorage.setItem('redirect_after_login', window.location.pathname);
            // Redirect to Google's OAuth page
            window.location.href = data.auth_url;
        } else {
            throw new Error(data.message || 'Failed to get Google auth URL');
        }
    } catch (error) {
        console.error('Google Sign-In Error:', error);
        showError('An error occurred during Google Sign-In. Please try again.');
        const googleBtn = document.getElementById('google-signin-btn');
        if (googleBtn) {
            googleBtn.disabled = false;
            googleBtn.innerHTML = originalBtnText || 'Continue with Google';
        }
    }
}

/**
 * Handle the callback after Google OAuth redirect
 */
async function handleGoogleCallback() {
    const urlParams = new URLSearchParams(window.location.search);
    const code = urlParams.get('code');
    const error = urlParams.get('error');
    
    if (error) {
        showError(`Google Sign-In Error: ${error}`);
        return;
    }
    
    if (code) {
        try {
            const response = await fetch(`/api/auth/google/callback?code=${encodeURIComponent(code)}`);
            const data = await response.json();
            
            if (data.status === 'success' && data.token) {
                // Store the token using the unified key used by admin pages
                localStorage.setItem('authToken', data.token);

                // If user image is present, display it
                if (data.user && data.user.image) {
                    const imgContainer = document.getElementById('google-user-image');
                    if (imgContainer) {
                        imgContainer.innerHTML = `<img src="${data.user.image}" alt="User Image" class="rounded-circle" width="80">`;
                    }
                }

                // Redirect to dashboard after Google sign-in
                window.location.replace('/pages/admin_dashboard/dashboard1.html');
            } else {
                throw new Error(data.message || 'Authentication failed');
            }
        } catch (error) {
            console.error('Google Callback Error:', error);
            showError('Failed to complete Google Sign-In. Please try again.');
        }
    }
}

/**
 * Show error message to the user
 */
function showError(message) {
    // You can implement a better error display mechanism
    const errorDiv = document.getElementById('error-message') || document.createElement('div');
    errorDiv.id = 'error-message';
    errorDiv.style.color = 'red';
    errorDiv.style.margin = '10px 0';
    errorDiv.textContent = message;
    
    const container = document.querySelector('.signin-container') || document.body;
    if (!document.getElementById('error-message')) {
        container.insertBefore(errorDiv, container.firstChild);
    }
}

// Add event listeners when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Handle Google Sign-In button
    const googleBtn = document.getElementById('google-signin-btn');
    if (googleBtn) {
        googleBtn.addEventListener('click', handleGoogleSignIn);
    }
    
    // Check if we're on the signup page and have a Google OAuth code
    if (window.location.pathname === '/pages/admin_dashboard/signup.html' && window.location.search.includes('code=')) {
        handleGoogleCallback();
    }
});
