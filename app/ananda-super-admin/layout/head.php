<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="ananda-super-admin-csrf-token" content="<?php echo htmlspecialchars(getCsrfToken()); ?>">
    <title><?php echo htmlspecialchars($app_name); ?> | <?php echo htmlspecialchars($page_name); ?></title>
    <link rel="icon" href="img/logo.png" type="image/x-icon" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="vendor/datatables/responsive.dataTables.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="css/card-stats.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<style>
    .no-hover:hover {
        text-decoration: none;
        cursor: default;
    }
    @media screen and (max-width: 1024px) {
        li.paginate_button.previous {
            display: inline;
        }

        li.paginate_button.next {
            display: inline;
        }

        li.paginate_button {
            display: none;
        }
    }
</style>