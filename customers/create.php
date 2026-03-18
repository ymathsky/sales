<?php
/**
 * Create Customer
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Add Customer';

$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());
requireCompanyAccess($companyId);

$company = Company::getById($companyId);

$errors = [];
$formData = [
    'customer_name' => '',
    'contact_person' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'tax_id' => '',
    'payment_terms' => 30,
    'credit_limit' => 0,
    'is_active' => 1,
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        'notes' => trim($_POST['notes'] ?? ''),
        'company_id' => $companyId
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
            $customerId = Customer::create($formData);
            setFlashMessage('Customer created successfully', 'success');
            header('Location: /sales/customers/list.php?company=' . $companyId);
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error creating customer: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../views/header.php';
?>

<div class="page-header" style="margin-bottom: 32px">
    <div style="display: flex; gap: 16px; align-items: center;">
        <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 12px; border-radius: 12px; color: white; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4);">
            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
            </svg>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 26px; font-weight: 800; color: #1f2937; letter-spacing: -0.5px;">Add Customer</h1>
            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 15px;">Create a new customer profile for <?= htmlspecialchars($company['name']) ?>.</p>
        </div>
    </div>
    <div>
        <a href="/sales/customers/list.php?company=<?= $companyId ?>" 
           class="btn btn-white" style="border: 1px solid #d1d5db; color: #374151; font-weight: 600; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
            <svg style="width: 18px; height: 18px; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Customers
        </a>
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
    <form method="POST" action="">
        
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
                    <span style="font-size: 15px; font-weight: 600; color: #374151;">Customer Actions Active</span>
                </label>
            </div>
        </div>
        
        <div class="form-actions" style="display: flex; gap: 16px; border-top: 1px solid #e5e7eb; padding-top: 32px;">
            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; padding: 12px 32px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4); transition: transform 0.1s;" onmousedown="this.style.transform='translateY(1px)'" onmouseup="this.style.transform='translateY(0)'">Create Customer</button>
            <a href="/sales/customers/list.php?company=<?= $companyId ?>" class="btn btn-white" style="border: 1px solid #d1d5db; color: #374151; font-weight: 600; padding: 12px 24px;">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
