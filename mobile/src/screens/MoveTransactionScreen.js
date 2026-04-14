import React, { useState, useEffect } from 'react';
import {
    View, Text, StyleSheet, ScrollView, TouchableOpacity,
    ActivityIndicator, Alert,
} from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { getCompanies, moveTransaction } from '../api/client';
import { useAuth } from '../context/AuthContext';

export default function MoveTransactionScreen() {
    const navigation = useNavigation();
    const route = useRoute();
    const { transaction } = route.params;
    const { company } = useAuth();

    const [companies, setCompanies] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedId, setSelectedId] = useState(null);
    const [moving, setMoving] = useState(false);

    useEffect(() => {
        getCompanies()
            .then(res => {
                if (res.success) {
                    // Exclude the current active company
                    const others = (res.companies || []).filter(
                        c => Number(c.company_id) !== Number(company?.company_id)
                    );
                    setCompanies(others);
                }
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [company]);

    async function handleMove() {
        if (!selectedId) {
            Alert.alert('Select Company', 'Please select a target company first.');
            return;
        }

        const target = companies.find(c => c.company_id === selectedId);

        Alert.alert(
            'Transfer Transaction',
            `Move this transaction to "${target?.name}"?\n\nThis cannot be undone from this company.`,
            [
                { text: 'Cancel', style: 'cancel' },
                {
                    text: 'Transfer',
                    style: 'destructive',
                    onPress: async () => {
                        setMoving(true);
                        try {
                            const result = await moveTransaction(transaction.transaction_id, selectedId);
                            if (result.success) {
                                Alert.alert(
                                    'Transferred',
                                    `Transaction moved to ${result.target_company}.`,
                                    [{ text: 'OK', onPress: () => navigation.navigate('Main') }]
                                );
                            } else {
                                Alert.alert('Failed', result.error || 'Could not move transaction.');
                            }
                        } catch {
                            Alert.alert('Error', 'Network error. Please try again.');
                        } finally {
                            setMoving(false);
                        }
                    },
                },
            ]
        );
    }

    const amount = parseFloat(transaction.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
    const isIn = transaction.type === 'in';

    return (
        <View style={styles.container}>
            <ScrollView contentContainerStyle={{ paddingBottom: 120 }}>
                {/* Transaction summary */}
                <View style={styles.txCard}>
                    <Text style={styles.txCardTitle}>Transaction to Transfer</Text>
                    <View style={styles.txRow}>
                        <View style={[styles.typeBadge, { backgroundColor: isIn ? '#DCFCE7' : '#FEE2E2' }]}>
                            <Text style={[styles.typeBadgeText, { color: isIn ? '#059669' : '#DC2626' }]}>
                                {isIn ? 'IN' : 'OUT'}
                            </Text>
                        </View>
                        <View style={{ flex: 1 }}>
                            <Text style={styles.txDesc} numberOfLines={2}>
                                {transaction.description || transaction.category || 'Transaction'}
                            </Text>
                            <Text style={styles.txMeta}>
                                {transaction.category} · {transaction.transaction_date}
                            </Text>
                        </View>
                        <Text style={[styles.txAmount, { color: isIn ? '#059669' : '#DC2626' }]}>
                            {isIn ? '+' : '-'}₱{amount}
                        </Text>
                    </View>

                    <View style={styles.fromRow}>
                        <Text style={styles.fromLabel}>From</Text>
                        <Text style={styles.fromValue}>{company?.name || 'Current Company'}</Text>
                    </View>
                </View>

                {/* Company picker */}
                <Text style={styles.sectionTitle}>Select Target Company</Text>

                {loading ? (
                    <ActivityIndicator color="#2563EB" style={{ marginTop: 32 }} />
                ) : companies.length === 0 ? (
                    <View style={styles.emptyBox}>
                        <Text style={styles.emptyText}>No other companies available.</Text>
                        <Text style={styles.emptyHint}>You need access to at least one other company to transfer transactions.</Text>
                    </View>
                ) : (
                    <View style={styles.companyList}>
                        {companies.map(c => {
                            const active = c.company_id === selectedId;
                            return (
                                <TouchableOpacity
                                    key={c.company_id}
                                    style={[styles.companyRow, active && styles.companyRowActive]}
                                    onPress={() => setSelectedId(c.company_id)}
                                    activeOpacity={0.7}
                                >
                                    <View style={[styles.radio, active && styles.radioActive]}>
                                        {active && <View style={styles.radioInner} />}
                                    </View>
                                    <View style={{ flex: 1 }}>
                                        <Text style={[styles.companyName, active && styles.companyNameActive]}>
                                            {c.name}
                                        </Text>
                                        {c.currency ? (
                                            <Text style={styles.companyCurrency}>{c.currency}</Text>
                                        ) : null}
                                    </View>
                                    {active && (
                                        <View style={styles.selectedTag}>
                                            <Text style={styles.selectedTagText}>Selected</Text>
                                        </View>
                                    )}
                                </TouchableOpacity>
                            );
                        })}
                    </View>
                )}
            </ScrollView>

            {/* Transfer button */}
            {companies.length > 0 && (
                <View style={styles.footer}>
                    <TouchableOpacity
                        style={[styles.transferBtn, (!selectedId || moving) && styles.transferBtnDisabled]}
                        onPress={handleMove}
                        disabled={!selectedId || moving}
                        activeOpacity={0.85}
                    >
                        {moving
                            ? <ActivityIndicator color="#fff" />
                            : <Text style={styles.transferBtnText}>Transfer Transaction →</Text>
                        }
                    </TouchableOpacity>
                </View>
            )}
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F1F5F9' },

    txCard: {
        backgroundColor: '#fff',
        margin: 16,
        borderRadius: 16,
        padding: 16,
        shadowColor: '#0F172A',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.06,
        shadowRadius: 8,
        elevation: 2,
    },
    txCardTitle: {
        fontSize: 11,
        fontWeight: '700',
        color: '#64748B',
        textTransform: 'uppercase',
        letterSpacing: 0.8,
        marginBottom: 12,
    },
    txRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
    typeBadge: {
        width: 44,
        height: 44,
        borderRadius: 12,
        justifyContent: 'center',
        alignItems: 'center',
    },
    typeBadgeText: { fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },
    txDesc: { fontSize: 15, fontWeight: '600', color: '#0F172A' },
    txMeta: { fontSize: 12, color: '#94A3B8', marginTop: 2 },
    txAmount: { fontSize: 16, fontWeight: '700' },
    fromRow: {
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: 14,
        paddingTop: 12,
        borderTopWidth: 1,
        borderTopColor: '#F1F5F9',
        gap: 8,
    },
    fromLabel: {
        fontSize: 11,
        fontWeight: '700',
        color: '#94A3B8',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
    },
    fromValue: { fontSize: 14, fontWeight: '600', color: '#1E293B' },

    sectionTitle: {
        fontSize: 11,
        fontWeight: '700',
        color: '#64748B',
        textTransform: 'uppercase',
        letterSpacing: 0.8,
        marginHorizontal: 16,
        marginBottom: 10,
    },

    companyList: {
        backgroundColor: '#fff',
        marginHorizontal: 16,
        borderRadius: 16,
        overflow: 'hidden',
        shadowColor: '#0F172A',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 8,
        elevation: 2,
    },
    companyRow: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 14,
        paddingHorizontal: 16,
        paddingVertical: 16,
        borderBottomWidth: 1,
        borderBottomColor: '#F1F5F9',
    },
    companyRowActive: { backgroundColor: '#EFF6FF' },
    radio: {
        width: 22,
        height: 22,
        borderRadius: 11,
        borderWidth: 2,
        borderColor: '#CBD5E1',
        justifyContent: 'center',
        alignItems: 'center',
    },
    radioActive: { borderColor: '#2563EB' },
    radioInner: {
        width: 11,
        height: 11,
        borderRadius: 6,
        backgroundColor: '#2563EB',
    },
    companyName: { fontSize: 15, fontWeight: '500', color: '#374151' },
    companyNameActive: { color: '#1D4ED8', fontWeight: '700' },
    companyCurrency: { fontSize: 12, color: '#94A3B8', marginTop: 2 },
    selectedTag: {
        backgroundColor: '#DBEAFE',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 20,
    },
    selectedTagText: { fontSize: 11, fontWeight: '700', color: '#1D4ED8' },

    emptyBox: {
        marginHorizontal: 16,
        backgroundColor: '#fff',
        borderRadius: 16,
        padding: 24,
        alignItems: 'center',
    },
    emptyText: { fontSize: 15, fontWeight: '600', color: '#374151', marginBottom: 6 },
    emptyHint: { fontSize: 13, color: '#94A3B8', textAlign: 'center', lineHeight: 20 },

    footer: {
        position: 'absolute',
        bottom: 0,
        left: 0,
        right: 0,
        padding: 16,
        backgroundColor: '#fff',
        borderTopWidth: 1,
        borderTopColor: '#F1F5F9',
    },
    transferBtn: {
        backgroundColor: '#2563EB',
        borderRadius: 14,
        paddingVertical: 16,
        alignItems: 'center',
        shadowColor: '#2563EB',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 8,
        elevation: 4,
    },
    transferBtnDisabled: { backgroundColor: '#94A3B8', shadowOpacity: 0 },
    transferBtnText: { color: '#fff', fontWeight: '700', fontSize: 16 },
});
