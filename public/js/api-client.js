// API Client for DTFTA CRM
class APIClient {
    constructor(baseURL = '/api/v1') {
        this.baseURL = baseURL;
        this.timeout = 10000;
    }

    getToken() {
        return localStorage.getItem('auth_token');
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const token = this.getToken();

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const config = {
            method: options.method || 'GET',
            headers,
            ...options
        };

        if (options.body && typeof options.body === 'object') {
            config.body = JSON.stringify(options.body);
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);

            const response = await fetch(url, { ...config, signal: controller.signal });
            clearTimeout(timeoutId);

            // Handle 401 - redirect to login
            if (response.status === 401) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('user_info');
                window.location.href = '{{ route("login") }}' || '/login';
                return;
            }

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            throw error;
        }
    }

    // Auth endpoints
    async getOrders(page = 1, perPage = 10, filters = {}) {
        const params = new URLSearchParams({ page, per_page: perPage, ...filters });
        return this.request(`/orders?${params}`);
    }

    async createOrder(data) {
        return this.request('/orders', { method: 'POST', body: data });
    }

    async getOrder(id) {
        return this.request(`/orders/${id}`);
    }

    async updateOrder(id, data) {
        return this.request(`/orders/${id}`, { method: 'PUT', body: data });
    }

    async getJobs(page = 1, perPage = 10, filters = {}) {
        const params = new URLSearchParams({ page, per_page: perPage, ...filters });
        return this.request(`/jobs?${params}`);
    }

    async createJob(data) {
        return this.request('/jobs', { method: 'POST', body: data });
    }

    async getJob(id) {
        return this.request(`/jobs/${id}`);
    }

    async updateJob(id, data) {
        return this.request(`/jobs/${id}`, { method: 'PUT', body: data });
    }

    async getJobStats() {
        return this.request('/jobs/stats/summary');
    }

    async getShipments(page = 1, perPage = 10, filters = {}) {
        const params = new URLSearchParams({ page, per_page: perPage, ...filters });
        return this.request(`/shipments?${params}`);
    }

    async createShipment(data) {
        return this.request('/shipments', { method: 'POST', body: data });
    }

    async getShipment(id) {
        return this.request(`/shipments/${id}`);
    }

    async updateShipment(id, data) {
        return this.request(`/shipments/${id}`, { method: 'PUT', body: data });
    }

    async getShops(page = 1, perPage = 10, filters = {}) {
        const params = new URLSearchParams({ page, per_page: perPage, ...filters });
        return this.request(`/shops?${params}`);
    }

    async createShop(data) {
        return this.request('/shops', { method: 'POST', body: data });
    }

    async getShop(id) {
        return this.request(`/shops/${id}`);
    }

    async updateShop(id, data) {
        return this.request(`/shops/${id}`, { method: 'PUT', body: data });
    }

    async deleteShop(id) {
        return this.request(`/shops/${id}`, { method: 'DELETE' });
    }

    async getUsers(page = 1, perPage = 10) {
        const params = new URLSearchParams({ page, per_page: perPage });
        return this.request(`/users?${params}`);
    }

    async createUser(data) {
        return this.request('/users', { method: 'POST', body: data });
    }

    async getUser(id) {
        return this.request(`/users/${id}`);
    }

    async updateUser(id, data) {
        return this.request(`/users/${id}`, { method: 'PUT', body: data });
    }

    async deleteUser(id) {
        return this.request(`/users/${id}`, { method: 'DELETE' });
    }

    async getActivityLogs(page = 1, perPage = 10) {
        const params = new URLSearchParams({ page, per_page: perPage });
        return this.request(`/activity-logs?${params}`);
    }
}

// Export and create global instance
const apiClient = new APIClient();
