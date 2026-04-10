import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { login as apiLogin, logout as apiLogout, getDashboard, switchCompany as apiSwitchCompany, getPermissions } from '../api/client';
import { getDraftSyncStatus, syncTransactionDrafts } from '../storage/offlineDrafts';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [company, setCompany] = useState(null);
    const [permissions, setPermissions] = useState({});
    const [draftSyncStatus, setDraftSyncStatus] = useState(null);
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
                    setPermissions(data.permissions || {});

                    // Best effort: sync drafts when session is valid and company context is active.
                    try {
                        await syncTransactionDrafts();
                    } catch {
                        // Ignore sync errors silently; drafts remain queued.
                    }

                    setDraftSyncStatus(await getDraftSyncStatus());
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
            setPermissions(data.permissions || {});

            try {
                await syncTransactionDrafts();
            } catch {
                // Keep silent; user can continue using app.
            }

            setDraftSyncStatus(await getDraftSyncStatus());

            // If permissions are not included by this backend, fetch fallback profile.
            if (!data.permissions) {
                try {
                    const permissionResult = await getPermissions();
                    if (permissionResult.success) {
                        setPermissions(permissionResult.permissions || {});
                    }
                } catch {
                    // Keep defaults.
                }
            }

            return { success: true };
        }
        return { success: false, error: data.error };
    }

    async function logout() {
        await apiLogout();
        setUser(null);
        setCompany(null);
        setPermissions({});
        setDraftSyncStatus(null);
    }

    async function switchCompany(companyId) {
        const result = await apiSwitchCompany(companyId);
        if (result.success) {
            // Reload dashboard to update company context
            const data = await getDashboard();
            if (data.success) {
                setCompany(data.company || null);
                setPermissions(data.permissions || {});

                try {
                    await syncTransactionDrafts();
                } catch {
                    // Ignore sync failures; drafts remain pending.
                }

                setDraftSyncStatus(await getDraftSyncStatus());
            }
            return { success: true };
        }
        return { success: false, error: result.error };
    }

    async function refreshDraftSyncStatus() {
        const status = await getDraftSyncStatus();
        setDraftSyncStatus(status);
        return status;
    }

    async function retryDraftSync() {
        const result = await syncTransactionDrafts();
        await refreshDraftSyncStatus();
        return result;
    }

    function can(permissionKey) {
        return !!permissions?.[permissionKey];
    }

    return (
        <AuthContext.Provider
            value={{
                user,
                company,
                permissions,
                draftSyncStatus,
                loading,
                login,
                logout,
                switchCompany,
                refreshDraftSyncStatus,
                retryDraftSync,
                can,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}
