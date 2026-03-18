# Cash Flow Mobile App

React Native (Expo) mobile app for the MD Office Support Cash Flow tracking system.

## Prerequisites

- [Node.js](https://nodejs.org/) 18+
- [Expo CLI](https://docs.expo.dev/get-started/installation/): `npm install -g expo-cli`
- [Expo Go](https://expo.dev/client) app on your phone (for quick testing)

## Setup

```bash
cd mobile
npm install
```

## Run

```bash
npx expo start
```

Scan the QR code with **Expo Go** on your phone to open the app.

## Configuration

The API base URL is in `src/api/config.js`:

```js
export const API_BASE = 'https://cashflow.md-officesupport.com/api';
```

Change this to `http://YOUR_LOCAL_IP/sales/api` for testing against your local PHP server (make sure your phone and PC are on the same Wi-Fi network).

## Project Structure

```
mobile/
├── App.js                        # Root component (navigation setup)
├── app.json                      # Expo config (app name, icons, etc.)
├── package.json                  # Dependencies
└── src/
    ├── api/
    │   ├── config.js             # API base URL constant
    │   └── client.js             # All API functions (login, transactions, etc.)
    ├── context/
    │   └── AuthContext.js        # Auth state (user, company) available app-wide
    └── screens/
        ├── LoginScreen.js        # Sign in form
        ├── DashboardScreen.js    # Monthly summary + recent transactions
        ├── TransactionsScreen.js # Full paginated/filterable transaction list
        ├── AddTransactionScreen.js  # Create income or expense transaction
        └── ProfileScreen.js      # User info + company switcher + sign out
```

## Screens

| Screen | Description |
|--------|-------------|
| **Login** | Username + password auth against `/api/login.php` |
| **Dashboard** | Month income/expense cards, all-time balance, last 5 transactions |
| **Transactions** | Scrollable list with type filter (All/Income/Expense) and search |
| **Add Transaction** | Form with type, amount, date, category, payment method |
| **Profile** | View user info, switch active company, sign out |

## Authentication

The app uses PHP cookie-based sessions. The `PHPSESSID` cookie returned on login is stored in `AsyncStorage` and sent as a `Cookie` header on every subsequent API request.

## Build

To create a production build:

```bash
npx expo build:android    # APK / AAB
npx expo build:ios        # iOS (requires Apple Developer account)
```

Or using EAS Build:

```bash
npm install -g eas-cli
eas build --platform android
```
