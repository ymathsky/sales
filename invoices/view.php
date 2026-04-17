<?php
/**
 * View Invoice
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'View Invoice';

$invoiceId = (int)($_GET['id'] ?? 0);
$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());

requireCompanyAccess($companyId);

$invoice = Invoice::getById($invoiceId, $companyId);

if (!$invoice) {
    die('Invoice not found or access denied.');
}

$company = Company::getById($companyId);
$customer = Customer::getById($invoice['customer_id'], $companyId);
$items = Invoice::getItems($invoiceId);

$created = isset($_GET['created']);
$errors = [];
$success = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_sent') {
        if (Invoice::updateStatus($invoiceId, $companyId, 'sent')) {
            $success = 'Invoice marked as sent.';
            $invoice['status'] = 'sent';
        } else {
            $errors[] = 'Failed to update invoice status.';
        }
    } elseif ($action === 'mark_paid') {
        if (Invoice::updateStatus($invoiceId, $companyId, 'paid')) {
            $success = 'Invoice marked as paid.';
            $invoice['status'] = 'paid';
            $invoice['amount_paid'] = $invoice['total_amount'];
            $invoice['amount_due'] = 0;
        } else {
            $errors[] = 'Failed to update invoice status.';
        }
    } elseif ($action === 'record_payment') {
        $amount = floatval($_POST['payment_amount'] ?? 0);
        $paymentDate = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $paymentMethod = trim($_POST['payment_method'] ?? 'Bank Transfer');
        $notes = trim($_POST['payment_notes'] ?? '');
        
        if ($amount <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
        } elseif ($amount > $invoice['amount_due']) {
            $errors[] = 'Payment amount cannot exceed balance due.';
        } else {
            if (Invoice::recordPayment($invoiceId, $companyId, $amount)) {
                $success = 'Payment recorded successfully.';
                // Refresh invoice
                $invoice = Invoice::getById($invoiceId, $companyId);
            } else {
                $errors[] = 'Failed to record payment.';
            }
        }
    } elseif ($action === 'cancel') {
        if (Invoice::updateStatus($invoiceId, $companyId, 'cancelled')) {
            $success = 'Invoice cancelled.';
            $invoice['status'] = 'cancelled';
        } else {
            $errors[] = 'Failed to cancel invoice.';
        }
    }
}

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <h1>
        <svg style="width: 28px; height: 28px; vertical-align: middle; margin-right: 10px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        Invoice <?= htmlspecialchars($invoice['invoice_number']) ?>
    </h1>
    <div>
        <button onclick="window.print()" class="btn btn-secondary">
            <svg style="width: 18px; height: 18px; vertical-align: middle; margin-right: 5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Print
        </button>
        <a href="<?= WEB_ROOT ?>/invoices/list.php?company=<?= $companyId ?>" class="btn btn-primary">← Back to Invoices</a>
    </div>
</div>

<?php if ($created): ?>
    <div class="alert alert-success">
        Invoice created successfully! Invoice number: <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger no-print">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success no-print">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- Status Badge -->
<div class="no-print" style="margin-bottom: 20px;">
    <?php
    $badges = [
        'draft' => 'secondary',
        'sent' => 'info',
        'partial' => 'warning',
        'paid' => 'success',
        'overdue' => 'danger',
        'cancelled' => 'secondary'
    ];
    $badgeClass = $badges[$invoice['status']] ?? 'secondary';
    ?>
    <span class="badge badge-<?= $badgeClass ?>" style="font-size: 1.2em; padding: 8px 15px;">
        Status: <?= ucfirst($invoice['status']) ?>
    </span>
    
    <?php if ($invoice['status'] == 'overdue'): ?>
        <span style="color: var(--danger-color); margin-left: 15px; font-weight: 600;">
            <?php
            $daysOverdue = (strtotime(date('Y-m-d')) - strtotime($invoice['due_date'])) / 86400;
            echo floor($daysOverdue) . ' days overdue';
            ?>
        </span>
    <?php endif; ?>
</div>

<!-- Invoice Display -->
<div class="form-card" id="invoiceDocument" style="max-width: 900px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 40px;">
        <div>
            <h2 style="margin: 0 0 10px 0; color: var(--primary-color);">INVOICE</h2>
            <div style="font-size: 1.1em; font-weight: 600;"><?= htmlspecialchars($company['name']) ?></div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.3em; font-weight: 700; margin-bottom: 5px;">
                <?= htmlspecialchars($invoice['invoice_number']) ?>
            </div>
            <div>Date: <?= formatDate($invoice['invoice_date'], 'F d, Y') ?></div>
            <div>Due Date: <?= formatDate($invoice['due_date'], 'F d, Y') ?></div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">
        <div>
            <h4 style="margin-bottom: 10px; color: #666;">Bill To:</h4>
            <div style="font-weight: 600; font-size: 1.1em; margin-bottom: 5px;">
                <?= htmlspecialchars($customer['customer_name']) ?>
            </div>
            <?php if ($customer['email']): ?>
                <div><?= htmlspecialchars($customer['email']) ?></div>
            <?php endif; ?>
            <?php if ($customer['phone']): ?>
                <div><?= htmlspecialchars($customer['phone']) ?></div>
            <?php endif; ?>
            <?php if ($customer['address']): ?>
                <div style="margin-top: 10px; white-space: pre-line;"><?= htmlspecialchars($customer['address']) ?></div>
            <?php endif; ?>
        </div>
        <div>
            <h4 style="margin-bottom: 10px; color: #666;">Payment Terms:</h4>
            <div>Net <?= $customer['payment_terms'] ?> days</div>
            <?php if ($customer['tax_id']): ?>
                <div style="margin-top: 10px;">Tax ID: <?= htmlspecialchars($customer['tax_id']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Line Items -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
            <tr style="background: #f5f5f5; border-top: 2px solid #333; border-bottom: 2px solid #333;">
                <th style="padding: 12px; text-align: left;">Description</th>
                <th style="padding: 12px; text-align: center; width: 100px;">Quantity</th>
                <th style="padding: 12px; text-align: right; width: 150px;">Unit Price</th>
                <th style="padding: 12px; text-align: right; width: 150px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr style="border-bottom: 1px solid #ddd;<?= !empty($item['is_paid']) ? 'background:#f0fdf4;' : '' ?>">
                    <td style="padding: 12px;">
                        <?= nl2br(htmlspecialchars($item['description'])) ?>
                        <?php if (!empty($item['is_paid'])): ?>
                            <span style="display:inline-block;margin-left:8px;background:#dcfce7;color:#16a34a;font-size:.72rem;font-weight:700;border-radius:99px;padding:1px 8px;vertical-align:middle;">✓ PAID</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px; text-align: center;"><?= number_format($item['quantity'], 2) ?></td>
                    <td style="padding: 12px; text-align: right;"><?= formatMoney($item['unit_price']) ?></td>
                    <td style="padding: 12px; text-align: right; font-weight: 600;">
                        <?= formatMoney($item['amount']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Totals -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
        <div style="min-width: 350px;">
            <div style="display: flex; justify-content: space-between; padding: 10px 0; font-size: 1.1em;">
                <span>Subtotal:</span>
                <span><?= formatMoney($invoice['subtotal']) ?></span>
            </div>
            <?php if ($invoice['tax_amount'] > 0): ?>
            <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                <span>Tax:</span>
                <span><?= formatMoney($invoice['tax_amount']) ?></span>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; padding: 15px 0; border-top: 2px solid #333; font-size: 1.4em; font-weight: 700;">
                <span>Total:</span>
                <span style="color: var(--primary-color);"><?= formatMoney($invoice['total_amount']) ?></span>
            </div>
            
            <?php if ($invoice['amount_paid'] > 0): ?>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; color: var(--success-color); font-size: 1.1em;">
                    <span>Amount Paid:</span>
                    <span><?= formatMoney($invoice['amount_paid']) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($invoice['amount_due'] > 0): ?>
                <div style="display: flex; justify-content: space-between; padding: 15px 0; border-top: 2px solid #333; font-size: 1.4em; font-weight: 700;">
                    <span>Balance Due:</span>
                    <span style="color: var(--danger-color);"><?= formatMoney($invoice['amount_due']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Terms & Notes -->
    <?php if (!empty($invoice['terms'])): ?>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4 style="margin-bottom: 10px;">Terms & Conditions</h4>
            <div style="white-space: pre-line; color: #666;">
                <?= nl2br(htmlspecialchars($invoice['terms'])) ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($invoice['notes']): ?>
        <div class="no-print" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid var(--primary-color);">
            <strong>Internal Notes:</strong>
            <div style="margin-top: 5px;"><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
        </div>
    <?php endif; ?>
</div>

<!-- Actions -->
<div class="no-print" style="max-width: 900px; margin: 30px auto; display: flex; gap: 15px; flex-wrap: wrap;">
    <?php if ($invoice['status'] == 'draft'): ?>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="mark_sent">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Mark this invoice as sent?')">
                Mark as Sent
            </button>
        </form>
        <a href="<?= WEB_ROOT ?>/invoices/edit.php?id=<?= $invoiceId ?>&company=<?= $companyId ?>" class="btn btn-secondary">
            Edit Invoice
        </a>
    <?php endif; ?>
    
    <?php if (in_array($invoice['status'], ['sent', 'partial', 'overdue'])): ?>
        <button type="button" class="btn btn-success" onclick="document.getElementById('paymentModal').style.display='block'">
            Record Payment
        </button>
        
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="mark_paid">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Mark this invoice as fully paid?')">
                Mark as Paid
            </button>
        </form>
    <?php endif; ?>
    
    <?php if ($invoice['status'] != 'cancelled' && $invoice['status'] != 'paid'): ?>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Cancel this invoice? This cannot be undone.')">
                Cancel Invoice
            </button>
        </form>
        <a href="<?= WEB_ROOT ?>/invoices/move.php?id=<?= $invoiceId ?>&company=<?= $companyId ?>"
           class="btn btn-secondary"
           style="background:#f5f3ff;color:#7c3aed;border:1px solid #c4b5fd;"
           title="Move invoice to a different company">
            <svg style="width:14px;height:14px;vertical-align:middle;margin-right:5px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"/>
            </svg>
            Move to Company
        </a>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close" onclick="document.getElementById('paymentModal').style.display='none'">&times;</span>
        <h2 style="margin-bottom: 20px;">Record Payment</h2>
        
        <form method="POST">
            <input type="hidden" name="action" value="record_payment">
            
            <div class="form-group">
                <label>Payment Amount <span style="color: red;">*</span></label>
                <input type="number" name="payment_amount" class="form-control" 
                       min="0.01" step="0.01" max="<?= $invoice['amount_due'] ?>" 
                       value="<?= $invoice['amount_due'] ?>" required>
                <small style="color: #666;">Balance Due: <?= formatMoney($invoice['amount_due']) ?></small>
            </div>
            
            <div class="form-group">
                <label>Payment Date <span style="color: red;">*</span></label>
                <input type="date" name="payment_date" class="form-control" 
                       value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" class="form-control">
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer" selected>Bank Transfer</option>
                    <option value="Check">Check</option>
                    <option value="GCash">GCash</option>
                    <option value="PayMaya">PayMaya</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="payment_notes" class="form-control" rows="3" 
                          placeholder="Payment reference or notes"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" 
                        onclick="document.getElementById('paymentModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-success">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: white;
    }
    .form-card {
        box-shadow: none;
        border: none;
    }
}
</style>

<?php include __DIR__ . '/../views/footer.php'; ?>
