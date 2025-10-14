// Role-based access control for pages
class RoleChecker {
    constructor() {
        this.API_BASE_URL = window.location.origin + "/";
    }

    // Check if user has required role for current page
    async checkPageAccess(requiredRole = 'user') {
        try {
            const token = localStorage.getItem('authToken');
            if (!token) {
                this.redirectToLogin();
                return false;
            }

            const response = await fetch(`${this.API_BASE_URL}api/auth/me`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                this.redirectToLogin();
                return false;
            }

            const data = await response.json();
            const userRole = data.user?.role || 'user';

            // Check role access
            if (requiredRole === 'admin' && userRole !== 'admin') {
                this.showAccessDenied(userRole);
                return false;
            }

            return true;
        } catch (error) {
            console.error('Role check error:', error);
            this.redirectToLogin();
            return false;
        }
    }

    // Redirect to appropriate login page
    redirectToLogin() {
        const currentPath = window.location.pathname;
        
        if (currentPath.includes('/admin_dashboard/')) {
            // Admin page - redirect to admin login
            window.location.href = '/pages/admin_dashboard/login.html';
        } else {
            // User page - redirect to general login
            window.location.href = '/pages/admin_dashboard/login.html';
        }
    }

    // Show access denied message
    showAccessDenied(userRole) {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            color: white;
            font-family: Arial, sans-serif;
        `;

        const modal = document.createElement('div');
        modal.style.cssText = `
            background: #1e1e24;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            max-width: 400px;
            border: 1px solid #27272a;
        `;

        modal.innerHTML = `
            <h3 style="color: #ef4444; margin-bottom: 1rem;">🚫 Access Denied</h3>
            <p style="color: #a1a1aa; margin-bottom: 1rem;">
                You need admin privileges to access this page.<br>
                Your current role: <strong>${userRole}</strong>
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button onclick="window.location.href='/pages/admin_dashboard/login.html'" 
                        style="background: #caf403; color: #000; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 500; cursor: pointer;">
                    Admin Login
                </button>
                <button onclick="window.location.href='/pages/userDashboard.html'" 
                        style="background: transparent; color: #a1a1aa; border: 1px solid #27272a; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">
                    User Dashboard
                </button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);
    }

    // Get current user role
    async getCurrentUserRole() {
        try {
            const token = localStorage.getItem('authToken');
            if (!token) return null;

            const response = await fetch(`${this.API_BASE_URL}api/auth/me`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) return null;

            const data = await response.json();
            return data.user?.role || 'user';
        } catch (error) {
            console.error('Error getting user role:', error);
            return null;
        }
    }
}

// Global role checker instance
window.roleChecker = new RoleChecker();

// Auto-check admin pages
document.addEventListener('DOMContentLoaded', async () => {
    const currentPath = window.location.pathname;
    
    // Check if this is an admin page
    if (currentPath.includes('/admin_dashboard/') && 
        !currentPath.includes('login.html') && 
        !currentPath.includes('otp.html')) {
        
        console.log('🔒 Checking admin access for:', currentPath);
        const hasAccess = await window.roleChecker.checkPageAccess('admin');
        
        if (!hasAccess) {
            console.log('❌ Admin access denied');
        } else {
            console.log('✅ Admin access granted');
        }
    }
});
