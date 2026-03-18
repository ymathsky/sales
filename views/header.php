<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Flow System</title>
    <link rel="stylesheet" href="<?= WEB_ROOT ?>/assets/css/style.css">
    <script>
        const webRoot = "<?= WEB_ROOT ?>";
    </script>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <div class="app-wrapper">
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" onclick="toggleSidebar()">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" onclick="toggleSidebar()"></div>
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="<?= WEB_ROOT ?>/index.php" class="sidebar-brand">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Cash Flow
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Main</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/index.php" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                                Dashboard
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Transactions</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/transactions/list.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'list.php' ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                All Transactions
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/transactions/create.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'create.php' ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Transaction
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Receivables</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/ar/dashboard.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/ar/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                AR Dashboard
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/invoices/list.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/invoices/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Invoices
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/customers/list.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/customers/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Clients
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Point of Sale</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/pos/index.php?company=<?= getCurrentCompanyId() ?>" target="_blank" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], '/pos/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                POS Cashier
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;margin-left:auto;opacity:0.5;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/pos/products.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' && strpos($_SERVER['PHP_SELF'], '/pos/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                                Products
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/pos/sales.php?company=<?= getCurrentCompanyId() ?>" target="_blank" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'sales.php' && strpos($_SERVER['PHP_SELF'], '/pos/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Sales History
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;margin-left:auto;opacity:0.5;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Memberships</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/memberships/list.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/memberships/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Subscriptions
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/memberships/plans.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/plans/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                Plans
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Reports</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/reports/index.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Financial Reports
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Reconciliation</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/reconciliation/index.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], '/reconciliation/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                Bank Reconciliation
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/reconciliation/opening-balance.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'opening-balance.php' ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Opening Balance
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/reconciliation/fund-transfers.php?company=<?= getCurrentCompanyId() ?>" class="sidebar-menu-link <?= basename($_SERVER['PHP_SELF']) == 'fund-transfers.php' ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                                Fund Transfers
                            </a>
                        </li>
                    </ul>
                </div>
                
                <?php if (getCurrentUserRole() === 'admin'): ?>
                <div class="sidebar-section">
                    <h3 class="sidebar-section-title">Administration</h3>
                    <ul class="sidebar-menu">
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/companies/list.php" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/companies/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                Companies
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/categories/list.php" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/categories/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                Categories
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/users/list.php" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                Users
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="<?= WEB_ROOT ?>/settings/index.php" class="sidebar-menu-link <?= strpos($_SERVER['PHP_SELF'], '/settings/') !== false ? 'active' : '' ?>">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Settings
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        <?= strtoupper(substr(getCurrentUserName(), 0, 1)) ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= htmlspecialchars(getCurrentUserName()) ?></div>
                        <div class="sidebar-user-role"><?= htmlspecialchars(getCurrentUserRole() ?? 'user') ?></div>
                    </div>
                </div>
                <a href="<?= WEB_ROOT ?>/auth/logout.php" class="btn btn-sm btn-secondary btn-block">Logout</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            
            <!-- Content Area with Company Selector -->
            <header class="main-header">
                <div class="header-right" style="margin-left: auto;">
                    <?php 
                    if (!class_exists('Company')) {
                        require_once __DIR__ . '/../models/Company.php';
                    }
                    $companies = Company::getByUser(getCurrentUserId());
                    $currentCompanyId = isset($companyId) ? $companyId : getCurrentCompanyId();
                    if (!empty($companies)): 
                    ?>
                    <div class="company-selector-wrapper">
                        <label>Company:</label>
                        <select id="companySelector" onchange="console.log('Dropdown changed to:', this.value); switchCompany(this.value);">
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?= $comp['company_id'] ?>" 
                                        <?= $comp['company_id'] == $currentCompanyId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comp['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </header>
            
            <main class="container">
                <div class="content-wrapper">
    <?php else: ?>
    <body class="login-page">
    <?php endif; ?>
