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
    $itemQuantities = $_POST['item_quantity'] ?? [];
    $itemUnitPrices = $_POST['item_unit_price'] ?? [];
    
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
        $quantity = floatval($itemQuantities[$index] ?? 0);
        $unitPrice = floatval($itemUnitPrices[$index] ?? 0);
        
        if ($description && $quantity > 0 && $unitPrice > 0) {
            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice
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
            header('Location: <?= WEB_ROOT ?>/invoices/view.php?id=' . $invoiceId . '&company=' . $companyId . '&created=1');
            exit;
        } else {
            $errors[] = 'Failed to create invoice. Please try again.';
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
        Create Invoice - <?= htmlspecialchars($company['name']) ?>
    </h1>
    <div>
        <a href="<?= WEB_ROOT ?>/invoices/list.php?company=<?= $companyId ?>" class="btn btn-secondary">← Back to Invoices</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 10px 0 0 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($customers)): ?>
    <div class="alert alert-warning">
        <strong>No customers found.</strong><br>
        You need to create at least one customer before creating an invoice.
        <a href="<?= WEB_ROOT ?>/customers/create.php?company=<?= $companyId ?>" class="btn btn-sm btn-primary" style="margin-left: 10px;">+ Create Customer</a>
    </div>
<?php else: ?>

<form method="POST" action="" id="invoiceForm">
    <div class="form-card">
        <h3 style="margin-bottom: 20px;">Invoice Details</h3>
        
        <div class="form-group">
            <label for="customer_id">Customer <span style="color: red;">*</span></label>
            <select name="customer_id" id="customer_id" class="form-control" required onchange="updatePaymentTerms()">
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
        
        <div class="form-group">
            <label for="invoice_date">Invoice Date <span style="color: red;">*</span></label>
            <input type="date" name="invoice_date" id="invoice_date" class="form-control" 
                   value="<?= date('Y-m-d') ?>" required onchange="updateDueDate()">
        </div>
        
        <div class="form-group">
            <label for="due_date">Due Date <span style="color: red;">*</span></label>
            <input type="date" name="due_date" id="due_date" class="form-control" 
                   value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
        </div>
    </div>
    
    <div class="form-card">
        <h3 style="margin-bottom: 20px;">Line Items <span style="color: red;">*</span></h3>

        <div style="display: grid; grid-template-columns: 2fr 100px 150px 150px 60px; gap: 15px; margin-bottom: 8px; align-items: center; padding-bottom: 8px; border-bottom: 1px solid #dee2e6;">
            <div style="font-weight: 600; color: #555; font-size: 13px;">Description</div>
            <div style="font-weight: 600; color: #555; font-size: 13px;">Quantity</div>
            <div style="font-weight: 600; color: #555; font-size: 13px;">Unit Price</div>
            <div style="font-weight: 600; color: #555; font-size: 13px;">Line Total</div>
            <div></div>
        </div>

        <div id="lineItemsContainer">
            <div class="line-item" style="display: grid; grid-template-columns: 2fr 100px 150px 150px 60px; gap: 15px; margin-bottom: 10px; align-items: center;">
                <div>
                    <input type="text" name="item_description[]" class="form-control" placeholder="Item description">
                </div>
                <div>
                    <input type="number" name="item_quantity[]" class="form-control item-qty" 
                           min="0" step="0.01" value="1" onchange="calculateLineTotals()">
                </div>
                <div>
                    <input type="number" name="item_unit_price[]" class="form-control item-price" 
                           min="0" step="0.01" placeholder="0.00" onchange="calculateLineTotals()">
                </div>
                <div>
                    <input type="text" class="form-control line-total" readonly value="₱0.00" style="background: #f5f5f5;">
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeLineItem(this)" style="width: 100%;">×</button>
                </div>
            </div>
        </div>
        
        <button type="button" class="btn btn-secondary" onclick="addLineItem()">+ Add Line Item</button>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd;">
            <div style="display: flex; justify-content: flex-end;">
                <div style="min-width: 300px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 18px;">
                        <strong>Subtotal:</strong>
                        <strong id="subtotalDisplay">₱0.00</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Tax (0%):</span>
                        <span id="taxDisplay">₱0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 2px solid #333; font-size: 20px;">
                        <strong>Total:</strong>
                        <strong id="totalDisplay" style="color: var(--primary-color);">₱0.00</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="form-card">
        <h3 style="margin-bottom: 20px;">Additional Information</h3>
        
        <div class="form-group">
            <label for="notes">Notes (internal)</label>
            <textarea name="notes" id="notes" class="form-control" rows="3" 
                      placeholder="Internal notes (not shown to customer)"></textarea>
        </div>
        
        <div class="form-group">
            <label for="terms_conditions">Terms & Conditions</label>
            <textarea name="terms_conditions" id="terms_conditions" class="form-control" rows="3" 
                      placeholder="Terms and conditions shown on invoice">Payment is due within the specified payment terms. Late payments may incur additional charges.</textarea>
        </div>
    </div>
    
    <div style="display: flex; gap: 15px; justify-content: flex-end;">
        <a href="<?= WEB_ROOT ?>/invoices/list.php?company=<?= $companyId ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-success">Create Invoice</button>
    </div>
</form>

<?php endif; ?>

<script>
function addLineItem() {
    const container = document.getElementById('lineItemsContainer');
    const newItem = container.firstElementChild.cloneNode(true);
    
    // Clear values
    const inputs = newItem.querySelectorAll('input');
    inputs.forEach(input => {
        if (input.classList.contains('item-qty')) {
            input.value = '1';
        } else if (input.classList.contains('line-total')) {
            input.value = '₱0.00';
        } else {
            input.value = '';
        }
    });
    
    container.appendChild(newItem);
    calculateLineTotals();
}

function removeLineItem(button) {
    const container = document.getElementById('lineItemsContainer');
    if (container.children.length > 1) {
        button.closest('.line-item').remove();
        calculateLineTotals();
    }
}

function calculateLineTotals() {
    const lineItems = document.querySelectorAll('.line-item');
    let subtotal = 0;
    
    lineItems.forEach(item => {
        const qty = parseFloat(item.querySelector('.item-qty').value) || 0;
        const price = parseFloat(item.querySelector('.item-price').value) || 0;
        const lineTotal = qty * price;
        
        item.querySelector('.line-total').value = '₱' + lineTotal.toFixed(2);
        subtotal += lineTotal;
    });
    
    const tax = 0; // No tax for now
    const total = subtotal + tax;
    
    document.getElementById('subtotalDisplay').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('taxDisplay').textContent = '₱' + tax.toFixed(2);
    document.getElementById('totalDisplay').textContent = '₱' + total.toFixed(2);
}

function updatePaymentTerms() {
    const customerSelect = document.getElementById('customer_id');
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    const paymentTerms = parseInt(selectedOption.dataset.paymentTerms) || 30;
    
    updateDueDate();
}

function updateDueDate() {
    const invoiceDate = document.getElementById('invoice_date').value;
    const customerSelect = document.getElementById('customer_id');
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    const paymentTerms = parseInt(selectedOption.dataset.paymentTerms) || 30;
    
    if (invoiceDate) {
        const date = new Date(invoiceDate);
        date.setDate(date.getDate() + paymentTerms);
        document.getElementById('due_date').valueAsDate = date;
    }
}

// Initialize
calculateLineTotals();
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
