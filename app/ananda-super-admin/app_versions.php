<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "App Versions";
$message = '';
$message_type = '';

function fetchAppVersions($db): array
{
    $stmt = $db->prepare("SELECT id, platform, min_version, update_url FROM app_versions ORDER BY id");
    if (!$stmt || !$stmt->execute()) {
        return ['list' => [], 'indexed' => []];
    }
    $result = $stmt->get_result();
    $versions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $indexed = [];
    foreach ($versions as $version) {
        $indexed[$version['id']] = $version;
    }

    return ['list' => $versions, 'indexed' => $indexed];
}

function validateVersionData($min_version, $update_url): array
{
    $errors = [];

    if (empty($min_version) || !preg_match('/^\d+(\.\d+){0,3}$/', $min_version) || strlen($min_version) > 20) {
        $errors[] = 'Min Version must be in format: X.Y, X.Y.Z, or X.Y.Z.W (max 20 chars)';
    }

    if (empty($update_url) || !filter_var($update_url, FILTER_VALIDATE_URL) || strlen($update_url) > 255) {
        $errors[] = 'Update URL must be a valid URL (max 255 chars)';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function updateAppVersions($db, array $versions_data): bool
{
    $stmt = $db->prepare("UPDATE app_versions SET min_version = ?, update_url = ? WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    foreach ($versions_data as $id => $data) {
        $stmt->bind_param("ssi", $data['min_version'], $data['update_url'], $id);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
    }

    $stmt->close();
    return true;
}

$app_versions_data = fetchAppVersions($db);
$app_versions = $app_versions_data['list'];
$app_versions_indexed = $app_versions_data['indexed'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $versions_data = [];
    $all_errors = [];

    if (!isset($_POST['min_version']) || !is_array($_POST['min_version']) ||
        !isset($_POST['update_url']) || !is_array($_POST['update_url']) ||
        !isset($_POST['id']) || !is_array($_POST['id'])) {
        $all_errors[] = 'Missing required form fields.';
    } else {
        foreach ($_POST['id'] as $id_str => $id_val) {
            $id = intval($id_str);
            $min_version = trim($_POST['min_version'][$id_str] ?? '');
            $update_url = trim($_POST['update_url'][$id_str] ?? '');

            if (!isset($app_versions_indexed[$id])) {
                $all_errors[] = "Invalid record ID: {$id}";
                break;
            }

            $platform = $app_versions_indexed[$id]['platform'];
            $validation = validateVersionData($min_version, $update_url);
            if (!$validation['success']) {
                foreach ($validation['errors'] as $error) {
                    $all_errors[] = ucfirst($platform) . ': ' . $error;
                }
            }

            $versions_data[$id] = [
                'min_version' => $min_version,
                'update_url' => $update_url
            ];
        }
    }

    if (!empty($all_errors)) {
        $message = implode('<br>', $all_errors);
        $message_type = 'alert-danger';
    } else {
        $db->begin_transaction();
        try {
            if (!updateAppVersions($db, $versions_data)) {
                throw new Exception('Failed to update app versions');
            }

            $db->commit();
            $message = 'App versions updated successfully!';
            $message_type = 'alert-success';
            $app_versions_data = fetchAppVersions($db);
            $app_versions = $app_versions_data['list'];
            $app_versions_indexed = $app_versions_data['indexed'];
        } catch (Exception $e) {
            $db->rollback();
            $message = 'Internal Server Error';
            $message_type = 'alert-danger';
        }
    }
}

$platform_icons = [
    'android' => 'fab fa-android',
    'ios' => 'fab fa-apple'
];
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
                        <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">App Versions</li>
                    </ol>
                </nav>

                <?php if ($message): ?>
                    <div class="alert <?= $message_type ?>" role="alert">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="appVersionsForm">
                    <?= csrfInputField() ?>
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-mobile-alt mr-2"></i>App Versions Management</h6>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                Configure the minimum required app version and update URL for each platform. Users with older versions will be prompted to update.
                            </div>

                            <div class="row">
                                <?php foreach ($app_versions as $app): ?>
                                    <div class="col-lg-6">
                                        <div class="card border-left-primary shadow h-100 py-2 mb-3">
                                            <div class="card-body">
                                                <input type="hidden" name="id[<?= $app['id'] ?>]" value="<?= $app['id'] ?>">

                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="mr-3">
                                                        <i class="<?= $platform_icons[$app['platform']] ?? 'fas fa-mobile-alt' ?> fa-2x text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="m-0 font-weight-bold text-primary"><?= ucfirst($app['platform']) ?></h6>
                                                        <small class="text-muted">Platform Configuration</small>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label for="min_version_<?= $app['id'] ?>">
                                                        Min Version <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text"
                                                           class="form-control"
                                                           id="min_version_<?= $app['id'] ?>"
                                                           name="min_version[<?= $app['id'] ?>]"
                                                           value="<?= htmlspecialchars($app['min_version']) ?>"
                                                           required
                                                           pattern="^\d+(\.\d+){0,3}$"
                                                           title="Version format: X.Y, X.Y.Z, or X.Y.Z.W"
                                                           maxlength="20">
                                                    <small class="text-muted">Format: X.Y, X.Y.Z, or X.Y.Z.W</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="update_url_<?= $app['id'] ?>">
                                                        Update URL <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="url"
                                                           class="form-control"
                                                           id="update_url_<?= $app['id'] ?>"
                                                           name="update_url[<?= $app['id'] ?>]"
                                                           value="<?= htmlspecialchars($app['update_url']) ?>"
                                                           required
                                                           maxlength="255"
                                                           placeholder="https://...">
                                                    <small class="text-muted">Store link for app updates</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-save mr-2"></i>Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php require_once 'layout/footer.php'; ?>
    </div>
</div>
<?php require_once 'layout/scripts.php'; ?>
<script>
    $(function() {
        $('#appVersionsForm').on('submit', function(e) {
            var form = this;

            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Please fix the errors in the form',
                    confirmButtonColor: '#0d6efd'
                });
            }

            form.classList.add('was-validated');
        });
    });
</script>
</body>
</html>
