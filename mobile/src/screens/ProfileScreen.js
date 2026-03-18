import React, { useState, useEffect } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, ScrollView,
    ActivityIndicator, Alert
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import { getCompanies } from '../api/client';

export default function ProfileScreen() {
    const { user, company, logout, switchCompany } = useAuth();
    const [companies, setCompanies] = useState([]);
    const [loadingCompanies, setLoadingCompanies] = useState(true);
    const [switching, setSwitching] = useState(null);

    useEffect(() => {
        getCompanies()
            .then(result => {
                if (result.success) setCompanies(result.companies || []);
            })
            .finally(() => setLoadingCompanies(false));
    }, []);

    async function handleSwitch(companyId) {
        if (companyId === company?.company_id) return;
        setSwitching(companyId);
        const result = await switchCompany(companyId);
        setSwitching(null);
        if (!result.success) {
            Alert.alert('Error', result.error || 'Failed to switch company');
        }
    }

    function handleLogout() {
        Alert.alert('Sign Out', 'Are you sure you want to sign out?', [
            { text: 'Cancel', style: 'cancel' },
            { text: 'Sign Out', style: 'destructive', onPress: logout },
        ]);
    }

    return (
        <ScrollView style={styles.container}>
            {/* User info card */}
            <View style={styles.userCard}>
                <View style={styles.avatar}>
                    <Text style={styles.avatarText}>
                        {(user?.username || 'U')[0].toUpperCase()}
                    </Text>
                </View>
                <View>
                    <Text style={styles.username}>{user?.username}</Text>
                    <Text style={styles.userRole}>{user?.role || 'User'}</Text>
                </View>
            </View>

            {/* Active company */}
            {company && (
                <View style={styles.activeCompanyBadge}>
                    <Text style={styles.activeLabel}>Active Company</Text>
                    <Text style={styles.activeName}>{company.name}</Text>
                </View>
            )}

            {/* Company Switcher */}
            <Text style={styles.sectionTitle}>Switch Company</Text>
            <View style={styles.companyList}>
                {loadingCompanies && <ActivityIndicator color="#2563EB" style={{ margin: 16 }} />}
                {!loadingCompanies && companies.length === 0 && (
                    <Text style={styles.empty}>No other companies available</Text>
                )}
                {companies.map(c => {
                    const isActive = c.company_id === company?.company_id;
                    const isLoading = switching === c.company_id;
                    return (
                        <TouchableOpacity
                            key={c.company_id}
                            style={[styles.companyRow, isActive && styles.companyRowActive]}
                            onPress={() => handleSwitch(c.company_id)}
                            disabled={isActive || !!switching}
                        >
                            <View style={[styles.companyDot, isActive && styles.companyDotActive]} />
                            <Text style={[styles.companyName, isActive && styles.companyNameActive]}>
                                {c.name}
                            </Text>
                            {isLoading && <ActivityIndicator size="small" color="#2563EB" />}
                            {isActive && !isLoading && <Text style={styles.activeTag}>Active</Text>}
                        </TouchableOpacity>
                    );
                })}
            </View>

            {/* Logout */}
            <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
                <Text style={styles.logoutText}>🚪 Sign Out</Text>
            </TouchableOpacity>

            <Text style={styles.version}>Cash Flow v1.0 · MD Office Support</Text>
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F3F4F6' },
    userCard: {
        backgroundColor: '#2563EB',
        padding: 24,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 16,
    },
    avatar: {
        width: 56,
        height: 56,
        borderRadius: 28,
        backgroundColor: 'rgba(255,255,255,0.25)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    avatarText: { color: '#fff', fontSize: 24, fontWeight: '800' },
    username: { color: '#fff', fontSize: 20, fontWeight: '700' },
    userRole: { color: '#BFDBFE', fontSize: 14, marginTop: 2, textTransform: 'capitalize' },
    activeCompanyBadge: {
        backgroundColor: '#EFF6FF',
        marginHorizontal: 16,
        marginTop: 16,
        borderRadius: 12,
        padding: 14,
        borderWidth: 1,
        borderColor: '#BFDBFE',
    },
    activeLabel: { fontSize: 11, fontWeight: '700', color: '#2563EB', textTransform: 'uppercase', letterSpacing: 0.5 },
    activeName: { fontSize: 18, fontWeight: '700', color: '#1E40AF', marginTop: 4 },
    sectionTitle: {
        fontSize: 12,
        fontWeight: '700',
        color: '#6B7280',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
        marginHorizontal: 16,
        marginTop: 24,
        marginBottom: 8,
    },
    companyList: {
        backgroundColor: '#fff',
        marginHorizontal: 16,
        borderRadius: 12,
        overflow: 'hidden',
        elevation: 1,
    },
    companyRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        paddingHorizontal: 16,
        paddingVertical: 14,
        borderBottomWidth: 1,
        borderBottomColor: '#F3F4F6',
    },
    companyRowActive: { backgroundColor: '#EFF6FF' },
    companyDot: {
        width: 10,
        height: 10,
        borderRadius: 5,
        backgroundColor: '#D1D5DB',
    },
    companyDotActive: { backgroundColor: '#2563EB' },
    companyName: { flex: 1, fontSize: 15, color: '#374151', fontWeight: '500' },
    companyNameActive: { color: '#1D4ED8', fontWeight: '700' },
    activeTag: {
        backgroundColor: '#DBEAFE',
        color: '#1D4ED8',
        fontSize: 12,
        fontWeight: '700',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 12,
    },
    empty: { textAlign: 'center', color: '#9CA3AF', padding: 20 },
    logoutBtn: {
        backgroundColor: '#fff',
        marginHorizontal: 16,
        marginTop: 24,
        borderRadius: 12,
        padding: 16,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#FCA5A5',
        elevation: 1,
    },
    logoutText: { color: '#EF4444', fontWeight: '700', fontSize: 16 },
    version: { textAlign: 'center', color: '#9CA3AF', fontSize: 12, marginTop: 24, marginBottom: 40 },
});
