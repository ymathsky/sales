<?php
/**
 * Create Invoice
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Create Invoice';

$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());
requireCompanyAccess($companyId);

$company = Company::getById($companyId);
$customers = Customer::getByCompany($companyId, true); // Active only

// Pre-select customer if provided
$selectedCustomerId = $_GET['customer_id'] ?? '';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceDate = trim($_POST['invoice_date'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $termsConditions = trim($_POST['terms_conditions'] ?? '');
    
    // Line items
    $itemDescriptions = $_POST['item_description'] ?? [];
    $itemQuantities   = $_POST['item_quantity']    ?? [];
    $itemUnitPrices   = $_POST['item_unit_price']  ?? [];
    $itemIsPaidArr    = $_POST['item_is_paid']     ?? [];

    // Validation
    if (!$customerId) {
        $errors[] = 'Please select a customer.';
    }
    if (!$invoiceDate) {
        $errors[] = 'Invoice date is required.';
    }
    if (!$dueDate) {
        $errors[] = 'Due date is required.';
    }
    
    // Validate at least one line item
    $items = [];
    foreach ($itemDescriptions as $index => $description) {
        $description = trim($description);
        $quantity    = floatval($itemQuantities[$index] ?? 0);
        $unitPrice   = floatval($itemUnitPrices[$index]  ?? 0);
        
        if ($description && $quantity > 0 && $unitPrice > 0) {
            $items[] = [
                'description' => $description,
                'quantity'    => $quantity,
                'unit_price'  => $unitPrice,
                'is_paid'     => !empty($itemIsPaidArr[$index]) ? 1 : 0,
            ];
        }
    }
    
    if (empty($items)) {
        $errors[] = 'Please add at least one line item.';
    }
    
    if (empty($errors)) {
        $data = [
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'notes' => $notes,
            'terms_conditions' => $termsConditions,
            'created_by' => getCurrentUserId()
        ];
        
        $invoiceId = Invoice::create($data, $items);
        
        if ($invoiceId) {
            header('Location: ' . WEB_ROOT . '/invoices/view.php?id=' . $invoiceId . '&company=' . $companyId . '&created=1');
            exit;
        } else {
            $errors[] = 'Failed to create invoice. Please try again.';
        }
    }
}

include __DIR__ . '/../views/header.php';
?>

<style>
/* ── Page-scoped modern invoice styles ── */
.inv-page { max-width: 1200px; margin: 0 auto; padding: 0 4px 60px; }

/* Page header */
.inv-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}
.inv-header-left { display: flex; align-items: center; gap: 14px; }
.inv-header-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(37,99,235,.3);
}
.inv-header-icon svg { width: 24px; height: 24px; color: #fff; stroke: #fff; }
.inv-header h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin: 0; }
.inv-header .inv-subtitle { font-size: .85rem; color: var(--text-light); margin-top: 2px; }

/* Two-column layout */
.inv-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; align-items: start; }
@media (max-width: 900px) { .inv-layout { grid-template-columns: 1fr; } }

/* Section cards */
.inv-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    margin-bottom: 20px;
    overflow: hidden;
    transition: box-shadow .2s;
}
.inv-card:hover { box-shadow: var(--shadow-md); }
.inv-card-header {
    display: flex; align-items: center; gap: 10px;
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--border-color);
}
.inv-card-header-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.icon-blue  { background: #eff6ff; color: #2563eb; }
.icon-green { background: #f0fdf4; color: #16a34a; }
.icon-amber { background: #fffbeb; color: #d97706; }
.inv-card-header h3 { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin: 0; }
.inv-card-header .badge-req {
    margin-left: auto;
    font-size: .7rem; font-weight: 700;
    background: #fef2f2; color: #dc2626;
    border-radius: 99px; padding: 2px 8px; letter-spacing: .3px;
}
.inv-card-body { padding: 20px 22px; }

/* Modern form fields */
.inv-field { margin-bottom: 18px; }
.inv-field:last-child { margin-bottom: 0; }
.inv-label {
    display: block; font-size: .8rem; font-weight: 600;
    color: var(--text-light); text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 6px;
}
.inv-label .req { color: #ef4444; margin-left: 2px; }
.inv-input, .inv-select, .inv-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: .95rem;
    color: var(--text-dark);
    background: #fafafa;
    transition: border-color .2s, box-shadow .2s, background .2s;
    outline: none;
    font-family: inherit;
}
.inv-input:focus, .inv-select:focus, .inv-textarea:focus {
    border-color: #2563eb;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
.inv-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
.inv-textarea { resize: vertical; min-height: 88px; }

/* Date grid */
.inv-date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Line items table */
.inv-table-wrap { overflow-x: auto; }
.inv-table {
    width: 100%;
    border-collapse: collapse;
}
.inv-table thead th {
    padding: 10px 12px;
    background: #f8fafc;
    font-size: .75rem; font-weight: 700;
    color: var(--text-light); text-transform: uppercase; letter-spacing: .5px;
    border-bottom: 2px solid #e5e7eb;
    white-space: nowrap;
}
.inv-table thead th:first-child { border-radius: 6px 0 0 0; }
.inv-table thead th:last-child  { border-radius: 0 6px 0 0; }
.inv-table tbody tr.line-item { transition: background .15s; }
.inv-table tbody tr.line-item:hover { background: #f8fafc; }
.inv-table tbody tr.line-item td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.inv-table .tbl-input {
    width: 100%; padding: 8px 10px;
    border: 1.5px solid #e5e7eb; border-radius: 6px;
    font-size: .9rem; color: var(--text-dark);
    background: #fff; outline: none; font-family: inherit;
    transition: border-color .2s, box-shadow .2s;
}
.inv-table .tbl-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.inv-table .tbl-input.tbl-readonly { background: #f1f5f9; color: #475569; font-weight: 600; cursor: default; }
.tbl-remove-btn {
    width: 32px; height: 32px; border-radius: 6px;
    border: none; background: #fef2f2; color: #dc2626;
    font-size: 1.2rem; line-height: 1; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s, transform .1s;
}
.tbl-remove-btn:hover { background: #fee2e2; transform: scale(1.1); }
.tbl-remove-btn:disabled { opacity: .3; cursor: not-allowed; transform: none; }
/* Paid toggle */
.paid-toggle { display:inline-flex; align-items:center; cursor:pointer; }
.paid-toggle input[type=checkbox] { display:none; }
.paid-pill { display:inline-block; width:36px; height:20px; background:#e5e7eb; border-radius:99px; position:relative; transition:background .2s; flex-shrink:0; }
.paid-pill::after { content:''; position:absolute; left:3px; top:3px; width:14px; height:14px; background:#fff; border-radius:50%; transition:left .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
.paid-toggle input:checked + .paid-pill { background:#16a34a; }
.paid-toggle input:checked + .paid-pill::after { left:19px; }
tr.line-item { transition:background .2s; }

/* Add row button */
.inv-add-row-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; margin: 14px 0 0;
    background: #eff6ff; color: #2563eb;
    border: 1.5px dashed #93c5fd; border-radius: 8px;
    font-size: .88rem; font-weight: 600; cursor: pointer;
    transition: background .15s, border-color .15s;
}
.inv-add-row-btn:hover { background: #dbeafe; border-color: #60a5fa; }

/* Sticky summary sidebar */
.inv-summary-col { position: sticky; top: 80px; }
.inv-summary-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}
.inv-summary-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    padding: 18px 20px;
}
.inv-summary-header h4 { color: #fff; font-size: .95rem; font-weight: 700; margin: 0 0 4px; }
.inv-summary-header p  { color: rgba(255,255,255,.7); font-size: .8rem; margin: 0; }
.inv-summary-body { padding: 20px; }
.inv-summary-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid #f1f5f9;
    font-size: .9rem;
}
.inv-summary-row:last-child { border-bottom: none; }
.inv-summary-row .s-label { color: var(--text-light); font-weight: 500; }
.inv-summary-row .s-value { font-weight: 700; color: var(--text-dark); }
.inv-summary-row .s-value.s-muted { color: var(--text-light); font-weight: 500; }
.inv-summary-total {
    margin-top: 12px; padding: 14px 16px;
    background: #f0f9ff; border-radius: 10px; border: 1.5px solid #bae6fd;
    display: flex; justify-content: space-between; align-items: center;
}
.inv-summary-total .t-label { font-size: 1rem; font-weight: 700; color: var(--text-dark); }
.inv-summary-total .t-value { font-size: 1.4rem; font-weight: 800; color: #2563eb; }

/* Line count badge */
.inv-item-count {
    display: inline-block;
    background: #eff6ff; color: #2563eb;
    border-radius: 99px; font-size: .75rem; font-weight: 700;
    padding: 1px 9px; margin-left: 6px;
}

/* Action buttons */
.inv-actions {
    display: flex; gap: 12px; justify-content: flex-end;
    padding-top: 8px;
}
.inv-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 22px; border-radius: 9px;
    font-size: .95rem; font-weight: 600; cursor: pointer;
    border: none; text-decoration: none; transition: all .2s;
}
.inv-btn-ghost {
    background: #f3f4f6; color: var(--text-dark);
    border: 1.5px solid #e5e7eb;
}
.inv-btn-ghost:hover { background: #e5e7eb; }
.inv-btn-primary {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    box-shadow: 0 4px 14px rgba(22,163,74,.35);
}
.inv-btn-primary:hover { box-shadow: 0 6px 20px rgba(22,163,74,.45); transform: translateY(-1px); }
.inv-btn-primary:active { transform: translateY(0); }

/* Alert */
.inv-alert {
    display: flex; gap: 12px; align-items: flex-start;
    background: #fef2f2; border: 1.5px solid #fecaca;
    border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;
}
.inv-alert-icon { color: #dc2626; flex-shrink: 0; margin-top: 1px; }
.inv-alert ul { margin: 6px 0 0 16px; color: #dc2626; font-size: .9rem; }
.inv-alert strong { color: #dc2626; }

.inv-warn {
    background: #fffbeb; border: 1.5px solid #fde68a;
    border-radius: 10px; padding: 16px 20px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 12px;
}
.inv-warn strong { color: #92400e; }
.inv-warn a { margin-left: 12px; }

/* Row enter animation */
@keyframes rowSlideIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.line-item { animation: rowSlideIn .2s ease; }
</style>

<div class="inv-page">

    <!-- Page Header -->
    <div class="inv-header">
        <div class="inv-header-left">
            <div class="inv-header-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <h1>Create Invoice</h1>
                <div class="inv-subtitle"><?= htmlspecialchars($company['name']) ?></div>
            </div>
        </div>
        <a href="<?= WEB_ROOT ?>/invoices/list.php?company=<?= $companyId ?>" class="inv-btn inv-btn-ghost">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Invoices
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="inv-alert">
        <div class="inv-alert-icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <strong>Please fix the following errors:</strong>
            <ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($customers)): ?>
    <div class="inv-warn">
        <svg width="20" height="20" fill="none" stroke="#d97706" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.293 3.716a2 2 0 013.414 0l7.071 12.247A2 2 0 0119.07 19H4.93a2 2 0 01-1.707-2.963L10.293 3.716z"/></svg>
        <div><strong>No customers found.</strong> Create a customer first before making an invoice.
        <a href="<?= WEB_ROOT ?>/customers/create.php?company=<?= $companyId ?>" class="btn btn-sm btn-primary">+ Create Customer</a></div>
    </div>
    <?php else: ?>

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
                        <select name="customer_id" id="customer_id" class="inv-select" required onchange="updatePaymentTerms()">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= $cust['customer_id'] ?>"
                                        data-payment-terms="<?= $cust['payment_terms'] ?>"
                                        <?= $selectedCustomerId == $cust['customer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="inv-date-grid">
                        <div class="inv-field">
                            <label class="inv-label" for="invoice_date">Invoice Date <span class="req">*</span></label>
                            <input type="date" name="invoice_date" id="invoice_date" class="inv-input"
                                   value="<?= date('Y-m-d') ?>" required onchange="updateDueDate()">
                        </div>
                        <div class="inv-field">
                            <label class="inv-label" for="due_date">Due Date <span class="req">*</span></label>
                            <input type="date" name="due_date" id="due_date" class="inv-input"
                                   value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
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
                    <span class="inv-item-count" id="itemCountBadge">1 item</span>
                </div>
                <div class="inv-card-body">
                    <div class="inv-table-wrap">
                        <table class="inv-table">
                            <thead>
                                <tr>
                                    <th style="width:37%">Description</th>
                                    <th style="width:9%; text-align:center">Qty</th>
                                    <th style="width:17%; text-align:right">Unit Price</th>
                                    <th style="width:17%; text-align:right">Line Total</th>
                                    <th style="width:60px; text-align:center">Paid</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="lineItemsContainer">
                                <tr class="line-item">
                                    <td><input type="text" name="item_description[]" class="tbl-input" placeholder="Item description"></td>
                                    <td><input type="number" name="item_quantity[]" class="tbl-input item-qty" style="text-align:center" min="0" step="0.01" value="1" oninput="calculateLineTotals()"></td>
                                    <td><input type="number" name="item_unit_price[]" class="tbl-input item-price" style="text-align:right" min="0" step="0.01" placeholder="0.00" oninput="calculateLineTotals()"></td>
                                    <td><input type="text" class="tbl-input tbl-readonly line-total" style="text-align:right" readonly value="₱0.00"></td>
                                    <td style="text-align:center"><label class="paid-toggle"><input type="checkbox" class="item-paid-cb" onchange="calculateLineTotals()"><span class="paid-pill"></span></label></td>
                                    <td><button type="button" class="tbl-remove-btn" onclick="removeLineItem(this)" disabled title="Remove">×</button></td>
                                </tr>
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
                        <label class="inv-label" for="notes">Notes <span style="font-weight:400;text-transform:none;letter-spacing:0">(internal — not shown to customer)</span></label>
                        <textarea name="notes" id="notes" class="inv-textarea" placeholder="Add internal notes…"></textarea>
                    </div>
                    <div class="inv-field">
                        <label class="inv-label" for="terms_conditions">Terms &amp; Conditions</label>
                        <textarea name="terms_conditions" id="terms_conditions" class="inv-textarea" placeholder="Terms and conditions shown on invoice">Payment is due within the specified payment terms. Late payments may incur additional charges.</textarea>
                    </div>
                </div>
            </div>

            <!-- Action Row -->
            <div class="inv-actions">
                <a href="<?= WEB_ROOT ?>/invoices/list.php?company=<?= $companyId ?>" class="inv-btn inv-btn-ghost">
                    Cancel
                </a>
                <button type="submit" class="inv-btn inv-btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Create Invoice
                </button>
            </div>
        </div>

        <!-- ── Right column — Sticky Summary ── -->
        <div class="inv-summary-col">
            <div class="inv-summary-card">
                <div class="inv-summary-header">
                    <h4>Invoice Summary</h4>
                    <p id="summaryCustomerName">No customer selected</p>
                </div>
                <div class="inv-summary-body">
                    <div class="inv-summary-row">
                        <span class="s-label">Invoice Date</span>
                        <span class="s-value" id="summaryInvoiceDate"><?= date('M d, Y') ?></span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Due Date</span>
                        <span class="s-value" id="summaryDueDate"><?= date('M d, Y', strtotime('+30 days')) ?></span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Line Items</span>
                        <span class="s-value" id="summaryItemCount">1 item</span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Subtotal</span>
                        <span class="s-value" id="subtotalDisplay">₱0.00</span>
                    </div>
                    <div class="inv-summary-row">
                        <span class="s-label">Tax (0%)</span>
                        <span class="s-value s-muted" id="taxDisplay">₱0.00</span>
                    </div>
                    <div class="inv-summary-row" id="paidRow" style="display:none">
                        <span class="s-label" style="color:#16a34a">✓ Paid</span>
                        <span class="s-value" style="color:#16a34a" id="paidDisplay">₱0.00</span>
                    </div>
                    <div class="inv-summary-total">
                        <span class="t-label" id="totalLabel">Total Due</span>
                        <span class="t-value" id="totalDisplay">₱0.00</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
    </form>

    <?php endif; ?>

</div><!-- /inv-page -->

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
    let subtotal = 0, paidAmount = 0;
    rows.forEach(row => {
        const qty    = parseFloat(row.querySelector('.item-qty').value)   || 0;
        const price  = parseFloat(row.querySelector('.item-price').value) || 0;
        const total  = qty * price;
        const isPaid = row.querySelector('.item-paid-cb').checked;
        row.querySelector('.line-total').value = fmt(total);
        subtotal += total;
        if (isPaid) paidAmount += total;
        row.style.background = isPaid ? '#f0fdf4' : '';
    });
    const balanceDue = subtotal - paidAmount;
    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('taxDisplay').textContent      = fmt(0);
    document.getElementById('totalDisplay').textContent    = fmt(balanceDue);
    document.getElementById('totalLabel').textContent      = paidAmount > 0 ? 'Balance Due' : 'Total Due';

    const paidRow = document.getElementById('paidRow');
    if (paidAmount > 0) {
        paidRow.style.display = '';
        document.getElementById('paidDisplay').textContent = fmt(paidAmount);
    } else {
        paidRow.style.display = 'none';
    }

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
        <td style="text-align:center"><label class="paid-toggle"><input type="checkbox" class="item-paid-cb" onchange="calculateLineTotals()"><span class="paid-pill"></span></label></td>
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

function updatePaymentTerms() {
    updateDueDate();
    const sel = document.getElementById('customer_id');
    const name = sel.options[sel.selectedIndex].text;
    document.getElementById('summaryCustomerName').textContent = sel.value ? name : 'No customer selected';
}

function updateDueDate() {
    const invoiceDate = document.getElementById('invoice_date').value;
    const sel  = document.getElementById('customer_id');
    const opt  = sel.options[sel.selectedIndex];
    const days = parseInt(opt?.dataset.paymentTerms) || 30;
    if (invoiceDate) {
        const d = new Date(invoiceDate + 'T00:00:00');
        d.setDate(d.getDate() + days);
        const ymd = d.toISOString().split('T')[0];
        document.getElementById('due_date').value = ymd;
        document.getElementById('summaryDueDate').textContent = fmtDate(ymd);
    }
    document.getElementById('summaryInvoiceDate').textContent = fmtDate(invoiceDate);
}

// Sync summary on date input
document.getElementById('invoice_date').addEventListener('change', () => {
    document.getElementById('summaryInvoiceDate').textContent = fmtDate(document.getElementById('invoice_date').value);
});
document.getElementById('due_date').addEventListener('change', () => {
    document.getElementById('summaryDueDate').textContent = fmtDate(document.getElementById('due_date').value);
});

// Serialize is_paid values with correct indices before submission
document.getElementById('invoiceForm').addEventListener('submit', function() {
    document.querySelectorAll('#lineItemsContainer .line-item').forEach(function(row, idx) {
        var h = document.createElement('input');
        h.type  = 'hidden';
        h.name  = 'item_is_paid[' + idx + ']';
        h.value = row.querySelector('.item-paid-cb').checked ? '1' : '0';
        row.appendChild(h);
    });
});

// Init
calculateLineTotals();
<?php if ($selectedCustomerId): ?>
updatePaymentTerms();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
