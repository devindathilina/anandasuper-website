<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "Services";
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
                        <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Services</li>
                    </ol>
                </nav>

                <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-bolt mr-2"></i>
                                        Services
                                    </h6>
                                </div>
                                <div class="col-md-4 text-right">
                                    <a href="add_service.php" class="btn btn-light btn-sm">
                                        <i class="fas fa-plus mr-1"></i> Add Service
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row mb-3" id="service-counts">
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
                                            <div class="text-muted small text-uppercase">Bill</div>
                                            <div class="h4 mb-0 font-weight-bold text-danger" id="count-bill">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-primary">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Reload</div>
                                            <div class="h4 mb-0 font-weight-bold text-primary" id="count-reload">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <form id="filterForm" onsubmit="return false;">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="service_type_filter">Service Type</label>
                                            <select name="service_type_filter" id="service_type_filter" class="form-control form-control-sm">
                                                <option value="">All Types</option>
                                                <option value="Bill">Bill</option>
                                                <option value="Reload">Reload</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="is_active">Status</label>
                                            <select name="is_active" id="is_active" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
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
                                <table id="servicesTable" class="table table-bordered" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Service Name</th>
                                            <th>Service Type</th>
                                            <th>Status</th>
                                            <th>Created Date</th>
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
            const badge = (data, type, row, labelMap) => {
                if (data == null || data === '') return '-';
                const v = data == 1 || data == '1';
                const color = labelMap?.[v ? 'on' : 'off'] || { on: 'success', off: 'danger' };
                const text = labelMap?.[v ? 'onText' : 'offText'] || { on: 'Yes', off: 'No' };
                return `<span class="badge badge-${color}">${text}</span>`;
            };

            const serviceTypeBadge = (data) => {
                if (data === 'Bill') return '<span class="badge badge-danger">Bill</span>';
                if (data === 'Reload') return '<span class="badge badge-primary">Reload</span>';
                return data;
            };

            const actions = (data, type, row) => {
                if (!row || !row.service_id) return '';
                return `
                    <a href="edit_service.php?id=${encodeURIComponent(row.service_id)}" class="btn btn-primary btn-sm" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    ${renderSwitch(row.service_id, 'is_active', row.is_active, 'Active')}
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
                const payload = { type: 'service', id, [switchType]: value };
                $.post('api/shared/change_status.php', payload, res => {
                    Swal.fire({ icon: 'success', title: 'Success', text: res, timer: 1500, showConfirmButton: false });
                    table.ajax.reload(null, false);
                    loadServiceCounts();
                }).fail(() => {
                    Swal.fire({ icon: 'error', title: 'Error', text: `Failed to update ${switchType.replace('_', ' ')}.` });
                    table.ajax.reload(null, false);
                });
            };

            const loadServiceCounts = () => {
                $.get('api/shared/fetch_counts.php', { type: 'service' }, res => {
                    if (res && res.success && res.counts) {
                        Object.entries(res.counts).forEach(([k, v]) => {
                            $('#count-' + k).text(v ?? 0);
                        });
                    }
                });
            };

            let filterServiceType = '';
            let filterActive = '';

            const fetchServices = () => table.ajax.reload();
            const clearFilters = () => {
                $('#filterForm')[0].reset();
                $('#service_type_filter').val('').trigger('change');
                filterServiceType = '';
                filterActive = '';
                fetchServices();
            };
            const applyFilters = () => {
                filterServiceType = $('#service_type_filter').val();
                filterActive = $('#is_active').val();
                table.ajax.reload();
            };

            loadServiceCounts();

            const status = new URLSearchParams(window.location.search).get('status');
            if (status === 'active') {
                filterActive = '1';
                $('#is_active').val('1');
            } else if (status === 'inactive') {
                filterActive = '0';
                $('#is_active').val('0');
            }

            const table = $('#servicesTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'api/service/fetch_services.php',
                    type: 'POST',
                    data: function(d) {
                        return {
                            draw: d.draw,
                            start: d.start,
                            length: d.length,
                            service_type: filterServiceType,
                            is_active: filterActive
                        };
                    },
                    dataSrc: function(json) {
                        if (!json || !json.data) return [];
                        return json.data;
                    }
                },
                columns: [
                    { data: 'service_id', defaultContent: '' },
                    { data: 'service_name', defaultContent: '' },
                    { data: 'service_type', render: serviceTypeBadge, defaultContent: '' },
                    { data: 'is_active', render: d => badge(d, null, null, { on: 'success', off: 'danger', onText: 'Active', offText: 'Inactive' }), defaultContent: '' },
                    { data: 'created_at', defaultContent: '' },
                    { data: null, orderable: false, render: actions, defaultContent: '' }
                ],
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });

            $('#servicesTable').on('change', '.toggle-switch', function() {
                updateStatus($(this).data('type'), $(this).data('id'), $(this).is(':checked') ? 1 : 0);
            });

            window.fetchServices = fetchServices;
            window.clearFilters = clearFilters;
            window.applyFilters = applyFilters;
        });
    </script>
</body>

</html>
