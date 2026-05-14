<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$message = '';
$message_type = '';

function getServiceById($db, $service_id): array
{
    $stmt = $db->prepare("SELECT service_id, service_name, service_type, is_active FROM services WHERE service_id = ?");
    if (!$stmt || !$stmt->bind_param("i", $service_id) || !$stmt->execute()) {
        return ['success' => false, 'data' => null];
    }
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }
    $data = $result->fetch_assoc();
    $stmt->close();

    return ['success' => true, 'data' => $data];
}

function validateServiceData($service_name, $service_type): array
{
    $errors = [];

    if (empty($service_name)) {
        $errors[] = 'Service name is required!';
    }
    if (strlen($service_name) < 2) {
        $errors[] = 'Service name must be at least 2 characters long!';
    }
    if (strlen($service_name) > 255) {
        $errors[] = 'Service name cannot exceed 255 characters!';
    }

    if (empty($service_type) || !in_array($service_type, ['Bill', 'Reload'])) {
        $errors[] = 'Valid service type is required!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkServiceExistsForUpdate($db, $service_name, $service_id): array
{
    $stmt = $db->prepare("SELECT service_id FROM services WHERE service_name = ? AND service_id != ? LIMIT 1");
    if (!$stmt || !$stmt->bind_param("si", $service_name, $service_id) || !$stmt->execute()) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    if ($exists) {
        return ['success' => false, 'errors' => ['A service with this name already exists!']];
    }
    return ['success' => true, 'errors' => []];
}

function updateService($db, $service_id, $service_name, $service_type, $is_active): bool
{
    $stmt = $db->prepare("UPDATE services SET service_name = ?, service_type = ?, is_active = ? WHERE service_id = ?");
    if (!$stmt || !$stmt->bind_param("ssii", $service_name, $service_type, $is_active, $service_id) || !$stmt->execute()) {
        return false;
    }
    $stmt->close();

    return true;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: services.php');
    exit;
}

$service_id = (int)$_GET['id'];
$serviceResult = getServiceById($db, $service_id);
if (!$serviceResult['success']) {
    header('Location: services.php');
    exit;
}

$service_data = $serviceResult['data'];
$service_name = $service_data['service_name'];
$service_type = $service_data['service_type'];
$is_active = $service_data['is_active'];

$form_service_name = $service_name;
$form_service_type = $service_type;
$form_is_active = $is_active;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_service_name = trim($_POST['service_name'] ?? '');
    $new_service_type = trim($_POST['service_type'] ?? '');
    $new_is_active = isset($_POST['is_active']) ? 1 : 0;

    $form_service_name = $new_service_name;
    $form_service_type = $new_service_type;
    $form_is_active = $new_is_active;

    $validation = validateServiceData($new_service_name, $new_service_type);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    if (!$message) {
        $exists = checkServiceExistsForUpdate($db, $new_service_name, $service_id);
        if (!$exists['success']) {
            $message = implode('<br>', $exists['errors']);
            $message_type = 'alert-danger';
        }
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!updateService($db, $service_id, $new_service_name, $new_service_type, $new_is_active)) {
                throw new Exception('Failed to update service.');
            }

            $db->commit();
            $message = 'Service updated successfully!';
            $message_type = 'alert-success';

            $service_name = $new_service_name;
            $service_type = $new_service_type;
            $is_active = $new_is_active;
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while updating the service. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Edit Service";
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
                            <li class="breadcrumb-item"><a href="services.php" class="text-light">Services</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Edit Service</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($message_type === 'alert-success'): ?>
                            <script>
                                setTimeout(() => { window.location.href = 'services.php'; }, 2000);
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-bolt mr-2"></i>
                                        Edit Service
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="serviceForm" novalidate>
                                <?= csrfInputField(); ?>

                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label for="service_name">Service Name</label>
                                        <input type="text" class="form-control" id="service_name" name="service_name" minlength="2" maxlength="255" required value="<?= htmlspecialchars($form_service_name, ENT_QUOTES, 'UTF-8'); ?>">
                                        <small class="text-muted">Enter service name (e.g., Dialog, Mobitel, CEB)</small>
                                        <div class="invalid-feedback">Please enter service name</div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="service_type">Service Type</label>
                                        <select id="service_type" name="service_type" class="form-control" required>
                                            <option value="">Select Service Type</option>
                                            <option value="Bill" <?= $form_service_type === 'Bill' ? 'selected' : '' ?>>Bill</option>
                                            <option value="Reload" <?= $form_service_type === 'Reload' ? 'selected' : '' ?>>Reload</option>
                                        </select>
                                        <small class="text-muted">Select service type</small>
                                        <div class="invalid-feedback">Please select service type</div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" <?= $form_is_active ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="is_active">Active Service</label>
                                        <small class="form-text text-muted d-block">Enable this service for customers</small>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="form-label">Current Status</label>
                                            <div class="form-control-plaintext">
                                                <span class="badge <?= $is_active == 1 ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= $is_active == 1 ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save mr-1"></i> Update Service
                                </button>
                                <a href="services.php" class="btn btn-secondary mt-3">Cancel</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once 'layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'layout/scripts.php'; ?>
    <script>
        $(function() {
            $('#serviceForm').on('submit', function(e) {
                var form = $(this)[0];

                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html>
