import React, { useState } from 'react';
import {
    View, Text, TextInput, TouchableOpacity, StyleSheet,
    ScrollView, Alert, ActivityIndicator, Switch
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { createTransaction } from '../api/client';

const PAYMENT_METHODS = ['cash', 'bank_transfer', 'check', 'credit_card', 'gcash', 'maya', 'other'];
const CATEGORIES_INCOME = ['Sales', 'Service Fee', 'Rental', 'Investment', 'Refund', 'Other'];
const CATEGORIES_EXPENSE = ['Supplies', 'Utilities', 'Salary', 'Rent', 'Marketing', 'Transport', 'Maintenance', 'Other'];

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
                    style={[styles.optionChip, selected === opt && styles.optionChipActive]}
                    onPress={() => onSelect(opt)}
                >
                    <Text style={[styles.optionChipText, selected === opt && styles.optionChipTextActive]}>
                        {opt.replace(/_/g, ' ')}
                    </Text>
                </TouchableOpacity>
            ))}
        </ScrollView>
    );
}

export default function AddTransactionScreen() {
    const navigation = useNavigation();
    const [type, setType] = useState('in');
    const [amount, setAmount] = useState('');
    const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
    const [category, setCategory] = useState('');
    const [description, setDescription] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [referenceNo, setReferenceNo] = useState('');
    const [loading, setLoading] = useState(false);

    const categories = type === 'income' ? CATEGORIES_INCOME : CATEGORIES_EXPENSE;

    async function handleSubmit() {
        if (!amount || isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
            Alert.alert('Validation Error', 'Please enter a valid amount');
            return;
        }
        if (!date) {
            Alert.alert('Validation Error', 'Please enter a date');
            return;
        }

        setLoading(true);
        try {
            const result = await createTransaction({
                type,
                amount: parseFloat(amount),
                transaction_date: date,
                category: category || null,
                description: description || null,
                payment_method: paymentMethod,
                reference_number: referenceNo || null,
            });

            if (result.success) {
                Alert.alert('Success', 'Transaction created!', [
                    { text: 'Add Another', onPress: resetForm },
                    { text: 'Done', onPress: () => navigation.navigate('Transactions') },
                ]);
            } else {
                Alert.alert('Error', result.error || 'Failed to create transaction');
            }
        } catch (e) {
            Alert.alert('Error', 'Network error. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    function resetForm() {
        setAmount('');
        setCategory('');
        setDescription('');
        setReferenceNo('');
        setDate(new Date().toISOString().split('T')[0]);
    }

    return (
        <ScrollView style={styles.container} keyboardShouldPersistTaps="handled">
            {/* Type Toggle */}
            <View style={styles.typeToggleRow}>
                <TouchableOpacity
                    style={[styles.typeBtn, type === 'in' && styles.typeBtnIncome]}
                    onPress={() => { setType('in'); setCategory(''); }}
                >
                    <Text style={[styles.typeBtnText, type === 'in' && styles.typeBtnTextActive]}>
                        📈 Income
                    </Text>
                </TouchableOpacity>
                <TouchableOpacity
                    style={[styles.typeBtn, type === 'out' && styles.typeBtnExpense]}
                    onPress={() => { setType('out'); setCategory(''); }}
                >
                    <Text style={[styles.typeBtnText, type === 'out' && styles.typeBtnTextActive]}>
                        📉 Expense
                    </Text>
                </TouchableOpacity>
            </View>

            <View style={styles.form}>
                {/* Amount */}
                <FieldLabel text="Amount" required />
                <View style={styles.amountRow}>
                    <Text style={styles.currencySymbol}>₱</Text>
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

                {/* Reference Number */}
                <FieldLabel text="Reference Number" />
                <TextInput
                    style={styles.input}
                    placeholder="Cheque no., OR no., etc."
                    placeholderTextColor="#9CA3AF"
                    value={referenceNo}
                    onChangeText={setReferenceNo}
                />

                {/* Submit */}
                <TouchableOpacity
                    style={[styles.submitBtn, loading && styles.submitBtnDisabled, type === 'expense' && styles.submitBtnExpense]}
                    onPress={handleSubmit}
                    disabled={loading}
                >
                    {loading
                        ? <ActivityIndicator color="#fff" />
                        : <Text style={styles.submitBtnText}>Save {type === 'income' ? 'Income' : 'Expense'}</Text>
                    }
                </TouchableOpacity>
            </View>
        </ScrollView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F3F4F6' },
    typeToggleRow: {
        flexDirection: 'row',
        backgroundColor: '#fff',
        padding: 12,
        gap: 10,
        borderBottomWidth: 1,
        borderBottomColor: '#E5E7EB',
    },
    typeBtn: {
        flex: 1,
        paddingVertical: 12,
        borderRadius: 10,
        alignItems: 'center',
        backgroundColor: '#F3F4F6',
    },
    typeBtnIncome: { backgroundColor: '#D1FAE5' },
    typeBtnExpense: { backgroundColor: '#FEE2E2' },
    typeBtnText: { fontWeight: '700', fontSize: 15, color: '#6B7280' },
    typeBtnTextActive: { color: '#111827' },
    form: { padding: 16 },
    label: { fontSize: 13, fontWeight: '700', color: '#374151', marginBottom: 8, textTransform: 'uppercase', letterSpacing: 0.4 },
    amountRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 16 },
    currencySymbol: { fontSize: 22, fontWeight: '700', color: '#374151', marginRight: 8 },
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
    optionChip: {
        paddingHorizontal: 14,
        paddingVertical: 8,
        backgroundColor: '#E5E7EB',
        borderRadius: 20,
        marginRight: 8,
    },
    optionChipActive: { backgroundColor: '#2563EB' },
    optionChipText: { color: '#374151', fontWeight: '600', fontSize: 13, textTransform: 'capitalize' },
    optionChipTextActive: { color: '#fff' },
    submitBtn: {
        backgroundColor: '#10B981',
        borderRadius: 12,
        paddingVertical: 16,
        alignItems: 'center',
        marginTop: 8,
        marginBottom: 40,
    },
    submitBtnExpense: { backgroundColor: '#EF4444' },
    submitBtnDisabled: { opacity: 0.6 },
    submitBtnText: { color: '#fff', fontWeight: '700', fontSize: 16 },
});
