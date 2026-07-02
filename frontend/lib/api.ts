const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8004/api';

interface ApiOptions {
  method?: string;
  body?: unknown;
  headers?: Record<string, string>;
}

class ApiClient {
  private getToken(): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem('token');
  }

  private async request<T>(endpoint: string, options: ApiOptions = {}): Promise<T> {
    const token = this.getToken();
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const config: RequestInit = {
      method: options.method || 'GET',
      headers,
    };

    if (options.body) {
      config.body = JSON.stringify(options.body);
    }

    const response = await fetch(`${API_URL}${endpoint}`, config);

    if (response.status === 401) {
      localStorage.removeItem('token');
      if (typeof window !== 'undefined') {
        window.location.href = '/login';
      }
      throw new Error('Unauthorized');
    }

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Request failed' }));
      throw new Error(error.message || `HTTP ${response.status}`);
    }

    return response.json();
  }

  // Auth
  async login(email: string, password: string) {
    const data = await this.request<{ data: { token: string; user: User } }>('/auth/login', {
      method: 'POST',
      body: { email, password },
    });
    localStorage.setItem('token', data.data.token);
    return data.data;
  }

  async register(name: string, email: string, password: string, company_name: string) {
    const data = await this.request<{ data: { token: string; user: User } }>('/auth/register', {
      method: 'POST',
      body: { name, email, password, password_confirmation: password, company_name },
    });
    localStorage.setItem('token', data.data.token);
    return data.data;
  }

  async logout() {
    await this.request('/auth/logout', { method: 'POST' });
    localStorage.removeItem('token');
  }

  async me() {
    return this.request<{ data: User }>('/auth/me');
  }

  // Customers
  async getCustomers(params?: Record<string, string>) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    return this.request<PaginatedResponse<Customer>>(`/customers${query}`);
  }

  async createCustomer(data: Partial<Customer>) {
    return this.request<{ data: Customer }>('/customers', { method: 'POST', body: data });
  }

  async updateCustomer(id: number, data: Partial<Customer>) {
    return this.request<{ data: Customer }>(`/customers/${id}`, { method: 'PUT', body: data });
  }

  async deleteCustomer(id: number) {
    return this.request(`/customers/${id}`, { method: 'DELETE' });
  }

  // Plans
  async getPlans(params?: Record<string, string>) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    return this.request<PaginatedResponse<Plan>>(`/plans${query}`);
  }

  async createPlan(data: Partial<Plan>) {
    return this.request<{ data: Plan }>('/plans', { method: 'POST', body: data });
  }

  async updatePlan(id: number, data: Partial<Plan>) {
    return this.request<{ data: Plan }>(`/plans/${id}`, { method: 'PUT', body: data });
  }

  async deletePlan(id: number) {
    return this.request(`/plans/${id}`, { method: 'DELETE' });
  }

  // Subscriptions
  async getSubscriptions(params?: Record<string, string>) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    return this.request<PaginatedResponse<Subscription>>(`/subscriptions${query}`);
  }

  async createSubscription(data: { customer_id: number; plan_id: number }) {
    return this.request<{ data: Subscription }>('/subscriptions', { method: 'POST', body: data });
  }

  async cancelSubscription(id: number, reason?: string) {
    return this.request<{ data: Subscription }>(`/subscriptions/${id}/cancel`, {
      method: 'PUT',
      body: { reason },
    });
  }

  // Invoices
  async getInvoices(params?: Record<string, string>) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    return this.request<PaginatedResponse<Invoice>>(`/invoices${query}`);
  }

  // Analytics
  async getDashboard() {
    return this.request<{ data: DashboardData }>('/analytics/dashboard');
  }

  async exportDashboardPdf(): Promise<Blob> {
    const token = this.getToken();
    const headers: Record<string, string> = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const response = await fetch(`${API_URL}/analytics/dashboard/pdf`, { headers });
    if (!response.ok) throw new Error('Failed to export PDF');
    return response.blob();
  }

  async exportDashboardCsv(): Promise<Blob> {
    const token = this.getToken();
    const headers: Record<string, string> = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;
    const response = await fetch(`${API_URL}/analytics/dashboard/csv`, { headers });
    if (!response.ok) throw new Error('Failed to export CSV');
    return response.blob();
  }

  async createSubscriptionPortal(returnUrl: string): Promise<{ data: { url: string } }> {
    return this.request<{ data: { url: string } }>('/subscriptions/portal', {
      method: 'POST',
      body: { return_url: returnUrl },
    });
  }

  // Admin User Management
  async getAdminUsers(params?: Record<string, string>) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    return this.request<LaravelPaginatedResponse<AdminUser>>(`/admin/users${query}`);
  }

  async getAdminUser(id: number) {
    return this.request<{ data: AdminUser }>(`/admin/users/${id}`);
  }

  async updateAdminUserLimit(id: number, subscriberLimit: number) {
    return this.request<{ message: string; data: AdminUser }>(`/admin/users/${id}/limit`, {
      method: 'PUT',
      body: { subscriber_limit: subscriberLimit },
    });
  }

  async toggleAdminUserLock(id: number) {
    return this.request<{ message: string; data: AdminUser }>(`/admin/users/${id}/toggle-lock`, {
      method: 'POST',
    });
  }

  // Admin System Health & Queues
  async getAdminSystemHealth() {
    return this.request<SystemHealthReport>('/admin/system/health');
  }

  async getAdminQueueStatus(params?: Record<string, string>) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    return this.request<QueueStatusResponse>(`/admin/system/queue${query}`);
  }

  async retryAdminQueueJob(id: string = 'all') {
    return this.request<{ message: string }>('/admin/system/queue/retry', {
      method: 'POST',
      body: { id },
    });
  }

  // Admin Webhooks
  async getAdminWebhooks(params?: Record<string, string>) {
    const query = params ? '?' + new URLSearchParams(params).toString() : '';
    return this.request<LaravelPaginatedResponse<AdminWebhookEvent>>(`/admin/webhooks${query}`);
  }

  async getAdminWebhook(id: number) {
    return this.request<{ data: AdminWebhookEvent }>(`/admin/webhooks/${id}`);
  }

  async reprocessAdminWebhook(id: number) {
    return this.request<{ message: string; data: AdminWebhookEvent }>(`/admin/webhooks/${id}/reprocess`, {
      method: 'POST',
    });
  }

  // Admin System Settings
  async getAdminSettings() {
    return this.request<{ data: AdminSettings }>('/admin/system/settings');
  }

  async updateAdminSettings(settings: Partial<AdminSettings>) {
    return this.request<{ message: string; data: AdminSettings }>('/admin/system/settings', {
      method: 'PUT',
      body: settings,
    });
  }

  // Admin Two-Factor Authentication
  async getAdminTwoFactorStatus() {
    return this.request<{ data: { two_factor_enabled: boolean } }>('/admin/2fa/status');
  }

  async setupAdminTwoFactor() {
    return this.request<{ data: { secret: string; otpauth_url: string } }>('/admin/2fa/setup', {
      method: 'POST',
    });
  }

  async confirmAdminTwoFactor(secret: string, code: string) {
    return this.request<{ message: string }>('/admin/2fa/confirm', {
      method: 'POST',
      body: { secret, code },
    });
  }

  async disableAdminTwoFactor(code: string) {
    return this.request<{ message: string }>('/admin/2fa/disable', {
      method: 'POST',
      body: { code },
    });
  }

  // Admin Tenant Resource Manual Additions
  async createTenantCustomer(userId: number, data: Partial<Customer>) {
    return this.request<{ message: string; data: Customer }>(`/admin/users/${userId}/customers`, {
      method: 'POST',
      body: data,
    });
  }

  async createTenantPlan(userId: number, data: Partial<Plan>) {
    return this.request<{ message: string; data: Plan }>(`/admin/users/${userId}/plans`, {
      method: 'POST',
      body: data,
    });
  }

  async createTenantSubscription(userId: number, data: { customer_id: number; plan_id: number }) {
    return this.request<{ message: string; data: Subscription }>(`/admin/users/${userId}/subscriptions`, {
      method: 'POST',
      body: data,
    });
  }

  async createTenantManualPayment(subscriptionId: number, data: { amount: number; currency: string }) {
    return this.request<{ message: string; data: Payment }>(`/admin/subscriptions/${subscriptionId}/payment`, {
      method: 'POST',
      body: data,
    });
  }

  // Onboarding
  async getOnboardingStatus() {
    return this.request<{ data: { onboarding: Onboarding | null; is_complete: boolean } }>('/onboarding/status');
  }

  async initiateStripeConnect() {
    return this.request<{ data: { onboarding_id: number; account_link_url: string; expires_at: number } }>('/onboarding/stripe-connect', {
      method: 'POST',
    });
  }

  async completeOnboarding() {
    return this.request<{ data: { onboarding: Onboarding } }>('/onboarding/complete', {
      method: 'POST',
    });
  }

  // Checkout
  async createCheckoutSession(data: { plan_id: number; success_url: string; cancel_url: string }) {
    return this.request<{ data: { session_id: string; url: string } }>('/checkout/subscription', {
      method: 'POST',
      body: data,
    });
  }

  async createCustomerPortal(returnUrl: string) {
    return this.request<{ data: { url: string } }>('/checkout/portal', {
      method: 'POST',
      body: { return_url: returnUrl },
    });
  }
}

// Types
export interface User {
  id: number;
  name: string;
  email: string;
  company_name: string;
  role: string;
}

export interface Customer {
  id: number;
  name: string;
  email: string;
  phone: string;
  external_id: string;
  status: string;
  created_at: string;
}

export interface Plan {
  id: number;
  name: string;
  price: string;
  currency: string;
  interval: string;
  trial_days: number;
  is_active: boolean;
  description: string;
  features: string[];
}

export interface Subscription {
  id: number;
  customer_id: number;
  plan_id: number;
  status: string;
  start_date: string;
  next_billing_date: string;
  canceled_at: string | null;
  customer?: Customer;
  plan?: Plan;
}

export interface Invoice {
  id: number;
  number: string;
  amount: string;
  currency: string;
  status: string;
  due_date: string;
  paid_at: string | null;
}

export interface DashboardData {
  mrr: number;
  arr: number;
  active_subscribers: number;
  churn_rate: number;
  ltv: number;
  nrr: number;
  expansion_mrr: number;
  total_revenue: number;
  new_subscribers_this_month: number;
  canceled_this_month: number;
  trial_conversions: { total_trials: number; converted: number; rate: number };
  revenue_by_plan: { name: string; total_revenue: number; subscriber_count: number }[];
  subscriber_growth: { month: string; label: string; new: number; canceled: number }[];
}

export interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface LaravelPaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  first_page_url?: string;
  last_page_url?: string;
  next_page_url?: string | null;
  prev_page_url?: string | null;
}

export interface AdminUser extends User {
  is_locked: boolean;
  subscriber_limit: number;
  customers_count?: number;
  plans_count?: number;
  total_subscriptions_count?: number;
  active_subscriptions_count?: number;
  created_at: string;
}

export interface SystemHealthReport {
  status: string;
  timestamp: string;
  services: {
    database: { status: string; error?: string };
    redis: { status: string; error?: string };
    s3: { status: string; error?: string };
    stripe: { status: string; error?: string };
  };
}

export interface QueueMetrics {
  queue_sizes: Record<string, number>;
  failed_jobs_count: number;
}

export interface FailedJob {
  id: number;
  uuid: string;
  connection: string;
  queue: string;
  payload: string;
  exception: string;
  failed_at: string;
}

export interface QueueStatusResponse {
  metrics: QueueMetrics;
  failed_jobs: LaravelPaginatedResponse<FailedJob>;
}

export interface AdminWebhookEvent {
  id: number;
  provider: string;
  provider_event_id: string;
  event_type: string;
  payload: Record<string, any>;
  status: string;
  processed_at: string | null;
  error: string | null;
  attempts: number;
  created_at: string;
  updated_at: string;
}

export interface AdminSettings {
  platform_commission_rate: number;
  default_free_tier_limit: number;
  maintenance_mode: boolean;
  stripe_mock_mode: boolean;
}

export interface Payment {
  id: number;
  subscription_id: number;
  amount: string;
  commission_amount: string;
  commission_rate: string;
  currency: string;
  status: string;
  provider: string;
  provider_payment_id: string;
  created_at: string;
}

export interface Onboarding {
  id: number;
  user_id: number;
  status: string;
  stripe_account_id: string | null;
  stripe_account_email: string | null;
  stripe_onboarding_complete: boolean;
  stripe_capabilities_enabled: boolean;
  metadata: Record<string, any> | null;
  created_at: string;
  updated_at: string;
}

export interface OnboardingStatusResponse {
  data: {
    onboarding: Onboarding | null;
    is_complete: boolean;
  };
}

export interface OnboardingConnectResponse {
  data: {
    onboarding_id: number;
    account_link_url: string;
    expires_at: number;
  };
}

export interface CheckoutSessionRequest {
  plan_id: number;
  success_url: string;
  cancel_url: string;
}

export interface CheckoutPortalRequest {
  return_url: string;
}

export const api = new ApiClient();
