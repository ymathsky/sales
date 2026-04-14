import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import { createInvoice, getCustomers } from '../api/client';

function toYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addDays(dateValue, days) {
    const date = new Date(dateValue);
    date.setDate(date.getDate() + days);
    return toYMD(date);
}

function formatMoney(value) {
    return `\u20B1${Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export default function InvoiceFormScreen({ navigation, route }) {
    const initialCustomer = route.params?.customer || null;
    const today = toYMD(new Date());

    const [customers, setCustomers] = useState([]);
    const [loadingCustomers, setLoadingCustomers] = useState(true);
    const [saving, setSaving] = useState(false);
    const [selectedCustomerId, setSelectedCustomerId] = useState(initialCustomer?.customer_id || null);
    const [invoiceDate, setInvoiceDate] = useState(today);
    const [dueDate, setDueDate] = useState(addDays(today, Number(initialCustomer?.payment_terms || 30)));
    const [notes, setNotes] = useState('');
    const [terms, setTerms] = useState('Payment is due within the specified payment terms. Late payments may incur additional charges.');
    const [items, setItems] = useState([{ description: '', quantity: '1', unit_price: '' }]);

    useEffect(() => {
        navigation.setOptions({ title: 'New Invoice' });
    }, [navigation]);

    useEffect(() => {
        getCustomers()
            .then(result => {
                if (!result.success) throw new Error(result.error || 'Failed to load customers.');
                const loadedCustomers = result.data || [];
                setCustomers(loadedCustomers);
                if (!selectedCustomerId && loadedCustomers.length > 0) {
                    const first = loadedCustomers[0];
                    setSelectedCustomerId(first.customer_id);
                    setDueDate(addDays(today, Number(first.payment_terms || 30)));
                }
            })
            .catch(err => Alert.alert('Error', err.message || 'Unable to load customers.'))
            .finally(() => setLoadingCustomers(false));
    }, []);

    function selectCustomer(customer) {
        setSelectedCustomerId(customer.customer_id);
        setDueDate(addDays(invoiceDate, Number(customer.payment_terms || 30)));
    }

    function updateItem(index, key, value) {
        setItems(current => current.map((item, i) => i === index ? { ...item, [key]: value } : item));
    }

    function addItemRow() {
        setItems(current => [...current, { description: '', quantity: '1', unit_price: '' }]);
    }

    function removeItemRow(index) {
        if (items.length > 1) {
            setItems(current => current.filter((_, i) => i !== index));
        }
    }

    const subtotal = items.reduce((sum, item) => {
        const qty = parseFloat(item.quantity || '0') || 0;
        const price = parseFloat(item.unit_price || '0') || 0;
        return sum + qty * price;
    }, 0);
    const total = subtotal; // Tax is 0%

    async function handleSave() {
        if (!selectedCustomerId) {
            Alert.alert('Validation', 'Please select a customer.');
            return;
        }

        const normalizedItems = items
            .map(item => ({
                description: item.description.trim(),
                quantity: parseFloat(item.quantity || '0') || 0,
                unit_price: parseFloat(item.unit_price || '0') || 0,
            }))
            .filter(item => item.description || item.quantity > 0 || item.unit_price > 0);

        if (normalizedItems.length === 0) {
            Alert.alert('Validation', 'Add at least one line item.');
            return;
        }

        const invalid = normalizedItems.find(item => !item.description || item.quantity <= 0 || item.unit_price < 0);
        if (invalid) {
            Alert.alert('Validation', 'Each item needs a description, quantity > 0, and a valid unit price.');
            return;
        }

        setSaving(true);
        try {
            const result = await createInvoice({
                customer_id: selectedCustomerId,
                invoice_date: invoiceDate,
                due_date: dueDate,
                tax_amount: 0,
                notes: notes.trim(),
                terms: terms.trim(),
                items: normalizedItems,
            });
            if (!result.success) {
                Alert.alert('Error', result.error || 'Unable to create invoice.');
                return;
            }
            Alert.alert('Invoice Created', 'Your invoice has been created successfully.', [
                { text: 'View Invoices', onPress: () => navigation.navigate('Invoices') },
                { text: 'Done', onPress: () => navigation.goBack() },
            ]);
        } catch {
            Alert.alert('Error', 'Unable to create invoice right now.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">

                {/* ── Invoice Details ── */}
                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Text style={styles.cardTitle}>Invoice Details</Text>
                    </View>
                    <View style={styles.cardBody}>
                        <Text style={styles.label}>Customer</Text>
                        {loadingCustomers ? (
                            <ActivityIndicator color="#1E3A8A" style={{ marginVertical: 12 }} />
                        ) : (
                            <View style={styles.chipWrap}>
                                {customers.map(c => (
                                    <TouchableOpacity
                                        key={c.customer_id}
                                        style={[styles.chip, selectedCustomerId === c.customer_id && styles.chipActive]}
                                        onPress={() => selectCustomer(c)}
                                    >
                                        <Text style={[styles.chipText, selectedCustomerId === c.customer_id && styles.chipTextActive]}>
                                            {c.customer_name}
                                        </Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                        )}

                        <View style={styles.dateRow}>
                            <View style={styles.col}>
                                <Text style={styles.label}>Invoice Date</Text>
                                <TextInput
                                    style={styles.input}
                                    value={invoiceDate}
                                    onChangeText={text => {
                                        setInvoiceDate(text);
                                        const sel = customers.find(c => c.customer_id === selectedCustomerId);
                                        if (sel) setDueDate(addDays(text, Number(sel.payment_terms || 30)));
                                    }}
                                    placeholder="YYYY-MM-DD"
                                    placeholderTextColor="#94A3B8"
                                />
                            </View>
                            <View style={styles.col}>
                                <Text style={styles.label}>Due Date</Text>
                                <TextInput
                                    style={styles.input}
                                    value={dueDate}
                                    onChangeText={setDueDate}
                                    placeholder="YYYY-MM-DD"
                                    placeholderTextColor="#94A3B8"
                                />
                            </View>
                        </View>
                    </View>
                </View>

                {/* ── Line Items ── */}
                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Text style={styles.cardTitle}>Line Items</Text>
                    </View>

                    <View style={styles.itemHeaderRow}>
                        <Text style={[styles.itemHeaderCell, { flex: 1 }]}>Description</Text>
                        <Text style={[styles.itemHeaderCell, { width: 52, textAlign: 'center' }]}>Qty</Text>
                        <Text style={[styles.itemHeaderCell, { width: 78, textAlign: 'right' }]}>Unit Price</Text>
                        <Text style={[styles.itemHeaderCell, { width: 78, textAlign: 'right' }]}>Total</Text>
                    </View>

                    {items.map((item, index) => {
                        const lineTotal = (parseFloat(item.quantity || '0') || 0) * (parseFloat(item.unit_price || '0') || 0);
                        return (
                            <View key={index} style={[styles.lineItem, index < items.length - 1 && styles.lineItemBorder]}>
                                <View style={styles.lineItemRow}>
                                    <TextInput
                                        style={[styles.input, { flex: 1 }]}
                                        value={item.description}
                                        onChangeText={v => updateItem(index, 'description', v)}
                                        placeholder="Item description"
                                        placeholderTextColor="#94A3B8"
                                    />
                                    <TextInput
                                        style={[styles.input, { width: 52, textAlign: 'center' }]}
                                        value={item.quantity}
                                        onChangeText={v => updateItem(index, 'quantity', v)}
                                        placeholder="1"
                                        placeholderTextColor="#94A3B8"
                                        keyboardType="decimal-pad"
                                    />
                                    <TextInput
                                        style={[styles.input, { width: 78, textAlign: 'right' }]}
                                        value={item.unit_price}
                                        onChangeText={v => updateItem(index, 'unit_price', v)}
                                        placeholder="0.00"
                                        placeholderTextColor="#94A3B8"
                                        keyboardType="decimal-pad"
                                    />
                                    <View style={styles.lineTotalCell}>
                                        <Text style={styles.lineTotalText}>{formatMoney(lineTotal)}</Text>
                                    </View>
                                </View>
                                {items.length > 1 && (
                                    <TouchableOpacity style={styles.removeBtn} onPress={() => removeItemRow(index)}>
                                        <Text style={styles.removeBtnText}>× Remove</Text>
                                    </TouchableOpacity>
                                )}
                            </View>
                        );
                    })}

                    <View style={styles.addBtnWrap}>
                        <TouchableOpacity style={styles.addBtn} onPress={addItemRow}>
                            <Text style={styles.addBtnText}>＋ Add Line Item</Text>
                        </TouchableOpacity>
                    </View>
                </View>

                {/* ── Totals ── */}
                <View style={styles.totalsCard}>
                    <View style={styles.totalRow}>
                        <Text style={styles.totalLabel}>Subtotal</Text>
                        <Text style={styles.totalValue}>{formatMoney(subtotal)}</Text>
                    </View>
                    <View style={styles.totalRow}>
                        <Text style={styles.totalLabel}>Tax (0%)</Text>
                        <Text style={styles.totalValue}>{formatMoney(0)}</Text>
                    </View>
                    <View style={styles.grandRow}>
                        <Text style={styles.grandLabel}>Total</Text>
                        <Text style={styles.grandValue}>{formatMoney(total)}</Text>
                    </View>
                </View>

                {/* ── Additional Information ── */}
                <View style={styles.card}>
                    <View style={styles.cardHeader}>
                        <Text style={styles.cardTitle}>Additional Information</Text>
                    </View>
                    <View style={styles.cardBody}>
                        <Text style={styles.label}>Notes (internal)</Text>
                        <TextInput
                            style={[styles.input, styles.textarea]}
                            value={notes}
                            onChangeText={setNotes}
                            placeholder="Internal notes (not shown to customer)"
                            placeholderTextColor="#94A3B8"
                            multiline
                            numberOfLines={3}
                        />
                        <Text style={styles.label}>Terms &amp; Conditions</Text>
                        <TextInput
                            style={[styles.input, styles.textarea]}
                            value={terms}
                            onChangeText={setTerms}
                            placeholder="Terms and conditions shown on invoice"
                            placeholderTextColor="#94A3B8"
                            multiline
                            numberOfLines={3}
                        />
                    </View>
                </View>

            </ScrollView>

            <View style={styles.footer}>
                <TouchableOpacity style={[styles.createBtn, saving && styles.createBtnDisabled]} onPress={handleSave} disabled={saving}>
                    {saving ? <ActivityIndicator color="#fff" /> : <Text style={styles.createBtnText}>Create Invoice</Text>}
                </TouchableOpacity>
            </View>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F1F5F9' },
    content: { padding: 16, paddingBottom: 110 },

    // Card
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
    cardTitle: { fontSize: 14, fontWeight: '800', color: '#fff', letterSpacing: 0.4, textTransform: 'uppercase' },
    cardBody: { padding: 16 },

    label: {
        fontSize: 11,
        fontWeight: '700',
        color: '#64748B',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
        marginBottom: 6,
        marginTop: 12,
    },
    input: {
        borderWidth: 1,
        borderColor: '#E2E8F0',
        borderRadius: 10,
        paddingHorizontal: 10,
        paddingVertical: 10,
        color: '#0F172A',
        fontSize: 14,
        backgroundColor: '#F8FAFC',
    },
    textarea: { minHeight: 80, textAlignVertical: 'top' },

    dateRow: { flexDirection: 'row', gap: 10 },
    col: { flex: 1 },

    // Customer chips
    chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    chip: {
        borderWidth: 1.5,
        borderColor: '#CBD5E1',
        borderRadius: 999,
        paddingHorizontal: 14,
        paddingVertical: 8,
        backgroundColor: '#fff',
    },
    chipActive: { borderColor: '#1E3A8A', backgroundColor: '#EFF6FF' },
    chipText: { color: '#475569', fontWeight: '600', fontSize: 13 },
    chipTextActive: { color: '#1E3A8A', fontWeight: '700' },

    // Line items header
    itemHeaderRow: {
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
    itemHeaderCell: { fontSize: 10, fontWeight: '700', color: '#94A3B8', textTransform: 'uppercase', letterSpacing: 0.5 },

    // Line item row
    lineItem: { paddingHorizontal: 14, paddingTop: 10, paddingBottom: 6 },
    lineItemBorder: { borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    lineItemRow: { flexDirection: 'row', gap: 6, alignItems: 'center' },
    lineTotalCell: { width: 78, alignItems: 'flex-end', paddingRight: 2 },
    lineTotalText: { fontSize: 13, fontWeight: '700', color: '#0F172A' },

    removeBtn: { alignSelf: 'flex-end', marginTop: 4, marginBottom: 2, paddingVertical: 2 },
    removeBtnText: { color: '#DC2626', fontWeight: '700', fontSize: 12 },

    addBtnWrap: { padding: 12 },
    addBtn: {
        paddingVertical: 12,
        backgroundColor: '#EFF6FF',
        borderRadius: 10,
        borderWidth: 1,
        borderColor: '#BFDBFE',
        alignItems: 'center',
    },
    addBtnText: { color: '#1E40AF', fontWeight: '800', fontSize: 13 },

    // Totals
    totalsCard: {
        backgroundColor: '#fff',
        borderRadius: 16,
        padding: 16,
        marginBottom: 14,
        elevation: 2,
        shadowColor: '#0F172A',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.07,
        shadowRadius: 4,
    },
    totalRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 5 },
    totalLabel: { fontSize: 14, color: '#64748B', fontWeight: '500' },
    totalValue: { fontSize: 14, color: '#334155', fontWeight: '600' },
    grandRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        paddingTop: 12,
        marginTop: 8,
        borderTopWidth: 2,
        borderTopColor: '#1E3A8A',
    },
    grandLabel: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    grandValue: { fontSize: 18, fontWeight: '800', color: '#1E3A8A' },

    // Footer
    footer: {
        position: 'absolute',
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: '#fff',
        borderTopWidth: 1,
        borderTopColor: '#E2E8F0',
        paddingHorizontal: 16,
        paddingVertical: 12,
        paddingBottom: Platform.OS === 'ios' ? 28 : 12,
    },
    createBtn: {
        backgroundColor: '#1E3A8A',
        borderRadius: 12,
        paddingVertical: 14,
        alignItems: 'center',
        shadowColor: '#1E3A8A',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 8,
        elevation: 4,
    },
    createBtnDisabled: { opacity: 0.6 },
    createBtnText: { color: '#fff', fontWeight: '800', fontSize: 16 },
});