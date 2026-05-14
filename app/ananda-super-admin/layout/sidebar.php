<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

$admin_info = getAnandaSuperAdminInfo();
$admin_role = $admin_info['admin_role'] ?? null;
$is_super_admin = ($admin_role === 'Super Admin');
?>
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard.php" data-toggle="tooltip" title="Dashboard">
        <div class="sidebar-brand-icon">
            <p class="mt-0 mb-0"><?= htmlspecialchars($app_name); ?></p>
        </div>
    </a>

    <hr class="sidebar-divider my-0">

    <li class="nav-item" data-toggle="tooltip" title="Dashboard">
        <a class="nav-link" href="dashboard.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading text-white">Core Operations</div>

    <li class="nav-item" data-toggle="tooltip" title="Manage Products">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#productsCollapse" aria-expanded="true" aria-controls="productsCollapse">
            <i class="fas fa-fw fa-box"></i>
            <span>Products</span>
        </a>
        <div id="productsCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="products.php">View Products</a>
                <a class="collapse-item" href="add_product.php">Add Product</a>
            </div>
        </div>
    </li>

    <li class="nav-item" data-toggle="tooltip" title="Manage Customers">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#customersCollapse" aria-expanded="true" aria-controls="customersCollapse">
            <i class="fas fa-fw fa-users"></i>
            <span>Customers</span>
        </a>
        <div id="customersCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="customers.php">View Customers</a>
                <a class="collapse-item" href="add_customer.php">Add Customer</a>
            </div>
        </div>
    </li>

    <li class="nav-item" data-toggle="tooltip" title="Manage Product Orders">
        <a class="nav-link" href="orders.php">
            <i class="fas fa-fw fa-shopping-cart"></i>
            <span>Product Orders</span>
        </a>
    </li>

    <li class="nav-item" data-toggle="tooltip" title="Manage Bill/Reload Orders">
        <a class="nav-link" href="bill_reload_orders.php">
            <i class="fas fa-fw fa-bolt"></i>
            <span>Bill/Reload Orders</span>
        </a>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading text-white">Configuration</div>

    <li class="nav-item" data-toggle="tooltip" title="Manage Services">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#servicesCollapse" aria-expanded="true" aria-controls="servicesCollapse">
            <i class="fas fa-fw fa-bolt"></i>
            <span>Services</span>
        </a>
        <div id="servicesCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="services.php">View Services</a>
                <a class="collapse-item" href="add_service.php">Add Service</a>
            </div>
        </div>
    </li>

    <li class="nav-item" data-toggle="tooltip" title="Manage Product Categories">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#productCategoriesCollapse" aria-expanded="true" aria-controls="productCategoriesCollapse">
            <i class="fas fa-fw fa-layer-group"></i>
            <span>Product Categories</span>
        </a>
        <div id="productCategoriesCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="product_categories.php">View Categories</a>
                <a class="collapse-item" href="add_product_category.php">Add Category</a>
            </div>
        </div>
    </li>

    <li class="nav-item" data-toggle="tooltip" title="Manage Product Units">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#productUnitsCollapse" aria-expanded="true" aria-controls="productUnitsCollapse">
            <i class="fas fa-fw fa-balance-scale"></i>
            <span>Product Units</span>
        </a>
        <div id="productUnitsCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="product_units.php">View Units</a>
                <a class="collapse-item" href="add_product_unit.php">Add Unit</a>
            </div>
        </div>
    </li>

    <?php if ($is_super_admin): ?>
    <li class="nav-item" data-toggle="tooltip" title="Manage Payment Methods">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#paymentMethodsCollapse" aria-expanded="true" aria-controls="paymentMethodsCollapse">
            <i class="fas fa-fw fa-credit-card"></i>
            <span>Payment Methods</span>
        </a>
        <div id="paymentMethodsCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="payment_methods.php">View Payment Methods</a>
                <a class="collapse-item" href="add_payment_method.php">Add Payment Method</a>
            </div>
        </div>
    </li>

    <li class="nav-item" data-toggle="tooltip" title="Manage Rates">
        <a class="nav-link" href="rates.php">
            <i class="fas fa-fw fa-percentage"></i>
            <span>Rates</span>
        </a>
    </li>
    <?php endif; ?>

    <hr class="sidebar-divider">

    <div class="sidebar-heading text-white">Marketing</div>

    <li class="nav-item" data-toggle="tooltip" title="Manage Offers">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#offersCollapse" aria-expanded="true" aria-controls="offersCollapse">
            <i class="fas fa-fw fa-tags"></i>
            <span>Offers</span>
        </a>
        <div id="offersCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="offers.php">View Offers</a>
                <a class="collapse-item" href="add_offer.php">Add Offer</a>
            </div>
        </div>
    </li>

    <hr class="sidebar-divider">

    <div class="sidebar-heading text-white">Admin Management</div>

    <li class="nav-item" data-toggle="tooltip" title="Manage Admins">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#adminsCollapse" aria-expanded="true" aria-controls="adminsCollapse">
            <i class="fas fa-fw fa-user-shield"></i>
            <span>Admins</span>
        </a>
        <div id="adminsCollapse" class="collapse" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <?php if ($is_super_admin): ?>
                <a class="collapse-item" href="admins.php">View Admins</a>
                <a class="collapse-item" href="add_admin.php">Add Admin</a>
                <?php else: ?>
                <a class="collapse-item" href="change_admin_password.php">Change Password</a>
                <?php endif; ?>
            </div>
        </div>
    </li>

    <li class="nav-item" data-toggle="tooltip" title="Change Password">
        <a class="nav-link" href="change_admin_password.php">
            <i class="fas fa-fw fa-key"></i>
            <span>Change Password</span>
        </a>
    </li>

    <hr class="sidebar-divider d-none d-md-block">

    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>

<script>
    $(function() {
        $('[data-toggle="tooltip"]').tooltip()
    })
</script>
