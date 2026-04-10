import React, { useState, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, FlatList, TouchableOpacity,
    ActivityIndicator, TextInput, RefreshControl, Alert
} from 'react-native';
import * as FileSystem from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import * as Print from 'expo-print';
import { useNavigation } from '@react-navigation/native';
import { useFocusEffect } from '@react-navigation/native';
import { getTransactions } from '../api/client';
import { useAuth } from '../context/AuthContext';

const TYPE_FILTERS = [
    { label: 'All', value: '' },
    { label: 'Income', value: 'in' },
    { label: 'Expense', value: 'out' },
];

const DATE_FILTERS = [
    { label: 'All', value: 'all' },
    { label: 'Today', value: 'today' },
    { label: 'Week', value: 'week' },
    { label: 'Month', value: 'month' },
    { label: 'Custom', value: 'custom' },
];

function toYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getPresetRange(preset) {
    const now = new Date();
    const end = toYMD(now);

    if (preset === 'today') {
        return { startDate: end, endDate: end };
    }

    if (preset === 'week') {
        const start = new Date(now);
        start.setDate(now.getDate() - 6);
        return { startDate: toYMD(start), endDate: end };
    }

    if (preset === 'month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        return { startDate: toYMD(start), endDate: end };
    }

    return { startDate: '', endDate: '' };
}

function isValidYMD(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function TransactionRow({ item, onPress }) {
    const isIncome = item.type === 'in';
    return (
        <TouchableOpacity style={styles.row} onPress={onPress} activeOpacity={0.7}>
            <View style={[styles.typeBadge, { backgroundColor: isIncome ? '#D1FAE5' : '#FEE2E2' }]}>
                <Text style={{ fontSize: 18 }}>{isIncome ? '📈' : '📉'}</Text>
            </View>
            <View style={styles.rowMid}>
                <Text style={styles.rowDesc} numberOfLines={1}>
                    {item.description || item.category || 'Transaction'}
                </Text>
                <Text style={styles.rowMeta}>
                    {item.category} · {item.transaction_date}
                </Text>
            </View>
            <Text style={[styles.rowAmount, { color: isIncome ? '#10B981' : '#EF4444' }]}>
                {isIncome ? '+' : '-'}₱{parseFloat(item.amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </Text>
        </TouchableOpacity>
    );
}

export default function TransactionsScreen() {
    const navigation = useNavigation();
    const { can } = useAuth();
    const [transactions, setTransactions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [typeFilter, setTypeFilter] = useState('');
    const [dateFilter, setDateFilter] = useState('all');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [customStartDate, setCustomStartDate] = useState('');
    const [customEndDate, setCustomEndDate] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [exporting, setExporting] = useState(false);

    const load = useCallback(async (pageNum = 1, reset = true) => {
        try {
            const params = { page: pageNum, limit: 20 };
            if (typeFilter) params.type = typeFilter;
            if (search) params.search = search;
            if (startDate) params.start_date = startDate;
            if (endDate) params.end_date = endDate;

            const result = await getTransactions(params);
            if (result.success) {
                const newItems = result.data || [];
                setTransactions(reset ? newItems : prev => [...prev, ...newItems]);
                setHasMore(newItems.length === 20);
                setPage(pageNum);
            }
        } finally {
            setLoading(false);
            setRefreshing(false);
            setLoadingMore(false);
        }
    }, [typeFilter, search, startDate, endDate]);

    useEffect(() => {
        setLoading(true);
        load(1, true);
    }, [typeFilter, search, startDate, endDate, load]);

    useFocusEffect(useCallback(() => {
        load(1, true);
    }, [load]));

    function onRefresh() {
        setRefreshing(true);
        load(1, true);
    }

    function onDateFilterChange(value) {
        setDateFilter(value);

        if (value === 'custom') {
            setStartDate('');
            setEndDate('');
            return;
        }

        const range = getPresetRange(value);
        setStartDate(range.startDate);
        setEndDate(range.endDate);
    }

    function applyCustomDateRange() {
        if (!customStartDate || !customEndDate) {
            Alert.alert('Validation Error', 'Please set both start and end date.');
            return;
        }

        if (!isValidYMD(customStartDate) || !isValidYMD(customEndDate)) {
            Alert.alert('Validation Error', 'Use YYYY-MM-DD format.');
            return;
        }

        if (customStartDate > customEndDate) {
            Alert.alert('Validation Error', 'Start date must be before or equal to end date.');
            return;
        }

        setStartDate(customStartDate);
        setEndDate(customEndDate);
    }

    function loadMore() {
        if (loadingMore || !hasMore) return;
        setLoadingMore(true);
        load(page + 1, false);
    }

    async function fetchAllFilteredTransactions() {
        let pageNum = 1;
        const allItems = [];

        while (true) {
            const params = { page: pageNum, limit: 100 };
            if (typeFilter) params.type = typeFilter;
            if (search) params.search = search;
            if (startDate) params.start_date = startDate;
            if (endDate) params.end_date = endDate;

            const result = await getTransactions(params);
            if (!result.success) {
                throw new Error(result.error || 'Failed to fetch transactions for export');
            }

            const batch = result.data || [];
            allItems.push(...batch);

            if (batch.length < 100) break;
            pageNum += 1;
        }

        return allItems;
    }

    function escapeCsv(value) {
        const str = String(value ?? '');
        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    }

    function formatAmountForDisplay(type, amount) {
        const num = Number(amount || 0);
        const sign = type === 'in' ? '+' : '-';
        return `${sign}${num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    async function exportCsv() {
        setExporting(true);
        try {
            const items = await fetchAllFilteredTransactions();
            if (items.length === 0) {
                Alert.alert('No Data', 'No transactions found for current filters.');
                return;
            }

            const headers = ['Date', 'Type', 'Amount', 'Category', 'Description', 'Payment Method', 'Reference Number'];
            const lines = [headers.join(',')];

            items.forEach(item => {
                lines.push([
                    escapeCsv(item.transaction_date),
                    escapeCsv(item.type === 'in' ? 'Income' : 'Expense'),
                    escapeCsv(formatAmountForDisplay(item.type, item.amount)),
                    escapeCsv(item.category || ''),
                    escapeCsv(item.description || ''),
                    escapeCsv(item.payment_method || ''),
                    escapeCsv(item.reference_number || ''),
                ].join(','));
            });

            const csvContent = lines.join('\n');
            const fileUri = `${FileSystem.cacheDirectory}transactions_${Date.now()}.csv`;
            await FileSystem.writeAsStringAsync(fileUri, csvContent, {
                encoding: FileSystem.EncodingType.UTF8,
            });

            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(fileUri, {
                    mimeType: 'text/csv',
                    dialogTitle: 'Export Transactions CSV',
                });
            } else {
                Alert.alert('Export Ready', `CSV saved to: ${fileUri}`);
            }
        } catch (e) {
            Alert.alert('Export Failed', e.message || 'Unable to export CSV.');
        } finally {
            setExporting(false);
        }
    }

    async function exportPdf() {
        setExporting(true);
        try {
            const items = await fetchAllFilteredTransactions();
            if (items.length === 0) {
                Alert.alert('No Data', 'No transactions found for current filters.');
                return;
            }

            const rows = items.map(item => `
                <tr>
                    <td>${item.transaction_date || ''}</td>
                    <td>${item.type === 'in' ? 'Income' : 'Expense'}</td>
                    <td>${formatAmountForDisplay(item.type, item.amount)}</td>
                    <td>${item.category || ''}</td>
                    <td>${item.description || ''}</td>
                </tr>
            `).join('');

            const html = `
                <html>
                    <head>
                        <meta charset="utf-8" />
                        <style>
                            body { font-family: Arial, sans-serif; padding: 16px; }
                            h1 { margin: 0 0 8px 0; font-size: 20px; }
                            p { margin: 0 0 16px 0; color: #555; }
                            table { width: 100%; border-collapse: collapse; font-size: 12px; }
                            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
                            th { background: #f3f4f6; }
                        </style>
                    </head>
                    <body>
                        <h1>Transactions Export</h1>
                        <p>Generated: ${new Date().toLocaleString()}</p>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </body>
                </html>
            `;

            const file = await Print.printToFileAsync({ html, base64: false });

            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(file.uri, {
                    mimeType: 'application/pdf',
                    dialogTitle: 'Export Transactions PDF',
                });
            } else {
                Alert.alert('Export Ready', `PDF saved to: ${file.uri}`);
            }
        } catch (e) {
            Alert.alert('Export Failed', e.message || 'Unable to export PDF.');
        } finally {
            setExporting(false);
        }
    }

    function renderFooter() {
        if (!loadingMore) return null;
        return <ActivityIndicator style={{ margin: 16 }} color="#2563EB" />;
    }

    if (loading) {
        return <View style={styles.center}><ActivityIndicator size="large" color="#2563EB" /></View>;
    }

    return (
        <View style={styles.container}>
            {/* Search */}
            <View style={styles.searchBar}>
                <TextInput
                    style={styles.searchInput}
                    placeholder="Search transactions..."
                    placeholderTextColor="#9CA3AF"
                    value={search}
                    onChangeText={setSearch}
                    returnKeyType="search"
                />
            </View>

            {/* Type Filter Tabs */}
            <View style={styles.filterRow}>
                {TYPE_FILTERS.map(f => (
                    <TouchableOpacity
                        key={f.value}
                        style={[styles.filterBtn, typeFilter === f.value && styles.filterBtnActive]}
                        onPress={() => setTypeFilter(f.value)}
                    >
                        <Text style={[styles.filterBtnText, typeFilter === f.value && styles.filterBtnTextActive]}>
                            {f.label}
                        </Text>
                    </TouchableOpacity>
                ))}
            </View>

            {/* Date Filter Tabs */}
            <View style={styles.filterRowDate}>
                {DATE_FILTERS.map(f => (
                    <TouchableOpacity
                        key={f.value}
                        style={[styles.filterBtn, dateFilter === f.value && styles.filterBtnActive]}
                        onPress={() => onDateFilterChange(f.value)}
                    >
                        <Text style={[styles.filterBtnText, dateFilter === f.value && styles.filterBtnTextActive]}>
                            {f.label}
                        </Text>
                    </TouchableOpacity>
                ))}
            </View>

            {dateFilter === 'custom' && (
                <View style={styles.customDateWrap}>
                    <TextInput
                        style={styles.customDateInput}
                        placeholder="Start YYYY-MM-DD"
                        placeholderTextColor="#9CA3AF"
                        value={customStartDate}
                        onChangeText={setCustomStartDate}
                    />
                    <TextInput
                        style={styles.customDateInput}
                        placeholder="End YYYY-MM-DD"
                        placeholderTextColor="#9CA3AF"
                        value={customEndDate}
                        onChangeText={setCustomEndDate}
                    />
                    <TouchableOpacity style={styles.applyBtn} onPress={applyCustomDateRange}>
                        <Text style={styles.applyBtnText}>Apply</Text>
                    </TouchableOpacity>
                </View>
            )}

            <View style={styles.exportRow}>
                <TouchableOpacity style={[styles.exportBtn, exporting && styles.exportBtnDisabled]} onPress={exportCsv} disabled={exporting}>
                    <Text style={styles.exportBtnText}>{exporting ? 'Working...' : 'Export CSV'}</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.exportBtn, styles.exportBtnPdf, exporting && styles.exportBtnDisabled]} onPress={exportPdf} disabled={exporting}>
                    <Text style={[styles.exportBtnText, styles.exportBtnTextPdf]}>{exporting ? 'Working...' : 'Export PDF'}</Text>
                </TouchableOpacity>
            </View>

            {/* List */}
            <FlatList
                data={transactions}
                keyExtractor={item => String(item.transaction_id)}
                renderItem={({ item }) => (
                    <TransactionRow
                        item={item}
                        onPress={() => {
                            if (can('edit_transactions')) {
                                navigation.navigate('EditTransaction', { transaction: item });
                            }
                        }}
                    />
                )}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
                onEndReached={loadMore}
                onEndReachedThreshold={0.4}
                ListFooterComponent={renderFooter}
                ListEmptyComponent={
                    <Text style={styles.empty}>No transactions found</Text>
                }
                contentContainerStyle={{ paddingBottom: 20 }}
            />

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
    center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    searchBar: { backgroundColor: '#fff', padding: 12 },
    searchInput: {
        backgroundColor: '#F3F4F6',
        borderRadius: 10,
        paddingHorizontal: 14,
        paddingVertical: 10,
        fontSize: 15,
        color: '#111827',
    },
    filterRow: {
        flexDirection: 'row',
        backgroundColor: '#fff',
        paddingHorizontal: 12,
        paddingBottom: 12,
        gap: 8,
    },
    filterRowDate: {
        flexDirection: 'row',
        backgroundColor: '#fff',
        paddingHorizontal: 12,
        paddingBottom: 12,
        gap: 8,
        borderTopWidth: 1,
        borderTopColor: '#F3F4F6',
    },
    filterBtn: {
        paddingHorizontal: 16,
        paddingVertical: 8,
        borderRadius: 20,
        backgroundColor: '#F3F4F6',
    },
    filterBtnActive: { backgroundColor: '#2563EB' },
    filterBtnText: { color: '#6B7280', fontWeight: '600', fontSize: 14 },
    filterBtnTextActive: { color: '#fff' },
    customDateWrap: {
        backgroundColor: '#fff',
        paddingHorizontal: 12,
        paddingBottom: 12,
        gap: 8,
    },
    customDateInput: {
        backgroundColor: '#F3F4F6',
        borderRadius: 10,
        paddingHorizontal: 12,
        paddingVertical: 10,
        fontSize: 14,
        color: '#111827',
    },
    applyBtn: {
        backgroundColor: '#2563EB',
        borderRadius: 10,
        alignItems: 'center',
        paddingVertical: 10,
    },
    applyBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },
    exportRow: {
        backgroundColor: '#fff',
        flexDirection: 'row',
        gap: 10,
        paddingHorizontal: 12,
        paddingBottom: 12,
        borderTopWidth: 1,
        borderTopColor: '#F3F4F6',
    },
    exportBtn: {
        flex: 1,
        backgroundColor: '#E0E7FF',
        borderRadius: 10,
        paddingVertical: 10,
        alignItems: 'center',
    },
    exportBtnPdf: { backgroundColor: '#FEE2E2' },
    exportBtnText: { color: '#3730A3', fontWeight: '700', fontSize: 14 },
    exportBtnTextPdf: { color: '#991B1B' },
    exportBtnDisabled: { opacity: 0.6 },
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
    row: {
        backgroundColor: '#fff',
        marginHorizontal: 12,
        marginTop: 10,
        borderRadius: 12,
        padding: 14,
        flexDirection: 'row',
        alignItems: 'center',
        gap: 12,
        elevation: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 3,
    },
    typeBadge: {
        width: 42,
        height: 42,
        borderRadius: 10,
        justifyContent: 'center',
        alignItems: 'center',
    },
    rowMid: { flex: 1 },
    rowDesc: { fontSize: 15, fontWeight: '600', color: '#111827' },
    rowMeta: { fontSize: 12, color: '#9CA3AF', marginTop: 2 },
    rowAmount: { fontSize: 15, fontWeight: '700' },
    empty: { textAlign: 'center', color: '#9CA3AF', marginTop: 40 },
});
