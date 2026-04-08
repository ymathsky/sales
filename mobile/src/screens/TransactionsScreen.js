import React, { useState, useEffect, useCallback } from 'react';
import {
    View, Text, StyleSheet, FlatList, TouchableOpacity,
    ActivityIndicator, TextInput, RefreshControl
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useFocusEffect } from '@react-navigation/native';
import { getTransactions } from '../api/client';

const TYPE_FILTERS = [
    { label: 'All', value: '' },
    { label: 'Income', value: 'in' },
    { label: 'Expense', value: 'out' },
];

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
    const [transactions, setTransactions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [typeFilter, setTypeFilter] = useState('');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);

    const load = useCallback(async (pageNum = 1, reset = true) => {
        try {
            const params = { page: pageNum, limit: 20 };
            if (typeFilter) params.type = typeFilter;
            if (search) params.search = search;

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
    }, [typeFilter, search]);

    useEffect(() => {
        setLoading(true);
        load(1, true);
    }, [typeFilter, search, load]);

    useFocusEffect(useCallback(() => {
        load(1, true);
    }, [load]));

    function onRefresh() {
        setRefreshing(true);
        load(1, true);
    }

    function loadMore() {
        if (loadingMore || !hasMore) return;
        setLoadingMore(true);
        load(page + 1, false);
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

            {/* List */}
            <FlatList
                data={transactions}
                keyExtractor={item => String(item.transaction_id)}
                renderItem={({ item }) => (
                    <TransactionRow
                        item={item}
                        onPress={() => navigation.navigate('EditTransaction', { transaction: item })}
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
    filterBtn: {
        paddingHorizontal: 16,
        paddingVertical: 8,
        borderRadius: 20,
        backgroundColor: '#F3F4F6',
    },
    filterBtnActive: { backgroundColor: '#2563EB' },
    filterBtnText: { color: '#6B7280', fontWeight: '600', fontSize: 14 },
    filterBtnTextActive: { color: '#fff' },
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
