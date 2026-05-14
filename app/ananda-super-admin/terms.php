<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';

$page_name = "Manage Terms";
$message = '';
$messageType = '';

function getTerm($db): array
{
    $stmt = $db->prepare("SELECT term_id, term_text FROM ananda_super_terms LIMIT 1");
    if (!$stmt || !$stmt->execute()) {
        return ['success' => false, 'data' => null];
    }
    $result = $stmt->get_result();
    $term = $result->fetch_assoc();
    $stmt->close();
    return ['success' => true, 'data' => $term];
}

function validateTermData($term_id, $term_text): array
{
    $errors = [];

    if ($term_id <= 0) {
        $errors[] = 'Invalid term ID.';
    }
    if (empty($term_text)) {
        $errors[] = 'Term text cannot be empty.';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function termExists($db, $term_id): bool
{
    $stmt = $db->prepare("SELECT term_id FROM ananda_super_terms WHERE term_id = ? LIMIT 1");
    if (!$stmt || !$stmt->execute()) {
        return false;
    }
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function updateTerm($db, $term_id, $term_text): bool
{
    $stmt = $db->prepare("UPDATE ananda_super_terms SET term_text = ? WHERE term_id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("si", $term_text, $term_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

$termData = getTerm($db);
$term = $termData['data'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $term_id = intval($_POST['term_id'] ?? 0);
    $term_text = trim($_POST['term_text'] ?? '');

    $validation = validateTermData($term_id, $term_text);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $messageType = 'alert-danger';
    } elseif (!termExists($db, $term_id)) {
        $message = 'Term not found.';
        $messageType = 'alert-danger';
    } else {
        if (updateTerm($db, $term_id, $term_text)) {
            $message = 'Term updated successfully!';
            $messageType = 'alert-success';
            $termData = getTerm($db);
            $term = $termData['data'];
        } else {
            $message = 'Failed to update term. Please try again.';
            $messageType = 'alert-danger';
        }
    }
}
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Manage Terms</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $messageType ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($term)): ?>
                        <div class="card shadow mb-4">
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    No terms found. Please add terms through the database.
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card shadow mb-4">
                            <div class="card-header bg-primary text-white">
                                <div class="row align-items-center">
                                    <div class="col-12">
                                        <h6 class="m-0 font-weight-bold">
                                            <i class="fas fa-file-contract mr-2"></i>
                                            Terms & Conditions
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form id="termForm" autocomplete="off">
                                    <?= csrfInputField() ?>
                                    <input type="hidden" name="term_id" value="<?= $term['term_id'] ?>">

                                    <div class="form-group">
                                        <label for="term_text">
                                            <strong>Term Text</strong>
                                            <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control"
                                                  id="term_text"
                                                  name="term_text"
                                                  rows="12"
                                                  required><?= htmlspecialchars($term['term_text']) ?></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary mt-2">
                                        <i class="fas fa-save mr-1"></i> Update Term
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php require_once 'layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'layout/scripts.php'; ?>
    <script>
        $(function() {
            $('#termForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this)[0];
                const formData = new FormData(this);

                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Validation Error',
                        text: 'Please fix the errors in the form',
                        confirmButtonColor: '#0d6efd'
                    });
                    form.classList.add('was-validated');
                    return;
                }

                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An unexpected error occurred. Please try again.'
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>
