/**
 * Admin API Service
 * Handles all admin-related API calls
 */

const API_BASE_URL = '/api/admin';

// Helper function to handle API responses
async function handleResponse(response) {
    const data = await response.json();
    if (!response.ok) {
        const error = (data && data.message) || response.statusText;
        return Promise.reject(error);
    }
    return data;
}

// Helper function to set auth headers
function getAuthHeaders() {
    // Prefer unified key used across admin pages
    const token = localStorage.getItem('authToken') || localStorage.getItem('admin_token');
    return {
        'Content-Type': 'application/json',
        'Authorization': token ? `Bearer ${token}` : ''
    };
}

// Dashboard API
const DashboardAPI = {
    getStats: async () => {
        const response = await fetch(`${API_BASE_URL}/dashboard`, {
            headers: getAuthHeaders()
        });
        return handleResponse(response);
    }
};

// Users API
const UsersAPI = {
    getUsers: async (page = 1, limit = 10) => {
        const response = await fetch(`${API_BASE_URL}/users?page=${page}&limit=${limit}`, {
            headers: getAuthHeaders()
        });
        return handleResponse(response);
    },
    
    updateStatus: async (userId, status) => {
        const response = await fetch(`${API_BASE_URL}/users/status`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify({ user_id: userId, status })
        });
        return handleResponse(response);
    }
};

// Campaigns API
const CampaignsAPI = {
    getCampaigns: async (filters = {}, page = 1, limit = 10) => {
        const queryParams = new URLSearchParams({
            ...filters,
            page,
            limit
        }).toString();
        
        const response = await fetch(`${API_BASE_URL}/campaigns?${queryParams}`, {
            headers: getAuthHeaders()
        });
        return handleResponse(response);
    },
    
    updateStatus: async (campaignId, status) => {
        const response = await fetch(`${API_BASE_URL}/campaigns/status`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify({ campaign_id: campaignId, status })
        });
        return handleResponse(response);
    }
};

// Transactions API
const TransactionsAPI = {
    getTransactions: async (filters = {}, page = 1, limit = 10) => {
        const queryParams = new URLSearchParams({
            ...filters,
            page,
            limit
        }).toString();
        
        const response = await fetch(`${API_BASE_URL}/transactions?${queryParams}`, {
            headers: getAuthHeaders()
        });
        return handleResponse(response);
    }
};

// Reports API
const ReportsAPI = {
    getReports: async (status = 'pending', page = 1, limit = 10) => {
        const response = await fetch(`${API_BASE_URL}/reports?status=${status}&page=${page}&limit=${limit}`, {
            headers: getAuthHeaders()
        });
        return handleResponse(response);
    },
    
    updateStatus: async (reportId, status, adminNotes = '') => {
        const response = await fetch(`${API_BASE_URL}/reports/status`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify({
                report_id: reportId,
                status,
                admin_notes: adminNotes
            })
        });
        return handleResponse(response);
    }
};

// Settings API
const SettingsAPI = {
    getSettings: async () => {
        const response = await fetch(`${API_BASE_URL}/settings`, {
            headers: getAuthHeaders()
        });
        return handleResponse(response);
    },
    
    updateSettings: async (settings) => {
        const response = await fetch(`${API_BASE_URL}/settings`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify(settings)
        });
        return handleResponse(response);
    }
};

// Export all API modules
export {
    DashboardAPI,
    UsersAPI,
    CampaignsAPI,
    TransactionsAPI,
    ReportsAPI,
    SettingsAPI
};
