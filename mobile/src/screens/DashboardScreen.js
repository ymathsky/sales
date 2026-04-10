import React, { useState, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, ScrollView, RefreshControl,
    TouchableOpacity, ActivityIndicator, Alert,
} from 'react-native';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import { getDashboard } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { checkFinancialAlerts } from '../services/financialAlerts';

function formatMoney(value) {
    return `₱${parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
}

function formatDateTime(iso) {
    if (!iso) return 'Never';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return 'Unknown';
    }
}

function SummaryCard({ label, amount, color, emoji }) {
    return (
        <View style={[styles.card, { borderLeftColor: color, borderLeftWidth: 4 }]}>
            <Text style={styles.cardLabel}>{emoji} {label}</Text>
            <Text style={[styles.cardAmount, { color }]}>{formatMoney(amount)}</Text>
        </View>
    );
}

function TrendChart({ data = [] }) {
    if (!Array.isArray(data) || data.length === 0) {
        return (
            <View style={styles.chartCard}>
                <Text style={styles.chartTitle}>Cash In vs Cash Out (6 Months)</Text>
                <Text style={styles.chartEmptyText}>No monthly trend data yet.</Text>
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

function DailySalesTrend({ data = [] }) {
    if (!Array.isArray(data) || data.length === 0) {
        return null;
    }

    const maxValue = Math.max(1, ...data.map(item => Number(item.sales_amount || 0)));
    const sample = data.slice(-10);

    return (
        <View style={styles.chartCard}>
            <Text style={styles.chartTitle}>Daily Sales Trend (Last 14 Days)</Text>
            <View style={styles.sparkRow}>
                {sample.map(item => {
                    const height = Math.max(6, (Number(item.sales_amount || 0) / maxValue) * 70);
                    return (
                        <View key={item.date} style={styles.sparkItem}>
                            <View style={[styles.sparkBar, { height }]} />
                            <Text style={styles.sparkLabel}>{item.label.slice(4)}</Text>
                        </View>
                    );
                })}
            </View>
        </View>
    );
}

export default function DashboardScreen() {
    const navigation = useNavigation();
    const {
        logout,
        draftSyncStatus,
        retryDraftSync,
        refreshDraftSyncStatus,
        can,
    } = useAuth();

    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [syncingDrafts, setSyncingDrafts] = useState(false);

    const load = useCallback(async () => {
        try {
            const [result] = await Promise.all([
                getDashboard(),
                refreshDraftSyncStatus(),
            ]);

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
    }, [refreshDraftSyncStatus]);

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

    async function handleRetrySync() {
        setSyncingDrafts(true);
        try {
            const result = await retryDraftSync();
            Alert.alert('Sync Completed', `Synced: ${result.synced}, Failed: ${result.failed}, Remaining: ${result.remaining}`);
        } catch {
            Alert.alert('Sync Failed', 'Unable to retry offline draft sync right now.');
        } finally {
            setSyncingDrafts(false);
        }
    }

    if (loading) {
        return <View style={styles.center}><ActivityIndicator size="large" color="#2563EB" /></View>;
    }

    const summary = data?.summary || {};
    const monthlyTrend = data?.monthly_trend || [];
    const dailySalesTrend = data?.daily_sales_trend || [];
    const topCategories = data?.top_categories || [];
    const topCustomers = data?.top_customers || [];
    const monthComparison = data?.monthly_comparison || null;
    const unpaidInvoices = data?.unpaid_invoices || {};
    const recent = data?.recent_transactions || [];

    return (
        <View style={styles.container}>
            <ScrollView
                style={styles.scroll}
                contentContainerStyle={{ paddingBottom: 110 }}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
            >
                <View style={styles.header}>
                    <View>
                        <Text style={styles.headerCompany}>{data?.company?.name || 'Dashboard'}</Text>
                        <Text style={styles.headerPeriod}>This Month: {data?.period}</Text>
                    </View>
                    <View style={styles.headerActions}>
                        {can('create_sales') && (
                            <TouchableOpacity
                                onPress={() => navigation.navigate('POSQuickEntry')}
                                style={styles.posBtn}
                                activeOpacity={0.9}
                            >
                                <Text style={styles.posBtnText}>POS</Text>
                            </TouchableOpacity>
                        )}
                        <TouchableOpacity onPress={logout} style={styles.logoutBtn}>
                            <Text style={styles.logoutText}>Logout</Text>
                        </TouchableOpacity>
                    </View>
                </View>

                <Text style={styles.sectionTitle}>Sync Center</Text>
                <View style={styles.card}>
                    <Text style={styles.cardLabel}>Pending Drafts</Text>
                    <Text style={styles.cardAmount}>{draftSyncStatus?.pending_count ?? 0}</Text>
                    <Text style={styles.syncMeta}>Last Sync: {formatDateTime(draftSyncStatus?.last_synced_at)}</Text>
                    <Text style={styles.syncMeta}>Last Attempt: {formatDateTime(draftSyncStatus?.last_attempt_at)}</Text>
                    {draftSyncStatus?.last_error ? <Text style={styles.syncError}>Last Error: {draftSyncStatus.last_error}</Text> : null}
                    <TouchableOpacity style={[styles.retryBtn, syncingDrafts && styles.retryBtnDisabled]} onPress={handleRetrySync} disabled={syncingDrafts}>
                        {syncingDrafts ? <ActivityIndicator color="#fff" /> : <Text style={styles.retryBtnText}>Retry Sync</Text>}
                    </TouchableOpacity>
                </View>

                <Text style={styles.sectionTitle}>This Month</Text>
                <SummaryCard label="Income" amount={summary.month_income} color="#10B981" emoji="📈" />
                <SummaryCard label="Expense" amount={summary.month_expense} color="#EF4444" emoji="📉" />

                <Text style={styles.sectionTitle}>All Time Balance</Text>
                <View style={[styles.card, styles.balanceCard]}>
                    <Text style={styles.cardLabel}>💰 Net Balance</Text>
                    <Text style={[styles.balanceAmount, { color: summary.net_balance >= 0 ? '#10B981' : '#EF4444' }]}>
                        {formatMoney(summary.net_balance)}
                    </Text>
                </View>

                <Text style={styles.sectionTitle}>Monthly Comparison</Text>
                <View style={styles.card}>
                    <Text style={styles.cardLabel}>{monthComparison?.current_month_label || 'Current'} vs {monthComparison?.previous_month_label || 'Previous'}</Text>
                    <Text style={styles.comparisonValue}>
                        {monthComparison ? `${monthComparison.net_change_amount >= 0 ? '+' : ''}${formatMoney(monthComparison.net_change_amount)}` : '₱0.00'}
                    </Text>
                    <Text style={[styles.comparisonPct, { color: (monthComparison?.net_change_pct || 0) >= 0 ? '#10B981' : '#EF4444' }]}>
                        {monthComparison ? `${monthComparison.net_change_pct >= 0 ? '+' : ''}${Number(monthComparison.net_change_pct || 0).toFixed(1)}%` : '0.0%'}
                    </Text>
                </View>

                <Text style={styles.sectionTitle}>Unpaid Invoices</Text>
                <View style={styles.card}>
                    <View style={styles.metricsRow}>
                        <View style={styles.metricBox}>
                            <Text style={styles.metricLabel}>Unpaid</Text>
                            <Text style={styles.metricValue}>{unpaidInvoices.unpaid_count || 0}</Text>
                        </View>
                        <View style={styles.metricBox}>
                            <Text style={styles.metricLabel}>Total Due</Text>
                            <Text style={styles.metricValue}>{formatMoney(unpaidInvoices.unpaid_total)}</Text>
                        </View>
                    </View>
                    <View style={styles.metricsRow}>
                        <View style={styles.metricBox}>
                            <Text style={styles.metricLabel}>Overdue</Text>
                            <Text style={[styles.metricValue, { color: '#B91C1C' }]}>{unpaidInvoices.overdue_count || 0}</Text>
                        </View>
                        <View style={styles.metricBox}>
                            <Text style={styles.metricLabel}>Overdue Amount</Text>
                            <Text style={[styles.metricValue, { color: '#B91C1C' }]}>{formatMoney(unpaidInvoices.overdue_total)}</Text>
                        </View>
                    </View>
                </View>

                <Text style={styles.sectionTitle}>Trends</Text>
                <TrendChart data={monthlyTrend} />
                <DailySalesTrend data={dailySalesTrend} />

                <Text style={styles.sectionTitle}>Top Categories</Text>
                <View style={styles.card}>
                    {topCategories.length === 0 ? <Text style={styles.empty}>No category analytics yet.</Text> : topCategories.map((item, idx) => (
                        <View key={`${item.category}-${idx}`} style={styles.rankRow}>
                            <Text style={styles.rankName}>{idx + 1}. {item.category}</Text>
                            <Text style={styles.rankValue}>{formatMoney(item.net)}</Text>
                        </View>
                    ))}
                </View>

                <Text style={styles.sectionTitle}>Top Customers</Text>
                <View style={styles.card}>
                    {topCustomers.length === 0 ? <Text style={styles.empty}>No customer invoice analytics yet.</Text> : topCustomers.map((item, idx) => (
                        <View key={`${item.customer_id}-${idx}`} style={styles.rankRow}>
                            <Text style={styles.rankName}>{idx + 1}. {item.customer_name}</Text>
                            <Text style={styles.rankValue}>{formatMoney(item.total_invoiced)}</Text>
                        </View>
                    ))}
                </View>

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
                            {t.type === 'in' ? '+' : '-'}{formatMoney(t.amount).replace('₱', '₱')}
                        </Text>
                    </View>
                ))}
            </ScrollView>

            {can('create_transactions') && (
                <TouchableOpacity
                    style={styles.fabFloating}
                    onPress={() => navigation.navigate('CreateTransaction')}
                    activeOpacity={0.9}
                >
                    <Text style={styles.fabFloatingText}>+ Add</Text>
                </TouchableOpacity>
            )}
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
    headerActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    posBtn: {
        backgroundColor: 'rgba(16,185,129,0.22)',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.35)',
        paddingHorizontal: 12,
        paddingVertical: 8,
        borderRadius: 8,
    },
    posBtnText: { color: '#ECFDF5', fontWeight: '700' },
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
    cardAmount: { fontSize: 22, fontWeight: '800', color: '#111827' },
    syncMeta: { color: '#6B7280', fontSize: 12, marginTop: 4 },
    syncError: { color: '#B91C1C', fontSize: 12, marginTop: 6 },
    retryBtn: { backgroundColor: '#1D4ED8', borderRadius: 10, marginTop: 10, paddingVertical: 11, alignItems: 'center' },
    retryBtnDisabled: { opacity: 0.65 },
    retryBtnText: { color: '#fff', fontWeight: '700' },
    balanceCard: { borderLeftWidth: 4, borderLeftColor: '#2563EB' },
    balanceAmount: { fontSize: 26, fontWeight: '800' },
    comparisonValue: { fontSize: 24, fontWeight: '800', color: '#111827', marginTop: 4 },
    comparisonPct: { fontSize: 13, fontWeight: '700', marginTop: 2 },
    metricsRow: { flexDirection: 'row', gap: 10, marginTop: 8 },
    metricBox: { flex: 1, backgroundColor: '#F9FAFB', borderRadius: 10, padding: 10 },
    metricLabel: { color: '#6B7280', fontSize: 11, fontWeight: '700', textTransform: 'uppercase' },
    metricValue: { color: '#111827', fontSize: 17, fontWeight: '800', marginTop: 4 },
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
    sparkRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', gap: 6 },
    sparkItem: { flex: 1, alignItems: 'center', justifyContent: 'flex-end' },
    sparkBar: { width: 9, backgroundColor: '#3B82F6', borderRadius: 6 },
    sparkLabel: { fontSize: 10, color: '#6B7280', marginTop: 4 },
    rankRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#F3F4F6' },
    rankName: { color: '#111827', fontWeight: '600', flex: 1, marginRight: 12 },
    rankValue: { color: '#1D4ED8', fontWeight: '800' },
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
    empty: { textAlign: 'center', color: '#9CA3AF', marginTop: 8, marginBottom: 10 },
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
