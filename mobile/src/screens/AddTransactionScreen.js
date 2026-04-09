import React, { useState, useEffect } from 'react';
import {
    View, Text, TextInput, TouchableOpacity, StyleSheet,
    Image,
    ScrollView, Alert, ActivityIndicator
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import { useNavigation } from '@react-navigation/native';
import { createTransaction, getCategories, uploadReceipts } from '../api/client';
import { addTransactionDraft } from '../storage/offlineDrafts';

const PAYMENT_METHODS = ['cash', 'bank_transfer', 'check', 'credit_card', 'gcash', 'maya', 'other'];
const RECEIPT_LIMIT = 20;
const CATEGORY_CACHE_KEY_PREFIX = 'category_cache_';

function defaultCategoriesByType(type) {
    if (type === 'in') {
        return ['Sales', 'Service Fee', 'Rental', 'Investment', 'Refund', 'Other'];
    }
    return ['Supplies', 'Utilities', 'Salary', 'Rent', 'Marketing', 'Transport', 'Maintenance', 'Other'];
}

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
    const [categories, setCategories] = useState([]);
    const [categoriesLoading, setCategoriesLoading] = useState(false);
    const [selectedReceipts, setSelectedReceipts] = useState([]);

    useEffect(() => {
        const loadCategories = async () => {
            setCategoriesLoading(true);
            const cacheKey = `${CATEGORY_CACHE_KEY_PREFIX}${type}`;

            try {
                const res = await getCategories(type);
                const names = res?.success ? res.data.map(c => c.name) : [];

                if (names.length > 0) {
                    setCategories(names);
                    await AsyncStorage.setItem(cacheKey, JSON.stringify(names));
                    return;
                }
            } catch {
                // Fallback to cache/defaults below.
            }

            try {
                const cached = await AsyncStorage.getItem(cacheKey);
                if (cached) {
                    const parsed = JSON.parse(cached);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                        setCategories(parsed);
                        return;
                    }
                }
            } catch {
                // Ignore cache parse errors and use defaults.
            }

            setCategories(defaultCategoriesByType(type));
        };

        loadCategories().finally(() => setCategoriesLoading(false));
    }, [type]);

    function buildPayload() {
        return {
            type,
            amount: parseFloat(amount),
            transaction_date: date,
            category: category || null,
            description: description || null,
            payment_method: paymentMethod,
            reference_number: referenceNo || null,
        };
    }

    function buildReceiptAssets() {
        return selectedReceipts.map((asset, idx) => ({
            uri: asset.uri,
            fileName: asset.fileName || `receipt_${Date.now()}_${idx}.jpg`,
            mimeType: asset.mimeType || 'image/jpeg',
        }));
    }

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
            const payload = buildPayload();
            const result = await createTransaction(payload);

            if (result.success) {
                let uploadSummary = '';
                if (selectedReceipts.length > 0) {
                    const uploadResult = await uploadReceipts(result.transaction_id, selectedReceipts);
                    if (uploadResult.success) {
                        uploadSummary = `\n${uploadResult.uploaded_count} receipt photo(s) uploaded.`;
                    } else {
                        uploadSummary = '\nTransaction saved, but receipt upload failed.';
                    }
                }

                Alert.alert('Success', `Transaction created!${uploadSummary}`, [
                    { text: 'Add Another', onPress: resetForm },
                    { text: 'Done', onPress: () => navigation.navigate('Transactions') },
                ]);
            } else {
                Alert.alert('Error', result.error || 'Failed to create transaction');
            }
        } catch (e) {
            const payload = buildPayload();
            const receiptAssets = buildReceiptAssets();
            await addTransactionDraft({ payload, receiptAssets });
            Alert.alert('Saved Offline', 'No internet detected. Transaction was saved as a local draft and will sync automatically once you are online.');
            resetForm();
        } finally {
            setLoading(false);
        }
    }

    async function handleSaveDraft() {
        if (!amount || isNaN(parseFloat(amount)) || parseFloat(amount) <= 0) {
            Alert.alert('Validation Error', 'Please enter a valid amount before saving draft.');
            return;
        }
        if (!date) {
            Alert.alert('Validation Error', 'Please enter a date before saving draft.');
            return;
        }

        const payload = buildPayload();
        const receiptAssets = buildReceiptAssets();
        await addTransactionDraft({ payload, receiptAssets });
        Alert.alert('Draft Saved', 'Transaction draft saved locally. It will sync automatically when online.');
        resetForm();
    }

    function resetForm() {
        setAmount('');
        setCategory('');
        setDescription('');
        setReferenceNo('');
        setDate(new Date().toISOString().split('T')[0]);
        setSelectedReceipts([]);
    }

    async function pickReceipts() {
        const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!permission.granted) {
            Alert.alert('Permission Required', 'Please allow photo library access to attach receipts.');
            return;
        }

        const result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsMultipleSelection: true,
            quality: 0.7,
            selectionLimit: RECEIPT_LIMIT,
        });

        if (result.canceled) return;

        setSelectedReceipts(prev => {
            const merged = [...prev, ...result.assets];
            return merged.slice(0, RECEIPT_LIMIT);
        });
    }

    async function captureReceiptPhoto() {
        if (selectedReceipts.length >= RECEIPT_LIMIT) {
            Alert.alert('Limit reached', `You can attach up to ${RECEIPT_LIMIT} photos.`);
            return;
        }

        const permission = await ImagePicker.requestCameraPermissionsAsync();
        if (!permission.granted) {
            Alert.alert('Permission Required', 'Please allow camera access to capture receipt photos.');
            return;
        }

        const result = await ImagePicker.launchCameraAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            quality: 0.7,
        });

        if (result.canceled) return;

        setSelectedReceipts(prev => {
            const merged = [...prev, ...result.assets];
            return merged.slice(0, RECEIPT_LIMIT);
        });
    }

    function removeReceipt(indexToRemove) {
        setSelectedReceipts(prev => prev.filter((_, index) => index !== indexToRemove));
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
                {categoriesLoading
                    ? <ActivityIndicator size="small" color="#2563EB" style={{ marginBottom: 16 }} />
                    : <OptionRow options={categories} selected={category} onSelect={setCategory} />
                }

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

                {/* Receipts */}
                <FieldLabel text="Receipt Photos" />
                <View style={styles.receiptActionRow}>
                    <TouchableOpacity style={[styles.receiptPickerBtn, styles.receiptActionBtn]} onPress={pickReceipts}>
                        <Text style={styles.receiptPickerText}>
                            + Gallery ({selectedReceipts.length}/{RECEIPT_LIMIT})
                        </Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[styles.receiptCameraBtn, styles.receiptActionBtn]} onPress={captureReceiptPhoto}>
                        <Text style={styles.receiptCameraText}>+ Camera</Text>
                    </TouchableOpacity>
                </View>

                {selectedReceipts.length > 0 && (
                    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.receiptPreviewRow}>
                        {selectedReceipts.map((asset, index) => (
                            <View key={`${asset.uri}-${index}`} style={styles.receiptThumbWrap}>
                                <Image source={{ uri: asset.uri }} style={styles.receiptThumb} />
                                <TouchableOpacity style={styles.receiptRemoveBtn} onPress={() => removeReceipt(index)}>
                                    <Text style={styles.receiptRemoveText}>x</Text>
                                </TouchableOpacity>
                            </View>
                        ))}
                    </ScrollView>
                )}

                {/* Submit */}
                <TouchableOpacity
                    style={[styles.submitBtn, loading && styles.submitBtnDisabled, type === 'out' && styles.submitBtnExpense]}
                    onPress={handleSubmit}
                    disabled={loading}
                >
                    {loading
                        ? <ActivityIndicator color="#fff" />
                        : <Text style={styles.submitBtnText}>Save {type === 'in' ? 'Income' : 'Expense'}</Text>
                    }
                </TouchableOpacity>

                <TouchableOpacity
                    style={styles.draftBtn}
                    onPress={handleSaveDraft}
                    disabled={loading}
                >
                    <Text style={styles.draftBtnText}>Save Offline Draft</Text>
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
    receiptActionRow: { flexDirection: 'row', gap: 10, marginBottom: 10 },
    receiptActionBtn: { flex: 1, marginBottom: 0 },
    receiptPickerBtn: {
        backgroundColor: '#EFF6FF',
        borderWidth: 1,
        borderColor: '#BFDBFE',
        borderRadius: 10,
        paddingVertical: 12,
        paddingHorizontal: 14,
    },
    receiptPickerText: { color: '#1D4ED8', fontWeight: '700', textAlign: 'center' },
    receiptCameraBtn: {
        backgroundColor: '#ECFDF5',
        borderWidth: 1,
        borderColor: '#A7F3D0',
        borderRadius: 10,
        paddingVertical: 12,
        paddingHorizontal: 14,
    },
    receiptCameraText: { color: '#047857', fontWeight: '700', textAlign: 'center' },
    receiptPreviewRow: { marginBottom: 16 },
    receiptThumbWrap: { marginRight: 10, position: 'relative' },
    receiptThumb: { width: 78, height: 78, borderRadius: 10, backgroundColor: '#E5E7EB' },
    receiptRemoveBtn: {
        position: 'absolute',
        top: -6,
        right: -6,
        width: 20,
        height: 20,
        borderRadius: 10,
        backgroundColor: '#EF4444',
        alignItems: 'center',
        justifyContent: 'center',
    },
    receiptRemoveText: { color: '#fff', fontSize: 11, fontWeight: '700' },
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
    draftBtn: {
        backgroundColor: '#E5E7EB',
        borderRadius: 12,
        paddingVertical: 14,
        alignItems: 'center',
        marginTop: -28,
        marginBottom: 40,
    },
    draftBtnText: { color: '#374151', fontWeight: '700', fontSize: 15 },
});
