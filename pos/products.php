<?php
/**
 * Product Management - Add/Edit Products and Services
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Product.php';

requireLogin();

$userId = getCurrentUserId();
$companyId = getCurrentCompanyId();

if (!$companyId) {
    header('Location: ../index.php');
    exit;
}

requireCompanyAccess($companyId);

$company = Company::getById($companyId);
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        try {
            Product::create([
                'company_id' => $companyId,
                'product_name' => $_POST['product_name'],
                'description' => $_POST['description'] ?? null,
                'sku' => $_POST['sku'] ?? null,
                'price' => floatval($_POST['price']),
                'cost' => floatval($_POST['cost'] ?? 0),
                'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
                'track_inventory' => isset($_POST['track_inventory']) ? 1 : 0,
                'is_service' => isset($_POST['is_service']) ? 1 : 0,
                'category' => $_POST['category'] ?? null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ]);
            $message = 'Product added successfully';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'update') {
        try {
            Product::update($_POST['product_id'], $companyId, [
                'product_name' => $_POST['product_name'],
                'description' => $_POST['description'] ?? null,
                'sku' => $_POST['sku'] ?? null,
                'price' => floatval($_POST['price']),
                'cost' => floatval($_POST['cost'] ?? 0),
                'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
                'track_inventory' => isset($_POST['track_inventory']) ? 1 : 0,
                'is_service' => isset($_POST['is_service']) ? 1 : 0,
                'category' => $_POST['category'] ?? null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ]);
            $message = '✓ Product "' . $_POST['product_name'] . '" updated successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    try {
        Product::delete($_GET['delete'], $companyId);
        header("Location: products.php?company=$companyId&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = 'Error deleting product: ' . $e->getMessage();
        $messageType = 'error';
    }
}

if (isset($_GET['deleted'])) {
    $message = '✓ Product deleted successfully!';
    $messageType = 'success';
}

// Get products
$products = Product::getByCompany($companyId, false);
$categories = Product::getCategories($companyId);

// Get product for editing
$editProduct = null;
if (isset($_GET['edit'])) {
    $editProduct = Product::getById($_GET['edit'], $companyId);
}

$pageTitle = 'Products & Services';
require_once __DIR__ . '/../views/header.php';
?>

<style>
    .products-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    
    .product-grid {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    
    .product-form {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        height: fit-content;
        position: sticky;
        top: 20px;
    }
    
    .product-form h2 {
        margin: 0 0 20px 0;
        font-size: 20px;
        color: #1f2937;
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
    }
    
    .checkbox-group label {
        margin: 0;
        font-size: 14px;
        color: #374151;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        width: 100%;
    }
    
    .btn-secondary {
        background: #6b7280;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        margin-top: 8px;
        width: 100%;
    }
    
    .products-list {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .products-list h2 {
        margin: 0 0 20px 0;
        font-size: 20px;
        color: #1f2937;
    }
    
    .product-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: start;
        transition: all 0.2s;
    }
    
    .product-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #3b82f6;
    }
    
    .product-info h3 {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #1f2937;
    }
    
    .product-info p {
        margin: 4px 0;
        font-size: 14px;
        color: #6b7280;
    }
    
    .product-price {
        font-size: 20px;
        font-weight: 700;
        color: #10b981;
        margin-top: 8px;
    }
    
    .product-actions {
        display: flex;
        gap: 8px;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
    }
    
    .btn-edit {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .btn-delete {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
        margin-left: 8px;
    }
    
    .badge-service {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .badge-product {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
</style>

<div class="products-container">
    <div class="page-header">
        <div>
            <h1>Products & Services</h1>
            <p style="margin: 0; color: #6b7280;"><?= htmlspecialchars($company['name']) ?></p>
        </div>
        <a href="index.php?company=<?= $companyId ?>" target="_blank" class="btn btn-success">🛒 Open POS</a>
    </div>
    
    <div class="product-grid">
        <div class="product-form">
            <h2><?= $editProduct ? 'Edit Product/Service' : 'Add New Product/Service' ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?= $editProduct ? 'update' : 'create' ?>">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="product_id" value="<?= $editProduct['product_id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="product_name" required 
                           value="<?= htmlspecialchars($editProduct['product_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>SKU / Code</label>
                    <input type="text" name="sku" value="<?= htmlspecialchars($editProduct['sku'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" list="categories" 
                           value="<?= htmlspecialchars($editProduct['category'] ?? '') ?>">
                    <datalist id="categories">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label>Price (₱) *</label>
                    <input type="number" name="price" step="0.01" required 
                           value="<?= $editProduct['price'] ?? '0.00' ?>">
                </div>
                
                <div class="form-group">
                    <label>Cost (₱)</label>
                    <input type="number" name="cost" step="0.01" 
                           value="<?= $editProduct['cost'] ?? '0.00' ?>">
                </div>
                
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" name="stock_quantity" 
                           value="<?= $editProduct['stock_quantity'] ?? '0' ?>">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="track_inventory" id="track_inventory" 
                           <?= ($editProduct['track_inventory'] ?? 0) ? 'checked' : '' ?>>
                    <label for="track_inventory">Track Inventory</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_service" id="is_service" 
                           <?= ($editProduct['is_service'] ?? 0) ? 'checked' : '' ?>>
                    <label for="is_service">This is a Service</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active" 
                           <?= ($editProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="is_active">Active</label>
                </div>
                
                <button type="submit" class="btn-primary">
                    <?= $editProduct ? 'Update Product' : 'Add Product' ?>
                </button>
                
                <?php if ($editProduct): ?>
                    <a href="products.php?company=<?= $companyId ?>" class="btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="products-list">
            <h2>All Products & Services (<?= count($products) ?>)</h2>
            
            <?php if (empty($products)): ?>
                <p style="text-align: center; color: #6b7280; padding: 40px;">
                    No products added yet. Create your first product to start using POS.
                </p>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-info">
                            <h3>
                                <?= htmlspecialchars($product['product_name']) ?>
                                <?php if ($product['is_service']): ?>
                                    <span class="badge badge-service">Service</span>
                                <?php else: ?>
                                    <span class="badge badge-product">Product</span>
                                <?php endif; ?>
                                <?php if (!$product['is_active']): ?>
                                    <span class="badge badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </h3>
                            <?php if ($product['description']): ?>
                                <p><?= htmlspecialchars($product['description']) ?></p>
                            <?php endif; ?>
                            <?php if ($product['sku']): ?>
                                <p><strong>SKU:</strong> <?= htmlspecialchars($product['sku']) ?></p>
                            <?php endif; ?>
                            <?php if ($product['category']): ?>
                                <p><strong>Category:</strong> <?= htmlspecialchars($product['category']) ?></p>
                            <?php endif; ?>
                            <?php if ($product['track_inventory']): ?>
                                <p><strong>Stock:</strong> <?= $product['stock_quantity'] ?> units</p>
                            <?php endif; ?>
                            <div class="product-price">₱<?= number_format($product['price'], 2) ?></div>
                        </div>
                        <div class="product-actions">
                            <a href="products.php?company=<?= $companyId ?>&edit=<?= $product['product_id'] ?>" 
                               class="btn-sm btn-edit">Edit</a>
                            <button onclick="if(confirm('Delete this product?')) window.location.href='products.php?company=<?= $companyId ?>&delete=<?= $product['product_id'] ?>'" 
                                    class="btn-sm btn-delete">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Show notification if there's a message
    <?php if ($message): ?>
        showNotification('<?= addslashes($message) ?>', '<?= $messageType ?>', 4000);
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
