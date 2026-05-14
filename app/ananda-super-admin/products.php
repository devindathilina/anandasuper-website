<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "Products";
?>
<!DOCTYPE html>
<html lang="en">
<?php require_once 'layout/head.php'; ?>

<body id="page-top">
    <div id="wrapper">
        <?php require_once 'layout/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php require_once 'layout/topbar.php'; ?>
                <div class="container-fluid">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb bg-primary">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-light">Dashboard</a></li>
                        <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Products</li>
                    </ol>
                </nav>

                <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-box mr-2"></i>
                                        Products
                                    </h6>
                                </div>
                                <div class="col-md-4 text-right">
                                    <a href="add_product.php" class="btn btn-light btn-sm">
                                        <i class="fas fa-plus mr-1"></i> Add Product
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row mb-3" id="product-counts">
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Total</div>
                                            <div class="h4 mb-0 font-weight-bold" id="count-total">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-success">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Active</div>
                                            <div class="h4 mb-0 font-weight-bold text-success" id="count-active">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-secondary">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Inactive</div>
                                            <div class="h4 mb-0 font-weight-bold text-secondary" id="count-inactive">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-primary">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Available</div>
                                            <div class="h4 mb-0 font-weight-bold text-primary" id="count-available">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-warning">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Low Stock</div>
                                            <div class="h4 mb-0 font-weight-bold text-warning" id="count-low_stock">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-danger">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Out of Stock</div>
                                            <div class="h4 mb-0 font-weight-bold text-danger" id="count-out_of_stock">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <form id="filterForm" onsubmit="return false;">
                                    <div class="form-row">
                                        <div class="form-group col-md-2">
                                            <label for="category_filter">Category</label>
                                            <select name="category_filter" id="category_filter" class="form-control form-control-sm">
                                                <option value="">All Categories</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label for="is_active">Status</label>
                                            <select name="is_active" id="is_active" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label for="is_available">Availability</label>
                                            <select name="is_available" id="is_available" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="1">Available</option>
                                                <option value="0">Unavailable</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary btn-sm mr-2" onclick="applyFilters()">Filter</button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="table">
                                <table id="productsTable" class="table table-bordered" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Unit</th>
                                            <th>Wholesale</th>
                                            <th>Retail</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Available</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once 'layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'layout/scripts.php'; ?>

    <script>
        $(document).ready(function() {
            const orDash = data => {
                if (!data || data === 'N/A') return '-';
                return data;
            };

            const price = data => {
                if (data == null || data === '' || isNaN(data)) return 'Rs. 0.00';
                return `Rs. ${parseFloat(data).toFixed(2)}`;
            };

            const image = (data, type, row) => {
                if (!row || !row.product_image || row.product_image === 'N/A') {
                    return '<span class="text-muted">No Image</span>';
                }
                return `<img src="../assets/product/${row.product_image}" alt="Product" style="width:50px;height:50px;object-fit:cover;border-radius:4px">`;
            };

            const stock = (qty, type, row) => {
                if (!row) return '-';
                const q = parseFloat(qty) || 0;
                const t = parseInt(row.low_stock_threshold) || 10;
                let html = `<span class="font-weight-bold">Qty: ${q.toFixed(2)}</span>`;
                if (q === 0) html += ' <span class="badge badge-danger ml-1">Out of Stock</span>';
                else if (q <= t) html += ' <span class="badge badge-warning ml-1">Low Stock</span>';
                return html + `<br><small class="text-muted">Threshold: ${t}</small>`;
            };

            const badge = (data, type, row, labelMap) => {
                if (data == null || data === '') return '-';
                const v = data == 1 || data == '1';
                const color = labelMap?.[v ? 'on' : 'off'] || { on: 'success', off: 'danger' };
                const text = labelMap?.[v ? 'onText' : 'offText'] || { on: 'Yes', off: 'No' };
                return `<span class="badge badge-${color}">${text}</span>`;
            };

            const actions = (data, type, row) => {
                if (!row || !row.product_id) return '';
                return `
                    <a href="edit_product.php?id=${encodeURIComponent(row.product_id)}" class="btn btn-primary btn-sm" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    ${renderSwitch(row.product_id, 'is_active', row.is_active, 'Active')}
                    ${renderSwitch(row.product_id, 'is_available', row.is_available, 'Available')}
                `;
            };

            const renderSwitch = (id, switchType, checked, label) => {
                const isChecked = (checked == 1 || checked == '1') ? 'checked' : '';
                return `
                    <div class="custom-control custom-switch d-inline-block ml-2">
                        <input type="checkbox" class="custom-control-input toggle-switch"
                               id="switch_${switchType}_${id}" data-type="${switchType}" data-id="${id}" ${isChecked}>
                        <label class="custom-control-label" for="switch_${switchType}_${id}">${label}</label>
                    </div>`;
            };

            const updateStatus = (switchType, id, value) => {
                const payload = { type: 'product', id, [switchType]: value };
                $.post('api/shared/change_status.php', payload, res => {
                    Swal.fire({ icon: 'success', title: 'Success', text: res, timer: 1500, showConfirmButton: false });
                    table.ajax.reload(null, false);
                    loadProductCounts();
                }).fail(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: `Failed to update ${switchType.replace('_', ' ')}.` });
                    table.ajax.reload(null, false);
                });
            };

            const loadProductCounts = () => {
                $.get('api/shared/fetch_counts.php', { type: 'product' }, res => {
                    if (res && res.success && res.counts) {
                        Object.entries(res.counts).forEach(([k, v]) => {
                            $('#count-' + k).text(v ?? 0);
                        });
                    }
                });
            };

            let filterCategory = '';
            let filterActive = '';
            let filterAvailable = '';

            const fetchProducts = () => table.ajax.reload();
            const clearFilters = () => {
                $('#filterForm')[0].reset();
                $('#category_filter').val('').trigger('change');
                filterCategory = '';
                filterActive = '';
                filterAvailable = '';
                fetchProducts();
            };
            const applyFilters = () => {
                filterCategory = $('#category_filter').val();
                filterActive = $('#is_active').val();
                filterAvailable = $('#is_available').val();
                table.ajax.reload();
            };

            loadProductCounts();

            $('#category_filter').select2({
                placeholder: 'All Categories',
                allowClear: true,
                ajax: {
                    url: 'api/product/fetch_product_category_2.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { search: params.term }; },
                    processResults: function(data) {
                        return data.success ? { results: data.categories.map(c => ({ id: c.category_id, text: c.category_name })) } : { results: [] };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            const status = new URLSearchParams(window.location.search).get('status');
            if (status === 'active') {
                filterActive = '1';
                $('#is_active').val('1');
            } else if (status === 'inactive') {
                filterActive = '0';
                $('#is_active').val('0');
            }

            const table = $('#productsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'api/product/fetch_products.php',
                    type: 'POST',
                    data: function(d) {
                        return {
                            draw: d.draw,
                            start: d.start,
                            length: d.length,
                            category_id: filterCategory,
                            is_active: filterActive,
                            is_available: filterAvailable
                        };
                    },
                    dataSrc: function(json) {
                        if (!json || !json.data) return [];
                        return json.data;
                    }
                },
                columns: [
                    { data: null, orderable: false, render: image, defaultContent: '' },
                    { data: 'product_name', defaultContent: '' },
                    { data: 'category_name', render: orDash, defaultContent: '' },
                    { data: 'unit_code', defaultContent: '' },
                    { data: 'wholesale_price', render: price, defaultContent: '' },
                    { data: 'retail_price', render: price, defaultContent: '' },
                    { data: 'qty', render: stock, defaultContent: '' },
                    { data: 'is_active', render: d => badge(d, null, null, { on: 'success', off: 'danger', onText: 'Active', offText: 'Inactive' }), defaultContent: '' },
                    { data: 'is_available', render: d => badge(d, null, null, { on: 'success', off: 'warning', onText: 'Available', offText: 'Unavailable' }), defaultContent: '' },
                    { data: null, orderable: false, render: actions, defaultContent: '' }
                ],
                responsive: true,
                pageLength: 25,
                order: [[1, 'desc']]
            });

            $('#productsTable').on('change', '.toggle-switch', function() {
                updateStatus($(this).data('type'), $(this).data('id'), $(this).is(':checked') ? 1 : 0);
            });

            window.fetchProducts = fetchProducts;
            window.clearFilters = clearFilters;
            window.applyFilters = applyFilters;
        });
    </script>
</body>

</html>