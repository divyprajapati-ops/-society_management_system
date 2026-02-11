/**
 * Society App API Client
 * Use this in your Stitch-generated UI for backend communication
 */

class SocietyAPI {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl || window.location.origin + '/divy1/society-app';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    // Get CSRF token from meta tag
    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || this.csrfToken;
    }

    // Generic request method
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}/${endpoint}`;
        const config = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...options.headers
            },
            ...options
        };

        // Add CSRF token for POST requests
        if (config.method === 'POST') {
            config.headers['X-CSRF-Token'] = this.getCsrfToken();
            
            if (config.body instanceof FormData) {
                config.body.append('csrf_token', this.getCsrfToken());
            } else if (typeof config.body === 'object') {
                config.body = JSON.stringify({
                    ...JSON.parse(config.body || '{}'),
                    csrf_token: this.getCsrfToken()
                });
                config.headers['Content-Type'] = 'application/json';
            }
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok || !data.success) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // GET requests
    async get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = queryString ? `${endpoint}?${queryString}` : endpoint;
        return this.request(url, { method: 'GET' });
    }

    // POST requests
    async post(endpoint, data = {}) {
        const isFormData = data instanceof FormData;
        return this.request(endpoint, {
            method: 'POST',
            body: isFormData ? data : JSON.stringify(data),
            headers: isFormData ? {} : { 'Content-Type': 'application/json' }
        });
    }

    // Form submission helper
    async submitForm(formElement, endpoint) {
        const formData = new FormData(formElement);
        return this.post(endpoint, formData);
    }

    // ============== SOCIETY ADMIN ENDPOINTS ==============

    // Dashboard
    async getDashboardStats() {
        return this.get('admin/api.php', { action: 'dashboard_stats' });
    }

    async getRecentActivity() {
        return this.get('admin/api.php', { action: 'recent_activity' });
    }

    // Buildings
    async getBuildings() {
        return this.get('admin/api.php', { action: 'buildings_list' });
    }

    async addBuilding(name) {
        return this.post('admin/api.php', {
            action: 'add_building',
            building_name: name
        });
    }

    // Fund Management
    async getFundTransactions(page = 1) {
        return this.get('admin/api.php', { action: 'fund_transactions', page });
    }

    async addFundTransaction(amount, type, description, date) {
        return this.post('admin/api.php', {
            action: 'add_fund_transaction',
            amount,
            type,
            description,
            date
        });
    }

    // Meetings
    async createMeeting(title, meetingDate, level = 'society', buildingId = null) {
        return this.post('admin/api.php', {
            action: 'create_meeting',
            title,
            meeting_date: meetingDate,
            level,
            building_id: buildingId
        });
    }

    // ============== BUILDING ADMIN ENDPOINTS ==============

    async getBuildingStats() {
        return this.get('building/api.php', { action: 'dashboard_stats' });
    }

    async getBuildingMembers() {
        return this.get('building/api.php', { action: 'members_list' });
    }

    async addMember(name, email, password) {
        return this.post('building/api.php', {
            action: 'add_member',
            name,
            email,
            password
        });
    }

    async getBuildingFund() {
        return this.get('building/api.php', { action: 'fund_balance' });
    }

    async addBuildingFundTransaction(amount, type, description, date) {
        return this.post('building/api.php', {
            action: 'add_transaction',
            amount,
            type,
            description,
            date
        });
    }

    // ============== MEMBER ENDPOINTS ==============

    async getMemberDashboard() {
        return this.get('member/api.php', { action: 'dashboard' });
    }

    async getMyMaintenance() {
        return this.get('member/api.php', { action: 'my_maintenance' });
    }

    async addNote(message) {
        return this.post('member/api.php', {
            action: 'add_note',
            message
        });
    }
}

// ============== UI HELPERS ==============

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Handle form submission with loading state
async function handleFormSubmit(form, api, endpoint, onSuccess = null) {
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn?.textContent || 'Submit';
    
    try {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Loading...';
        }
        
        const result = await api.submitForm(form, endpoint);
        showToast(result.message || 'Success!');
        
        if (onSuccess) {
            onSuccess(result);
        }
        
        return result;
    } catch (error) {
        showToast(error.message || 'An error occurred', 'error');
        throw error;
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
}

// Export for use
window.SocietyAPI = SocietyAPI;
window.showToast = showToast;
window.handleFormSubmit = handleFormSubmit;
