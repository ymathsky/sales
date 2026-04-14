<?php
/**
 * Edit Invoice
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Edit Invoice';

$invoiceId = (int)($_GET['id'] ?? 0);
$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());

requireCompanyAccess($companyId);

$invoice = Invoice::getById($invoiceId, $companyId);
if (!$invoice) {
    die('Invoice not found or access denied.');
}

$company   = Company::getById($companyId);
$customers = Customer::getByCompany($companyId, false); // include inactive so existing customer always appears
$items     = Invoice::getItems($invoiceId);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId   = (int)($_POST['customer_id'] ?? 0);
    $invoiceDate  = trim($_POST['invoice_date'] ?? '');
    $dueDate      = trim($_POST['due_date'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    $termsConditions = trim($_POST['terms_conditions'] ?? '');

    // Line items
    $itemDescriptions = $_POST['item_description'] ?? [];
    $itemQuantities   = $_POST['item_quantity']    ?? [];
    $itemUnitPrices   = $_POST['item_unit_price']  ?? [];

    // Validation
    if (!$customerId)  { $errors[] = 'Please select a customer.'; }
    if (!$invoiceDate) { $errors[] = 'Invoice date is required.'; }
    if (!$dueDate)     { $errors[] = 'Due date is required.'; }

    $newItems = [];
    foreach ($itemDescriptions as $index => $description) {
        $description = trim($description);
        $quantity    = floatval($itemQuantities[$index] ?? 0);
        $unitPrice   = floatval($itemUnitPrices[$index]  ?? 0);
        if ($description && $quantity > 0 && $unitPrice > 0) {
            $newItems[] = [
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
            ];
        }
    }

    if (empty($newItems)) {
        $errors[] = 'Please add at least one line item.';
    }

    if (empty($errors)) {
        $data = [
            'customer_id'  => $customerId,
            'invoice_date' => $invoiceDate,
            'due_date'     => $dueDate,
            'notes'        => $notes,
            'terms'        => $termsConditions,
        ];

        Invoice::update($invoiceId, $companyId, $data, $newItems);
        header('Location: ' . WEB_ROOT . '/invoices/view.php?id=' . $invoiceId . '&company=' . $companyId . '&updated=1');
        exit;
    }

    // On error, keep posted values for re-display
    $items = [];
    foreach ($itemDescriptions as $index => $description) {
        $items[] = [
            'description' => $description,
            'quantity'    => $itemQuantities[$index] ?? '',
            'unit_price'  => $itemUnitPrices[$index]  ?? '',
        ];
    }
    if (empty($items)) {
        $items = Invoice::getItems($invoiceId);
    }
}

include __DIR__ . '/../views/header.php';
?>

<style>
.inv-page { max-width: 1200px; margin: 0 auto; padding: 0 4px 60px; }
.inv-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px; padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}
.inv-header-left { display: flex; align-items: center; gap: 14px; }
.inv-header-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    border-radius: 12px; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(217,119,6,.3);
}
.inv-header-icon svg { width: 24px; height: 24px; stroke: #fff; }
.inv-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin: 0; }
.inv-header .inv-subtitle { font-size: .85rem; color: var(--text-light); margin-top: 2px; }
.inv-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; align-items: start; }
@media (max-width: 900px) { .inv-layout { grid-template-columns: 1fr; } }
.inv-card { background: #fff; border-radius: 14px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 20px; overflow: hidden; transition: box-shadow .2s; }
.inv-card:hover { box-shadow: var(--shadow-md); }
.inv-card-header { display: flex; align-items: center; gap: 10px; padding: 18px 22px 14px; border-bottom: 1px solid var(--border-color); }
.inv-card-header-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.icon-blue  { background: #eff6ff; color: #2563eb; }
.icon-green { background: #f0fdf4; color: #16a34a; }
.icon-amber { background: #fffbeb; color: #d97706; }
.inv-card-header h3 { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin: 0; }
.inv-card-header .badge-req { margin-left: auto; font-size: .7rem; font-weight: 700; background: #fef2f2; color: #dc2626; border-radius: 99px; padding: 2px 8px; letter-spacing: .3px; }
.inv-card-body { padding: 20px 22px; }
.inv-field { margin-bottom: 18px; }
.inv-field:last-child { margin-bottom: 0; }
.inv-label { display: block; font-size: .8rem; font-weight: 600; color: var(--text-light); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.inv-label .req { color: #ef4444; margin-left: 2px; }
.inv-input, .inv-select, .inv-textarea { width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: .95rem; color: var(--text-dark); background: #fafafa; transition: border-color .2s, box-shadow .2s, background .2s; outline: none; font-family: inherit; }
.inv-input:focus, .inv-select:focus, .inv-textarea:focus { border-color: #d97706; background: #fff; box-shadow: 0 0 0 3px rgba(217,119,6,.1); }
.inv-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
.inv-textarea { resize: vertical; min-height: 88px; }
.inv-date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.inv-table-wrap { overflow-x: auto; }
.inv-table { width: 100%; border-collapse: collapse; }
.inv-table thead th { padding: 10px 12px; background: #f8fafc; font-size: .75rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid #e5e7eb; white-space: nowrap; }
.inv-table thead th:first-child { border-radius: 6px 0 0 0; }
.inv-table thead th:last-child  { border-radius: 0 6px 0 0; }
.inv-table tbody tr.line-item:hover { background: #f8fafc; }
.inv-table tbody tr.line-item td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.inv-table .tbl-input { width: 100%; padding: 8px 10px; border: 1.5px solid #e5e7eb; border-radius: 6px; font-size: .9rem; color: var(--text-dark); background: #fff; outline: none; font-family: inherit; transition: border-color .2s, box-shadow .2s; }
.inv-table .tbl-input:focus { border-color: #d97706; box-shadow: 0 0 0 3px rgba(217,119,6,.1); }
.inv-table .tbl-input.tbl-readonly { background: #f1f5f9; color: #475569; font-weight: 600; cursor: default; }
.tbl-remove-btn { width: 32px; height: 32px; border-radius: 6px; border: none; background: #fef2f2; color: #dc2626; font-size: 1.2rem; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s, transform .1s; }
.tbl-remove-btn:hover { background: #fee2e2; transform: scale(1.1); }
.tbl-remove-btn:disabled { opacity: .3; cursor: not-allowed; transform: none; }
.inv-add-row-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; margin: 14px 0 0; background: #fffbeb; color: #d97706; border: 1.5px dashed #fcd34d; border-radius: 8px; font-size: .88rem; font-weight: 600; cursor: pointer; transition: background .15s, border-color .15s; }
.inv-add-row-btn:hover { background: #fef3c7; border-color: #f59e0b; }
.inv-summary-col { position: sticky; top: 80px; }
.inv-summary-card { background: #fff; border-radius: 14px; border: 1px solid var(--border-color); box-shadow: var(--shadow-md); overflow: hidden; }
.inv-summary-header { background: linear-gradient(135deg, #78350f 0%, #d97706 100%); padding: 18px 20px; }
.inv-summary-header h4 { color: #fff; font-size: .95rem; font-weight: 700; margin: 0 0 4px; }
.inv-summary-header p  { color: rgba(255,255,255,.7); font-size: .8rem; margin: 0; }
.inv-summary-body { padding: 20px; }
.inv-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: .9rem; }
.inv-summary-row:last-child { border-bottom: none; }
.inv-summary-row .s-label { color: var(--text-light); font-weight: 500; }
.inv-summary-row .s-value { font-weight: 700; color: var(--text-dark); }
.inv-summary-row .s-value.s-muted { color: var(--text-light); font-weight: 500; }
.inv-summary-total { margin-top: 12px; padding: 14px 16px; background: #fffbeb; border-radius: 10px; border: 1.5px solid #fcd34d; display: flex; justify-content: space-between; align-items: center; }
.inv-summary-total .t-label { font-size: 1rem; font-weight: 700; color: var(--text-dark); }
.inv-summary-total .t-value { font-size: 1.4rem; font-weight: 800; color: #d97706; }
.inv-item-count { display: inline-block; background: #fffbeb; color: #d97706; border-radius: 99px; font-size: .75rem; font-weight: 700; padding: 1px 9px; margin-left: 6px; }
.inv-actions { display: flex; gap: 12px; justify-content: flex-end; padding-top: 8px; }
.inv-btn { display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border-radius: 9px; font-size: .95rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .2s; }
.inv-btn-ghost { background: #f3f4f6; color: var(--text-dark); border: 1.5px solid #e5e7eb; }
.inv-btn-ghost:hover { background: #e5e7eb; }
.inv-btn-primary { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: #fff; box-shadow: 0 4px 14px rgba(217,119,6,.35); }
.inv-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(217,119,6,.45); }
.inv-alert { display: flex; gap: 12px; padding: 14px 18px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; color: #dc2626; margin-bottom: 20px; }
.inv-alert ul { margin: 4px 0 0 16px; padding: 0; }
.inv-invoice-no { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 20px; font-size: .85rem; font-weight: 700; color: #92400e; }
</style>

<div class="content-area">
<div class="inv-page">

    <!-- Page Header -->
    <div class="inv-header">
        <div class="inv-header-left">
            <div class="inv-header-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
            <div>
                <h1>Edit Invoice</h1>
                <div class="inv-subtitle"><?= htmlspecialchars($company['name'] ?? '') ?></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="inv-invoice-no">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <?= htmlspecialchars($invoice['invoice_number']) ?>
            </span>
            <a href="<?= WEB_ROOT ?>/invoices/view.php?id=<?= $invoiceId ?>&company=<?= $companyId ?>" class="inv-btn inv-btn-ghost" style="padding:8px 16px;font-size:.88rem;">
                ← Back to Invoice
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="inv-alert">
        <div>
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <strong>Please fix the following errors:</strong>
            <ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="invoiceForm">
    <div class="inv-layout">

        <!-- ── Left column ── -->
        <div>
            <!-- Invoice Details -->
            <div class="inv-card">
                <div class="inv-card-header">
                    <div class="inv-card-header-icon icon-blue">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3>Invoice Details</h3>
                </div>
                <div class="inv-card-body">
                    <div class="inv-field">
                        <label class="inv-label" for="customer_id">Customer <span class="req">*</span></label>
                        <select name="customer_id" id="customer_id" class="inv-select" required onchange="updateSummaryCustomer()">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= $cust['customer_id'] ?>"
                                        data-payment-terms="<?= $cust['payment_terms'] ?>"
                                        <?= $invoice['customer_id'] == $cust['customer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="inv-date-grid">
                        <div class="inv-field">
                            <label class="inv-label" for="invoice_date">Invoice Date <span class="req">*</span></label>
                            <input type="date" name="invoice_date" id="invoice_date" class="inv-input"
                                   value="<?= htmlspecialchars($invoice['invoice_date']) ?>" required
                                   onchange="document.getElementById('summaryInvoiceDate').textContent = fmtDate(this.value)">
                        </div>
                        <div class="inv-field">
                            <label class="inv-label" for="due_date">Due Date <span class="req">*</span></label>
                            <input type="date" name="due_date" id="due_date" class="inv-input"
                                   value="<?= htmlspecialchars($invoice['due_date']) ?>" required
                                   onchange="document.getElementById('summaryDueDate').textContent = fmtDate(this.value)">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="inv-card">
                <div class="inv-card-header">
                    <div class="inv-card-header-icon icon-green">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </div>
                    <h3>Line Items</h3>
                    <span class="badge-req">Required</span>
                    <span class="inv-item-count" id="itemCountBadge"><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></span>
                </div>
                <div class="inv-card-body">
                    <div class="inv-table-wrap">
                        <table class="inv-table">
                            <thead>
                                <tr>
                                    <th style="width:40%">Description</th>
                                    <th style="width:10%; text-align:center">Qty</th>
                                    <th style="width:18%; text-align:right">Unit Price</th>
                                    <th style="width:18%; text-align:right">Line Total</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItemsContainer">
                                <?php foreach ($items as $item): ?>
                                <tr class="line-item">
                                    <td><input type="text" name="item_description[]" class="tbl-input" placeholder="Item description" value="<?= htmlspecialchars($item['description']) ?>"></td>
                                    <td><input type="number" name="item_quantity[]" class="tbl-input item-qty" style="text-align:center" min="0" step="0.01" value="<?= htmlspecialchars($item['quantity']) ?>" oninput="calculateLineTotals()"></td>
                                    <td><input type="number" name="item_unit_price[]" class="tbl-input item-price" style="text-align:right" min="0" step="0.01" value="<?= htmlspecialchars($item['unit_price']) ?>" oninput="calculateLineTotals()"></td>
                                    <td><input type="text" class="tbl-input tbl-readonly line-total" style="text-align:right" readonly value="₱<?= number_format($item['quantity'] * $item['unit_price'], 2) ?>"></td>
                                    <td><button type="button" class="tbl-remove-btn" onclick="removeLineItem(this)" title="Remove">×</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="inv-add-row-btn" onclick="addLineItem()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Add Line Item
                    </button>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="inv-card">
                <div class="inv-card-header">
                    <div class="inv-card-header-icon icon-amber">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    </div>
                    <h3>Additional Information</h3>
                </div>
                <div class="inv-card-body">
                    <div class="inv-field">
                        <label class="inv-label" for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="inv-textarea" placeholder="Add internal notes…"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="inv-field">
                        <label class="inv-label" for="terms_conditions">Terms &amp; Conditions</label>
                        <textarea name="terms_conditions" id="terms_conditions" class="inv-textarea" placeholder="Terms and conditions shown on invoice"><?= htmlspecialchars($invoice['terms'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Action Row -->
            <div class="inv-actions">
                <a href="<?= WEB_ROOT ?>/invoices/view.php?id=<?= $invoiceId ?>&company=<?= $companyId ?>" class="inv-btn inv-btn-ghost">
                    Cancel
                </a>
                <button type="submit" class="inv-btn inv-btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Save Changes
                </button>
            </div>
        </div>

        <!-- ── Right column — Sticky Summary ── -->
        <div class="inv-summary-col">
            <div class="inv-summary-card">
                <div class="inv-summary-header">
                    <h4>Invoice Summary</h4>
                    <p id="summaryCustomerName"><?= htmlspecialchars($invoice['customer_name'] ?? 'No customer selected') ?></p>
                </div>
                <div class="inv-summary-body">
                    <div class="inv-summary-row">
                        <span class="s-label">Invoice #</span>
                        <span class="s-value"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Invoice Date</span>
                        <span class="s-value" id="summaryInvoiceDate"><?= date('M d, Y', strtotime($invoice['invoice_date'])) ?></span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Due Date</span>
                        <span class="s-value" id="summaryDueDate"><?= date('M d, Y', strtotime($invoice['due_date'])) ?></span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Line Items</span>
                        <span class="s-value" id="summaryItemCount"><?= count($items) ?> <?= count($items) === 1 ? 'item' : 'items' ?></span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Subtotal</span>
                        <span class="s-value" id="subtotalDisplay">₱<?= number_format($invoice['subtotal'], 2) ?></span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Tax</span>
                        <span class="s-value s-muted" id="taxDisplay">₱0.00</span>
                    </div>
                    <div class="inv-summary-total">
                        <span class="t-label">Total Due</span>
                        <span class="t-value" id="totalDisplay">₱<?= number_format($invoice['total_amount'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </form>

</div><!-- /inv-page -->
</div><!-- /content-area -->

<script>
function fmt(n) {
    return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(val) {
    if (!val) return '—';
    const d = new Date(val + 'T00:00:00');
    return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' });
}

function calculateLineTotals() {
    const rows = document.querySelectorAll('#lineItemsContainer .line-item');
    let subtotal = 0;
    rows.forEach(row => {
        const qty   = parseFloat(row.querySelector('.item-qty').value)   || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const total = qty * price;
        row.querySelector('.line-total').value = fmt(total);
        subtotal += total;
    });
    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('totalDisplay').textContent    = fmt(subtotal);

    const count = rows.length;
    const label = count + (count === 1 ? ' item' : ' items');
    document.getElementById('itemCountBadge').textContent   = label;
    document.getElementById('summaryItemCount').textContent = label;

    updateRemoveButtons();
}

function updateRemoveButtons() {
    const btns = document.querySelectorAll('#lineItemsContainer .tbl-remove-btn');
    btns.forEach(btn => btn.disabled = btns.length === 1);
}

function addLineItem() {
    const tbody = document.getElementById('lineItemsContainer');
    const tr = document.createElement('tr');
    tr.className = 'line-item';
    tr.innerHTML = `
        <td><input type="text" name="item_description[]" class="tbl-input" placeholder="Item description"></td>
        <td><input type="number" name="item_quantity[]" class="tbl-input item-qty" style="text-align:center" min="0" step="0.01" value="1" oninput="calculateLineTotals()"></td>
        <td><input type="number" name="item_unit_price[]" class="tbl-input item-price" style="text-align:right" min="0" step="0.01" placeholder="0.00" oninput="calculateLineTotals()"></td>
        <td><input type="text" class="tbl-input tbl-readonly line-total" style="text-align:right" readonly value="₱0.00"></td>
        <td><button type="button" class="tbl-remove-btn" onclick="removeLineItem(this)" title="Remove">×</button></td>
    `;
    tbody.appendChild(tr);
    tr.querySelector('input').focus();
    calculateLineTotals();
}

function removeLineItem(btn) {
    const rows = document.querySelectorAll('#lineItemsContainer .line-item');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        calculateLineTotals();
    }
}

function updateSummaryCustomer() {
    const sel = document.getElementById('customer_id');
    document.getElementById('summaryCustomerName').textContent =
        sel.value ? sel.options[sel.selectedIndex].text : 'No customer selected';
}

// Init
updateRemoveButtons();
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
