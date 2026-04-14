<?php
/**
 * Move Invoice to Another Company
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$userId           = getCurrentUserId();
$currentCompanyId = (int)($_GET['company'] ?? $_POST['company'] ?? getCurrentCompanyId());
requireCompanyAccess($currentCompanyId);

$invoiceId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$invoiceId) {
    setFlashMessage('Invoice ID is required.', 'error');
    header('Location: ' . WEB_ROOT . '/invoices/list.php?company=' . $currentCompanyId);
    exit;
}

$invoice = Invoice::getById($invoiceId, $currentCompanyId);
if (!$invoice) {
    setFlashMessage('Invoice not found or access denied.', 'error');
    header('Location: ' . WEB_ROOT . '/invoices/list.php?company=' . $currentCompanyId);
    exit;
}

// Only draft/sent invoices should be movable
if (in_array($invoice['status'], ['paid', 'cancelled'])) {
    setFlashMessage('Paid or cancelled invoices cannot be moved.', 'error');
    header('Location: ' . WEB_ROOT . '/invoices/view.php?id=' . $invoiceId . '&company=' . $currentCompanyId);
    exit;
}

// Get other companies the user has access to
$allCompanies    = Company::getByUser($userId);
$targetCompanies = array_filter($allCompanies, fn($c) => $c['company_id'] != $currentCompanyId);

if (empty($targetCompanies)) {
    setFlashMessage('You do not have access to any other companies to move this invoice to.', 'error');
    header('Location: ' . WEB_ROOT . '/invoices/view.php?id=' . $invoiceId . '&company=' . $currentCompanyId);
    exit;
}

$items         = Invoice::getItems($invoiceId);
$sourceCompany = Company::getById($currentCompanyId);
$errors        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetCompanyId = (int)($_POST['target_company_id'] ?? 0);

    if (!$targetCompanyId) {
        $errors[] = 'Please select a destination company.';
    } elseif (!userHasAccessToCompany($userId, $targetCompanyId)) {
        $errors[] = 'You do not have access to the selected company.';
    } else {
        $newNumber = Invoice::moveToCompany($invoiceId, $currentCompanyId, $targetCompanyId);
        if ($newNumber) {
            $targetCompany = Company::getById($targetCompanyId);
            setFlashMessage(
                'Invoice ' . htmlspecialchars($invoice['invoice_number']) .
                ' moved to ' . htmlspecialchars($targetCompany['name']) .
                ' (new number: ' . htmlspecialchars($newNumber) . ').',
                'success'
            );
            header('Location: ' . WEB_ROOT . '/invoices/list.php?company=' . $currentCompanyId);
            exit;
        } else {
            $errors[] = 'Failed to move invoice. Please try again.';
        }
    }
}

$pageTitle = 'Move Invoice';
include __DIR__ . '/../views/header.php';
?>

<style>
.move-page { max-width: 760px; margin: 0 auto; padding: 0 4px 60px; }
.move-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 28px; padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}
.move-header-icon {
    width: 48px; height: 48px; flex-shrink: 0;
    background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(124,58,237,.3);
}
.move-header-icon svg { width: 24px; height: 24px; stroke: #fff; }
.move-header h1 { font-size: 1.4rem; font-weight: 700; color: var(--text-dark); margin: 0; }
.move-header .move-subtitle { font-size: .85rem; color: var(--text-light); margin-top: 2px; }

.move-card {
    background: #fff; border-radius: 14px;
    border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
    margin-bottom: 20px; overflow: hidden;
}
.move-card-header {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 22px; border-bottom: 1px solid var(--border-color);
    background: #fafafa;
}
.move-card-header h3 { font-size: .95rem; font-weight: 700; color: var(--text-dark); margin: 0; }
.move-card-body { padding: 20px 22px; }

/* Invoice summary table */
.inv-summary-table { width: 100%; border-collapse: collapse; font-size: .92rem; }
.inv-summary-table td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.inv-summary-table tr:last-child td { border-bottom: none; }
.inv-summary-table .lbl { color: var(--text-light); font-weight: 500; width: 140px; }
.inv-summary-table .val { font-weight: 600; color: var(--text-dark); }

/* Status badge */
.status-badge {
    display: inline-block; padding: 2px 10px; border-radius: 99px;
    font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
}
.status-draft    { background: #f3f4f6; color: #6b7280; }
.status-sent     { background: #eff6ff; color: #2563eb; }
.status-partial  { background: #fffbeb; color: #d97706; }
.status-overdue  { background: #fef2f2; color: #dc2626; }
.status-paid     { background: #f0fdf4; color: #16a34a; }
.status-cancelled{ background: #fef2f2; color: #9ca3af; }

/* Line items mini table */
.items-mini { width: 100%; border-collapse: collapse; font-size: .87rem; margin-top: 4px; }
.items-mini th { padding: 7px 10px; background: #f8fafc; font-size: .75rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: .4px; border-bottom: 2px solid #e5e7eb; }
.items-mini td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; color: var(--text-dark); }
.items-mini tr:last-child td { border-bottom: none; }
.items-mini .al { text-align: left; }
.items-mini .ac { text-align: center; }
.items-mini .ar { text-align: right; }

/* Company radio cards */
.company-list { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
.company-radio { display: none; }
.company-label {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 18px; border-radius: 10px;
    border: 2px solid #e5e7eb; cursor: pointer;
    transition: border-color .2s, background .2s;
}
.company-radio:checked + .company-label {
    border-color: #7c3aed; background: #faf5ff;
}
.company-label:hover { border-color: #c4b5fd; background: #faf5ff; }
.company-dot {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid #d1d5db; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: border-color .2s;
}
.company-radio:checked + .company-label .company-dot {
    border-color: #7c3aed; background: #7c3aed;
}
.company-radio:checked + .company-label .company-dot::after {
    content: ''; width: 7px; height: 7px; border-radius: 50%; background: #fff; display: block;
}
.company-name { font-weight: 700; color: var(--text-dark); font-size: .95rem; }
.company-meta { font-size: .8rem; color: var(--text-light); margin-top: 1px; }

/* Warning box */
.move-warning {
    display: flex; gap: 12px; padding: 14px 18px;
    background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px;
    color: #92400e; margin-bottom: 20px; font-size: .9rem;
}
.move-warning svg { flex-shrink: 0; margin-top: 1px; }

/* Errors */
.move-alert { display: flex; gap: 12px; padding: 14px 18px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; color: #dc2626; margin-bottom: 20px; }
.move-alert ul { margin: 4px 0 0 16px; padding: 0; }

/* Actions */
.move-actions { display: flex; gap: 12px; justify-content: flex-end; padding-top: 8px; }
.mv-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border-radius: 9px; font-size: .95rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .2s; }
.mv-btn-ghost { background: #f3f4f6; color: var(--text-dark); border: 1.5px solid #e5e7eb; }
.mv-btn-ghost:hover { background: #e5e7eb; }
.mv-btn-primary { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); color: #fff; box-shadow: 0 4px 14px rgba(124,58,237,.35); }
.mv-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(124,58,237,.45); }
</style>

<div class="content-area">
<div class="move-page">

    <!-- Header -->
    <div class="move-header">
        <div class="move-header-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
            </svg>
        </div>
        <div>
            <h1>Move Invoice</h1>
            <div class="move-subtitle">Transfer invoice to a different company</div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="move-alert">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <strong>Please fix the following:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="move-warning">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.293 3.716a2 2 0 013.414 0l7.071 12.247A2 2 0 0119.07 19H4.93a2 2 0 01-1.707-2.963L10.293 3.716z"/></svg>
        <div>
            <strong>Note:</strong> Moving an invoice reassigns it to the selected company and generates a new invoice number for that company. The customer on the invoice must also exist in the destination company.
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="id" value="<?= $invoiceId ?>">
        <input type="hidden" name="company" value="<?= $currentCompanyId ?>">

        <!-- Invoice Summary -->
        <div class="move-card">
            <div class="move-card-header">
                <svg width="16" height="16" fill="none" stroke="#6b7280" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <h3>Invoice Being Moved</h3>
            </div>
            <div class="move-card-body">
                <table class="inv-summary-table">
                    <tr>
                        <td class="lbl">Invoice #</td>
                        <td class="val"><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Customer</td>
                        <td class="val"><?= htmlspecialchars($invoice['customer_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Invoice Date</td>
                        <td class="val"><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Due Date</td>
                        <td class="val"><?= date('M d, Y', strtotime($invoice['due_date'])) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Status</td>
                        <td class="val">
                            <span class="status-badge status-<?= $invoice['status'] ?>"><?= ucfirst($invoice['status']) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Total</td>
                        <td class="val" style="color:#2563eb;">₱<?= number_format($invoice['total_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Current Company</td>
                        <td class="val"><?= htmlspecialchars($sourceCompany['name']) ?></td>
                    </tr>
                </table>

                <?php if (!empty($items)): ?>
                <div style="margin-top: 16px;">
                    <div style="font-size:.8rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">Line Items</div>
                    <table class="items-mini">
                        <thead>
                            <tr>
                                <th class="al">Description</th>
                                <th class="ac">Qty</th>
                                <th class="ar">Unit Price</th>
                                <th class="ar">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="al"><?= htmlspecialchars($item['description']) ?></td>
                                <td class="ac"><?= htmlspecialchars($item['quantity'] + 0) ?></td>
                                <td class="ar">₱<?= number_format($item['unit_price'], 2) ?></td>
                                <td class="ar">₱<?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Destination Company -->
        <div class="move-card">
            <div class="move-card-header">
                <svg width="16" height="16" fill="none" stroke="#6b7280" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <h3>Select Destination Company</h3>
            </div>
            <div class="move-card-body">
                <div class="company-list">
                    <?php foreach ($targetCompanies as $company): ?>
                    <input type="radio" name="target_company_id"
                           id="company_<?= $company['company_id'] ?>"
                           value="<?= $company['company_id'] ?>"
                           class="company-radio"
                           <?= count($targetCompanies) === 1 ? 'checked' : '' ?>>
                    <label for="company_<?= $company['company_id'] ?>" class="company-label">
                        <span class="company-dot"></span>
                        <div>
                            <div class="company-name"><?= htmlspecialchars($company['name']) ?></div>
                            <?php if (!empty($company['address'])): ?>
                            <div class="company-meta"><?= htmlspecialchars($company['address']) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="move-actions">
            <a href="<?= WEB_ROOT ?>/invoices/view.php?id=<?= $invoiceId ?>&company=<?= $currentCompanyId ?>" class="mv-btn mv-btn-ghost">
                Cancel
            </a>
            <button type="submit" class="mv-btn mv-btn-primary"
                    onclick="return confirm('Move invoice <?= htmlspecialchars($invoice['invoice_number']) ?> to the selected company? This action cannot be undone.')">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"/>
                </svg>
                Move Invoice
            </button>
        </div>

    </form>

</div><!-- /move-page -->
</div><!-- /content-area -->

<?php include __DIR__ . '/../views/footer.php'; ?>
