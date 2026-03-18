<?php
/**
 * Transaction Receipt
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Transaction.php';

requireLogin();

$companyId = getCurrentCompanyId();
$transactionId = $_GET['id'] ?? 0;

if (!$transactionId || !$companyId) {
    die('Invalid request');
}

requireCompanyAccess($companyId);

$company = Company::getById($companyId);
$transaction = Transaction::getById($transactionId, $companyId);

if (!$transaction) {
    die('Transaction not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $transaction['transaction_id'] ?></title>
    <style>
        @media print {
            body { margin: 0; padding: 0; width: 58mm; -webkit-print-color-adjust: exact; }
            @page { size: 58mm auto; margin: 0; }
            .no-print { display: none !important; }
            .receipt { margin: 0; box-shadow: none; width: 100%; padding: 0; }
        }
        
        body { 
            font-family: 'Courier New', Courier, monospace; /* Thermal receipt style font */
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
            padding: 4mm; 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); 
        }
        
        .receipt-header { 
            text-align: center; 
            margin-bottom: 3mm; 
            border-bottom: 1px dashed #000; 
            padding-bottom: 3mm; 
        }
        
        .company-name { 
            font-size: 14px; 
            font-weight: 800; 
            margin-bottom: 2mm; 
            word-wrap: break-word; 
            text-transform: uppercase;
        }
        
        .receipt-title { 
            font-size: 12px; 
            font-weight: 700; 
            margin: 2mm 0; 
            text-transform: uppercase; 
        }
        
        .receipt-info { 
            font-size: 11px; 
            margin-bottom: 3mm; 
        }
        
        .receipt-info div { 
            margin-bottom: 1mm; 
            display: flex;
            justify-content: space-between;
        }
        
        .items-table { 
            width: 100%; 
            margin: 3mm 0; 
            font-size: 11px; 
        }
        
        .separator { 
            border-bottom: 1px dashed #000; 
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
            font-size: 14px; 
            font-weight: 800; 
            border-top: 1px dashed #000; 
            border-bottom: 1px dashed #000; 
            padding: 2mm 0; 
            margin: 3mm 0; 
        }
        
        .receipt-footer { 
            text-align: center; 
            margin-top: 5mm; 
            padding-top: 3mm; 
            border-top: 1px dashed #000; 
            font-size: 10px; 
        }
        
        .actions { 
            text-align: center; 
            margin: 0 auto 20px auto; 
            width: 58mm;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .btn { 
            padding: 10px; 
            border: none; 
            border-radius: 6px; 
            font-size: 13px; 
            font-weight: 600; 
            cursor: pointer; 
            text-decoration: none; 
            display: block;
            text-align: center;
        }
        
        .btn-print { 
            background: #000; 
            color: white; 
        }
        
        .btn-secondary { 
            background: #e5e7eb; 
            color: #374151; 
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Receipt</button>
        <?php
        // Determine where to go back based on transaction type
        $backUrl = '/sales/transactions/list.php?company=' . $companyId;
        if (strpos($transaction['category'], 'Subscription') !== false || strpos($transaction['description'], 'Subscription') !== false) {
            $backUrl = '/sales/memberships/list.php?company=' . $companyId;
        }
        ?>
        <a href="<?= $backUrl ?>" class="btn btn-secondary">← Go Back</a>
    </div>
    
    <div class="receipt">
        <div class="receipt-header">
            <div class="company-name"><?= strtoupper(htmlspecialchars($company['name'])) ?></div>
             <div style="font-size: 10px; margin-top: 2mm;">
                <?= htmlspecialchars($company['address'] ?? '') ?><br>
                <?= htmlspecialchars($company['phone'] ?? '') ?>
            </div>
            <div class="receipt-title">OFFICIAL RECEIPT</div>
        </div>
        
        <div class="receipt-info">
            <div>
                <span>Ref No:</span>
                <span><?= htmlspecialchars($transaction['reference_number'] ?: '#' . $transaction['transaction_id']) ?></span>
            </div>
            <div>
                 <span>Date:</span>
                 <span><?= date('m/d/Y h:i A', strtotime($transaction['transaction_date'])) ?></span>
            </div>
            <div>
                <span>Staff:</span>
                <span><?= htmlspecialchars(explode(' ', $transaction['created_by_name'] ?? 'System')[0]) ?></span>
            </div>
        </div>
        
        <div class="separator"></div>
        
        <div class="items-table">
            <div style="margin-bottom: 2mm;">
                <div style="font-weight: bold; margin-bottom: 1mm;"><?= htmlspecialchars($transaction['description']) ?></div>
                <div style="display: flex; justify-content: space-between;">
                    <span>1.00 x <?= number_format($transaction['amount'], 2) ?></span>
                    <span><?= number_format($transaction['amount'], 2) ?></span>
                </div>
            </div>
        </div>
        
        <div class="separator"></div>
        
        <div class="totals">
             <div class="totals-row grand-total">
                <span>TOTAL AMOUNT</span>
                <span>₱<?= number_format($transaction['amount'], 2) ?></span>
            </div>
             <div class="totals-row">
                <span>Payment Type</span>
                <span><?= strtoupper($transaction['payment_method']) ?></span>
            </div>
        </div>
        
        <div class="receipt-footer">
            <div style="font-weight: bold; font-size: 12px; margin: 3mm 0;">THANK YOU!</div>
            <div>This receipt is system generated.</div>
        </div>
    </div>
</body>
</html>