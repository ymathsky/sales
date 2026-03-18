<?php
/**
 * POS - Point of Sale System
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/POSSale.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Membership.php';
require_once __DIR__ . '/../models/Customer.php';

requireLogin();

$userId = getCurrentUserId();
$companyId = getCurrentCompanyId();

if (!$companyId) {
    header('Location: ../index.php');
    exit;
}

requireCompanyAccess($companyId);

$company = Company::getById($companyId);

// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    try {
        $items = json_decode($_POST['items'], true);
        
        if (empty($items)) {
            throw new Exception('No items in cart');
        }
        
        $subtotal = floatval($_POST['subtotal']);
        $taxAmount = floatval($_POST['tax_amount']);
        $discountAmount = floatval($_POST['discount_amount']);
        $totalAmount = floatval($_POST['total_amount']);
        $paymentReceived = floatval($_POST['payment_received']);
        $changeAmount = floatval($_POST['change_amount']);
        $customerId = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        
        // Handle Memberships if present
        $hasMembership = false; 
        foreach ($items as $item) {
            if (isset($item['type']) && $item['type'] === 'membership') {
                $hasMembership = true;
                break;
            }
        }

        if ($hasMembership) {
            if (!$customerId) {
                throw new Exception('Customer selection is required when selling a membership/subscription.');
            }
            // Process subscriptions
            foreach ($items as $item) {
                if (isset($item['type']) && $item['type'] === 'membership') {
                    Membership::addMembership($customerId, $item['plan_id']);
                }
            }
        }

        // Create POS sale
        $result = POSSale::create([
            'company_id' => $companyId,
            'sale_date' => date('Y-m-d H:i:s'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $_POST['payment_method'],
            'payment_received' => $paymentReceived,
            'change_amount' => $changeAmount,
            'customer_name' => $_POST['customer_name'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'created_by' => $userId
        ], $items);
        
        // Create transaction
        $transactionId = Transaction::create([
            'company_id' => $companyId,
            'type' => 'in',
            'amount' => $totalAmount,
            'transaction_date' => date('Y-m-d'),
            'category' => 'POS Sale',
            'description' => 'POS Sale #' . $result['sale_number'],
            'payment_method' => $_POST['payment_method'] === 'cash' ? 'cash' : 'bank_transfer',
            'transaction_account' => $_POST['payment_method'] === 'cash' ? 'cash' : 'bank',
            'created_by' => $userId
        ]);
        
        // Link transaction to sale
        POSSale::linkTransaction($result['sale_id'], $companyId, $transactionId);
        
        // Redirect to receipt
        header("Location: receipt.php?sale_id={$result['sale_id']}&company=$companyId");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get products
$products = Product::getByCompany($companyId, true);
$categories = Product::getCategories($companyId);
$customers = Customer::getByCompany($companyId, true);
$membershipPlans = Membership::getPlans($companyId, true);

$pageTitle = 'Point of Sale';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= htmlspecialchars($company['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f3f4f6;
            height: 100vh;
            overflow: hidden;
        }
        
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            height: 100vh;
        }
        
        .products-section {
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .pos-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pos-header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-white {
            background: white;
            color: #667eea;
        }
        
        .search-bar {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .categories {
            padding: 10px 20px;
            display: flex;
            gap: 8px;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }
        
        .category-btn {
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            font-size: 14px;
        }
        
        .category-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .products-grid {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            align-content: start;
        }
        
        .product-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .product-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .product-card h3 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #1f2937;
            min-height: 40px;
        }
        
        .product-card .price {
            font-size: 18px;
            font-weight: 700;
            color: #10b981;
        }
        
        .product-card .stock {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .cart-section {
            background: white;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #e5e7eb;
        }
        
        .cart-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .cart-header h2 {
            font-size: 18px;
            color: #1f2937;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        .cart-item {
            padding: 12px;
            border-radius: 8px;
            background: #f9fafb;
            margin-bottom: 8px;
        }
        
        .cart-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 14px;
        }
        
        .cart-item-remove {
            color: #ef4444;
            cursor: pointer;
            font-size: 18px;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .qty-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .qty-btn {
            width: 28px;
            height: 28px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 16px;
        }
        
        .qty-display {
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }
        
        .item-total {
            font-weight: 700;
            color: #10b981;
        }
        
        .cart-summary {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
            border-top: 2px solid #e5e7eb;
            padding-top: 12px;
            margin-top: 8px;
        }
        
        .payment-section {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .btn-checkout {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
        }
        
        .btn-checkout:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }
        
        .change-display {
            text-align: center;
            padding: 12px;
            background: #d1fae5;
            border-radius: 8px;
            margin-top: 12px;
        }
        
        .change-display .label {
            font-size: 12px;
            color: #065f46;
            font-weight: 600;
        }
        
        .change-display .amount {
            font-size: 24px;
            font-weight: 700;
            color: #10b981;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }
        
        .empty-cart-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="pos-container">
        <!-- Products Section -->
        <div class="products-section">
            <div class="pos-header">
                <div>
                    <h1>🛒 Point of Sale</h1>
                    <p style="font-size: 14px; opacity: 0.9;"><?= htmlspecialchars($company['name']) ?></p>
                </div>
                <div class="header-actions">
                    <a href="products.php?company=<?= $companyId ?>" target="_blank" class="btn btn-white">📦 Products</a>
                    <a href="sales.php?company=<?= $companyId ?>" target="_blank" class="btn btn-white">📊 Sales</a>
                    <a href="../index.php?company=<?= $companyId ?>" class="btn btn-white">← Dashboard</a>
                </div>
            </div>
            
            <div class="search-bar">
                <input type="text" class="search-input" id="searchInput" placeholder="🔍 Search products...">
            </div>
            
            <div class="categories">
                <button class="category-btn active" onclick="filterCategory('')">All</button>
                <button class="category-btn" onclick="filterCategory('membership')">⭐ Memberships</button>
                <?php foreach ($categories as $category): ?>
                    <button class="category-btn" onclick="filterCategory('<?= htmlspecialchars($category) ?>')">
                        <?= htmlspecialchars($category) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="products-grid" id="productsGrid">
                <!-- Memberships -->
                <?php foreach ($membershipPlans as $plan): ?>
                    <div class="product-card" 
                         data-category="membership"
                         data-name="<?= htmlspecialchars($plan['name']) ?>"
                         onclick='addMembershipToCart(<?= json_encode($plan) ?>)'
                         style="border-left: 4px solid #f59e0b; background: #fffbeb;">
                        <h3>⭐ <?= htmlspecialchars($plan['name']) ?></h3>
                        <div class="price">₱<?= number_format($plan['price'], 2) ?></div>
                        <div class="stock" style="color: #d97706;"><?= $plan['duration_days'] ?> Days</div>
                    </div>
                <?php endforeach; ?>

                <!-- Products -->
                <?php foreach ($products as $product): ?>
                    <div class="product-card" 
                         data-category="<?= htmlspecialchars($product['category'] ?? '') ?>"
                         data-name="<?= htmlspecialchars($product['product_name']) ?>"
                         onclick='addToCart(<?= json_encode($product) ?>)'>
                        <h3><?= htmlspecialchars($product['product_name']) ?></h3>
                        <div class="price">₱<?= number_format($product['price'], 2) ?></div>
                        <?php if ($product['track_inventory']): ?>
                            <div class="stock">Stock: <?= $product['stock_quantity'] ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Cart Section -->
        <div class="cart-section">
            <div class="cart-header">
                <h2>🛒 Current Sale</h2>
            </div>
            
            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛍️</div>
                    <div>Add items to start a sale</div>
                </div>
            </div>
            
            <div class="cart-summary" id="cartSummary" style="display: none;">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="subtotalDisplay">₱0.00</span>
                </div>
                <div class="summary-row">
                    <span>Discount:</span>
                    <span id="discountDisplay">₱0.00</span>
                </div>
                <div class="summary-row total">
                    <span>TOTAL:</span>
                    <span id="totalDisplay">₱0.00</span>
                </div>
            </div>
            
            <form method="POST" id="checkoutForm" class="payment-section" style="display: none;">
                <input type="hidden" name="action" value="complete_sale">
                <input type="hidden" name="items" id="itemsInput">
                <input type="hidden" name="subtotal" id="subtotalInput">
                <input type="hidden" name="tax_amount" id="taxInput">
                <input type="hidden" name="discount_amount" id="discountInput">
                <input type="hidden" name="total_amount" id="totalInput">
                <input type="hidden" name="change_amount" id="changeInput">
                
                <div class="form-group">
                    <label>Customer</label>
                    <select name="customer_id" id="customerId" class="form-control" onchange="updateCustomerName()">
                        <option value="">Guest Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['customer_id'] ?>" data-name="<?= htmlspecialchars($c['customer_name']) ?>">
                                <?= htmlspecialchars($c['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="customer_name" id="customerName">
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" id="paymentMethod" required>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Amount Received (₱)</label>
                    <input type="number" name="payment_received" id="paymentReceived" 
                           step="0.01" min="0" required onkeyup="calculateChange()">
                </div>
                
                <div class="change-display" id="changeDisplay" style="display: none;">
                    <div class="label">CHANGE</div>
                    <div class="amount" id="changeAmount">₱0.00</div>
                </div>
                
                <button type="submit" class="btn-checkout" id="checkoutBtn" disabled>
                    💳 Complete Sale
                </button>
            </form>
        </div>
    </div>

    <script>
        let cart = [];
        let currentCategory = '';
        
        function updateCustomerName() {
            const select = document.getElementById('customerId');
            const input = document.getElementById('customerName');
            if (select.selectedIndex > 0) {
                input.value = select.options[select.selectedIndex].dataset.name;
            } else {
                input.value = ''; // Guest
            }
        }

        function addMembershipToCart(plan) {
             // Check if already in cart
            const existingIndex = cart.findIndex(item => item.type === 'membership' && item.plan_id === plan.plan_id);
            
            if (existingIndex >= 0) {
                cart[existingIndex].quantity++;
                cart[existingIndex].line_total = cart[existingIndex].quantity * cart[existingIndex].unit_price;
            } else {
                cart.push({
                    product_id: null,
                    plan_id: plan.plan_id,
                    type: 'membership',
                    product_name: '⭐ ' + plan.name,
                    quantity: 1,
                    unit_price: parseFloat(plan.price),
                    discount_amount: 0,
                    line_total: parseFloat(plan.price)
                });
            }
            renderCart();
        }

        function addToCart(product) {
            // Check if product already in cart
            const existingIndex = cart.findIndex(item => item.product_id === product.product_id);
            
            if (existingIndex >= 0) {
                // Increase quantity
                cart[existingIndex].quantity++;
                cart[existingIndex].line_total = cart[existingIndex].quantity * cart[existingIndex].unit_price;
            } else {
                // Add new item
                cart.push({
                    product_id: product.product_id,
                    product_name: product.product_name,
                    quantity: 1,
                    unit_price: parseFloat(product.price),
                    discount_amount: 0,
                    line_total: parseFloat(product.price)
                });
            }
            
            renderCart();
        }
        
        function removeFromCart(index) {
            cart.splice(index, 1);
            renderCart();
        }
        
        function updateQuantity(index, change) {
            cart[index].quantity += change;
            if (cart[index].quantity <= 0) {
                removeFromCart(index);
            } else {
                cart[index].line_total = cart[index].quantity * cart[index].unit_price;
                renderCart();
            }
        }
        
        function renderCart() {
            const cartItems = document.getElementById('cartItems');
            const cartSummary = document.getElementById('cartSummary');
            const checkoutForm = document.getElementById('checkoutForm');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛍️</div>
                        <div>Add items to start a sale</div>
                    </div>
                `;
                cartSummary.style.display = 'none';
                checkoutForm.style.display = 'none';
                return;
            }
            
            // Render cart items
            cartItems.innerHTML = cart.map((item, index) => `
                <div class="cart-item">
                    <div class="cart-item-header">
                        <span class="cart-item-name">${item.product_name}</span>
                        <span class="cart-item-remove" onclick="removeFromCart(${index})">×</span>
                    </div>
                    <div class="cart-item-controls">
                        <div class="qty-controls">
                            <button type="button" class="qty-btn" onclick="updateQuantity(${index}, -1)">−</button>
                            <span class="qty-display">${item.quantity}</span>
                            <button type="button" class="qty-btn" onclick="updateQuantity(${index}, 1)">+</button>
                        </div>
                        <span class="item-total">₱${item.line_total.toFixed(2)}</span>
                    </div>
                </div>
            `).join('');
            
            // Calculate totals
            const subtotal = cart.reduce((sum, item) => sum + item.line_total, 0);
            const discount = 0;
            const total = subtotal - discount;
            
            document.getElementById('subtotalDisplay').textContent = `₱${subtotal.toFixed(2)}`;
            document.getElementById('discountDisplay').textContent = `₱${discount.toFixed(2)}`;
            document.getElementById('totalDisplay').textContent = `₱${total.toFixed(2)}`;
            
            document.getElementById('itemsInput').value = JSON.stringify(cart);
            document.getElementById('subtotalInput').value = subtotal.toFixed(2);
            document.getElementById('taxInput').value = '0.00';
            document.getElementById('discountInput').value = discount.toFixed(2);
            document.getElementById('totalInput').value = total.toFixed(2);
            
            cartSummary.style.display = 'block';
            checkoutForm.style.display = 'block';
        }
        
        function calculateChange() {
            const total = parseFloat(document.getElementById('totalInput').value);
            const received = parseFloat(document.getElementById('paymentReceived').value) || 0;
            const change = received - total;
            
            document.getElementById('changeInput').value = change.toFixed(2);
            
            if (change >= 0) {
                document.getElementById('changeDisplay').style.display = 'block';
                document.getElementById('changeAmount').textContent = `₱${change.toFixed(2)}`;
                document.getElementById('checkoutBtn').disabled = false;
            } else {
                document.getElementById('changeDisplay').style.display = 'none';
                document.getElementById('checkoutBtn').disabled = true;
            }
        }
        
        function filterCategory(category) {
            currentCategory = category;
            
            // Update active button
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            filterProducts();
        }
        
        function filterProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const products = document.querySelectorAll('.product-card');
            
            products.forEach(product => {
                const name = product.dataset.name.toLowerCase();
                const category = product.dataset.category;
                
                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = !currentCategory || category === currentCategory;
                
                product.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
            });
        }
        
        document.getElementById('searchInput').addEventListener('keyup', filterProducts);
        
        // Auto-focus payment received when cart has items
        setInterval(() => {
            if (cart.length > 0 && !document.getElementById('paymentReceived').value) {
                document.getElementById('paymentReceived').focus();
            }
        }, 1000);
    </script>
</body>
</html>
