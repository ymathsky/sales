# AI Coding Agent Instructions - Sales Project

## Project Overview
PHP-based multi-company cash flow tracking system.
- **Architecture**: Page Controller pattern (scripts in functional directories e.g., `transactions/`, `companies/`).
- **Data Access**: Active Record-style models in `models/`.
- **Database**: MySQL via PDO.
- **Multi-tenancy**: Strict logical separation by `company_id`.

## Architecture & Core Patterns

### 1. Multi-Tenancy (CRITICAL)
- **Manual Isolation**: Every database query MUST filter by `company_id`.
- **Session State**: `$_SESSION['company_id']` holds the active context.
- **Access Control**: Always call `requireCompanyAccess($companyId)` at the top of pages.
- **Model Pattern**: Model methods usually require `$companyId` as the first argument.
  ```php
  // CORRECT
  public static function getByCompany($companyId, $limit) { ... WHERE company_id = ? ... }
  
  // INCORRECT - Never query without company_id
  public static function getAll() { ... }
  ```

### 2. Page Structure (Controller/View Hybrid)
Pages (e.g., `transactions/list.php`) function as both controller and view:
1.  **Imports**: Session config, functions, models.
2.  **Auth Checks**: `requireLogin()`, `requireCompanyAccess()`.
3.  **Logic**: Fetch data via Models.
4.  **View Rendering**: Include `views/header.php`, render HTML, include `views/footer.php`.

### 3. Database Access
- Use `getDBConnection()` from `config/database.php`.
- **Always** use prepared statements (`$pdo->prepare()`).
- **Money**: Store as `DECIMAL(10,2)`, display using `formatMoney($amount)`.

## File Organization
- `/models/` - Data Classes (Transaction, Company, User). Contains all SQL.
- `/includes/` - Core logic (`session.php` for auth, `functions.php` for helpers).
- `/views/` - Shared layouts (`header.php`, `footer.php`).
- `/assets/` - CSS (`style.css`) and JS.
- `/config/` - Database credentials.
- `/[module]/` - Functional modules (e.g., `transactions/`, `companies/`) containing:
    - `create.php`, `edit.php`, `list.php`, `delete.php`.

## Developer Workflow

### Creating a New Feature
1.  **Model**: Add data methods in `models/[Entity].php`.
2.  **Page**: Create `[entity]/create.php` or `list.php`.
3.  **Auth**: Add `requireLogin()` and `requireCompanyAccess($companyId)`.
4.  **UI**: Use Bootstrap classes (implied by class names) and include shared header/footer.

### Key Helper Functions
- `formatMoney($amount)` - Standard currency formatting.
- `formatDate($date)` - Standard date formatting.
- `sanitizeInput($string)` - Basic input sanitization.
- `getCurrentUserId()` - Safe session access.
- `userHasAccessToCompany($userId, $companyId)` - Permission check.

## Common Code Snippets

### Standard Page Header
```php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../models/Transaction.php';
requireLogin();

$companyId = getCurrentCompanyId();
requireCompanyAccess($companyId); // Security fence

include __DIR__ . '/../views/header.php';
```

### Standard Model Query
```php
public static function getActive($companyId) { // Always pass companyId
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM table WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$companyId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

## Security Rules
- **XSS**: Use `htmlspecialchars()` or `sanitizeInput()` on output.
- **SQLi**: NEVER inject variables into SQL strings. Use PDO placeholders `?`.
- **Auth**: Never assume user is logged in; always verify session.
