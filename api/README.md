# Cash Flow API Documentation

## Authentication
Base URL: `/sales/api/`

### 1. Login
**POST** `/login.php`
- Body: `{ "username": "...", "password": "..." }`
- Response: User details + Session Cookie (PHPSESSID)

### 2. Dashboard
**GET** `/dashboard.php`
- Headers: `Cookie: PHPSESSID=...`
- Response: Financial summary, recent transactions.

### 3. Transactions
**GET** `/transactions.php?page=1&type=income`
- Params: `page`, `limit`, `type`, `start_date`, `end_date`

**POST** `/transactions.php`
- Body: 
  ```json
  {
    "type": "income",
    "amount": 100.50,
    "transaction_date": "2023-10-27",
    "category": "Sales",
    "description": "Consulting"
  }
  ```

### 4. Companies
**GET** `/companies.php`
- List all available companies.

**POST** `/companies.php`
- Body: `{ "company_id": 123 }`
- Switch active company context.
