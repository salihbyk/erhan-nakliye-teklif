<?php
// Active page detection
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Modern Sidebar -->
<nav class="modern-sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <div class="logo-text">
                <h4>Europatrans</h4>
                <small>Admin Panel</small>
            </div>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                <span class="user-role">Yönetici</span>
            </div>
        </div>
    </div>

    <div class="sidebar-content">
        <div class="nav-section">
            <span class="nav-section-title">Ana Menü</span>
            <div class="nav-items">
                <a href="index.php" class="nav-item <?= $current_page === 'index.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <span class="nav-text">Dashboard</span>
                    <div class="nav-indicator"></div>
                </a>

                <a href="quotes.php" class="nav-item <?= $current_page === 'quotes.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <span class="nav-text">Teklifler</span>
                    <div class="nav-indicator"></div>
                </a>

                <a href="customers.php" class="nav-item <?= $current_page === 'customers.php' || $current_page === 'view-customer.php' || $current_page === 'edit-customer.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="nav-text">Müşteriler</span>
                    <div class="nav-indicator"></div>
                </a>
            </div>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Yönetim</span>
            <div class="nav-items">
                <a href="transport-modes.php" class="nav-item <?= $current_page === 'transport-modes.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <span class="nav-text">Taşıma Modları</span>
                    <div class="nav-indicator"></div>
                </a>

                <a href="cost-lists.php" class="nav-item <?= $current_page === 'cost-lists.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <span class="nav-text">Maliyet Listeleri</span>
                    <div class="nav-indicator"></div>
                </a>

                <a href="email-templates.php" class="nav-item <?= $current_page === 'email-templates.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <span class="nav-text">E-posta Şablonları</span>
                    <div class="nav-indicator"></div>
                </a>
            </div>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Sistem</span>
            <div class="nav-items">
                <a href="settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <span class="nav-text">Ayarlar</span>
                    <div class="nav-indicator"></div>
                </a>

                <a href="update-manager.php" class="nav-item <?= $current_page === 'update-manager.php' ? 'active' : '' ?>">
                    <div class="nav-icon">
                        <i class="fas fa-cloud-download-alt"></i>
                    </div>
                    <span class="nav-text">Sistem Güncelleme</span>
                    <div class="nav-indicator"></div>
                </a>
            </div>
        </div>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item logout-item">
            <div class="nav-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <span class="nav-text">Çıkış Yap</span>
        </a>
    </div>
</nav>
