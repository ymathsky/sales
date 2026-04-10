import React, { useEffect } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Text, View, ActivityIndicator } from 'react-native';

import { AuthProvider, useAuth } from './src/context/AuthContext';
import { LockProvider, useLock } from './src/context/LockContext';
import LoginScreen from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import TransactionsScreen from './src/screens/TransactionsScreen';
import CustomersScreen from './src/screens/CustomersScreen';
import AddTransactionScreen from './src/screens/AddTransactionScreen';
import EditTransactionScreen from './src/screens/EditTransactionScreen';
import POSQuickEntryScreen from './src/screens/POSQuickEntryScreen';
import CustomerFormScreen from './src/screens/CustomerFormScreen';
import InvoiceFormScreen from './src/screens/InvoiceFormScreen';
import InvoicesScreen from './src/screens/InvoicesScreen';
import ProfileScreen from './src/screens/ProfileScreen';
import LockScreen from './src/screens/LockScreen';
import { initializeFinancialAlerts } from './src/services/financialAlerts';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

function MainTabs() {
    const { can } = useAuth();

    return (
        <Tab.Navigator
            screenOptions={{
                tabBarActiveTintColor: '#2563EB',
                tabBarInactiveTintColor: '#6B7280',
                tabBarStyle: { backgroundColor: '#fff', borderTopWidth: 1, borderTopColor: '#E5E7EB' },
                headerStyle: { backgroundColor: '#2563EB' },
                headerTintColor: '#fff',
                headerTitleStyle: { fontWeight: 'bold' },
            }}
        >
            <Tab.Screen
                name="Dashboard"
                component={DashboardScreen}
                options={{
                    headerShown: false,
                    tabBarLabel: 'Dashboard',
                    tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>📊</Text>,
                }}
            />
            <Tab.Screen
                name="Transactions"
                component={TransactionsScreen}
                options={{
                    title: 'Transactions',
                    tabBarLabel: 'Transactions',
                    tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>📋</Text>,
                }}
            />
            {can('manage_customers') && (
                <Tab.Screen
                    name="Customers"
                    component={CustomersScreen}
                    options={{
                        headerShown: false,
                        tabBarLabel: 'Customers',
                        tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>🧾</Text>,
                    }}
                />
            )}
            <Tab.Screen
                name="Profile"
                component={ProfileScreen}
                options={{
                    headerShown: false,
                    tabBarLabel: 'Profile',
                    tabBarIcon: ({ color }) => <Text style={{ fontSize: 20, color }}>👤</Text>,
                }}
            />
        </Tab.Navigator>
    );
}

function AppNavigator() {
    const { user, loading } = useAuth();
    const { can } = useAuth();
    const {
        loading: lockLoading,
        pinEnabled,
        biometricEnabled,
        isUnlocked,
        unlockWithPin,
        unlockWithBiometric,
    } = useLock();

    if (loading || lockLoading) {
        return (
            <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#2563EB' }}>
                <ActivityIndicator size="large" color="#fff" />
            </View>
        );
    }

    if (user && pinEnabled && !isUnlocked) {
        return (
            <LockScreen
                biometricEnabled={biometricEnabled}
                onUnlockPin={unlockWithPin}
                onUnlockBiometric={unlockWithBiometric}
            />
        );
    }

    return (
        <NavigationContainer>
            <Stack.Navigator screenOptions={{ headerShown: false }}>
                {!user ? (
                    <Stack.Screen name="Login" component={LoginScreen} />
                ) : (
                    <>
                        <Stack.Screen name="Main" component={MainTabs} />
                        <Stack.Screen
                            name="CreateTransaction"
                            component={AddTransactionScreen}
                            options={{
                                headerShown: true,
                                title: 'Add Transaction',
                                headerStyle: { backgroundColor: '#2563EB' },
                                headerTintColor: '#fff',
                                headerTitleStyle: { fontWeight: 'bold' },
                            }}
                        />
                        <Stack.Screen
                            name="EditTransaction"
                            component={EditTransactionScreen}
                            options={{
                                headerShown: true,
                                title: 'Edit Transaction',
                                headerStyle: { backgroundColor: '#2563EB' },
                                headerTintColor: '#fff',
                                headerTitleStyle: { fontWeight: 'bold' },
                            }}
                        />
                        <Stack.Screen
                            name="POSQuickEntry"
                            component={POSQuickEntryScreen}
                            options={{
                                headerShown: true,
                                title: 'POS Quick Entry',
                                headerStyle: { backgroundColor: '#111827' },
                                headerTintColor: '#fff',
                                headerTitleStyle: { fontWeight: 'bold' },
                            }}
                        />
                        {can('manage_customers') && (
                            <Stack.Screen
                                name="CustomerForm"
                                component={CustomerFormScreen}
                                options={{
                                    headerShown: true,
                                    title: 'Customer',
                                    headerStyle: { backgroundColor: '#2563EB' },
                                    headerTintColor: '#fff',
                                    headerTitleStyle: { fontWeight: 'bold' },
                                }}
                            />
                        )}
                        {can('manage_invoices') && (
                            <Stack.Screen
                                name="InvoiceForm"
                                component={InvoiceFormScreen}
                                options={{
                                    headerShown: true,
                                    title: 'Create Invoice',
                                    headerStyle: { backgroundColor: '#111827' },
                                    headerTintColor: '#fff',
                                    headerTitleStyle: { fontWeight: 'bold' },
                                }}
                            />
                        )}
                        {can('manage_invoices') && (
                            <Stack.Screen
                                name="Invoices"
                                component={InvoicesScreen}
                                options={{
                                    headerShown: true,
                                    title: 'Invoices',
                                    headerStyle: { backgroundColor: '#1D4ED8' },
                                    headerTintColor: '#fff',
                                    headerTitleStyle: { fontWeight: 'bold' },
                                }}
                            />
                        )}
                    </>
                )}
            </Stack.Navigator>
        </NavigationContainer>
    );
}

export default function App() {
    useEffect(() => {
        initializeFinancialAlerts().catch(() => {
            // Non-blocking: app should continue even if notification permission/setup fails.
        });
    }, []);

    return (
        <AuthProvider>
            <LockProvider>
                <AppNavigator />
            </LockProvider>
        </AuthProvider>
    );
}
