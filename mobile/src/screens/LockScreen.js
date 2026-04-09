import React, { useEffect, useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert } from 'react-native';

export default function LockScreen({ biometricEnabled, onUnlockPin, onUnlockBiometric }) {
    const [pin, setPin] = useState('');

    useEffect(() => {
        if (biometricEnabled) {
            onUnlockBiometric().catch(() => {});
        }
    }, [biometricEnabled, onUnlockBiometric]);

    async function handleUnlock() {
        if (!pin) {
            Alert.alert('PIN Required', 'Please enter your PIN.');
            return;
        }

        const ok = await onUnlockPin(pin);
        if (!ok) {
            Alert.alert('Invalid PIN', 'The PIN you entered is incorrect.');
            setPin('');
        }
    }

    return (
        <View style={styles.container}>
            <View style={styles.card}>
                <Text style={styles.title}>App Locked</Text>
                <Text style={styles.subtitle}>Enter PIN to continue</Text>

                <TextInput
                    style={styles.input}
                    placeholder="PIN"
                    placeholderTextColor="#9CA3AF"
                    value={pin}
                    onChangeText={setPin}
                    keyboardType="number-pad"
                    secureTextEntry
                    maxLength={6}
                />

                <TouchableOpacity style={styles.unlockBtn} onPress={handleUnlock}>
                    <Text style={styles.unlockText}>Unlock with PIN</Text>
                </TouchableOpacity>

                {biometricEnabled && (
                    <TouchableOpacity style={styles.bioBtn} onPress={onUnlockBiometric}>
                        <Text style={styles.bioText}>Use Biometrics</Text>
                    </TouchableOpacity>
                )}
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#2563EB',
        justifyContent: 'center',
        padding: 20,
    },
    card: {
        backgroundColor: '#fff',
        borderRadius: 14,
        padding: 20,
    },
    title: { fontSize: 24, fontWeight: '800', color: '#111827' },
    subtitle: { fontSize: 14, color: '#6B7280', marginTop: 4, marginBottom: 16 },
    input: {
        backgroundColor: '#F3F4F6',
        borderRadius: 10,
        paddingHorizontal: 14,
        paddingVertical: 12,
        fontSize: 16,
        color: '#111827',
        marginBottom: 12,
    },
    unlockBtn: {
        backgroundColor: '#2563EB',
        borderRadius: 10,
        paddingVertical: 12,
        alignItems: 'center',
        marginBottom: 10,
    },
    unlockText: { color: '#fff', fontWeight: '700', fontSize: 15 },
    bioBtn: {
        backgroundColor: '#E0F2FE',
        borderRadius: 10,
        paddingVertical: 12,
        alignItems: 'center',
    },
    bioText: { color: '#0369A1', fontWeight: '700', fontSize: 15 },
});
