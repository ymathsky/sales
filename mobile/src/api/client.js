import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE } from './config';

const SESSION_KEY = 'session_cookie';

/**
 * Make an authenticated API request.
 * PHP uses cookie-based sessions, so we manually persist the PHPSESSID cookie.
 */
async function apiRequest(endpoint, options = {}) {
    const session = await AsyncStorage.getItem(SESSION_KEY);
    const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;

    const headers = {
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        ...(session ? { Cookie: session } : {}),
        ...options.headers,
    };

    const response = await fetch(`${API_BASE}${endpoint}`, {
        ...options,
        headers,
        credentials: 'include',
    });

    // Persist session cookie from server response
    const setCookie = response.headers.get('set-cookie');
    if (setCookie) {
        // Extract just the PHPSESSID=xxx part
        const match = setCookie.match(/PHPSESSID=[^;]+/);
        if (match) {
            await AsyncStorage.setItem(SESSION_KEY, match[0]);
        }
    }

    return response;
}

async function parseApiResponse(response) {
    const raw = await response.text();

    try {
        return JSON.parse(raw);
    } catch {
        return {
            success: false,
            error: `Invalid API JSON response (${response.status}): ${String(raw).slice(0, 140)}`,
        };
    }
}

// =========================================
// Auth
// =========================================

export async function login(username, password) {
    const res = await apiRequest('/login.php', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
    });
    const data = await parseApiResponse(res);
    // React Native's fetch does not expose set-cookie headers.
    // The login endpoint returns session_id in the JSON body — use that directly.
    if (data.success && data.session_id) {
        await AsyncStorage.setItem(SESSION_KEY, `PHPSESSID=${data.session_id}`);
    }
    return data;
}

export async function logout() {
    await apiRequest('/logout.php', { method: 'POST' });
    await AsyncStorage.removeItem(SESSION_KEY);
}

export async function getPermissions() {
    const res = await apiRequest('/permissions.php');
    return parseApiResponse(res);
}

// =========================================
// Dashboard
// =========================================

export async function getDashboard() {
    const res = await apiRequest('/dashboard.php');
    return parseApiResponse(res);
}

// =========================================
// Transactions
// =========================================

export async function getTransactions(params = {}) {
    const query = new URLSearchParams({ page: 1, limit: 20, ...params });
    const res = await apiRequest(`/transactions.php?${query}`);
    return parseApiResponse(res);
}

export async function getTransaction(id) {
    const res = await apiRequest(`/transactions.php?id=${id}`);
    return parseApiResponse(res);
}

export async function createTransaction(data) {
    const res = await apiRequest('/transactions.php', {
        method: 'POST',
        body: JSON.stringify(data),
    });
    return parseApiResponse(res);
}

export async function updateTransaction(id, data) {
    const res = await apiRequest(`/transactions.php?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
    return parseApiResponse(res);
}

export async function deleteTransaction(id) {
    const res = await apiRequest(`/transactions.php?id=${id}`, {
        method: 'DELETE',
    });
    return parseApiResponse(res);
}

// =========================================
// Receipts
// =========================================

export async function getReceipts(transactionId) {
    const res = await apiRequest(`/receipts.php?transaction_id=${transactionId}`);
    return parseApiResponse(res);
}

export async function uploadReceipts(transactionId, assets = []) {
    const formData = new FormData();
    formData.append('transaction_id', String(transactionId));

    assets.forEach((asset, idx) => {
        formData.append('receipts[]', {
            uri: asset.uri,
            name: asset.fileName || `receipt_${Date.now()}_${idx}.jpg`,
            type: asset.mimeType || 'image/jpeg',
        });
    });

    const res = await apiRequest('/receipts.php', {
        method: 'POST',
        body: formData,
    });
    return parseApiResponse(res);
}

export async function deleteReceipt(transactionId, receiptId) {
    const res = await apiRequest(`/receipts.php?transaction_id=${transactionId}&id=${receiptId}`, {
        method: 'DELETE',
    });
    return parseApiResponse(res);
}

// =========================================
// Customers
// =========================================

export async function getCustomers(params = {}) {
    const query = new URLSearchParams(params).toString();
    const endpoint = query ? `/customers.php?${query}` : '/customers.php';
    const res = await apiRequest(endpoint);
    return parseApiResponse(res);
}

export async function getCustomer(id) {
    const res = await apiRequest(`/customers.php?id=${id}`);
    return parseApiResponse(res);
}

export async function createCustomer(data) {
    const res = await apiRequest('/customers.php', {
        method: 'POST',
        body: JSON.stringify(data),
    });
    return parseApiResponse(res);
}

export async function updateCustomer(id, data) {
    const res = await apiRequest(`/customers.php?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    });
    return parseApiResponse(res);
}

// =========================================
// Invoices
// =========================================

export async function getInvoices(params = {}) {
    const query = new URLSearchParams(params).toString();
    const endpoint = query ? `/invoices.php?${query}` : '/invoices.php';
    const res = await apiRequest(endpoint);
    return parseApiResponse(res);
}

export async function createInvoice(data) {
    const res = await apiRequest('/invoices.php', {
        method: 'POST',
        body: JSON.stringify(data),
    });
    return parseApiResponse(res);
}

// =========================================
// Categories
// =========================================

export async function getCategories(type = null) {
    const query = type ? `?type=${type}` : '';
    const res = await apiRequest(`/categories.php${query}`);
    return parseApiResponse(res);
}

// =========================================
// Companies
// =========================================

export async function getCompanies() {
    const res = await apiRequest('/companies.php');
    return parseApiResponse(res);
}

export async function switchCompany(companyId) {
    const res = await apiRequest('/switch-company.php', {
        method: 'POST',
        body: JSON.stringify({ company_id: companyId }),
    });
    return parseApiResponse(res);
}
