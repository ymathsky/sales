# Multi-Company Cash Flow Tracking System

A comprehensive PHP-based application for tracking cash in/out transactions across multiple companies.

## Features

- **Multi-Company Support**: Manage transactions for multiple companies with proper data isolation
- **Cash Flow Tracking**: Record and monitor all cash in/out transactions
- **Financial Reports**: Generate summaries and reports by date range and category
- **User Access Control**: Role-based access with company-level permissions
- **Transaction Management**: Create, edit, and delete transactions with full audit trail
- **Category Organization**: Organize transactions by custom categories
- **Responsive Design**: Mobile-friendly interface

## Installation

### Prerequisites
- XAMPP (Apache + MySQL) installed on Windows
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Setup Steps

1. **Clone or copy** the project to XAMPP htdocs:
   ```
   c:\xampp\htdocs\sales\
   ```

2. **Create the database**:
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Import or execute the SQL schema from `database/schema.sql`

3. **Configure database connection**:
   - Edit `config/database.php` if needed (default XAMPP settings should work)
   - Default credentials: `root` / empty password

4. **Start XAMPP**:
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

5. **Access the application**:
   - Open browser: `http://localhost/sales/`
   - Default login: `admin` / `admin123`

## Project Structure

```
sales/
├── .github/
│   └── copilot-instructions.md  # AI coding guidelines
├── assets/
│   ├── css/
│   │   └── style.css            # Main stylesheet
│   └── js/
│       └── main.js              # JavaScript functionality
├── auth/
│   ├── login.php                # Login page
│   └── logout.php               # Logout handler
├── config/
│   └── database.php             # Database configuration
├── database/
│   └── schema.sql               # Database schema and sample data
├── includes/
│   ├── session.php              # Session management
│   └── functions.php            # Helper functions
├── models/
│   ├── Company.php              # Company model
│   ├── Transaction.php          # Transaction model
│   └── User.php                 # User model
├── reports/
│   └── index.php                # Financial reports
├── transactions/
│   ├── create.php               # Add transaction
│   ├── edit.php                 # Edit/delete transaction
│   └── list.php                 # Transaction list
├── views/
│   ├── header.php               # Page header
│   └── footer.php               # Page footer
├── index.php                    # Dashboard (main page)
└── README.md                    # This file
```

## Usage

### Managing Companies

1. Companies are created via database initially
2. Users are granted access to companies through the `user_companies` table
3. Switch between companies using the dropdown on the dashboard

### Adding Transactions

1. Click "Add Transaction" button
2. Select transaction type (Cash In or Cash Out)
3. Enter amount, date, category, and description
4. Submit to record the transaction

### Viewing Reports

1. Navigate to Reports section
2. Select date range
3. View summary by category and transaction type
4. Print or export reports as needed

### User Management

- **Admin**: Full access to all companies and features
- **Manager**: Write access to assigned companies
- **User**: Read-only access to assigned companies

## Security Features

- Password hashing with bcrypt
- SQL injection prevention with prepared statements
- Company-level data isolation
- Session-based authentication
- Input sanitization and validation

## Customization

### Adding New Categories

Categories are created automatically when used in transactions, or add them via:
```sql
INSERT INTO transaction_categories (company_id, name, type) 
VALUES (1, 'New Category', 'both');
```

### Changing Colors/Theme

Edit `assets/css/style.css` and modify CSS variables at the top:
```css
:root {
    --primary-color: #2563eb;
    --success-color: #10b981;
    --danger-color: #ef4444;
    /* ... */
}
```

## Database Backup

Regular backups recommended. Use phpMyAdmin:
1. Select `sales_cash_flow` database
2. Click Export tab
3. Choose SQL format
4. Save file

## Troubleshooting

### Cannot connect to database
- Verify MySQL is running in XAMPP Control Panel
- Check credentials in `config/database.php`
- Ensure database `sales_cash_flow` exists

### Login not working
- Verify user exists in database
- Check default credentials: `admin` / `admin123`
- Clear browser cache and cookies

### Transactions not appearing
- Verify company is selected
- Check date range filters
- Confirm user has access to selected company

## License

This project is open source and available for personal and commercial use.

## Support

For issues or questions, refer to `.github/copilot-instructions.md` for AI-assisted development guidance.
