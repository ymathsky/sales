import React, { useState, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, ScrollView, RefreshControl,
    TouchableOpacity, ActivityIndicator
} from 'react-native';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import { getDashboard } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { checkFinancialAlerts } from '../services/financialAlerts';

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

function TrendChart({ data = [] }) {
    if (!Array.isArray(data) || data.length === 0) {
        return (
            <View style={styles.chartCard}>
                <Text style={styles.chartTitle}>Cash In vs Cash Out (6 Months)</Text>
                <Text style={styles.chartEmptyText}>
                    No monthly trend data yet. Add more transactions or ensure latest dashboard API is deployed.
                </Text>
            </View>
        );
    }

    const maxValue = Math.max(
        1,
        ...data.map(item => Number(item.income || 0)),
        ...data.map(item => Number(item.expense || 0))
    );

    return (
        <View style={styles.chartCard}>
            <Text style={styles.chartTitle}>Cash In vs Cash Out (6 Months)</Text>
            <View style={styles.chartLegendRow}>
                <View style={styles.legendItem}>
                    <View style={[styles.legendDot, { backgroundColor: '#10B981' }]} />
                    <Text style={styles.legendText}>Income</Text>
                </View>
                <View style={styles.legendItem}>
                    <View style={[styles.legendDot, { backgroundColor: '#EF4444' }]} />
                    <Text style={styles.legendText}>Expense</Text>
                </View>
            </View>

            <View style={styles.chartBarsRow}>
                {data.map(item => {
                    const income = Number(item.income || 0);
                    const expense = Number(item.expense || 0);
                    const incomeHeight = Math.max(4, (income / maxValue) * 120);
                    const expenseHeight = Math.max(4, (expense / maxValue) * 120);

                    return (
                        <View key={item.month_key} style={styles.monthGroup}>
                            <View style={styles.monthBars}>
                                <View style={[styles.bar, { height: incomeHeight, backgroundColor: '#10B981' }]} />
                                <View style={[styles.bar, { height: expenseHeight, backgroundColor: '#EF4444' }]} />
                            </View>
                            <Text style={styles.monthLabel}>{item.month_label}</Text>
                        </View>
                    );
                })}
            </View>
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
            if (result.success) {
                setData(result);
                checkFinancialAlerts(result).catch(() => {
                    // Notification check failure should not block dashboard rendering.
                });
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, []);

    useEffect(() => { load(); }, [load]);

    useFocusEffect(
        useCallback(() => {
            load();
        }, [load])
    );

    function onRefresh() {
        setRefreshing(true);
        load();
    }

    if (loading) {
        return <View style={styles.center}><ActivityIndicator size="large" color="#2563EB" /></View>;
    }

    const summary = data?.summary || {};
    const monthlyTrend = data?.monthly_trend || [];
    const recent = data?.recent_transactions || [];

    return (
        <View style={styles.container}>
            <ScrollView
                style={styles.scroll}
                contentContainerStyle={{ paddingBottom: 100 }}
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

            {/* Trend Chart */}
            <Text style={styles.sectionTitle}>Trends</Text>
            <TrendChart data={monthlyTrend} />

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
                    <Text style={[styles.txAmount, { color: t.type === 'in' ? '#10B981' : '#EF4444' }]}>
                        {t.type === 'in' ? '+' : '-'}₱{parseFloat(t.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </Text>
                </View>
            ))}

            </ScrollView>

            <TouchableOpacity
                style={styles.fabFloating}
                onPress={() => navigation.navigate('CreateTransaction')}
                activeOpacity={0.9}
            >
                <Text style={styles.fabFloatingText}>+ Add</Text>
            </TouchableOpacity>
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F3F4F6' },
    scroll: { flex: 1, backgroundColor: '#F3F4F6' },
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
    chartCard: {
        backgroundColor: '#fff',
        marginHorizontal: 16,
        marginBottom: 10,
        borderRadius: 12,
        padding: 16,
        elevation: 2,
    },
    chartTitle: { fontSize: 14, fontWeight: '700', color: '#374151', marginBottom: 10 },
    chartEmptyText: { color: '#6B7280', fontSize: 13, lineHeight: 20 },
    chartLegendRow: { flexDirection: 'row', gap: 16, marginBottom: 10 },
    legendItem: { flexDirection: 'row', alignItems: 'center' },
    legendDot: { width: 10, height: 10, borderRadius: 5, marginRight: 6 },
    legendText: { color: '#6B7280', fontSize: 12, fontWeight: '600' },
    chartBarsRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'flex-end',
        minHeight: 150,
        borderTopWidth: 1,
        borderTopColor: '#F3F4F6',
        paddingTop: 12,
    },
    monthGroup: { alignItems: 'center', flex: 1 },
    monthBars: {
        height: 124,
        flexDirection: 'row',
        alignItems: 'flex-end',
        gap: 4,
        marginBottom: 6,
    },
    bar: { width: 10, borderRadius: 6 },
    monthLabel: { color: '#6B7280', fontSize: 11, fontWeight: '600' },
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
    fabFloating: {
        position: 'absolute',
        right: 16,
        bottom: 20,
        backgroundColor: '#2563EB',
        borderRadius: 24,
        paddingHorizontal: 18,
        paddingVertical: 12,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 6,
        elevation: 6,
    },
    fabFloatingText: { color: '#fff', fontWeight: '800', fontSize: 15 },
});
