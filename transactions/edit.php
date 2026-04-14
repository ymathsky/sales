<?php
/**
 * Edit Transaction
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Company.php';

requireLogin();

$pageTitle = 'Edit Transaction';

$transactionId = (int)($_GET['id'] ?? 0);
$companyId = (int)($_GET['company'] ?? getCurrentCompanyId());

requireCompanyAccess($companyId);

if (!userHasWriteAccess(getCurrentUserId(), $companyId)) {
    die('You do not have permission to edit transactions for this company');
}

$transaction = Transaction::getById($transactionId, $companyId);
if (!$transaction) {
    die('Transaction not found');
}

$currentReceipts = Transaction::getReceipts($transactionId);
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        Transaction::delete($transactionId, $companyId);
        setFlashMessage('✓ Transaction #' . $transactionId . ' deleted successfully!', 'success');
        redirect(WEB_ROOT . "/transactions/list.php?company={$companyId}");
    } elseif (isset($_POST['delete_receipt_id'])) {
        if (Transaction::deleteReceipt($_POST['delete_receipt_id'], $transactionId)) {
            setFlashMessage('Receipt deleted successfully', 'success');
        } else {
            setFlashMessage('Failed to delete receipt', 'error');
        }
        redirect(WEB_ROOT . "/transactions/edit.php?id={$transactionId}&company={$companyId}");
    } else {
        // Handle multiple receipt uploads
        if (!empty($_FILES['receipts']['name'][0])) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            $maxSize = 5 * 1024 * 1024;
            
            $fileCount = count($_FILES['receipts']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['receipts']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileType = $_FILES['receipts']['type'][$i];
                    $fileSize = $_FILES['receipts']['size'][$i];
                    
                    if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
                        $originalName = $_FILES['receipts']['name'][$i];
                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        $fileName = 'receipt_' . $companyId . '_' . time() . '_' . uniqid() . '.' . $extension;
                        $uploadDir = __DIR__ . '/../uploads/receipts/';
                        
                        // Create directory if it doesn't exist
                        if (!is_dir($uploadDir)) {
                            if (!mkdir($uploadDir, 0755, true)) {
                                $errors[] = "Failed to create uploads directory for $originalName";
                                continue;
                            }
                        }
                        
                        // Check if directory is writable
                        if (!is_writable($uploadDir)) {
                            $errors[] = "Uploads directory is not writable for $originalName. Please check permissions.";
                            continue;
                        }
                        
                        if (move_uploaded_file($_FILES['receipts']['tmp_name'][$i], $uploadDir . $fileName)) {
                            Transaction::addReceipt($transactionId, 'uploads/receipts/' . $fileName, $originalName);
                        } else {
                            $errors[] = "Failed to upload file: $originalName. Please check directory permissions.";
                        }
                    }
                }
            }
        }

        // Handle update
        $formData = [
            'type' => $_POST['type'] ?? 'in',
            'amount' => $_POST['amount'] ?? '',
            'transaction_date' => $_POST['transaction_date'] ?? '',
            'category' => sanitizeInput($_POST['category'] ?? ''),
            'description' => sanitizeInput($_POST['description'] ?? ''),
            'reference_number' => sanitizeInput($_POST['reference_number'] ?? ''),
            'payment_method' => $_POST['payment_method'] ?? 'cash',
            'receipt_path' => $transaction['receipt_path']
        ];

        if (!in_array($formData['type'], ['in', 'out'])) {
            $errors[] = 'Invalid transaction type';
        }
        
        if (!isValidAmount($formData['amount'])) {
            $errors[] = 'Invalid amount';
        }
        
        if (!isValidDate($formData['transaction_date'])) {
            $errors[] = 'Invalid date';
        }
        
        if (empty($errors)) {
            try {
                Transaction::update($transactionId, $companyId, $formData);
                setFlashMessage('✓ Transaction updated successfully!', 'success');
                redirect(WEB_ROOT . "/transactions/list.php?company={$companyId}");
            } catch (Exception $e) {
                $errors[] = 'Failed to update transaction: ' . $e->getMessage();
            }
        } else {
            $transaction = array_merge($transaction, $formData);
        }
    }
}

$categories = Transaction::getCategories($companyId);

include __DIR__ . '/../views/header.php';
?>

<div class="page-header">
    <div style="display: flex; gap: 12px; align-items: center;">
        <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 12px; border-radius: 12px; color: white; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);">
            <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
        </div>
        <div>
            <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #1f2937;">Edit Transaction</h1>
            <p style="margin: 4px 0 0 0; color: #6b7280; font-size: 14px;">Modify transaction details or update receipt.</p>
        </div>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="<?= WEB_ROOT ?>/transactions/list.php?company=<?= $companyId ?>" 
           class="btn btn-white" style="border: 1px solid #d1d5db; color: #374151; font-weight: 500; display: flex; align-items: center; gap: 6px; padding: 8px 16px;">
            Cancel
        </a>
        <button type="submit" form="transactionForm"
                style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; color: white; padding: 8px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Save
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="background-color: #fef2f2; border-color: #fee2e2; color: #b91c1c; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
        <div style="font-weight: 600; margin-bottom: 8px;">Please correct the following errors:</div>
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="card" style="max-width: 900px; margin: 0 auto; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #f3f4f6;">
        <div style="background: #f9fafb; padding: 20px 32px; border-bottom: 1px solid #e5e7eb;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                 <h2 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0;">Transaction Details</h2>
                 <span style="font-size: 13px; color: #6b7280;">ID: #<?= $transactionId ?></span>
            </div>
        </div>
        
        <form id="transactionForm" method="POST" enctype="multipart/form-data" style="padding: 32px;">
            
            <!-- Type -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Transaction Type <span style="color: #ef4444">*</span></label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <label style="cursor: pointer; display: block;">
                        <input type="radio" name="type" value="in" <?= $transaction['type'] === 'in' ? 'checked' : '' ?> style="display: none;" onchange="updateTypeCard()">
                        <div class="type-card type-card-in" style="border: 2px solid <?= $transaction['type'] === 'in' ? '#10b981' : '#e5e7eb' ?>; background: <?= $transaction['type'] === 'in' ? '#ecfdf5' : 'white' ?>; border-radius: 12px; padding: 16px; text-align: center; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">Cash In</div>
                            <div style="font-size: 12px; color: #6b7280;">Income, Deposits</div>
                        </div>
                    </label>
                    <label style="cursor: pointer; display: block;">
                        <input type="radio" name="type" value="out" <?= $transaction['type'] === 'out' ? 'checked' : '' ?> style="display: none;" onchange="updateTypeCard()">
                        <div class="type-card type-card-out" style="border: 2px solid <?= $transaction['type'] === 'out' ? '#ef4444' : '#e5e7eb' ?>; background: <?= $transaction['type'] === 'out' ? '#fef2f2' : 'white' ?>; border-radius: 12px; padding: 16px; text-align: center; transition: all 0.2s;">
                            <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">Cash Out</div>
                            <div style="font-size: 12px; color: #6b7280;">Expenses, Withdrawals</div>
                        </div>
                    </label>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <!-- Amount -->
                <div class="form-group">
                    <label for="amount" style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Amount <span style="color: #ef4444">*</span></label>
                    <div style="position: relative;">
                        <div style="position: absolute; left: 12px; top: 10px; color: #6b7280; font-weight: 500;">₱</div>
                        <input type="number" step="0.01" id="amount" name="amount" required
                               value="<?= htmlspecialchars($transaction['amount']) ?>"
                               style="width: 100%; padding: 10px 12px 10px 32px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 16px; font-weight: 600;">
                    </div>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label for="transaction_date" style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Date <span style="color: #ef4444">*</span></label>
                    <input type="date" id="transaction_date" name="transaction_date" required
                           value="<?= htmlspecialchars($transaction['transaction_date']) ?>"
                           style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px;">
                </div>
            </div>

            <!-- Category -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="category" style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Category</label>
                <input type="text" id="category" name="category" 
                       value="<?= htmlspecialchars($transaction['category']) ?>"
                       placeholder="Type category"
                       style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px;">
            </div>
            
            <!-- Payment Method -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="payment_method" style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Payment Method</label>
                <select id="payment_method" name="payment_method" 
                        style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; background-color: #fff;">
                    <option value="cash" <?= $transaction['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bank_transfer" <?= $transaction['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="check" <?= $transaction['payment_method'] === 'check' ? 'selected' : '' ?>>Check</option>
                    <option value="other" <?= $transaction['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <!-- Description -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="description" style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Description / Notes</label>
                <textarea id="description" name="description" rows="3" 
                          placeholder="e.g. Monthly subscription payment"
                          style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px; resize: vertical;"><?= htmlspecialchars($transaction['description']) ?></textarea>
            </div>

            <!-- Reference Number -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label for="reference_number" style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Reference Number <span style="color: #9ca3af; font-weight: normal;">(Optional)</span></label>
                <input type="text" id="reference_number" name="reference_number" 
                       value="<?= htmlspecialchars($transaction['reference_number']) ?>"
                       placeholder="e.g. INV-2024-001"
                       style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 15px;">
            </div>

            <!-- Receipts -->
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">Receipts</label>
                <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px;">
                    
                    <!-- Existing Receipts -->
                    <?php if (!empty($currentReceipts)): ?>
                        <div style="margin-bottom: 16px;">
                            <?php foreach ($currentReceipts as $receipt): ?>
                                <?php 
                                    $ext = strtolower(pathinfo($receipt['file_path'], PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    $displayName = $receipt['original_name'] ?? basename($receipt['file_path']);
                                ?>
                                <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                    <div style="width: 48px; height: 48px; background: #f3f4f6; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                        <?php if ($isImage): ?>
                                            <img src="<?= WEB_ROOT ?>/<?= $receipt['file_path'] ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <svg style="width: 20px; height: 20px; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-size: 13px; font-weight: 500; color: #374151;"><?= htmlspecialchars($displayName) ?></div>
                                        <a href="<?= WEB_ROOT ?>/<?= $receipt['file_path'] ?>" target="_blank" style="font-size: 11px; color: #4f46e5;">View</a>
                                    </div>
                                    <button type="submit" form="delete-receipt-<?= $receipt['id'] ?>" onclick="return confirm('Delete this receipt?');" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 6px;">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- New Files Preview (before upload) -->
                    <div id="new-files-preview" style="margin-bottom: 16px; display: none;">
                        <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px;">Files Ready to Upload:</div>
                        <div id="preview-container"></div>
                    </div>
                    
                    <!-- Upload Options -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <label for="receipts" style="display: flex; flex-direction: column; align-items: center; padding: 20px; border: 2px dashed #e5e7eb; border-radius: 12px; background: #fff; cursor: pointer;">
                            <svg style="width: 32px; height: 32px; color: #6366f1; margin-bottom: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            <span style="font-size: 13px; font-weight: 600; color: #374151;">Upload Files</span>
                            <span style="font-size: 10px; color: #6b7280; margin-top: 4px;">Choose from device</span>
                        </label>
                        
                        <button type="button" onclick="openCamera()" style="display: flex; flex-direction: column; align-items: center; padding: 20px; border: 2px dashed #e5e7eb; border-radius: 12px; background: #fff; cursor: pointer;">
                            <svg style="width: 32px; height: 32px; color: #6366f1; margin-bottom: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <span style="font-size: 13px; font-weight: 600; color: #374151;">Take Photo</span>
                            <span style="font-size: 10px; color: #6b7280; margin-top: 4px;">Use camera</span>
                        </button>
                    </div>
                    
                    <input type="file" id="receipts" name="receipts[]" multiple accept="image/*,application/pdf" style="display: none;" onchange="updateFilePreview()">
                    <input type="file" id="camera-input" accept="image/*" capture="environment" style="display: none;" onchange="handleCameraCapture(this)">
                </div>
            </div>
            
            <!-- Camera Modal -->
            <div id="camera-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; align-items: center; justify-content: center;">
                <div style="position: relative; max-width: 640px; width: 90%;">
                    <video id="camera-stream" autoplay playsinline style="width: 100%; border-radius: 12px; background: #000;"></video>
                    <canvas id="camera-canvas" style="display: none;"></canvas>
                    <div style="display: flex; justify-content: center; gap: 16px; margin-top: 20px;">
                        <button type="button" onclick="capturePhoto()" style="background: #6366f1; color: white; border: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            📷 Capture
                        </button>
                        <button type="button" onclick="closeCamera()" style="background: #ef4444; color: white; border: none; padding: 12px 32px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            ✕ Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div style="padding-top: 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between;">
                <button type="submit" name="delete" value="1" onclick="return confirm('Delete this transaction?');" style="background: white; border: 1px solid #ef4444; color: #ef4444; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Delete Transaction
                </button>
                
                <div style="display: flex; gap: 12px;">
                    <a href="<?= WEB_ROOT ?>/transactions/list.php?company=<?= $companyId ?>" style="padding: 10px 20px; color: #4b5563; font-weight: 500; text-decoration: none;">Cancel</a>
                    <button type="submit" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none; color: white; padding: 10px 32px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Forms -->
<?php if (!empty($currentReceipts)): ?>
    <?php foreach ($currentReceipts as $receipt): ?>
        <form id="delete-receipt-<?= $receipt['id'] ?>" method="POST" style="display: none;">
            <input type="hidden" name="delete_receipt_id" value="<?= $receipt['id'] ?>">
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<script>
let cameraStream = null;
let capturedPhotos = [];

// Open camera using mobile file input (fallback for desktop)
function openCamera() {
    // Try to use getUserMedia for desktop/laptop
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'environment' }
        })
        .then(function(stream) {
            cameraStream = stream;
            const video = document.getElementById('camera-stream');
            video.srcObject = stream;
            document.getElementById('camera-modal').style.display = 'flex';
        })
        .catch(function(err) {
            // Fallback to file input with camera capture
            console.log('Camera access denied or not available, using file input');
            document.getElementById('camera-input').click();
        });
    } else {
        // Mobile device - use file input with capture
        document.getElementById('camera-input').click();
    }
}

// Close camera and stop stream
function closeCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    document.getElementById('camera-modal').style.display = 'none';
}

// Capture photo from video stream
function capturePhoto() {
    const video = document.getElementById('camera-stream');
    const canvas = document.getElementById('camera-canvas');
    const context = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    canvas.toBlob(function(blob) {
        const file = new File([blob], 'camera-capture-' + Date.now() + '.jpg', { type: 'image/jpeg' });
        
        // Create a DataTransfer to add files to input
        const dataTransfer = new DataTransfer();
        
        // Add existing files
        const existingFiles = document.getElementById('receipts').files;
        for (let i = 0; i < existingFiles.length; i++) {
            dataTransfer.items.add(existingFiles[i]);
        }
        
        // Add new captured photo
        dataTransfer.items.add(file);
        
        // Update input files
        document.getElementById('receipts').files = dataTransfer.files;
        
        closeCamera();
        updateFilePreview();
    }, 'image/jpeg', 0.9);
}

// Handle camera input file selection (mobile)
function handleCameraCapture(input) {
    if (input.files && input.files.length > 0) {
        const dataTransfer = new DataTransfer();
        
        // Add existing files from main input
        const existingFiles = document.getElementById('receipts').files;
        for (let i = 0; i < existingFiles.length; i++) {
            dataTransfer.items.add(existingFiles[i]);
        }
        
        // Add new camera photo
        for (let i = 0; i < input.files.length; i++) {
            dataTransfer.items.add(input.files[i]);
        }
        
        // Update main input
        document.getElementById('receipts').files = dataTransfer.files;
        
        // Reset camera input
        input.value = '';
        
        updateFilePreview();
    }
}

// Update type card styling when selection changes
function updateTypeCard() {
    const typeIn = document.querySelector('input[name="type"][value="in"]');
    const typeOut = document.querySelector('input[name="type"][value="out"]');
    const cardIn = document.querySelector('.type-card-in');
    const cardOut = document.querySelector('.type-card-out');
    
    if (typeIn.checked) {
        cardIn.style.border = '2px solid #10b981';
        cardIn.style.background = '#ecfdf5';
        cardOut.style.border = '2px solid #e5e7eb';
        cardOut.style.background = 'white';
    } else {
        cardOut.style.border = '2px solid #ef4444';
        cardOut.style.background = '#fef2f2';
        cardIn.style.border = '2px solid #e5e7eb';
        cardIn.style.background = 'white';
    }
}

// Update file preview when files are selected
function updateFilePreview() {
    const fileInput = document.getElementById('receipts');
    const previewSection = document.getElementById('new-files-preview');
    const previewContainer = document.getElementById('preview-container');
    
    if (fileInput.files.length === 0) {
        previewSection.style.display = 'none';
        return;
    }
    
    previewSection.style.display = 'block';
    previewContainer.innerHTML = '';
    
    Array.from(fileInput.files).forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.style.cssText = 'background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px; margin-bottom: 8px;';
        
        const thumbnail = document.createElement('div');
        thumbnail.style.cssText = 'width: 48px; height: 48px; background: white; border-radius: 6px; display: flex; align-items: center; justify-content: center; overflow: hidden;';
        
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
            const reader = new FileReader();
            reader.onload = (e) => { img.src = e.target.result; };
            reader.readAsDataURL(file);
            thumbnail.appendChild(img);
        } else {
            thumbnail.innerHTML = '<svg style="width: 20px; height: 20px; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>';
        }
        
        const fileInfo = document.createElement('div');
        fileInfo.style.cssText = 'flex: 1;';
        fileInfo.innerHTML = `
            <div style="font-size: 13px; font-weight: 500; color: #374151;">${file.name}</div>
            <div style="font-size: 11px; color: #16a34a;">${(file.size / 1024).toFixed(1)} KB - Ready to upload</div>
        `;
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.style.cssText = 'background: none; border: none; color: #ef4444; cursor: pointer; padding: 6px;';
        removeBtn.innerHTML = '<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
        removeBtn.onclick = () => removeFileFromPreview(index);
        
        fileItem.appendChild(thumbnail);
        fileItem.appendChild(fileInfo);
        fileItem.appendChild(removeBtn);
        previewContainer.appendChild(fileItem);
    });
}

// Remove file from preview
function removeFileFromPreview(index) {
    const fileInput = document.getElementById('receipts');
    const dataTransfer = new DataTransfer();
    
    Array.from(fileInput.files).forEach((file, i) => {
        if (i !== index) {
            dataTransfer.items.add(file);
        }
    });
    
    fileInput.files = dataTransfer.files;
    updateFilePreview();
}
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>

