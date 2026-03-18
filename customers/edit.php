<?php
/**
 * Edit Customer
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Edit Customer';

$customerId = (int)($_GET['id'] ?? 0);
$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());

requireCompanyAccess($companyId);

if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
    die('You do not have permission to edit customers for this company');
}

$customer = Customer::getById($customerId, $companyId);
if (!$customer) {
    die('Customer not found');
}

$company = Company::getById($companyId);
$errors = [];

$formData = [
    'customer_name' => $customer['customer_name'],
    'contact_person' => $customer['contact_person'] ?? '',
    'email' => $customer['email'] ?? '',
    'phone' => $customer['phone'] ?? '',
    'address' => $customer['address'] ?? '',
    'tax_id' => $customer['tax_id'] ?? '',
    'payment_terms' => $customer['payment_terms'] ?? 30,
    'credit_limit' => $customer['credit_limit'] ?? 0,
    'is_active' => $customer['is_active'] ?? 1,
    'notes' => $customer['notes'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        Customer::delete($customerId, $companyId);
        setFlashMessage('Customer deleted successfully', 'success');
        header('Location: /sales/customers/list.php?company=' . $companyId);
        exit;
    }
    
    $formData = [
        'customer_name' => trim($_POST['customer_name'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'tax_id' => trim($_POST['tax_id'] ?? ''),
        'payment_terms' => (int)($_POST['payment_terms'] ?? 30),
        'credit_limit' => (float)($_POST['credit_limit'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    // Validation
    if (empty($formData['customer_name'])) {
        $errors[] = 'Customer name is required';
    }
    
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($errors)) {
        try {
            Customer::update($customerId, $companyId, $formData);
            setFlashMessage('Customer updated successfully', 'success');
            header('Location: /sales/customers/list.php?company=' . $companyId);
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error updating customer: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../views/header.php';
?>

<div class="page-header" style="margin-bottom: 32px">
    <div style="display: flex; gap: 16px; align-items: center;">
        <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 12px; border-radius: 12px; color: white; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.4);">
            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 26px; font-weight: 800; color: #1f2937; letter-spacing: -0.5px;">Edit Customer</h1>
            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 15px;">Update customer profile for <?= htmlspecialchars($company['name']) ?>.</p>
        </div>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="/sales/customers/list.php?company=<?= $companyId ?>" 
           class="btn btn-white" style="border: 1px solid #d1d5db; color: #374151; font-weight: 600; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
            <svg style="width: 18px; height: 18px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Customers
        </a>
        <button type="submit" form="customerForm" class="btn btn-primary"
                style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; display: flex; align-items: center; gap: 8px; padding: 10px 24px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.4);">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Save Changes
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 16px; border-radius: 12px; margin-bottom: 24px; display: flex; gap: 12px;">
        <svg style="width: 24px; height: 24px; color: #ef4444; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <div>
            <strong style="display: block; margin-bottom: 4px; font-weight: 700;">Please fix the following errors:</strong>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="form-container" style="max-width: 900px; margin: 0 auto; background: none; padding: 0; box-shadow: none;">
    <form id="customerForm" method="POST" action="">
        
        <!-- Basic Information Card -->
        <div class="card" style="background: #fff; padding: 32px; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px;">
            <div style="border-bottom: 1px solid #f3f4f6; margin: -32px -32px 32px -32px; padding: 24px 32px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1f2937;">Basic Information</h3>
                <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">Primary contact details for this customer.</p>
            </div>
            
            <div style="display: grid; gap: 24px;">
                <div class="form-group">
                    <label for="customer_name" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Customer Name <span style="color: #ef4444">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" placeholder="Company or Individual Name" required value="<?= htmlspecialchars($formData['customer_name']) ?>" 
                           style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                           onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                           onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';">
                </div>

                <div class="form-group">
                    <label for="contact_person" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" placeholder="Primary Contact Name" value="<?= htmlspecialchars($formData['contact_person']) ?>" 
                           style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                           onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                           onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <label for="email" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Email Address</label>
                        <div style="position: relative;">
                            <div style="position: absolute; left: 12px; top: 12px; color: #9ca3af;">
                                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <input type="email" id="email" name="email" placeholder="customer@example.com" value="<?= htmlspecialchars($formData['email']) ?>" 
                                   style="width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                                   onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                                   onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Phone Number</label>
                        <div style="position: relative;">
                            <div style="position: absolute; left: 12px; top: 12px; color: #9ca3af;">
                                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <input type="text" id="phone" name="phone" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($formData['phone']) ?>" 
                                   style="width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                                   onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                                   onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Address & Additional Details -->
        <div class="card" style="background: #fff; padding: 32px; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px;">
            <div style="border-bottom: 1px solid #f3f4f6; margin: -32px -32px 32px -32px; padding: 24px 32px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1f2937;">Address & Details</h3>
                <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">Billing information and tax details.</p>
            </div>
            
            <div style="display: grid; gap: 24px;">
                <div class="form-group">
                    <label for="address" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Billing Address</label>
                    <textarea id="address" name="address" rows="3" placeholder="Street, City, Province, Postal Code" 
                              style="width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); resize: vertical; min-height: 80px;"
                              onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                              onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';"><?= htmlspecialchars($formData['address']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="tax_id" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Tax ID / TIN</label>
                    <input type="text" id="tax_id" name="tax_id" placeholder="Tax Identification Number" value="<?= htmlspecialchars($formData['tax_id']) ?>" 
                           style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                           onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                           onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';">
                </div>
                
                <div class="form-group">
                    <label for="notes" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Internal Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any additional notes about this customer..." 
                              style="width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); resize: vertical;"
                              onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                              onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';"><?= htmlspecialchars($formData['notes']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Financial Settings -->
        <div class="card" style="background: #fff; padding: 32px; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 32px;">
            <div style="border-bottom: 1px solid #f3f4f6; margin: -32px -32px 32px -32px; padding: 24px 32px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1f2937;">Financial Settings</h3>
                <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">Credit limits and payment terms.</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="form-group">
                    <label for="payment_terms" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Payment Terms (Days)</label>
                    <input type="number" id="payment_terms" name="payment_terms" min="0" step="1" value="<?= htmlspecialchars($formData['payment_terms']) ?>" 
                           style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                           onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                           onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';">
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #6b7280;">Number of days to pay invoices (e.g., 30)</p>
                </div>

                <div class="form-group">
                    <label for="credit_limit" style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px;">Credit Limit</label>
                    <div style="position: relative;">
                        <div style="position: absolute; left: 12px; top: 12px; color: #9ca3af; font-weight: 600;">₱</div>
                        <input type="number" id="credit_limit" name="credit_limit" min="0" step="0.01" value="<?= htmlspecialchars($formData['credit_limit']) ?>" placeholder="0.00" 
                               style="width: 100%; padding: 10px 14px 10px 32px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; outline: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                               onfocus="this.style.borderColor = '#3b82f6'; this.style.boxShadow = '0 0 0 3px rgba(59, 130, 246, 0.1)';" 
                               onblur="this.style.borderColor = '#d1d5db'; this.style.boxShadow = '0 1px 2px rgba(0,0,0,0.05)';">
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px;">
                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; width: fit-content;">
                    <input type="checkbox" name="is_active" value="1" <?= $formData['is_active'] ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer; accent-color: #3b82f6;">
                    <span style="font-size: 15px; font-weight: 600; color: #374151;">Customer is Active</span>
                </label>
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e5e7eb; padding-top: 32px;">
            <button type="submit" name="delete" onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');" 
                    class="btn" style="background: #ef4444; color: white; border: none; padding: 12px 24px; font-size: 15px; font-weight: 600; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);">
                <svg style="width: 16px; height: 16px; vertical-align: middle; margin-right: 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Delete Customer
            </button>
            
            <div style="display: flex; gap: 16px;">
                <a href="/sales/customers/list.php?company=<?= $companyId ?>" class="btn btn-white" style="border: 1px solid #d1d5db; color: #374151; font-weight: 600; padding: 12px 24px;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.4); transition: transform 0.1s;" onmousedown="this.style.transform='translateY(1px)'" onmouseup="this.style.transform='translateY(0)'">Update Customer</button>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
