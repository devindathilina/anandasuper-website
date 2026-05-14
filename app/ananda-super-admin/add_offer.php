<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/../config/file_upload_utils.php';
require_once __DIR__ . '/../config/onesignal.php';

$message = '';
$message_type = '';

function validateOfferData($offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $offer_image): array
{
    $errors = [];

    if (empty($offer_name) || strlen($offer_name) < 2 || strlen($offer_name) > 255) {
        $errors[] = 'Offer name must be between 2 and 255 characters!';
    }

    if (empty($description) || strlen($description) < 10 || strlen($description) > 500) {
        $errors[] = 'Description must be between 10 and 500 characters!';
    }

    if (empty($offer_code) || strlen($offer_code) < 2 || strlen($offer_code) > 255) {
        $errors[] = 'Offer code must be between 2 and 255 characters!';
    }

    if (!preg_match('/^[A-Z0-9_-]+$/i', $offer_code)) {
        $errors[] = 'Offer code can only contain letters, numbers, hyphens, and underscores!';
    }

    if (!is_numeric($off_percentage) || floatval($off_percentage) < 0 || floatval($off_percentage) > 100) {
        $errors[] = 'Offer percentage must be between 0 and 100!';
    }

    if (empty($start_date)) {
        $errors[] = 'Start date is required!';
    } elseif (!strtotime($start_date)) {
        $errors[] = 'Invalid start date format!';
    }

    if (empty($end_date)) {
        $errors[] = 'End date is required!';
    } elseif (!strtotime($end_date)) {
        $errors[] = 'Invalid end date format!';
    }

    if (strtotime($start_date) && strtotime($end_date) && strtotime($start_date) >= strtotime($end_date)) {
        $errors[] = 'Start date must be before end date!';
    }

    if (empty($offer_image)) {
        $errors[] = 'Offer image is required!';
    }

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkOfferExists($db, $offer_name, $offer_code): array
{
    $stmt = $db->prepare("SELECT offer_id FROM offers WHERE offer_name = ? OR offer_code = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("ss", $offer_name, $offer_code);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'errors' => ['An offer with this name or code already exists!']];
    }
    $stmt->close();

    return ['success' => true, 'errors' => []];
}

function uploadOfferImage($file): array
{
    $uploadDir = __DIR__ . '/../assets/offer/';
    $uploadResult = processImageUpload($file, [
        'upload_path' => $uploadDir,
        'allowed_types' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_size' => 2 * 1024 * 1024,
        'min_width' => 800,
        'min_height' => 450,
        'max_width' => 800,
        'max_height' => 450,
        'output_size' => null,
        'crop_square' => false,
        'quality' => 85,
        'prefix' => 'offer_',
        'output_format' => 'webp'
    ]);

    if (!$uploadResult['success']) {
        return ['success' => false, 'errors' => [$uploadResult['error']]];
    }

    return ['success' => true, 'image_path' => $uploadResult['filename']];
}

function saveOffer($db, $offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $image_path): int|false
{
    $stmt = $db->prepare("INSERT INTO offers (offer_name, description, offer_code, off_percentage, start_date, end_date, offer_image, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sssdsss", $offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $image_path);

    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $offer_id = $db->insert_id;
    $stmt->close();

    return $offer_id;
}

$form_offer_name = '';
$form_description = '';
$form_offer_code = '';
$form_off_percentage = '';
$form_start_date = '';
$form_end_date = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $offer_name = trim($_POST['offer_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $offer_code = trim($_POST['offer_code'] ?? '');
    $off_percentage = trim($_POST['off_percentage'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');

    $form_offer_name = $offer_name;
    $form_description = $description;
    $form_offer_code = $offer_code;
    $form_off_percentage = $off_percentage;
    $form_start_date = $start_date;
    $form_end_date = $end_date;

    $image_uploaded = isset($_FILES['offer_image']) && $_FILES['offer_image']['error'] === UPLOAD_ERR_OK;
    $validation = validateOfferData($offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $image_uploaded);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkOfferExists($db, $offer_name, $offer_code);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    $image_path = null;
    if (!$message) {
        if (!isset($_FILES['offer_image']) || $_FILES['offer_image']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Offer image is required!';
            $message_type = 'alert-danger';
        } else {
            $uploadResult = uploadOfferImage($_FILES['offer_image']);
            if (!$uploadResult['success']) {
                $message = implode('<br>', $uploadResult['errors']);
                $message_type = 'alert-danger';
            }
            $image_path = $uploadResult['image_path'] ?? null;
        }
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            $offer_id = saveOffer($db, $offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $image_path);
            if (!$offer_id) {
                throw new Exception('Failed to save offer.');
            }

            $db->commit();

            $push_title = "🎉 New Offer Available!";
            $push_message = "{$offer_name}: Get {$off_percentage}% off! Valid from " . date('M d', strtotime($start_date)) . " to " . date('M d', strtotime($end_date));
            $push_sent = sendPublicPush(
                $push_title,
                $push_message,
                [
                    'offer_id' => $offer_id,
                    'offer_code' => $offer_code,
                    'off_percentage' => $off_percentage,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]
            );

            $message = 'Offer added successfully!';
            if ($push_sent) {
                $message .= ' Push notification sent to all customers.';
            } else {
                $message .= ' Push notification failed.';
            }
            $message_type = 'alert-success';

            $form_offer_name = '';
            $form_description = '';
            $form_offer_code = '';
            $form_off_percentage = '';
            $form_start_date = '';
            $form_end_date = '';
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while adding the offer. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Add Offer";
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
                            <li class="breadcrumb-item"><a href="offers.php" class="text-light">Offers</a></li>
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Add Offer</li>
                        </ol>
                    </nav>

                    <?php if ($message): ?>
                        <div class="alert <?= $message_type ?>" role="alert">
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php if ($message_type === 'alert-success'): ?>
                            <script>
                                setTimeout(() => {
                                    window.location.href = 'offers.php';
                                }, 2000);
                            </script>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col-12">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-tags mr-2"></i>
                                        Add Offer
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="offerForm" enctype="multipart/form-data" novalidate>
                                <?= csrfInputField(); ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="offer_name">Offer Name</label>
                                            <input type="text" id="offer_name" name="offer_name" class="form-control" minlength="2" maxlength="255" required value="<?= htmlspecialchars($form_offer_name, ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter offer name (e.g., "Summer Discount")</small>
                                            <div class="invalid-feedback">Please enter offer name</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="offer_code">Offer Code</label>
                                            <input type="text" id="offer_code" name="offer_code" class="form-control text-uppercase" minlength="2" maxlength="255" pattern="[A-Z0-9_-]+" required value="<?= htmlspecialchars($form_offer_code, ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Unique code for customers (e.g., "SUMMER2024")</small>
                                            <div class="invalid-feedback">Please enter a valid offer code</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="off_percentage">Discount Percentage (%)</label>
                                            <input type="number" id="off_percentage" name="off_percentage" class="form-control" min="0" max="100" step="0.01" required value="<?= htmlspecialchars($form_off_percentage, ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter discount percentage (0-100)</small>
                                            <div class="invalid-feedback">Please enter discount percentage</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_date">Start Date</label>
                                            <input type="date" id="start_date" name="start_date" class="form-control" required value="<?= htmlspecialchars($form_start_date, ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Offer start date</small>
                                            <div class="invalid-feedback">Please select start date</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="end_date">End Date</label>
                                            <input type="date" id="end_date" name="end_date" class="form-control" required value="<?= htmlspecialchars($form_end_date, ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Offer end date</small>
                                            <div class="invalid-feedback">Please select end date</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea id="description" name="description" class="form-control" rows="3" minlength="10" maxlength="500" required><?= htmlspecialchars($form_description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <small class="text-muted">Brief description of the offer (10-500 characters)</small>
                                            <div class="invalid-feedback">Please enter description (min 10 characters)</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="offer_image">Offer Image</label>
                                            <input type="file" id="offer_image" name="offer_image" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                                            <small class="text-muted">JPG, PNG, or WEBP. Max 2MB. Required: 800x450px</small>
                                            <div class="invalid-feedback">Please select an offer image</div>
                                        </div>
                                        <div id="imagePreview" class="mt-2" style="display: none;">
                                            <img src="" alt="Preview" class="img-thumbnail" style="max-width: 300px; max-height: 150px;">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-plus mr-1"></i> Add Offer
                                </button>
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
            $('#offer_code').on('input', function() {
                this.value = this.value.toUpperCase();
            });

            $('#offer_image').on('change', function(e) {
                var file = e.target.files[0];
                if (file) {
                    $(this).removeClass('is-invalid');
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imagePreview img').attr('src', e.target.result);
                        $('#imagePreview').show();
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#imagePreview').hide();
                }
            });

            $('#start_date').on('change', function() {
                $('#end_date').attr('min', $(this).val());
                if ($('#end_date').val() && $('#end_date').val() < $(this).val()) {
                    $('#end_date').val($(this).val());
                }
            });

            $('#offerForm').on('submit', function(e) {
                var form = $(this)[0];

                var imageFile = $('#offer_image')[0].files[0];
                if (!imageFile) {
                    e.preventDefault();
                    e.stopPropagation();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Image Required',
                        text: 'Please select an offer image.'
                    });
                    $('#offer_image').addClass('is-invalid');
                    return;
                } else {
                    $('#offer_image').removeClass('is-invalid');
                }

                var startDate = new Date($('#start_date').val());
                var endDate = new Date($('#end_date').val());

                if (startDate >= endDate) {
                    e.preventDefault();
                    e.stopPropagation();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Date Range',
                        text: 'Start date must be before end date.'
                    });
                    return;
                }

                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                form.classList.add('was-validated');
            });

            var forms = document.getElementsByClassName('needs-validation');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        });
    </script>
</body>
</html>
