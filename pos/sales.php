<?php
/**
 * Sales History - View POS Sales
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/POSSale.php';

requireLogin();

$companyId = getCurrentCompanyId();

if (!$companyId) {
    header('Location: ../index.php');
    exit;
}

requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

// Build filters
$filters = [
    'start_date' => $startDate,
    'end_date' => $endDate
];

if ($search) {
    $filters['search'] = $search;
}

if ($paymentMethod) {
    $filters['payment_method'] = $paymentMethod;
}

// Get sales
$sales = POSSale::getByCompany($companyId, $filters);

// Calculate totals
$totalSales = 0;
$totalAmount = 0;

foreach ($sales as $sale) {
    $totalSales++;
    $totalAmount += $sale['total_amount'];
}

$pageTitle = 'Sales History';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($company['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header-subtitle {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
        }
        
        .btn-primary:hover {
            background: #f3f4f6;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .summary-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #374151;
        }
        
        .form-control {
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-filter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-reset {
            background: #6b7280;
            color: white;
        }
        
        .sales-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 12px 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            border-bottom: 2px solid #e5e7eb;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        tbody tr:hover {
            background: #f9fafb;
        }
        
        .sale-number {
            font-weight: 600;
            color: #667eea;
        }
        
        .payment-method {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .payment-cash {
            background: #d1fae5;
            color: #065f46;
        }
        
        .payment-card {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .payment-bank {
            background: #fce7f3;
            color: #9f1239;
        }
        
        .amount {
            font-weight: 600;
            color: #059669;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-view {
            background: #667eea;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 14px;
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <h1>📊 Sales History</h1>
                <div class="header-subtitle"><?= htmlspecialchars($company['name']) ?></div>
            </div>
            <div class="header-actions">
                <a href="index.php?company=<?= $companyId ?>" target="_blank" class="btn btn-primary">
                    ➕ New Sale
                </a>
                <a href="products.php?company=<?= $companyId ?>" target="_blank" class="btn btn-secondary">
                    📦 Products
                </a>
                <a href="../index.php?company=<?= $companyId ?>" class="btn btn-secondary">
                    🏠 Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($flash = getFlashMessage()): ?>
            <div class="alert alert-<?= $flash['type'] ?>">
                <?= htmlspecialchars($flash['text']) ?>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-label">Total Sales</div>
                <div class="summary-value"><?= number_format($totalSales) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Amount</div>
                <div class="summary-value">₱<?= number_format($totalAmount, 2) ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Average Sale</div>
                <div class="summary-value">
                    ₱<?= $totalSales > 0 ? number_format($totalAmount / $totalSales, 2) : '0.00' ?>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Date Range</div>
                <div class="summary-value" style="font-size: 16px;">
                    <?= date('M d', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET">
                <input type="hidden" name="company" value="<?= $companyId ?>">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Sale number or customer..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-control">
                            <option value="">All Methods</option>
                            <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Card</option>
                            <option value="bank_transfer" <?= $paymentMethod === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-filter">🔍 Apply Filters</button>
                    <a href="sales.php?company=<?= $companyId ?>" class="btn btn-reset">🔄 Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Sales Table -->
        <div class="sales-table">
            <?php if (empty($sales)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🧾</div>
                    <h3>No Sales Found</h3>
                    <p>No sales match your filter criteria.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Sale Number</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Payment</th>
                            <th>Total</th>
                            <th>Cashier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td>
                                    <div class="sale-number"><?= htmlspecialchars($sale['sale_number']) ?></div>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($sale['sale_date'])) ?><br>
                                    <small style="color: #6b7280;"><?= date('h:i A', strtotime($sale['sale_date'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></td>
                                <td><?= $sale['item_count'] ?? 0 ?> item(s)</td>
                                <td>
                                    <span class="payment-method payment-<?= $sale['payment_method'] ?>">
                                        <?= strtoupper($sale['payment_method']) ?>
                                    </span>
                                </td>
                                <td class="amount">₱<?= number_format($sale['total_amount'], 2) ?></td>
                                <td>
                                    <small><?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></small>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="receipt.php?sale_id=<?= $sale['sale_id'] ?>&company=<?= $companyId ?>" 
                                           target="_blank" class="btn btn-small btn-view">
                                            👁️ View
                                        </a>
                                        <?php if (hasPermission('sale_delete')): ?>
                                            <a href="delete-sale.php?id=<?= $sale['sale_id'] ?>&company=<?= $companyId ?>"
                                               class="btn btn-small" style="background: #ef4444; color: white;"
                                               onclick="return confirm('Are you sure you want to delete this sale? This will restore inventory quantities.');">
                                                🗑️ Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
