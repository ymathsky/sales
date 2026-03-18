<?php
/**
 * Receipt - Thermal Printer Receipt
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/POSSale.php';

requireLogin();

$companyId = getCurrentCompanyId();
$saleId = $_GET['sale_id'] ?? 0;

if (!$saleId || !$companyId) {
    header('Location: index.php');
    exit;
}

requireCompanyAccess($companyId);

$company = Company::getById($companyId);
$sale = POSSale::getById($saleId, $companyId);

if (!$sale) {
    die('Sale not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $sale['sale_number'] ?></title>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
                width: 58mm;
            }
            @page {
                size: 58mm auto;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .receipt {
                margin: 0;
                box-shadow: none;
                width: 100%;
                padding: 2mm 0;
            }
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-weight: 500;
            background: #f3f4f6;
            color: #000;
            padding: 20px;
            margin: 0;
        }
        
        .receipt {
            width: 58mm;
            max-width: 100%;
            background: white;
            margin: 0 auto;
            padding: 2mm;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 3mm;
            border-bottom: 2px solid #000;
            padding-bottom: 3mm;
        }
        
        .company-name {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 1mm;
            word-wrap: break-word;
            line-height: 1.2;
        }
        
        .receipt-title {
            font-size: 13px;
            font-weight: 700;
            margin: 2mm 0;
            text-transform: uppercase;
        }
        
        .receipt-info {
            font-size: 11px;
            margin-bottom: 3mm;
            font-weight: 600;
        }
        
        .receipt-info div {
            margin-bottom: 1mm;
        }
        
        .items-table {
            width: 100%;
            margin: 3mm 0;
            font-size: 11px;
            font-weight: 500;
        }
        
        .items-table td {
            padding: 2mm 0;
        }
        
        .item-name {
            font-weight: bold;
        }
        
        .item-qty-price {
            display: flex;
            justify-content: space-between;
        }
        
        .separator {
            border-bottom: 2px solid #000;
            margin: 2mm 0;
        }
        
        .totals {
            font-size: 11px;
            font-weight: 600;
            margin: 3mm 0;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin: 1mm 0;
        }
        
        .totals-row.grand-total {
            font-size: 15px;
            font-weight: 800;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 2mm 0;
            margin: 3mm 0;
        }
        
        .payment-info {
            font-size: 11px;
            font-weight: 600;
            margin: 3mm 0;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 5mm;
            padding-top: 3mm;
            border-top: 1px dashed #000;
            font-size: 10px;
        }
        
        .thank-you {
            font-weight: bold;
            font-size: 12px;
            margin: 3mm 0;
        }
        
        .actions {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            padding: 12px 24px;
            margin: 0 5px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-new {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Receipt</button>
        <a href="index.php?company=<?= $companyId ?>" target="_blank" class="btn btn-new">➕ New Sale</a>
        <a href="sales.php?company=<?= $companyId ?>" target="_blank" class="btn btn-secondary">📊 View Sales</a>
    </div>
    
    <div class="receipt">
        <div class="receipt-header">
            <div class="company-name"><?= strtoupper(htmlspecialchars($company['name'])) ?></div>
            <div style="font-size: 11px; margin-top: 2mm;">
                <?= htmlspecialchars($company['address'] ?? '') ?><br>
                <?= htmlspecialchars($company['phone'] ?? '') ?>
            </div>
            <div class="receipt-title">SALES RECEIPT</div>
        </div>
        
        <div class="receipt-info">
            <div>Receipt #: <?= htmlspecialchars($sale['sale_number']) ?></div>
            <div>Date: <?= date('M d, Y h:i A', strtotime($sale['sale_date'])) ?></div>
            <?php if ($sale['customer_name']): ?>
                <div>Customer: <?= htmlspecialchars($sale['customer_name']) ?></div>
            <?php endif; ?>
            <div>Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'N/A') ?></div>
        </div>
        
        <div class="separator"></div>
        
        <div class="items-table">
            <?php foreach ($sale['items'] as $item): ?>
                <div style="margin-bottom: 3mm;">
                    <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="item-qty-price">
                        <span><?= number_format($item['quantity'], 2) ?> x ₱<?= number_format($item['unit_price'], 2) ?></span>
                        <span>₱<?= number_format($item['line_total'], 2) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="separator"></div>
        
        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>₱<?= number_format($sale['subtotal'], 2) ?></span>
            </div>
            
            <?php if ($sale['discount_amount'] > 0): ?>
                <div class="totals-row">
                    <span>Discount:</span>
                    <span>-₱<?= number_format($sale['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($sale['tax_amount'] > 0): ?>
                <div class="totals-row">
                    <span>Tax:</span>
                    <span>₱<?= number_format($sale['tax_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="totals-row grand-total">
                <span>TOTAL:</span>
                <span>₱<?= number_format($sale['total_amount'], 2) ?></span>
            </div>
        </div>
        
        <div class="payment-info">
            <div class="totals-row">
                <span>Payment Method:</span>
                <span><?= strtoupper($sale['payment_method']) ?></span>
            </div>
            <div class="totals-row">
                <span>Amount Received:</span>
                <span>₱<?= number_format($sale['payment_received'], 2) ?></span>
            </div>
            <div class="totals-row">
                <span>Change:</span>
                <span>₱<?= number_format($sale['change_amount'], 2) ?></span>
            </div>
        </div>
        
        <div class="receipt-footer">
            <div class="thank-you">THANK YOU!</div>
            <div>Please come again</div>
            <div style="margin-top: 5mm; font-size: 10px;">
                This serves as your official receipt.<br>
                For inquiries, please contact us.
            </div>
        </div>
    </div>
    
    <script src="<?= WEB_ROOT ?>/assets/js/notifications.js"></script>
    <script>
        // Show success notification
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('✓ Sale completed successfully! Receipt #<?= htmlspecialchars($sale['sale_number']) ?>', 'success', 5000);
        });
        
        // Auto-print option (uncomment if you want automatic printing)
        // window.onload = function() {
        //     setTimeout(() => window.print(), 500);
        // }
    </script>
</body>
</html>
