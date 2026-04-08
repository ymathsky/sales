import React, { useState, useEffect } from 'react';
import {
    View, Text, TextInput, TouchableOpacity, StyleSheet,
    ScrollView, Alert, ActivityIndicator
} from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { updateTransaction, deleteTransaction } from '../api/client';

const PAYMENT_METHODS = ['cash', 'bank_transfer', 'check', 'credit_card', 'gcash', 'maya', 'other'];
const CATEGORIES_IN  = ['Sales', 'Service Fee', 'Rental', 'Investment', 'Refund', 'Other'];
const CATEGORIES_OUT = ['Supplies', 'Utilities', 'Salary', 'Rent', 'Marketing', 'Transport', 'Maintenance', 'Other'];

function FieldLabel({ text, required }) {
    return (
        <Text style={styles.label}>
            {text}{required && <Text style={{ color: '#EF4444' }}> *</Text>}
        </Text>
    );
}

function OptionRow({ options, selected, onSelect }) {
    return (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 16 }}>
            {options.map(opt => (
                <TouchableOpacity
                    key={opt}
                    style={[styles.chip, selected === opt && styles.chipActive]}
                    onPress={() => onSelect(selected === opt ? '' : opt)}
                >
                    <Text style={[styles.chipText, selected === opt && styles.chipTextActive]}>
                        {opt.replace(/_/g, ' ')}
                    </Text>
                </TouchableOpacity>
            ))}
        </ScrollView>
    );
}

export default function EditTransactionScreen() {
    const navigation = useNavigation();
    const route = useRoute();
    const { transaction } = route.params;

    const [type, setType]                 = useState(transaction.type || 'in');
    const [amount, setAmount]             = useState(String(transaction.amount || ''));
    const [date, setDate]                 = useState(transaction.transaction_date || '');
    const [category, setCategory]         = useState(transaction.category || '');
    const [description, setDescription]   = useState(transaction.description || '');
    const [paymentMethod, setPaymentMethod] = useState(transaction.payment_method || 'cash');
    const [referenceNo, setReferenceNo]   = useState(transaction.reference_number || '');
    const [saving, setSaving]             = useState(false);
    const [deleting, setDeleting]         = useState(false);

    const categories = type === 'in' ? CATEGORIES_IN : CATEGORIES_OUT;

    async function handleSave() {
        if (!amount || isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
            Alert.alert('Validation Error', 'Please enter a valid amount');
            return;
        }
        if (!date) {
            Alert.alert('Validation Error', 'Please enter a date');
            return;
        }
        setSaving(true);
        try {
            const result = await updateTransaction(transaction.transaction_id, {
                type,
                amount: parseFloat(amount),
                transaction_date: date,
                category: category || null,
                description: description || null,
                payment_method: paymentMethod,
                reference_number: referenceNo || null,
            });
            if (result.success) {
                navigation.goBack();
            } else {
                Alert.alert('Error', result.error || 'Failed to save');
            }
        } catch {
            Alert.alert('Error', 'Network error. Please try again.');
        } finally {
            setSaving(false);
        }
    }

    function confirmDelete() {
        Alert.alert(
            'Delete Transaction',
            'Are you sure you want to permanently delete this transaction?',
            [
                { text: 'Cancel', style: 'cancel' },
                { text: 'Delete', style: 'destructive', onPress: handleDelete },
            ]
        );
    }

    async function handleDelete() {
        setDeleting(true);
        try {
            const result = await deleteTransaction(transaction.transaction_id);
            if (result.success) {
                navigation.goBack();
            } else {
                Alert.alert('Error', result.error || 'Failed to delete');
            }
        } catch {
            Alert.alert('Error', 'Network error. Please try again.');
        } finally {
            setDeleting(false);
        }
    }

    return (
        <ScrollView style={styles.container} keyboardShouldPersistTaps="handled">
            {/* Type toggle */}
            <View style={styles.typeRow}>
                <TouchableOpacity
                    style={[styles.typeBtn, type === 'in' && styles.typeBtnIncome]}
                    onPress={() => { setType('in'); setCategory(''); }}
                >
                    <Text style={[styles.typeBtnText, type === 'in' && styles.typeBtnTextActive]}>📈 Income</Text>
                </TouchableOpacity>
                <TouchableOpacity
                    style={[styles.typeBtn, type === 'out' && styles.typeBtnExpense]}
                    onPress={() => { setType('out'); setCategory(''); }}
                >
                    <Text style={[styles.typeBtnText, type === 'out' && styles.typeBtnTextActive]}>📉 Expense</Text>
                </TouchableOpacity>
            </View>

            <View style={styles.form}>
                {/* Amount */}
                <FieldLabel text="Amount" required />
                <View style={styles.amountRow}>
                    <Text style={styles.currency}>₱</Text>
                    <TextInput
                        style={[styles.input, styles.amountInput]}
                        placeholder="0.00"
                        placeholderTextColor="#9CA3AF"
                        keyboardType="decimal-pad"
                        value={amount}
                        onChangeText={setAmount}
                    />
                </View>

                {/* Date */}
                <FieldLabel text="Date" required />
                <TextInput
                    style={styles.input}
                    placeholder="YYYY-MM-DD"
                    placeholderTextColor="#9CA3AF"
                    value={date}
                    onChangeText={setDate}
                />

                {/* Category */}
                <FieldLabel text="Category" />
                <OptionRow options={categories} selected={category} onSelect={setCategory} />

                {/* Description */}
                <FieldLabel text="Description" />
                <TextInput
                    style={[styles.input, styles.textArea]}
                    placeholder="Optional note..."
                    placeholderTextColor="#9CA3AF"
                    multiline
                    numberOfLines={3}
                    value={description}
                    onChangeText={setDescription}
                />

                {/* Payment Method */}
                <FieldLabel text="Payment Method" />
                <OptionRow options={PAYMENT_METHODS} selected={paymentMethod} onSelect={setPaymentMethod} />

                {/* Reference */}
                <FieldLabel text="Reference Number" />
                <TextInput
                    style={styles.input}
                    placeholder="Cheque no., OR no., etc."
                    placeholderTextColor="#9CA3AF"
                    value={referenceNo}
                    onChangeText={setReferenceNo}
                />

                {/* Save */}
                <TouchableOpacity
                    style={[styles.saveBtn, saving && styles.disabled, type === 'out' && styles.saveBtnExpense]}
                    onPress={handleSave}
                    disabled={saving || deleting}
                >
                    {saving
                        ? <ActivityIndicator color="#fff" />
                        : <Text style={styles.saveBtnText}>Save Changes</Text>
                    }
                </TouchableOpacity>

                {/* Delete */}
                <TouchableOpacity
                    style={[styles.deleteBtn, deleting && styles.disabled]}
                    onPress={confirmDelete}
                    disabled={saving || deleting}
                >
                    {deleting
                        ? <ActivityIndicator color="#EF4444" />
                        : <Text style={styles.deleteBtnText}>🗑 Delete Transaction</Text>
                    }
                </TouchableOpacity>
            </View>
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F3F4F6' },
    typeRow: {
        flexDirection: 'row',
        backgroundColor: '#fff',
        padding: 12,
        gap: 10,
        borderBottomWidth: 1,
        borderBottomColor: '#E5E7EB',
    },
    typeBtn: { flex: 1, paddingVertical: 12, borderRadius: 10, alignItems: 'center', backgroundColor: '#F3F4F6' },
    typeBtnIncome: { backgroundColor: '#D1FAE5' },
    typeBtnExpense: { backgroundColor: '#FEE2E2' },
    typeBtnText: { fontWeight: '700', fontSize: 15, color: '#6B7280' },
    typeBtnTextActive: { color: '#111827' },
    form: { padding: 16 },
    label: { fontSize: 12, fontWeight: '700', color: '#374151', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 0.4 },
    amountRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 16 },
    currency: { fontSize: 22, fontWeight: '700', color: '#374151', marginRight: 8 },
    amountInput: { flex: 1, fontSize: 24, fontWeight: '700', marginBottom: 0 },
    input: {
        backgroundColor: '#fff',
        borderRadius: 10,
        paddingHorizontal: 14,
        paddingVertical: 12,
        fontSize: 16,
        color: '#111827',
        marginBottom: 16,
        borderWidth: 1,
        borderColor: '#E5E7EB',
    },
    textArea: { height: 80, textAlignVertical: 'top' },
    chip: { paddingHorizontal: 14, paddingVertical: 8, backgroundColor: '#E5E7EB', borderRadius: 20, marginRight: 8 },
    chipActive: { backgroundColor: '#2563EB' },
    chipText: { color: '#374151', fontWeight: '600', fontSize: 13, textTransform: 'capitalize' },
    chipTextActive: { color: '#fff' },
    saveBtn: { backgroundColor: '#10B981', borderRadius: 12, paddingVertical: 16, alignItems: 'center', marginTop: 8 },
    saveBtnExpense: { backgroundColor: '#EF4444' },
    saveBtnText: { color: '#fff', fontWeight: '700', fontSize: 16 },
    deleteBtn: {
        backgroundColor: '#fff',
        borderRadius: 12,
        paddingVertical: 14,
        alignItems: 'center',
        marginTop: 12,
        marginBottom: 40,
        borderWidth: 1,
        borderColor: '#FCA5A5',
    },
    deleteBtnText: { color: '#EF4444', fontWeight: '700', fontSize: 15 },
    disabled: { opacity: 0.6 },
});
