<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "Payment Methods";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Payment Methods</li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-credit-card mr-2"></i>
                                        Payment Methods
                                    </h6>
                                </div>
                                <div class="col-md-4 text-right">
                                    <a href="add_payment_method.php" class="btn btn-light btn-sm">
                                        <i class="fas fa-plus mr-1"></i> Add Payment Method
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row mb-3" id="payment-counts">
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
                                    <div class="card card-stats bg-white border-left-danger">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Today</div>
                                            <div class="h4 mb-0 font-weight-bold text-danger" id="count-joined_today">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-warning">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">This Week</div>
                                            <div class="h4 mb-0 font-weight-bold text-warning" id="count-joined_this_week">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-info">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">This Month</div>
                                            <div class="h4 mb-0 font-weight-bold text-info" id="count-joined_this_month">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <form id="filterForm" onsubmit="return false;">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="is_active">Status</label>
                                            <select name="is_active" id="is_active" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary btn-sm mr-2" onclick="applyFilters()">Filter</button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="table">
                                <table id="paymentMethodsTable" class="table table-bordered" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Payment Method Name</th>
                                            <th>Status</th>
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
            const renderPaymentMethodName = data => {
                return data || '<span class="text-muted">-</span>';
            };

            const renderStatus = data => {
                if (data == null || data === '') return '-';
                return data == 1 || data == '1'
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-danger">Inactive</span>';
            };

            const renderActions = (data, type, row) => {
                if (!row || !row.payment_method_id) return '';
                const checked = (row.is_active == 1 || row.is_active == '1') ? 'checked' : '';
                const id = row.payment_method_id;
                return `
                    <a href="edit_payment_method.php?id=${encodeURIComponent(id)}" class="btn btn-primary btn-sm mr-2" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <div class="custom-control custom-switch d-inline-block">
                        <input type="checkbox" class="custom-control-input status-switch"
                               id="switch_${id}" data-id="${id}" ${checked}>
                        <label class="custom-control-label" for="switch_${id}">Active</label>
                    </div>`;
            };

            const updateStatus = (id, isActive) => {
                $.post('api/shared/change_status.php', { type: 'payment_method', id, is_active: isActive })
                    .done(res => {
                        Swal.fire({ icon: 'success', title: 'Success', text: res, timer: 1500, showConfirmButton: false });
                        table.ajax.reload(null, false);
                        loadPaymentCounts();
                    })
                    .fail(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update payment method status.' });
                        table.ajax.reload(null, false);
                    });
            };

            const loadPaymentCounts = () => {
                $.get('api/shared/fetch_counts.php', { type: 'payment_method' }, res => {
                    if (res && res.success && res.counts) {
                        Object.entries(res.counts).forEach(([k, v]) => {
                            $('#count-' + k).text(v ?? 0);
                        });
                    }
                });
            };

            let filterActive = '';

            const fetchPaymentMethods = () => table.ajax.reload();
            const clearFilters = () => {
                $('#filterForm')[0].reset();
                filterActive = '';
                fetchPaymentMethods();
            };
            const applyFilters = () => {
                filterActive = $('#is_active').val();
                table.ajax.reload();
            };

            loadPaymentCounts();

            const status = new URLSearchParams(window.location.search).get('status');
            if (status === 'active') {
                filterActive = '1';
                $('#is_active').val('1');
            } else if (status === 'inactive') {
                filterActive = '0';
                $('#is_active').val('0');
            }

            const table = $('#paymentMethodsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'api/payment_methods/fetch_payment_methods.php',
                    type: 'POST',
                    data: function(d) {
                        return {
                            draw: d.draw,
                            start: d.start,
                            length: d.length,
                            is_active: filterActive
                        };
                    },
                    dataSrc: function(json) {
                        if (!json || !json.data) return [];
                        return json.data;
                    }
                },
                columns: [
                    { data: 'payment_method_id', defaultContent: '' },
                    { data: 'payment_method_name', render: renderPaymentMethodName, defaultContent: '' },
                    { data: 'is_active', render: renderStatus, defaultContent: '' },
                    { data: null, orderable: false, render: renderActions, defaultContent: '' }
                ],
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });

            $('#paymentMethodsTable').on('change', '.status-switch', function() {
                updateStatus($(this).data('id'), $(this).is(':checked') ? 1 : 0);
            });

            window.fetchPaymentMethods = fetchPaymentMethods;
            window.clearFilters = clearFilters;
            window.applyFilters = applyFilters;
        });
    </script>
</body>

</html>
