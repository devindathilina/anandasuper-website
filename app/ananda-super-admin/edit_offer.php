<?php
define('ANANDA_SUPER_SECURE_ACCESS', true);
require_once __DIR__ . '/auth/auth.php';
require_once __DIR__ . '/../config/file_upload_utils.php';

$message = '';
$message_type = '';

function getOfferById($db, $offer_id): array
{
    $stmt = $db->prepare("SELECT offer_name, description, offer_code, off_percentage, start_date, end_date, offer_image, is_active FROM offers WHERE offer_id = ?");
    if (!$stmt) {
        return ['success' => false, 'data' => null];
    }

    $stmt->bind_param("i", $offer_id);
    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'data' => null];
    }

    $offer_name = null;
    $description = null;
    $offer_code = null;
    $off_percentage = null;
    $start_date = null;
    $end_date = null;
    $offer_image = null;
    $is_active = null;
    $stmt->bind_result($offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $offer_image, $is_active);
    $stmt->fetch();
    $stmt->close();

    return [
        'success' => true,
        'data' => [
            'offer_name' => $offer_name,
            'description' => $description,
            'offer_code' => $offer_code,
            'off_percentage' => $off_percentage,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'offer_image' => $offer_image,
            'is_active' => $is_active
        ]
    ];
}

function validateOfferData($offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date): array
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

    return ['success' => empty($errors), 'errors' => $errors];
}

function checkOfferExistsForUpdate($db, $offer_name, $offer_code, $offer_id): array
{
    $stmt = $db->prepare("SELECT offer_id FROM offers WHERE (offer_name = ? OR offer_code = ?) AND offer_id != ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'errors' => ['Internal Server Error']];
    }
    $stmt->bind_param("ssi", $offer_name, $offer_code, $offer_id);
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

function updateOffer($db, $offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $image_path, $offer_id): bool
{
    $stmt = $db->prepare("UPDATE offers SET offer_name = ?, description = ?, offer_code = ?, off_percentage = ?, start_date = ?, end_date = ?, offer_image = ? WHERE offer_id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sssdsssi", $offer_name, $description, $offer_code, $off_percentage, $start_date, $end_date, $image_path, $offer_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: offers.php');
    exit;
}

$offer_id = (int)$_GET['id'];

$offerResult = getOfferById($db, $offer_id);
if (!$offerResult['success']) {
    header('Location: offers.php');
    exit;
}

$offer_data = $offerResult['data'];
$offer_name = $offer_data['offer_name'];
$description = $offer_data['description'];
$offer_code = $offer_data['offer_code'];
$off_percentage = $offer_data['off_percentage'];
$start_date = $offer_data['start_date'];
$end_date = $offer_data['end_date'];
$offer_image = $offer_data['offer_image'];
$is_active = $offer_data['is_active'];

$form_offer_name = $offer_name;
$form_description = $description;
$form_offer_code = $offer_code;
$form_off_percentage = $off_percentage;
$form_start_date = $start_date;
$form_end_date = $end_date;
$form_offer_image = $offer_image;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_offer_name = trim($_POST['offer_name'] ?? '');
    $new_description = trim($_POST['description'] ?? '');
    $new_offer_code = trim($_POST['offer_code'] ?? '');
    $new_off_percentage = trim($_POST['off_percentage'] ?? '');
    $new_start_date = trim($_POST['start_date'] ?? '');
    $new_end_date = trim($_POST['end_date'] ?? '');

    $form_offer_name = $new_offer_name;
    $form_description = $new_description;
    $form_offer_code = $new_offer_code;
    $form_off_percentage = $new_off_percentage;
    $form_start_date = $new_start_date;
    $form_end_date = $new_end_date;

    $validation = validateOfferData($new_offer_name, $new_description, $new_offer_code, $new_off_percentage, $new_start_date, $new_end_date);
    if (!$validation['success']) {
        $message = implode('<br>', $validation['errors']);
        $message_type = 'alert-danger';
    }

    $exists = checkOfferExistsForUpdate($db, $new_offer_name, $new_offer_code, $offer_id);
    if (!$exists['success']) {
        $message = implode('<br>', $exists['errors']);
        $message_type = 'alert-danger';
    }

    $image_path = $offer_image;
    if (!$message && isset($_FILES['offer_image']) && $_FILES['offer_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadOfferImage($_FILES['offer_image']);
        if (!$uploadResult['success']) {
            $message = implode('<br>', $uploadResult['errors']);
            $message_type = 'alert-danger';
        }
        $image_path = $uploadResult['image_path'] ?? $offer_image;
    }

    if (!$message) {
        $db->begin_transaction();
        try {
            if (!updateOffer($db, $new_offer_name, $new_description, $new_offer_code, $new_off_percentage, $new_start_date, $new_end_date, $image_path, $offer_id)) {
                throw new Exception('Failed to update offer.');
            }

            $db->commit();
            $message = 'Offer updated successfully!';
            $message_type = 'alert-success';

            $offer_name = $new_offer_name;
            $description = $new_description;
            $offer_code = $new_offer_code;
            $off_percentage = $new_off_percentage;
            $start_date = $new_start_date;
            $end_date = $new_end_date;
            $offer_image = $image_path;

            $form_offer_name = $new_offer_name;
            $form_description = $new_description;
            $form_offer_code = $new_offer_code;
            $form_off_percentage = $new_off_percentage;
            $form_start_date = $new_start_date;
            $form_end_date = $new_end_date;
            $form_offer_image = $image_path;
        } catch (Exception $e) {
            $db->rollback();
            $message = 'An error occurred while updating the offer. Please try again.';
            $message_type = 'alert-danger';
        }
    }
}

$page_name = "Edit Offer";
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
                            <li class="breadcrumb-item active text-white font-weight-bold" aria-current="page">Edit Offer</li>
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
                                        Edit Offer
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
                                            <input type="text" id="offer_name" name="offer_name" class="form-control" minlength="2" maxlength="255" required value="<?= htmlspecialchars($form_offer_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter offer name (e.g., "Summer Discount")</small>
                                            <div class="invalid-feedback">Please enter offer name</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="offer_code">Offer Code</label>
                                            <input type="text" id="offer_code" name="offer_code" class="form-control text-uppercase" minlength="2" maxlength="255" pattern="[A-Z0-9_-]+" required value="<?= htmlspecialchars($form_offer_code ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Unique code for customers (e.g., "SUMMER2024")</small>
                                            <div class="invalid-feedback">Please enter a valid offer code</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="off_percentage">Discount Percentage (%)</label>
                                            <input type="number" id="off_percentage" name="off_percentage" class="form-control" min="0" max="100" step="0.01" required value="<?= htmlspecialchars($form_off_percentage ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Enter discount percentage (0-100)</small>
                                            <div class="invalid-feedback">Please enter discount percentage</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_date">Start Date</label>
                                            <input type="date" id="start_date" name="start_date" class="form-control" required value="<?= htmlspecialchars($form_start_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Offer start date</small>
                                            <div class="invalid-feedback">Please select start date</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="end_date">End Date</label>
                                            <input type="date" id="end_date" name="end_date" class="form-control" required value="<?= htmlspecialchars($form_end_date ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <small class="text-muted">Offer end date</small>
                                            <div class="invalid-feedback">Please select end date</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea id="description" name="description" class="form-control" rows="3" minlength="10" maxlength="500" required><?= htmlspecialchars($form_description ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <small class="text-muted">Brief description of the offer (10-500 characters)</small>
                                            <div class="invalid-feedback">Please enter description (min 10 characters)</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="offer_image">Offer Image</label>
                                            <input type="file" id="offer_image" name="offer_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                            <small class="text-muted">Optional. Leave blank to keep current image. JPG, PNG, or WEBP. Max 2MB. Required: 800x450px</small>
                                            <?php if (!empty($form_offer_image)): ?>
                                                <div class="mt-2">
                                                    <small>Current image:</small><br>
                                                    <img src="<?= htmlspecialchars('../assets/offer/' . $form_offer_image, ENT_QUOTES, 'UTF-8'); ?>" alt="Current Offer Image" class="img-thumbnail" style="max-width: 300px; max-height: 150px;">
                                                </div>
                                            <?php endif; ?>
                                            <div id="imagePreview" class="mt-2" style="display: none;">
                                                <small>New image preview:</small><br>
                                                <img src="" alt="Preview" class="img-thumbnail" style="max-width: 300px; max-height: 150px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="fas fa-save mr-1"></i> Update Offer
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
