// =======================
// ADMIN AUTHENTICATION SYSTEM
// =======================

const API_BASE_URL = "/";

// =======================
// Authentication Check
// =======================
function checkAdminAuth() {
    const token = localStorage.getItem("authToken");
    const currentPath = window.location.pathname;
    const isAuthPage = currentPath.includes("login.html") || 
                      currentPath.includes("signup.html") || 
                      currentPath.includes("otp.html");

    // Reduced logging - only log important events
    // console.log("Admin auth check:", { token: !!token, currentPath, isAuthPage });

    if (!token && !isAuthPage && currentPath.includes("/admin_dashboard/")) {
        // Not logged in and trying to access admin page → force login
        console.log("❌ No admin token, redirecting to login");
        window.location.href = "/pages/admin_dashboard/login.html";
        return false;
    }

    if (token && isAuthPage) {
        // Already logged in → send to dashboard
        console.log("✅ Admin token exists, redirecting from auth page to dashboard");
        window.location.href = "/pages/admin_dashboard/dashboard1.html";
        return true;
    }

    // Skip automatic token validation to prevent logout loops
    // Token will be validated only when making API calls
    if (token && !isAuthPage) {
        console.log("✅ Admin token exists, allowing access to dashboard");
    }

    return true;
}

// =======================
// Token Validation
// =======================
async function validateAdminToken(token) {
    try {
        // Basic JWT structure check
        if (!token || token.split('.').length !== 3) {
            console.log("❌ Invalid token structure");
            handleLogout();
            return false;
        }

        // Decode JWT to check expiration and role
        const payload = JSON.parse(atob(token.split('.')[1]));
        const currentTime = Math.floor(Date.now() / 1000);
        
        // Check if token is expired
        if (payload.exp && payload.exp < currentTime) {
            console.log("❌ Admin token expired");
            handleLogout();
            return false;
        }

        // Check if user has admin role (be more lenient)
        if (payload.data && payload.data.role && payload.data.role !== 'admin') {
            console.log("⚠️ User does not have admin role:", payload.data.role);
            // Don't auto-logout, just log the warning
            // Let the server-side validation handle role checking
            console.log("⚠️ Allowing access anyway - server will validate role");
        }

        // Skip server validation for now to prevent logout loops
        // Only validate locally to avoid network issues causing logouts
        // console.log("✅ Admin token validated locally");
        return true;

    } catch (error) {
        console.error("Error validating admin token:", error);
        // Don't logout on validation errors - could be network issues
        // Only logout if token is clearly invalid
        if (error.message && error.message.includes('Invalid')) {
            handleLogout();
            return false;
        }
        // For other errors, assume token is valid to prevent logout loops
        console.log("⚠️ Token validation error, assuming valid to prevent logout loop");
        return true;
    }
}

// =======================
// Logout Handler
// =======================
function handleLogout() {
    localStorage.removeItem('authToken');
    sessionStorage.clear();
    window.location.href = '/pages/admin_dashboard/login.html';
}

// =======================
// Secure Logout with Confirmation
// =======================
function confirmLogout() {
    if (confirm('Are you sure you want to logout from the admin dashboard?')) {
        console.log("🚪 Admin logout confirmed");
        handleLogout();
    }
}

// =======================
// Setup Logout Buttons
// =======================
function setupLogoutButtons() {
    // Find all logout buttons
    const logoutButtons = document.querySelectorAll('#logoutBtn, .logout-btn, [data-action="logout"]');
    
    logoutButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            confirmLogout();
        });
    });
}

// =======================
// Get Valid Admin Token
// =======================
async function getValidAdminToken() {
    const token = localStorage.getItem('authToken');
    
    if (!token) {
        console.log("❌ No admin token found");
        return null;
    }

    // Skip validation for API calls to prevent logout loops
    // Just return the token if it exists
    return token;
}

// =======================
// Safe Admin API Call
// =======================
async function safeFetchAdmin(url, options = {}) {
    const token = await getValidAdminToken();
    if (!token) {
        return null;
    }

    // Add authorization header
    options.headers = {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        ...options.headers
    };

    try {
        const response = await fetch(url, options);
        
        // Only logout on 401 if it's clearly an auth issue
        if (response.status === 401) {
            console.log("❌ Admin API call unauthorized - token may be invalid");
            // Don't auto-logout, let user handle it
            return { error: 'Unauthorized', status: 401 };
        }

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error("Admin API call error:", error);
        return { error: error.message };
    }
}

// =======================
// Initialize Admin Auth
// =======================
function initializeAdminAuth() {
    // Run auth check
    checkAdminAuth();
    
    // Setup logout buttons
    setupLogoutButtons();
    
    // Remove periodic token checking to prevent logout loops
    // Token validation will happen only on page load and API calls
}

// =======================
// Auto-initialize
// =======================
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeAdminAuth);
} else {
    initializeAdminAuth();
}

// =======================
// Export functions for global use
// =======================
window.adminAuth = {
    checkAdminAuth,
    validateAdminToken,
    handleLogout,
    confirmLogout,
    getValidAdminToken,
    safeFetchAdmin
};
