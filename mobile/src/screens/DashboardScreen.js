import React, { useState, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, ScrollView, RefreshControl,
    TouchableOpacity, ActivityIndicator
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { getDashboard } from '../api/client';
import { useAuth } from '../context/AuthContext';

function SummaryCard({ label, amount, color, emoji }) {
    return (
        <View style={[styles.card, { borderLeftColor: color, borderLeftWidth: 4 }]}>
            <Text style={styles.cardLabel}>{emoji} {label}</Text>
            <Text style={[styles.cardAmount, { color }]}>
                ₱{parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </Text>
        </View>
    );
}

export default function DashboardScreen() {
    const navigation = useNavigation();
    const { logout } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    const load = useCallback(async () => {
        try {
            const result = await getDashboard();
            if (result.success) setData(result);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    function onRefresh() {
        setRefreshing(true);
        load();
    }

    if (loading) {
        return <View style={styles.center}><ActivityIndicator size="large" color="#2563EB" /></View>;
    }

    const summary = data?.summary || {};
    const recent = data?.recent_transactions || [];

    return (
        <ScrollView
            style={styles.container}
            refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        >
            {/* Header */}
            <View style={styles.header}>
                <View>
                    <Text style={styles.headerCompany}>{data?.company?.name || 'Dashboard'}</Text>
                    <Text style={styles.headerPeriod}>This Month: {data?.period}</Text>
                </View>
                <TouchableOpacity onPress={logout} style={styles.logoutBtn}>
                    <Text style={styles.logoutText}>Logout</Text>
                </TouchableOpacity>
            </View>

            {/* Monthly Summary Cards */}
            <Text style={styles.sectionTitle}>This Month</Text>
            <SummaryCard label="Income" amount={summary.month_income} color="#10B981" emoji="📈" />
            <SummaryCard label="Expense" amount={summary.month_expense} color="#EF4444" emoji="📉" />

            {/* All-time Balance */}
            <Text style={styles.sectionTitle}>All Time Balance</Text>
            <View style={[styles.card, styles.balanceCard]}>
                <Text style={styles.cardLabel}>💰 Net Balance</Text>
                <Text style={[styles.balanceAmount, { color: summary.net_balance >= 0 ? '#10B981' : '#EF4444' }]}>
                    ₱{parseFloat(summary.net_balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                </Text>
            </View>

            {/* Recent Transactions */}
            <Text style={styles.sectionTitle}>Recent Transactions</Text>
            {recent.length === 0 && (
                <Text style={styles.empty}>No recent transactions</Text>
            )}
            {recent.map((t) => (
                <View key={t.transaction_id} style={styles.txRow}>
                    <View style={{ flex: 1 }}>
                        <Text style={styles.txDesc} numberOfLines={1}>
                            {t.description || t.category || 'Transaction'}
                        </Text>
                        <Text style={styles.txDate}>{t.transaction_date}</Text>
                    </View>
                    <Text style={[styles.txAmount, { color: t.type === 'income' ? '#10B981' : '#EF4444' }]}>
                        {t.type === 'income' ? '+' : '-'}₱{parseFloat(t.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </Text>
                </View>
            ))}

            {/* Add Transaction Button */}
            <TouchableOpacity
                style={styles.fab}
                onPress={() => navigation.navigate('CreateTransaction')}
            >
                <Text style={styles.fabText}>+ Add Transaction</Text>
            </TouchableOpacity>
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F3F4F6' },
    center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    header: {
        backgroundColor: '#2563EB',
        padding: 20,
        paddingTop: 16,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    headerCompany: { color: '#fff', fontSize: 18, fontWeight: '700' },
    headerPeriod: { color: '#BFDBFE', fontSize: 13, marginTop: 2 },
    logoutBtn: { backgroundColor: 'rgba(255,255,255,0.2)', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8 },
    logoutText: { color: '#fff', fontWeight: '600' },
    sectionTitle: { fontSize: 13, fontWeight: '700', color: '#6B7280', marginHorizontal: 16, marginTop: 20, marginBottom: 8, textTransform: 'uppercase', letterSpacing: 0.5 },
    card: {
        backgroundColor: '#fff',
        marginHorizontal: 16,
        marginBottom: 10,
        borderRadius: 12,
        padding: 16,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.06,
        shadowRadius: 4,
        elevation: 2,
    },
    cardLabel: { fontSize: 14, color: '#6B7280', marginBottom: 4 },
    cardAmount: { fontSize: 22, fontWeight: '800' },
    balanceCard: { borderLeftWidth: 4, borderLeftColor: '#2563EB' },
    balanceAmount: { fontSize: 26, fontWeight: '800' },
    txRow: {
        backgroundColor: '#fff',
        marginHorizontal: 16,
        marginBottom: 8,
        borderRadius: 10,
        padding: 14,
        flexDirection: 'row',
        alignItems: 'center',
        elevation: 1,
    },
    txDesc: { fontSize: 15, fontWeight: '600', color: '#111827' },
    txDate: { fontSize: 12, color: '#9CA3AF', marginTop: 2 },
    txAmount: { fontSize: 16, fontWeight: '700' },
    empty: { textAlign: 'center', color: '#9CA3AF', marginTop: 20, marginBottom: 10 },
    fab: {
        backgroundColor: '#2563EB',
        margin: 16,
        marginTop: 24,
        padding: 16,
        borderRadius: 12,
        alignItems: 'center',
        marginBottom: 40,
    },
    fabText: { color: '#fff', fontWeight: '700', fontSize: 16 },
});
