import React, { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AppState } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import * as LocalAuthentication from 'expo-local-authentication';

const LockContext = createContext(null);

const PIN_KEY = 'app_security_pin';
const SETTINGS_KEY = 'app_security_settings';

export function LockProvider({ children }) {
    const [loading, setLoading] = useState(true);
    const [pinEnabled, setPinEnabled] = useState(false);
    const [biometricEnabled, setBiometricEnabled] = useState(false);
    const [isUnlocked, setIsUnlocked] = useState(true);

    const appState = useRef(AppState.currentState);

    useEffect(() => {
        initialize();
    }, []);

    useEffect(() => {
        const sub = AppState.addEventListener('change', nextState => {
            if (pinEnabled && appState.current === 'active' && (nextState === 'inactive' || nextState === 'background')) {
                setIsUnlocked(false);
            }
            appState.current = nextState;
        });

        return () => sub.remove();
    }, [pinEnabled]);

    async function initialize() {
        try {
            const settingsRaw = await AsyncStorage.getItem(SETTINGS_KEY);
            const settings = settingsRaw ? JSON.parse(settingsRaw) : {};
            const pin = await SecureStore.getItemAsync(PIN_KEY);

            const enabled = !!pin && !!settings.pinEnabled;
            setPinEnabled(enabled);
            setBiometricEnabled(enabled && !!settings.biometricEnabled);
            setIsUnlocked(!enabled);
        } catch {
            setPinEnabled(false);
            setBiometricEnabled(false);
            setIsUnlocked(true);
        } finally {
            setLoading(false);
        }
    }

    async function persistSettings(nextSettings) {
        await AsyncStorage.setItem(SETTINGS_KEY, JSON.stringify(nextSettings));
    }

    async function enablePin(pin, useBiometric = false) {
        await SecureStore.setItemAsync(PIN_KEY, pin);
        const nextSettings = { pinEnabled: true, biometricEnabled: !!useBiometric };
        await persistSettings(nextSettings);

        setPinEnabled(true);
        setBiometricEnabled(!!useBiometric);
        setIsUnlocked(true);
    }

    async function verifyPin(pin) {
        const current = await SecureStore.getItemAsync(PIN_KEY);
        return !!current && current === pin;
    }

    async function unlockWithPin(pin) {
        const ok = await verifyPin(pin);
        if (ok) {
            setIsUnlocked(true);
        }
        return ok;
    }

    async function unlockWithBiometric() {
        if (!biometricEnabled || !pinEnabled) return false;

        const hasHardware = await LocalAuthentication.hasHardwareAsync();
        const enrolled = await LocalAuthentication.isEnrolledAsync();
        if (!hasHardware || !enrolled) return false;

        const result = await LocalAuthentication.authenticateAsync({
            promptMessage: 'Unlock Cash Flow',
            cancelLabel: 'Cancel',
            disableDeviceFallback: false,
        });

        if (result.success) {
            setIsUnlocked(true);
            return true;
        }

        return false;
    }

    async function setBiometricPreference(enabled) {
        const next = { pinEnabled, biometricEnabled: !!enabled };
        await persistSettings(next);
        setBiometricEnabled(!!enabled);
    }

    async function disablePin(currentPin) {
        const ok = await verifyPin(currentPin);
        if (!ok) return false;

        await SecureStore.deleteItemAsync(PIN_KEY);
        await persistSettings({ pinEnabled: false, biometricEnabled: false });

        setPinEnabled(false);
        setBiometricEnabled(false);
        setIsUnlocked(true);
        return true;
    }

    function lockNow() {
        if (pinEnabled) setIsUnlocked(false);
    }

    const value = useMemo(() => ({
        loading,
        pinEnabled,
        biometricEnabled,
        isUnlocked,
        enablePin,
        disablePin,
        unlockWithPin,
        unlockWithBiometric,
        setBiometricPreference,
        lockNow,
    }), [loading, pinEnabled, biometricEnabled, isUnlocked]);

    return <LockContext.Provider value={value}>{children}</LockContext.Provider>;
}

export function useLock() {
    return useContext(LockContext);
}
