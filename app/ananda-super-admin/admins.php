<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$admin_info = getAnandaSuperAdminInfo();
if (!$admin_info || $admin_info['admin_role'] !== 'Super Admin') {
    header('Location: dashboard.php');
    exit;
}

$page_name = "Admins";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Admins</li>
                        </ol>
                    </nav>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-user-shield mr-2"></i>
                                        Admins
                                    </h6>
                                </div>
                                <div class="col-md-4 text-right">
                                    <a href="add_admin.php" class="btn btn-light btn-sm">
                                        <i class="fas fa-plus mr-1"></i> Add Admin
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row mb-3" id="admin-counts">
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
                                    <div class="card card-stats bg-white border-left-danger">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Inactive</div>
                                            <div class="h4 mb-0 font-weight-bold text-danger" id="count-inactive">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-danger">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Super Admins</div>
                                            <div class="h4 mb-0 font-weight-bold text-danger" id="count-super_admin">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-2">
                                    <div class="card card-stats bg-white border-left-info">
                                        <div class="card-body p-2">
                                            <div class="text-muted small text-uppercase">Normal Admins</div>
                                            <div class="h4 mb-0 font-weight-bold text-info" id="count-normal_admin">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <form id="filterForm" onsubmit="return false;">
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label for="date_start">Date From</label>
                                            <input type="date" name="date_start" id="date_start" class="form-control form-control-sm">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="date_end">Date To</label>
                                            <input type="date" name="date_end" id="date_end" class="form-control form-control-sm">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="is_active">Status</label>
                                            <select name="is_active" id="is_active" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="admin_role">Role</label>
                                            <select name="admin_role" id="admin_role" class="form-control form-control-sm">
                                                <option value="">All</option>
                                                <option value="Super Admin">Super Admin</option>
                                                <option value="Normal Admin">Normal Admin</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-12">
                                            <button type="button" class="btn btn-primary btn-sm mr-2" onclick="applyFilters()">Filter</button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="clearFilters()">Clear</button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="table">
                                <table id="adminsTable" class="table table-bordered" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
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
            const loadAdminCounts = () => {
                $.get('api/shared/fetch_counts.php', { type: 'admin' }, res => {
                    if (res && res.success && res.counts) {
                        Object.entries(res.counts).forEach(([k, v]) => {
                            $('#count-' + k).text(v ?? 0);
                        });
                    }
                });
            };

            let filterActive = '';
            let filterRole = '';
            let filterDateStart = '';
            let filterDateEnd = '';

            const fetchAdmins = () => table.ajax.reload();
            const clearFilters = () => {
                $('#filterForm')[0].reset();
                filterActive = '';
                filterRole = '';
                filterDateStart = '';
                filterDateEnd = '';
                fetchAdmins();
            };
            const applyFilters = () => {
                filterActive = $('#is_active').val();
                filterRole = $('#admin_role').val();
                filterDateStart = $('#date_start').val();
                filterDateEnd = $('#date_end').val();
                table.ajax.reload();
            };

            const updateStatus = (id, isActive) => {
                $.post('api/shared/change_status.php', { type: 'admin', id, is_active: isActive })
                    .done(res => {
                        Swal.fire({ icon: 'success', title: 'Success', text: res, timer: 1500, showConfirmButton: false });
                        table.ajax.reload(null, false);
                        loadAdminCounts();
                    })
                    .fail(() => {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to update admin status.' });
                        table.ajax.reload(null, false);
                    });
            };

            loadAdminCounts();

            const table = $('#adminsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'api/admin/fetch_admins.php',
                    type: 'POST',
                    data: function(d) {
                        return {
                            draw: d.draw,
                            start: d.start,
                            length: d.length,
                            is_active: filterActive,
                            admin_role: filterRole,
                            date_start: filterDateStart,
                            date_end: filterDateEnd
                        };
                    },
                    dataSrc: function(json) {
                        if (!json || !json.data) return [];
                        return json.data;
                    }
                },
                columns: [
                    { data: 'admin_id', defaultContent: '' },
                    { data: 'admin_username', defaultContent: '' },
                    { data: null, render: (data, type, row) => {
                        const first = row.admin_first_name || '';
                        const last = row.admin_last_name || '';
                        return (first + ' ' + last).trim() || '-';
                    }, defaultContent: '' },
                    { data: 'admin_email', defaultContent: '' },
                    { data: 'admin_role', render: data => {
                        if (data === 'Super Admin') return '<span class="badge badge-danger">Super Admin</span>';
                        if (data === 'Normal Admin') return '<span class="badge badge-primary">Normal Admin</span>';
                        return '<span class="badge badge-secondary">' + (data || '-') + '</span>';
                    }, defaultContent: '' },
                    { data: 'admin_is_active', render: data => {
                        return data == '1'
                            ? '<span class="badge badge-success">Active</span>'
                            : '<span class="badge badge-danger">Inactive</span>';
                    }, defaultContent: '' },
                    { data: 'admin_created_at', render: data => {
                        if (!data) return '-';
                        return new Date(data).toLocaleDateString();
                    }, defaultContent: '' },
                    { data: null, orderable: false, render: data => {
                        if (data.admin_role === 'Super Admin') {
                            return '<span class="text-muted"><em>Cannot edit Super Admin</em></span>';
                        }
                        const checked = data.admin_is_active == '1' ? 'checked' : '';
                        return `
                            <a href="edit_admin.php?id=${encodeURIComponent(data.admin_id)}" class="btn btn-primary btn-sm mr-2" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <div class="custom-control custom-switch d-inline-block">
                                <input type="checkbox" class="custom-control-input status-switch"
                                       id="switch_${data.admin_id}" data-id="${data.admin_id}" ${checked}>
                                <label class="custom-control-label" for="switch_${data.admin_id}">Active</label>
                            </div>`;
                    }, defaultContent: '' }
                ],
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });

            $('#adminsTable').on('change', '.status-switch', function() {
                updateStatus($(this).data('id'), $(this).is(':checked') ? 1 : 0);
            });

            window.fetchAdmins = fetchAdmins;
            window.clearFilters = clearFilters;
            window.applyFilters = applyFilters;
        });
    </script>
</body>

</html>
