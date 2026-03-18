import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE } from './config';

const SESSION_KEY = 'session_cookie';

/**
 * Make an authenticated API request.
 * PHP uses cookie-based sessions, so we manually persist the PHPSESSID cookie.
 */
async function apiRequest(endpoint, options = {}) {
    const session = await AsyncStorage.getItem(SESSION_KEY);

    const headers = {
        'Content-Type': 'application/json',
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

// =========================================
// Auth
// =========================================

export async function login(username, password) {
    const res = await apiRequest('/login.php', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
    });
    return res.json();
}

export async function logout() {
    await apiRequest('/logout.php', { method: 'POST' });
    await AsyncStorage.removeItem(SESSION_KEY);
}

// =========================================
// Dashboard
// =========================================

export async function getDashboard() {
    const res = await apiRequest('/dashboard.php');
    return res.json();
}

// =========================================
// Transactions
// =========================================

export async function getTransactions(params = {}) {
    const query = new URLSearchParams({ page: 1, limit: 20, ...params });
    const res = await apiRequest(`/transactions.php?${query}`);
    return res.json();
}

export async function createTransaction(data) {
    const res = await apiRequest('/transactions.php', {
        method: 'POST',
        body: JSON.stringify(data),
    });
    return res.json();
}

// =========================================
// Companies
// =========================================

export async function getCompanies() {
    const res = await apiRequest('/companies.php');
    return res.json();
}

export async function switchCompany(companyId) {
    const res = await apiRequest('/companies.php', {
        method: 'POST',
        body: JSON.stringify({ company_id: companyId }),
    });
    return res.json();
}
