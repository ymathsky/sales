import React, { useCallback, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
    Modal,
    Platform,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { getInvoice, updateInvoiceStatus, recordInvoicePayment } from '../api/client';

function formatMoney(value) {
    return `\u20B1${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function statusConfig(status) {
    switch (status) {
        case 'paid':      return { bg: '#DCFCE7', color: '#166534', label: 'Paid' };
        case 'overdue':   return { bg: '#FEE2E2', color: '#991B1B', label: 'Overdue' };
        case 'sent':      return { bg: '#FEF3C7', color: '#92400E', label: 'Sent' };
        case 'partial':   return { bg: '#FEF3C7', color: '#92400E', label: 'Partial' };
        case 'cancelled': return { bg: '#F3F4F6', color: '#6B7280', label: 'Cancelled' };
        default:          return { bg: '#E5E7EB', color: '#374151', label: 'Draft' };
    }
}

function InfoRow({ label, value }) {
    return (
        <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>{label}</Text>
            <Text style={styles.infoValue}>{value ?? '—'}</Text>
        </View>
    );
}

export default function InvoiceViewScreen({ navigation, route }) {
    const { invoiceId } = route.params;

    const [invoice, setInvoice] = useState(null);
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [actioning, setActioning] = useState(false);

    // Payment modal
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [paymentAmount, setPaymentAmount] = useState('');

    const load = useCallback(async (isRefresh = false) => {
        if (!isRefresh) setLoading(true);
        try {
            const result = await getInvoice(invoiceId);
            if (result.success) {
                setInvoice(result.data.invoice);
                setItems(result.data.items || []);
                navigation.setOptions({ title: result.data.invoice.invoice_number });
            } else {
                Alert.alert('Error', result.error || 'Failed to load invoice.');
            }
        } catch {
            Alert.alert('Error', 'Unable to load invoice right now.');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [invoiceId]);

    useFocusEffect(useCallback(() => { load(); }, [load]));

    function onRefresh() {
        setRefreshing(true);
        load(true);
    }

    async function handleStatusChange(newStatus, confirmMessage) {
        Alert.alert('Confirm', confirmMessage, [
            { text: 'Cancel', style: 'cancel' },
            {
                text: 'Confirm',
                onPress: async () => {
                    setActioning(true);
                    try {
                        const result = await updateInvoiceStatus(invoiceId, newStatus);
                        if (result.success) {
                            setInvoice(result.data);
                        } else {
                            Alert.alert('Error', result.error || 'Failed to update status.');
                        }
                    } catch {
                        Alert.alert('Error', 'Unable to update status.');
                    } finally {
                        setActioning(false);
                    }
                },
            },
        ]);
    }

    async function handleRecordPayment() {
        const amount = parseFloat(paymentAmount || '0');
        if (!amount || amount <= 0) {
            Alert.alert('Validation', 'Enter a valid payment amount.');
            return;
        }
        const amountDue = parseFloat(invoice?.amount_due || '0');
        if (amount > amountDue + 0.01) {
            Alert.alert('Validation', `Amount cannot exceed balance due (${formatMoney(amountDue)}).`);
            return;
        }
        setActioning(true);
        try {
            const result = await recordInvoicePayment(invoiceId, amount);
            if (result.success) {
                setInvoice(result.data.invoice);
                setItems(result.data.items || []);
                setShowPaymentModal(false);
                setPaymentAmount('');
            } else {
                Alert.alert('Error', result.error || 'Failed to record payment.');
            }
        } catch {
            Alert.alert('Error', 'Unable to record payment.');
        } finally {
            setActioning(false);
        }
    }

    if (loading) {
        return (
            <View style={styles.centered}>
                <ActivityIndicator size="large" color="#1E3A8A" />
            </View>
        );
    }

    if (!invoice) {
        return (
            <View style={styles.centered}>
                <Text style={styles.errorText}>Invoice not found.</Text>
            </View>
        );
    }

    const sc = statusConfig(invoice.status);
    const subtotal = parseFloat(invoice.subtotal || 0);
    const taxAmount = parseFloat(invoice.tax_amount || 0);
    const totalAmount = parseFloat(invoice.total_amount || 0);
    const amountPaid = parseFloat(invoice.amount_paid || 0);
    const amountDue = parseFloat(invoice.amount_due || 0);
    const isDraft = invoice.status === 'draft';
    const isSent = invoice.status === 'sent';
    const isPartial = invoice.status === 'partial';
    const isOverdue = invoice.status === 'overdue';
    const canPay = (isSent || isPartial || isOverdue) && amountDue > 0.01;
    const canMarkSent = isDraft;
    const canMarkPaid = canPay;
    const canCancel = !['paid', 'cancelled'].includes(invoice.status);

    return (
        <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <ScrollView
                contentContainerStyle={styles.content}
                refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
            >
                {/* ── Header: Number + Status ── */}
                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Text style={styles.cardTitle}>Invoice Details</Text>
                    </View>
                    <View style={styles.cardBody}>
                        <View style={styles.invHeadRow}>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.invNumber}>{invoice.invoice_number}</Text>
                                <Text style={styles.invCustomer}>{invoice.customer_name}</Text>
                            </View>
                            <View style={[styles.statusBadge, { backgroundColor: sc.bg }]}>
                                <Text style={[styles.statusBadgeText, { color: sc.color }]}>{sc.label}</Text>
                            </View>
                        </View>

                        <View style={styles.divider} />
                        <InfoRow label="Invoice Date" value={invoice.invoice_date} />
                        <InfoRow label="Due Date" value={invoice.due_date} />
                        {invoice.contact_person ? <InfoRow label="Contact" value={invoice.contact_person} /> : null}
                        {invoice.email ? <InfoRow label="Email" value={invoice.email} /> : null}
                        {invoice.phone ? <InfoRow label="Phone" value={invoice.phone} /> : null}
                    </View>
                </View>

                {/* ── Line Items ── */}
                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Text style={styles.cardTitle}>Line Items</Text>
                    </View>

                    <View style={styles.lineHeader}>
                        <Text style={[styles.lineHeaderCell, { flex: 1 }]}>Description</Text>
                        <Text style={[styles.lineHeaderCell, { width: 44, textAlign: 'center' }]}>Qty</Text>
                        <Text style={[styles.lineHeaderCell, { width: 76, textAlign: 'right' }]}>Unit Price</Text>
                        <Text style={[styles.lineHeaderCell, { width: 76, textAlign: 'right' }]}>Amount</Text>
                    </View>

                    {items.map((item, idx) => (
                        <View key={idx} style={[styles.lineRow, idx < items.length - 1 && styles.lineRowBorder]}>
                            <Text style={[styles.lineCell, { flex: 1 }]}>{item.description}</Text>
                            <Text style={[styles.lineCell, { width: 44, textAlign: 'center' }]}>{Number(item.quantity)}</Text>
                            <Text style={[styles.lineCell, { width: 76, textAlign: 'right' }]}>{formatMoney(item.unit_price)}</Text>
                            <Text style={[styles.lineCell, styles.lineCellBold, { width: 76, textAlign: 'right' }]}>{formatMoney(item.amount)}</Text>
                        </View>
                    ))}

                    {/* Totals */}
                    <View style={styles.totalsSection}>
                        <View style={styles.totalRow}>
                            <Text style={styles.totalLabel}>Subtotal:</Text>
                            <Text style={styles.totalValue}>{formatMoney(subtotal)}</Text>
                        </View>
                        {taxAmount > 0 && (
                            <View style={styles.totalRow}>
                                <Text style={styles.totalLabel}>Tax:</Text>
                                <Text style={styles.totalValue}>{formatMoney(taxAmount)}</Text>
                            </View>
                        )}
                        <View style={styles.totalRow}>
                            <Text style={styles.totalLabel}>Tax (0%):</Text>
                            <Text style={styles.totalValue}>{formatMoney(0)}</Text>
                        </View>
                        <View style={styles.grandRow}>
                            <Text style={styles.grandLabel}>Total:</Text>
                            <Text style={styles.grandValue}>{formatMoney(totalAmount)}</Text>
                        </View>
                        {amountPaid > 0 && (
                            <View style={styles.totalRow}>
                                <Text style={[styles.totalLabel, { color: '#166534' }]}>Amount Paid:</Text>
                                <Text style={[styles.totalValue, { color: '#166534', fontWeight: '700' }]}>{formatMoney(amountPaid)}</Text>
                            </View>
                        )}
                        {amountDue > 0.01 && (
                            <View style={[styles.totalRow, { marginTop: 4 }]}>
                                <Text style={[styles.totalLabel, { color: '#991B1B', fontWeight: '700' }]}>Balance Due:</Text>
                                <Text style={[styles.totalValue, { color: '#991B1B', fontWeight: '800', fontSize: 16 }]}>{formatMoney(amountDue)}</Text>
                            </View>
                        )}
                    </View>
                </View>

                {/* ── Notes & Terms ── */}
                {(invoice.notes || invoice.terms) && (
                    <View style={styles.card}>
                        <View style={styles.cardHeader}>
                            <Text style={styles.cardTitle}>Additional Information</Text>
                        </View>
                        <View style={styles.cardBody}>
                            {invoice.notes ? (
                                <>
                                    <Text style={styles.notesLabel}>Notes (internal)</Text>
                                    <Text style={styles.notesText}>{invoice.notes}</Text>
                                </>
                            ) : null}
                            {invoice.terms ? (
                                <>
                                    <Text style={[styles.notesLabel, { marginTop: 12 }]}>Terms &amp; Conditions</Text>
                                    <Text style={styles.notesText}>{invoice.terms}</Text>
                                </>
                            ) : null}
                        </View>
                    </View>
                )}

                {/* ── Action Buttons ── */}
                <View style={styles.actionsCard}>
                    {canMarkSent && (
                        <TouchableOpacity
                            style={[styles.actionBtn, styles.actionBtnBlue]}
                            onPress={() => handleStatusChange('sent', 'Mark this invoice as Sent?')}
                            disabled={actioning}
                        >
                            {actioning ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.actionBtnText}>📤  Mark as Sent</Text>}
                        </TouchableOpacity>
                    )}
                    {canMarkPaid && (
                        <TouchableOpacity
                            style={[styles.actionBtn, styles.actionBtnGreen]}
                            onPress={() => handleStatusChange('paid', 'Mark this invoice as fully Paid?')}
                            disabled={actioning}
                        >
                            {actioning ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.actionBtnText}>✅  Mark as Paid</Text>}
                        </TouchableOpacity>
                    )}
                    {canPay && (
                        <TouchableOpacity
                            style={[styles.actionBtn, styles.actionBtnAmber]}
                            onPress={() => { setPaymentAmount(String(amountDue.toFixed(2))); setShowPaymentModal(true); }}
                            disabled={actioning}
                        >
                            <Text style={styles.actionBtnText}>💰  Record Partial Payment</Text>
                        </TouchableOpacity>
                    )}
                    {canCancel && (
                        <TouchableOpacity
                            style={[styles.actionBtn, styles.actionBtnRed]}
                            onPress={() => handleStatusChange('cancelled', 'Cancel this invoice? This cannot be undone.')}
                            disabled={actioning}
                        >
                            {actioning ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.actionBtnText}>✖  Cancel Invoice</Text>}
                        </TouchableOpacity>
                    )}
                </View>

            </ScrollView>

            {/* ── Record Payment Modal ── */}
            <Modal visible={showPaymentModal} transparent animationType="fade" onRequestClose={() => setShowPaymentModal(false)}>
                <TouchableOpacity style={styles.overlay} activeOpacity={1} onPress={() => setShowPaymentModal(false)}>
                    <View style={styles.modalBox} onStartShouldSetResponder={() => true}>
                        <Text style={styles.modalTitle}>Record Payment</Text>
                        <Text style={styles.modalSubtitle}>Balance due: {formatMoney(amountDue)}</Text>
                        <TextInput
                            style={styles.modalInput}
                            value={paymentAmount}
                            onChangeText={setPaymentAmount}
                            keyboardType="decimal-pad"
                            placeholder="Enter amount"
                            placeholderTextColor="#94A3B8"
                            autoFocus
                        />
                        <View style={styles.modalBtns}>
                            <TouchableOpacity style={styles.modalCancelBtn} onPress={() => setShowPaymentModal(false)}>
                                <Text style={styles.modalCancelText}>Cancel</Text>
                            </TouchableOpacity>
                            <TouchableOpacity
                                style={[styles.modalConfirmBtn, actioning && { opacity: 0.6 }]}
                                onPress={handleRecordPayment}
                                disabled={actioning}
                            >
                                {actioning ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.modalConfirmText}>Record</Text>}
                            </TouchableOpacity>
                        </View>
                    </View>
                </TouchableOpacity>
            </Modal>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F1F5F9' },
    content: { padding: 16, paddingBottom: 40 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#F1F5F9' },
    errorText: { color: '#6B7280', fontSize: 15 },

    card: {
        backgroundColor: '#fff',
        borderRadius: 16,
        marginBottom: 14,
        overflow: 'hidden',
        elevation: 2,
        shadowColor: '#0F172A',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.07,
        shadowRadius: 4,
    },
    cardHeader: {
        backgroundColor: '#1E3A8A',
        paddingHorizontal: 16,
        paddingVertical: 12,
    },
    cardTitle: { fontSize: 13, fontWeight: '800', color: '#fff', letterSpacing: 0.5, textTransform: 'uppercase' },
    cardBody: { padding: 16 },

    // Invoice header
    invHeadRow: { flexDirection: 'row', alignItems: 'flex-start' },
    invNumber: { fontSize: 20, fontWeight: '800', color: '#0F172A' },
    invCustomer: { fontSize: 14, color: '#475569', marginTop: 2 },
    statusBadge: { borderRadius: 999, paddingHorizontal: 12, paddingVertical: 5, marginLeft: 10, marginTop: 2 },
    statusBadgeText: { fontSize: 12, fontWeight: '700', textTransform: 'capitalize' },

    // Info rows
    divider: { height: 1, backgroundColor: '#F1F5F9', marginVertical: 12 },
    infoRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 5 },
    infoLabel: { fontSize: 12, color: '#94A3B8', fontWeight: '600', textTransform: 'uppercase', letterSpacing: 0.4 },
    infoValue: { fontSize: 13, color: '#334155', fontWeight: '600', flexShrink: 1, textAlign: 'right', marginLeft: 12 },

    // Line items
    lineHeader: {
        flexDirection: 'row',
        gap: 6,
        paddingHorizontal: 14,
        paddingVertical: 8,
        backgroundColor: '#F8FAFC',
        borderTopWidth: 1,
        borderTopColor: '#E2E8F0',
        borderBottomWidth: 1,
        borderBottomColor: '#E2E8F0',
    },
    lineHeaderCell: { fontSize: 9, fontWeight: '700', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.6 },
    lineRow: { flexDirection: 'row', gap: 6, paddingHorizontal: 14, paddingVertical: 10, alignItems: 'flex-start' },
    lineRowBorder: { borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    lineCell: { fontSize: 13, color: '#334155' },
    lineCellBold: { fontWeight: '700', color: '#0F172A' },

    // Totals
    totalsSection: {
        marginHorizontal: 14,
        marginTop: 10,
        marginBottom: 14,
        paddingTop: 12,
        borderTopWidth: 2,
        borderTopColor: '#E2E8F0',
    },
    totalRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
    totalLabel: { fontSize: 14, color: '#64748B' },
    totalValue: { fontSize: 14, color: '#334155', fontWeight: '600' },
    grandRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginTop: 8,
        paddingTop: 10,
        borderTopWidth: 1.5,
        borderTopColor: '#CBD5E1',
        marginBottom: 4,
    },
    grandLabel: { fontSize: 17, fontWeight: '800', color: '#0F172A' },
    grandValue: { fontSize: 17, fontWeight: '800', color: '#1E3A8A' },

    // Notes
    notesLabel: { fontSize: 11, fontWeight: '700', color: '#64748B', textTransform: 'uppercase', letterSpacing: 0.5, marginBottom: 4 },
    notesText: { fontSize: 14, color: '#334155', lineHeight: 20 },

    // Actions
    actionsCard: {
        backgroundColor: '#fff',
        borderRadius: 16,
        marginBottom: 14,
        padding: 16,
        gap: 10,
        elevation: 2,
        shadowColor: '#0F172A',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.07,
        shadowRadius: 4,
    },
    actionBtn: {
        borderRadius: 12,
        paddingVertical: 13,
        alignItems: 'center',
    },
    actionBtnBlue: { backgroundColor: '#1E40AF' },
    actionBtnGreen: { backgroundColor: '#166534' },
    actionBtnAmber: { backgroundColor: '#92400E' },
    actionBtnRed: { backgroundColor: '#991B1B' },
    actionBtnText: { color: '#fff', fontWeight: '700', fontSize: 14 },

    // Payment modal
    overlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        paddingHorizontal: 28,
    },
    modalBox: {
        backgroundColor: '#fff',
        borderRadius: 20,
        padding: 24,
        elevation: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.25,
        shadowRadius: 16,
    },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A', marginBottom: 4 },
    modalSubtitle: { fontSize: 13, color: '#64748B', marginBottom: 16 },
    modalInput: {
        borderWidth: 1.5,
        borderColor: '#E2E8F0',
        borderRadius: 12,
        paddingHorizontal: 14,
        paddingVertical: 13,
        fontSize: 18,
        fontWeight: '700',
        color: '#0F172A',
        backgroundColor: '#F8FAFC',
        marginBottom: 20,
    },
    modalBtns: { flexDirection: 'row', gap: 10 },
    modalCancelBtn: {
        flex: 1,
        paddingVertical: 13,
        borderRadius: 12,
        backgroundColor: '#F1F5F9',
        alignItems: 'center',
    },
    modalCancelText: { color: '#475569', fontWeight: '700', fontSize: 14 },
    modalConfirmBtn: {
        flex: 1,
        paddingVertical: 13,
        borderRadius: 12,
        backgroundColor: '#1E3A8A',
        alignItems: 'center',
    },
    modalConfirmText: { color: '#fff', fontWeight: '700', fontSize: 14 },
});
