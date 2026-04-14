import React, { useCallback, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    FlatList,
    RefreshControl,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { getInvoices } from '../api/client';

const STATUS_FILTERS = [
    { label: 'All', value: '' },
    { label: 'Draft', value: 'draft' },
    { label: 'Sent', value: 'sent' },
    { label: 'Paid', value: 'paid' },
    { label: 'Overdue', value: 'overdue' },
];

function formatMoney(value) {
    return `P${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function statusStyle(status) {
    if (status === 'paid') return { backgroundColor: '#DCFCE7', color: '#166534' };
    if (status === 'overdue') return { backgroundColor: '#FEE2E2', color: '#991B1B' };
    if (status === 'sent' || status === 'partial') return { backgroundColor: '#FEF3C7', color: '#92400E' };
    return { backgroundColor: '#E5E7EB', color: '#374151' };
}

export default function InvoicesScreen() {
    const navigation = useNavigation();
    const [statusFilter, setStatusFilter] = useState('');
    const [invoices, setInvoices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);

    const load = useCallback(async () => {
        try {
            const result = await getInvoices(statusFilter ? { status: statusFilter } : {});
            if (result.success) {
                setInvoices(result.data || []);
            } else {
                Alert.alert('Error', result.error || 'Failed to load invoices.');
            }
        } catch {
            Alert.alert('Error', 'Unable to load invoices right now.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [statusFilter]);

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
        return (
            <View style={styles.centered}>
                <ActivityIndicator size="large" color="#2563EB" />
            </View>
        );
    }

    return (
        <View style={styles.container}>
            <FlatList
                data={invoices}
                keyExtractor={item => String(item.invoice_id)}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                contentContainerStyle={styles.listContent}
                ListHeaderComponent={
                    <>
                        <View style={styles.headerCard}>
                            <Text style={styles.headerTitle}>Invoices</Text>
                            <Text style={styles.headerSubtitle}>Monitor draft, sent, paid, and overdue invoices</Text>
                        </View>

                        <TouchableOpacity style={styles.createBtn} onPress={() => navigation.navigate('InvoiceForm')}>
                            <Text style={styles.createBtnText}>+ New Invoice</Text>
                        </TouchableOpacity>

                        <View style={styles.filterRow}>
                            {STATUS_FILTERS.map(filter => (
                                <TouchableOpacity
                                    key={filter.value || 'all'}
                                    style={[styles.filterChip, statusFilter === filter.value && styles.filterChipActive]}
                                    onPress={() => setStatusFilter(filter.value)}
                                >
                                    <Text style={[styles.filterChipText, statusFilter === filter.value && styles.filterChipTextActive]}>
                                        {filter.label}
                                    </Text>
                                </TouchableOpacity>
                            ))}
                        </View>
                    </>
                }
                ListEmptyComponent={
                    <View style={styles.emptyCard}>
                        <Text style={styles.emptyTitle}>No invoices yet</Text>
                        <Text style={styles.emptyText}>Create an invoice from a customer card or from the button above.</Text>
                    </View>
                }
                renderItem={({ item }) => {
                    const chip = statusStyle(item.status);
                    return (
                        <TouchableOpacity
                            style={styles.invoiceCard}
                            onPress={() => navigation.navigate('InvoiceView', { invoiceId: item.invoice_id })}
                            activeOpacity={0.75}
                        >
                            <View style={styles.invoiceHead}>
                                <View>
                                    <Text style={styles.invoiceNumber}>{item.invoice_number}</Text>
                                    <Text style={styles.invoiceCustomer}>{item.customer_name}</Text>
                                </View>
                                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                                    <View style={[styles.statusBadge, { backgroundColor: chip.backgroundColor }]}>
                                        <Text style={[styles.statusBadgeText, { color: chip.color }]}>{item.status}</Text>
                                    </View>
                                    <Text style={{ color: '#CBD5E1', fontSize: 16 }}>›</Text>
                                </View>
                            </View>
                            <View style={styles.invoiceMetaRow}>
                                <Text style={styles.invoiceMeta}>Invoice: {item.invoice_date}</Text>
                                <Text style={styles.invoiceMeta}>Due: {item.due_date}</Text>
                            </View>
                            <View style={styles.invoiceTotals}>
                                <View>
                                    <Text style={styles.totalLabel}>Total</Text>
                                    <Text style={styles.totalValue}>{formatMoney(item.total_amount)}</Text>
                                </View>
                                <View>
                                    <Text style={styles.totalLabel}>Balance</Text>
                                    <Text style={styles.totalValue}>{formatMoney(item.amount_due)}</Text>
                                </View>
                            </View>
                        </TouchableOpacity>
                    );
                }}
            />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F3F4F6' },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#F3F4F6' },
    listContent: { padding: 16, paddingBottom: 28 },
    headerCard: { backgroundColor: '#1D4ED8', borderRadius: 16, padding: 18, marginBottom: 14 },
    headerTitle: { color: '#fff', fontSize: 23, fontWeight: '800' },
    headerSubtitle: { color: '#DBEAFE', fontSize: 13, marginTop: 4 },
    createBtn: { backgroundColor: '#111827', borderRadius: 12, paddingVertical: 14, alignItems: 'center', marginBottom: 12 },
    createBtnText: { color: '#fff', fontWeight: '800' },
    filterRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
    filterChip: { borderWidth: 1, borderColor: '#D1D5DB', backgroundColor: '#fff', borderRadius: 999, paddingHorizontal: 12, paddingVertical: 8 },
    filterChipActive: { borderColor: '#2563EB', backgroundColor: '#EFF6FF' },
    filterChipText: { color: '#374151', fontWeight: '600' },
    filterChipTextActive: { color: '#1D4ED8' },
    invoiceCard: { backgroundColor: '#fff', borderRadius: 14, padding: 14, marginBottom: 12, elevation: 2 },
    invoiceHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12 },
    invoiceNumber: { fontSize: 17, fontWeight: '800', color: '#111827' },
    invoiceCustomer: { fontSize: 13, color: '#6B7280', marginTop: 2 },
    statusBadge: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 6 },
    statusBadgeText: { fontSize: 11, fontWeight: '700', textTransform: 'capitalize' },
    invoiceMetaRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 12, gap: 10 },
    invoiceMeta: { color: '#6B7280', fontSize: 12 },
    invoiceTotals: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 14, backgroundColor: '#F9FAFB', borderRadius: 12, padding: 12 },
    totalLabel: { fontSize: 11, color: '#6B7280', fontWeight: '700', textTransform: 'uppercase' },
    totalValue: { fontSize: 17, color: '#111827', fontWeight: '800', marginTop: 4 },
    emptyCard: { backgroundColor: '#fff', borderRadius: 14, padding: 20, alignItems: 'center' },
    emptyTitle: { fontSize: 17, fontWeight: '700', color: '#111827' },
    emptyText: { fontSize: 13, color: '#6B7280', marginTop: 6, textAlign: 'center', lineHeight: 20 },
});