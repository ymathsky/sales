import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { login as apiLogin, logout as apiLogout, getDashboard, switchCompany as apiSwitchCompany } from '../api/client';
import { syncTransactionDrafts } from '../storage/offlineDrafts';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [company, setCompany] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        checkSession();
    }, []);

    async function checkSession() {
        try {
            const session = await AsyncStorage.getItem('session_cookie');
            if (session) {
                const data = await getDashboard();
                if (data.success) {
                    setUser(data.user || null);
                    setCompany(data.company || null);

                    // Best effort: sync drafts when session is valid and company context is active.
                    try {
                        await syncTransactionDrafts();
                    } catch {
                        // Ignore sync errors silently; drafts remain queued.
                    }
                }
            }
        } catch (e) {
            // Session expired or invalid — stay logged out
        } finally {
            setLoading(false);
        }
    }

    async function login(username, password) {
        const data = await apiLogin(username, password);
        if (data.success) {
            setUser(data.user || null);
            setCompany(data.company || null);

            try {
                await syncTransactionDrafts();
            } catch {
                // Keep silent; user can continue using app.
            }

            return { success: true };
        }
        return { success: false, error: data.error };
    }

    async function logout() {
        await apiLogout();
        setUser(null);
        setCompany(null);
    }

    async function switchCompany(companyId) {
        const result = await apiSwitchCompany(companyId);
        if (result.success) {
            // Reload dashboard to update company context
            const data = await getDashboard();
            if (data.success) {
                setCompany(data.company || null);

                try {
                    await syncTransactionDrafts();
                } catch {
                    // Ignore sync failures; drafts remain pending.
                }
            }
            return { success: true };
        }
        return { success: false, error: result.error };
    }

    return (
        <AuthContext.Provider value={{ user, company, loading, login, logout, switchCompany }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}
