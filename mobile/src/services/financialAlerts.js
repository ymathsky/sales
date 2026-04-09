import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

const LOW_BALANCE_THRESHOLD = 5000;
const EXPENSE_SPIKE_RATIO = 1.25;
const EXPENSE_SPIKE_MIN = 10000;
const NOTIFY_COOLDOWN_MS = 6 * 60 * 60 * 1000; // 6 hours

const LOW_BALANCE_KEY = 'alert_last_low_balance_at';
const EXPENSE_SPIKE_KEY = 'alert_last_expense_spike_at';
const PREV_MONTH_EXPENSE_KEY = 'alert_prev_month_expense';

Notifications.setNotificationHandler({
    handleNotification: async () => ({
        shouldShowAlert: true,
        shouldPlaySound: true,
        shouldSetBadge: false,
    }),
});

export async function initializeFinancialAlerts() {
    const permissions = await Notifications.getPermissionsAsync();

    if (permissions.status !== 'granted') {
        const requested = await Notifications.requestPermissionsAsync();
        if (requested.status !== 'granted') {
            return false;
        }
    }

    if (Platform.OS === 'android') {
        await Notifications.setNotificationChannelAsync('financial-alerts', {
            name: 'Financial Alerts',
            importance: Notifications.AndroidImportance.HIGH,
            vibrationPattern: [0, 250, 250, 250],
            lightColor: '#EF4444',
        });
    }

    return true;
}

function formatMoney(amount) {
    return `Php ${Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

async function shouldNotify(key) {
    const now = Date.now();
    const raw = await AsyncStorage.getItem(key);
    const last = raw ? Number(raw) : 0;

    if (now - last < NOTIFY_COOLDOWN_MS) {
        return false;
    }

    await AsyncStorage.setItem(key, String(now));
    return true;
}

async function notify(title, body) {
    await Notifications.scheduleNotificationAsync({
        content: {
            title,
            body,
            sound: true,
        },
        trigger: null,
    });
}

export async function checkFinancialAlerts(dashboardPayload) {
    const summary = dashboardPayload?.summary;
    if (!summary) return;

    const netBalance = Number(summary.net_balance || 0);
    const monthExpense = Number(summary.month_expense || 0);

    if (netBalance < LOW_BALANCE_THRESHOLD) {
        const canNotify = await shouldNotify(LOW_BALANCE_KEY);
        if (canNotify) {
            await notify(
                'Low Cash Balance',
                `Your current balance is ${formatMoney(netBalance)}. Consider adding funds or reducing expenses.`
            );
        }
    }

    const prevExpenseRaw = await AsyncStorage.getItem(PREV_MONTH_EXPENSE_KEY);
    const prevExpense = prevExpenseRaw ? Number(prevExpenseRaw) : 0;

    if (prevExpense > 0) {
        const ratio = monthExpense / prevExpense;
        const isSpike = ratio >= EXPENSE_SPIKE_RATIO && monthExpense >= EXPENSE_SPIKE_MIN;

        if (isSpike) {
            const canNotify = await shouldNotify(EXPENSE_SPIKE_KEY);
            if (canNotify) {
                await notify(
                    'Unusual Expense Spike',
                    `Monthly expenses rose to ${formatMoney(monthExpense)} (${Math.round((ratio - 1) * 100)}% increase).`
                );
            }
        }
    }

    await AsyncStorage.setItem(PREV_MONTH_EXPENSE_KEY, String(monthExpense));
}
