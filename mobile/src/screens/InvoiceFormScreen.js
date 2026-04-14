import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    FlatList,
    KeyboardAvoidingView,
    Modal,
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
    const [showCustomerPicker, setShowCustomerPicker] = useState(false);
    const [invoiceDate, setInvoiceDate] = useState(today);
    const [dueDate, setDueDate] = useState(addDays(today, Number(initialCustomer?.payment_terms || 30)));
    const [notes, setNotes] = useState('');
    const [terms, setTerms] = useState('Payment is due within the specified payment terms. Late payments may incur additional charges.');
    const [items, setItems] = useState([{ description: '', quantity: '1', unit_price: '' }]);

    useEffect(() => {
        navigation.setOptions({ title: 'Create Invoice' });
    }, [navigation]);

    useEffect(() => {
        getCustomers()
            .then(result => {
                if (!result.success) throw new Error(result.error || 'Failed to load customers.');
                const loaded = result.data || [];
                setCustomers(loaded);
                if (!selectedCustomerId && loaded.length > 0) {
                    const first = loaded[0];
                    setSelectedCustomerId(first.customer_id);
                    setDueDate(addDays(today, Number(first.payment_terms || 30)));
                }
            })
            .catch(err => Alert.alert('Error', err.message || 'Unable to load customers.'))
            .finally(() => setLoadingCustomers(false));
    }, []);

    const selectedCustomer = customers.find(c => c.customer_id === selectedCustomerId) || null;

    function selectCustomer(customer) {
        setSelectedCustomerId(customer.customer_id);
        setDueDate(addDays(invoiceDate, Number(customer.payment_terms || 30)));
        setShowCustomerPicker(false);
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
        return sum + (parseFloat(item.quantity || '0') || 0) * (parseFloat(item.unit_price || '0') || 0);
    }, 0);
    const total = subtotal;

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

                {/* â”€â”€ Invoice Details â”€â”€ */}
                <View style={styles.card}>
                    <Text style={styles.sectionTitle}>Invoice Details</Text>

                    <Text style={styles.label}>Customer <Text style={styles.required}>*</Text></Text>
                    {loadingCustomers ? (
                        <ActivityIndicator color="#1E3A8A" style={{ marginVertical: 12 }} />
                    ) : (
                        <TouchableOpacity style={styles.select} onPress={() => setShowCustomerPicker(true)} activeOpacity={0.7}>
                            <Text style={selectedCustomer ? styles.selectText : styles.selectPlaceholder}>
                                {selectedCustomer ? selectedCustomer.customer_name : 'Select Customer'}
                            </Text>
                            <Text style={styles.selectArrow}>â–¾</Text>
                        </TouchableOpacity>
                    )}

                    <Text style={[styles.label, { marginTop: 16 }]}>Invoice Date <Text style={styles.required}>*</Text></Text>
                    <TextInput
                        style={styles.input}
                        value={invoiceDate}
                        onChangeText={text => {
                            setInvoiceDate(text);
                            if (selectedCustomer) setDueDate(addDays(text, Number(selectedCustomer.payment_terms || 30)));
                        }}
                        placeholder="YYYY-MM-DD"
                        placeholderTextColor="#94A3B8"
                    />

                    <Text style={[styles.label, { marginTop: 16 }]}>Due Date <Text style={styles.required}>*</Text></Text>
                    <TextInput
                        style={styles.input}
                        value={dueDate}
                        onChangeText={setDueDate}
                        placeholder="YYYY-MM-DD"
                        placeholderTextColor="#94A3B8"
                    />
                </View>

                {/* â”€â”€ Line Items â”€â”€ */}
                <View style={styles.card}>
                    <Text style={styles.sectionTitle}>
                        Line Items <Text style={styles.required}>*</Text>
                    </Text>

                    {/* Column headers */}
                    <View style={styles.lineHeaderRow}>
                        <Text style={[styles.lineHeaderCell, { flex: 1 }]}>Description</Text>
                        <Text style={[styles.lineHeaderCell, { width: 52, textAlign: 'center' }]}>Quantity</Text>
                        <Text style={[styles.lineHeaderCell, { width: 80, textAlign: 'right' }]}>Unit Price</Text>
                        <Text style={[styles.lineHeaderCell, { width: 80, textAlign: 'right' }]}>Line Total</Text>
                        <View style={{ width: 32 }} />
                    </View>

                    {items.map((item, index) => {
                        const lineTotal = (parseFloat(item.quantity || '0') || 0) * (parseFloat(item.unit_price || '0') || 0);
                        return (
                            <View key={index} style={[styles.lineItemRow, index < items.length - 1 && styles.lineItemBorder]}>
                                <TextInput
                                    style={[styles.lineInput, { flex: 1 }]}
                                    value={item.description}
                                    onChangeText={v => updateItem(index, 'description', v)}
                                    placeholder="Item description"
                                    placeholderTextColor="#94A3B8"
                                />
                                <TextInput
                                    style={[styles.lineInput, { width: 52, textAlign: 'center' }]}
                                    value={item.quantity}
                                    onChangeText={v => updateItem(index, 'quantity', v)}
                                    placeholder="1"
                                    placeholderTextColor="#94A3B8"
                                    keyboardType="decimal-pad"
                                />
                                <TextInput
                                    style={[styles.lineInput, { width: 80, textAlign: 'right' }]}
                                    value={item.unit_price}
                                    onChangeText={v => updateItem(index, 'unit_price', v)}
                                    placeholder="0.00"
                                    placeholderTextColor="#94A3B8"
                                    keyboardType="decimal-pad"
                                />
                                <View style={[styles.lineInput, { width: 80, alignItems: 'flex-end', justifyContent: 'center', backgroundColor: '#F5F5F5' }]}>
                                    <Text style={styles.lineTotalText}>{formatMoney(lineTotal)}</Text>
                                </View>
                                <TouchableOpacity
                                    style={styles.removeBtn}
                                    onPress={() => removeItemRow(index)}
                                    disabled={items.length === 1}
                                >
                                    <Text style={[styles.removeBtnText, items.length === 1 && { opacity: 0.3 }]}>Ã—</Text>
                                </TouchableOpacity>
                            </View>
                        );
                    })}

                    <View style={styles.addBtnWrap}>
                        <TouchableOpacity style={styles.addBtn} onPress={addItemRow}>
                            <Text style={styles.addBtnText}>+ Add Line Item</Text>
                        </TouchableOpacity>
                    </View>

                    {/* Totals â€” right-aligned like the web */}
                    <View style={styles.totalsDivider} />
                    <View style={styles.totalsOuter}>
                        <View style={styles.totalsInner}>
                            <View style={styles.totalRow}>
                                <Text style={styles.totalLabel}>Subtotal:</Text>
                                <Text style={styles.totalValueBold}>{formatMoney(subtotal)}</Text>
                            </View>
                            <View style={styles.totalRow}>
                                <Text style={styles.totalLabelLight}>Tax (0%):</Text>
                                <Text style={styles.totalValueLight}>{formatMoney(0)}</Text>
                            </View>
                            <View style={styles.grandRow}>
                                <Text style={styles.grandLabel}>Total:</Text>
                                <Text style={styles.grandValue}>{formatMoney(total)}</Text>
                            </View>
                        </View>
                    </View>
                </View>

                {/* â”€â”€ Additional Information â”€â”€ */}
                <View style={styles.card}>
                    <Text style={styles.sectionTitle}>Additional Information</Text>

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

                    <Text style={[styles.label, { marginTop: 16 }]}>Terms &amp; Conditions</Text>
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

            </ScrollView>

            {/* Sticky footer */}
            <View style={styles.footer}>
                <TouchableOpacity style={[styles.createBtn, saving && styles.createBtnDisabled]} onPress={handleSave} disabled={saving}>
                    {saving ? <ActivityIndicator color="#fff" /> : <Text style={styles.createBtnText}>Create Invoice</Text>}
                </TouchableOpacity>
            </View>

            {/* Customer picker modal */}
            <Modal visible={showCustomerPicker} transparent animationType="fade" onRequestClose={() => setShowCustomerPicker(false)}>
                <TouchableOpacity style={styles.modalOverlay} activeOpacity={1} onPress={() => setShowCustomerPicker(false)}>
                    <View style={styles.modalBox} onStartShouldSetResponder={() => true}>
                        <Text style={styles.modalTitle}>Select Customer</Text>
                        <FlatList
                            data={customers}
                            keyExtractor={c => String(c.customer_id)}
                            style={{ maxHeight: 320 }}
                            renderItem={({ item }) => (
                                <TouchableOpacity
                                    style={[styles.modalItem, selectedCustomerId === item.customer_id && styles.modalItemActive]}
                                    onPress={() => selectCustomer(item)}
                                >
                                    <Text style={[styles.modalItemText, selectedCustomerId === item.customer_id && styles.modalItemTextActive]}>
                                        {item.customer_name}
                                    </Text>
                                    {selectedCustomerId === item.customer_id && <Text style={styles.checkMark}>âœ“</Text>}
                                </TouchableOpacity>
                            )}
                            ItemSeparatorComponent={() => <View style={{ height: 1, backgroundColor: '#F1F5F9' }} />}
                        />
                    </View>
                </TouchableOpacity>
            </Modal>

        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F1F5F9' },
    content: { padding: 16, paddingBottom: 110 },

    // Card
    card: {
        backgroundColor: '#fff',
        borderRadius: 8,
        marginBottom: 16,
        padding: 20,
        elevation: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.06,
        shadowRadius: 3,
    },

    // Section title matches web <h3>
    sectionTitle: {
        fontSize: 18,
        fontWeight: '700',
        color: '#1a1a1a',
        marginBottom: 20,
    },

    label: {
        fontSize: 13,
        fontWeight: '500',
        color: '#333',
        marginBottom: 6,
    },
    required: { color: '#e53e3e' },

    input: {
        borderWidth: 1,
        borderColor: '#ced4da',
        borderRadius: 4,
        paddingHorizontal: 12,
        paddingVertical: 10,
        color: '#333',
        fontSize: 14,
        backgroundColor: '#fff',
    },
    textarea: { minHeight: 80, textAlignVertical: 'top' },

    // Customer dropdown
    select: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: '#ced4da',
        borderRadius: 4,
        paddingHorizontal: 12,
        paddingVertical: 11,
        backgroundColor: '#fff',
    },
    selectText: { color: '#333', fontSize: 14, flex: 1 },
    selectPlaceholder: { color: '#94A3B8', fontSize: 14, flex: 1 },
    selectArrow: { color: '#666', fontSize: 14, marginLeft: 8 },

    // Line items column header
    lineHeaderRow: {
        flexDirection: 'row',
        gap: 6,
        paddingBottom: 8,
        borderBottomWidth: 1,
        borderBottomColor: '#dee2e6',
        marginBottom: 4,
        alignItems: 'center',
    },
    lineHeaderCell: { fontSize: 12, fontWeight: '600', color: '#555' },

    // Line item row
    lineItemRow: {
        flexDirection: 'row',
        gap: 6,
        paddingVertical: 8,
        alignItems: 'center',
    },
    lineItemBorder: { borderBottomWidth: 1, borderBottomColor: '#f0f0f0' },
    lineInput: {
        borderWidth: 1,
        borderColor: '#ced4da',
        borderRadius: 4,
        paddingHorizontal: 7,
        paddingVertical: 8,
        color: '#333',
        fontSize: 13,
        backgroundColor: '#fff',
    },
    lineTotalText: { fontSize: 13, fontWeight: '600', color: '#333' },

    removeBtn: {
        width: 32,
        height: 34,
        borderRadius: 4,
        backgroundColor: '#dc3545',
        alignItems: 'center',
        justifyContent: 'center',
    },
    removeBtnText: { color: '#fff', fontWeight: '700', fontSize: 18, lineHeight: 20 },

    addBtnWrap: { marginTop: 12, marginBottom: 4 },
    addBtn: {
        paddingVertical: 10,
        paddingHorizontal: 16,
        backgroundColor: '#6c757d',
        borderRadius: 4,
        alignSelf: 'flex-start',
    },
    addBtnText: { color: '#fff', fontWeight: '600', fontSize: 14 },

    // Totals â€” right-aligned panel like the web
    totalsDivider: {
        marginTop: 20,
        borderTopWidth: 2,
        borderTopColor: '#ddd',
    },
    totalsOuter: {
        alignItems: 'flex-end',
        marginTop: 16,
    },
    totalsInner: {
        minWidth: 240,
    },
    totalRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 10 },
    totalLabel: { fontSize: 16, fontWeight: '700', color: '#1a1a1a' },
    totalValueBold: { fontSize: 16, fontWeight: '700', color: '#1a1a1a' },
    totalLabelLight: { fontSize: 15, color: '#555' },
    totalValueLight: { fontSize: 15, color: '#555' },
    grandRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        paddingTop: 10,
        borderTopWidth: 2,
        borderTopColor: '#333',
    },
    grandLabel: { fontSize: 18, fontWeight: '700', color: '#1a1a1a' },
    grandValue: { fontSize: 18, fontWeight: '700', color: '#1E3A8A' },

    // Footer
    footer: {
        position: 'absolute',
        left: 0,
        right: 0,
        bottom: 0,
        backgroundColor: '#fff',
        borderTopWidth: 1,
        borderTopColor: '#dee2e6',
        paddingHorizontal: 16,
        paddingVertical: 12,
        paddingBottom: Platform.OS === 'ios' ? 28 : 12,
    },
    createBtn: {
        backgroundColor: '#28a745',
        borderRadius: 4,
        paddingVertical: 14,
        alignItems: 'center',
    },
    createBtnDisabled: { opacity: 0.6 },
    createBtnText: { color: '#fff', fontWeight: '700', fontSize: 16 },

    // Customer picker modal
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.45)',
        justifyContent: 'center',
        paddingHorizontal: 24,
    },
    modalBox: {
        backgroundColor: '#fff',
        borderRadius: 8,
        overflow: 'hidden',
        elevation: 8,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 12,
    },
    modalTitle: {
        fontSize: 15,
        fontWeight: '700',
        color: '#1a1a1a',
        paddingHorizontal: 16,
        paddingVertical: 14,
        borderBottomWidth: 1,
        borderBottomColor: '#dee2e6',
    },
    modalItem: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 14,
    },
    modalItemActive: { backgroundColor: '#EFF6FF' },
    modalItemText: { fontSize: 15, color: '#333' },
    modalItemTextActive: { color: '#1E3A8A', fontWeight: '700' },
    checkMark: { color: '#1E3A8A', fontWeight: '700', fontSize: 15 },
});

