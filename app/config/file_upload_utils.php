<?php
if (!defined('ANANDA_SUPER_SECURE_ACCESS')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

if (!defined('FILE_UPLOAD_UTILS_INCLUDED')) {
    define('FILE_UPLOAD_UTILS_INCLUDED', true);
}

function validateImageSize(array $imageInfo, array $opts): array {
    $width = $imageInfo[0];
    $height = $imageInfo[1];

    if ($width < $opts['min_width'] || $height < $opts['min_height']) {
        return ['valid' => false, 'error' => "Image too small. Minimum: {$opts['min_width']}x{$opts['min_height']}px"];
    }

    if ($width > $opts['max_width'] || $height > $opts['max_height']) {
        return ['valid' => false, 'error' => "Image too large. Maximum: {$opts['max_width']}x{$opts['max_height']}px"];
    }

    if ($opts['exact_width'] !== null && $width !== $opts['exact_width']) {
        return ['valid' => false, 'error' => "Image width must be exactly {$opts['exact_width']}px"];
    }

    if ($opts['exact_height'] !== null && $height !== $opts['exact_height']) {
        return ['valid' => false, 'error' => "Image height must be exactly {$opts['exact_height']}px"];
    }

    return ['valid' => true];
}

function validateImageSignature(string $tmpPath, string $extension): array {
    $signatures = [
        'jpg' => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'webp' => ["\x52\x49\x46\x46"]
    ];

    if (!isset($signatures[$extension])) {
        return ['valid' => false, 'error' => 'Unsupported file type'];
    }

    $handle = fopen($tmpPath, 'rb');
    if (!$handle) {
        return ['valid' => false, 'error' => 'Cannot read file'];
    }

    $header = fread($handle, 32);
    fclose($handle);

    $signatureValid = false;
    foreach ($signatures[$extension] as $signature) {
        if (strpos($header, $signature) === 0) {
            $signatureValid = true;
            break;
        }
    }

    if (!$signatureValid) {
        return ['valid' => false, 'error' => 'File signature invalid'];
    }

    return ['valid' => true];
}

function validateMimeType(string $tmpPath, array $allowedTypes): array {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return ['valid' => false, 'error' => 'Cannot determine file type'];
    }

    $mimeType = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];

    $validMimes = [];
    foreach ($allowedTypes as $type) {
        if (isset($allowedMimes[$type])) {
            $validMimes[] = $allowedMimes[$type];
        }
    }

    if (!in_array($mimeType, $validMimes, true)) {
        return ['valid' => false, 'error' => 'Invalid image type: ' . $mimeType];
    }

    return ['valid' => true, 'mime_type' => $mimeType];
}

function checkMaliciousContent(string $tmpPath): array {
    $content = file_get_contents($tmpPath, false, null, 0, 8192);
    if ($content === false) {
        return ['valid' => false, 'error' => 'Cannot read file content'];
    }

    $patterns = [
        '/<\?php/i', '/<\?=/i', '/<script/i', '/<html/i', '/<body/i', '/<iframe/i',
        '/javascript:/i', '/vbscript:/i', '/data:/i', '/eval\s*\(/i', '/base64_decode\s*\(/i',
        '/shell_exec\s*\(/i', '/system\s*\(/i', '/exec\s*\(/i', '/passthru\s*\(/i',
        '/proc_open\s*\(/i', '/popen\s*\(/i', '/curl_exec\s*\(/i', '/file_get_contents\s*\(/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return ['valid' => false, 'error' => 'Malicious content detected'];
        }
    }

    return ['valid' => true];
}

function generateSecureFilename(string $prefix, string $extension): string {
    $maxPrefixLength = 184;
    if (strlen($prefix) > $maxPrefixLength) {
        $prefix = substr($prefix, 0, $maxPrefixLength);
    }

    return $prefix . uniqid() . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
}

function createImageResource(string $tmpPath, string $extension) {
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            return @imagecreatefromjpeg($tmpPath);
        case 'png':
            $image = @imagecreatefrompng($tmpPath);
            if ($image && function_exists('imagepalettetotruecolor')) {
                imagepalettetotruecolor($image);
                imagesavealpha($image, true);
            }
            return $image;
        case 'webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false;
        default:
            return false;
    }
}

function processAndSaveImage($sourceImage, string $outputPath, string $outputFormat, int $quality, array $opts): bool {
    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);

    $needsResize = false;
    $newWidth = $sourceWidth;
    $newHeight = $sourceHeight;

    if ($opts['crop_square']) {
        $size = min($sourceWidth, $sourceHeight);
        $cropX = ($sourceWidth - $size) / 2;
        $cropY = ($sourceHeight - $size) / 2;

        $cropped = imagecreatetruecolor($size, $size);

        if ($outputFormat === 'png' || $outputFormat === 'webp') {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
            imagefill($cropped, 0, 0, $transparent);
        }

        imagecopy($cropped, $sourceImage, 0, 0, $cropX, $cropY, $size, $size);

        imagedestroy($sourceImage);
        $sourceImage = $cropped;
        $sourceWidth = $sourceHeight = $size;
        $needsResize = true;
    }

    if ($opts['output_size'] > 0 && ($sourceWidth > $opts['output_size'] || $sourceHeight > $opts['output_size'])) {
        $ratio = min($opts['output_size'] / $sourceWidth, $opts['output_size'] / $sourceHeight);
        $newWidth = intval($sourceWidth * $ratio);
        $newHeight = intval($sourceHeight * $ratio);
        $needsResize = true;
    }

    $outputImage = imagecreatetruecolor($newWidth, $newHeight);

    if ($outputFormat === 'png' || $outputFormat === 'webp') {
        imagealphablending($outputImage, false);
        imagesavealpha($outputImage, true);
        $transparent = imagecolorallocatealpha($outputImage, 255, 255, 255, 127);
        imagefill($outputImage, 0, 0, $transparent);
    } else {
        $white = imagecolorallocate($outputImage, 255, 255, 255);
        imagefill($outputImage, 0, 0, $white);
    }

    if ($needsResize) {
        imagecopyresampled($outputImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
    } else {
        imagecopy($outputImage, $sourceImage, 0, 0, 0, 0, $sourceWidth, $sourceHeight);
    }

    imagedestroy($sourceImage);

    $saved = false;
    switch ($outputFormat) {
        case 'webp':
            $saved = function_exists('imagewebp') ? imagewebp($outputImage, $outputPath, $quality) : false;
            break;
        case 'jpg':
        case 'jpeg':
            $saved = imagejpeg($outputImage, $outputPath, $quality);
            break;
        case 'png':
            $pngQuality = 9 - intval(($quality / 100) * 6);
            $saved = imagepng($outputImage, $outputPath, $pngQuality);
            break;
        default:
            $saved = false;
    }

    imagedestroy($outputImage);
    return $saved;
}

function processImageUpload(array $file, array $options = []): array {
    try {
        if (!function_exists('finfo_open')) {
            throw new RuntimeException('Fileinfo extension required');
        }

        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
            throw new RuntimeException('GD extension required');
        }

        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
        }

        $opts = array_merge([
            'upload_path' => '',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'webp'],
            'max_size' => 5 * 1024 * 1024,
            'min_width' => 100,
            'min_height' => 100,
            'max_width' => 3000,
            'max_height' => 3000,
            'exact_width' => null,
            'exact_height' => null,
            'output_size' => 500,
            'crop_square' => false,
            'quality' => 85,
            'check_malicious' => true,
            'prefix' => 'upload_',
            'output_format' => 'webp',
            'force_reprocess' => true
        ], $options);

        if (!$opts['upload_path'] || !is_dir($opts['upload_path']) || !is_writable($opts['upload_path'])) {
            return ['success' => false, 'error' => 'Invalid upload path'];
        }

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'Partial upload',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
            ];
            return ['success' => false, 'error' => $errors[$file['error']] ?? 'Upload error'];
        }

        $tmpPath = $file['tmp_name'];
        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            return ['success' => false, 'error' => 'Invalid upload'];
        }

        $actualSize = filesize($tmpPath);
        if ($file['size'] !== $actualSize || $file['size'] === 0) {
            return ['success' => false, 'error' => 'File size validation failed'];
        }

        if ($file['size'] > $opts['max_size']) {
            $maxMB = round($opts['max_size'] / 1024 / 1024, 1);
            return ['success' => false, 'error' => "File too large. Max: {$maxMB}MB"];
        }

        $originalName = $file['name'];
        $dangerousExtensions = [
            'php', 'php3', 'php4', 'php5', 'pht', 'phtml', 'shtml', 'asp', 'aspx',
            'jsp', 'jspx', 'cfm', 'cfc', 'pl', 'bat', 'exe', 'com', 'scr', 'msi',
            'htaccess', 'htpasswd', 'ini', 'cfg', 'conf', 'config', 'sql', 'sh',
            'bash', 'cmd', 'vbs', 'ps1'
        ];

        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
        $filename = ltrim($filename, '.');
        $filename = substr($filename, 0, 255);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, $dangerousExtensions, true)) {
            return ['success' => false, 'error' => 'Dangerous file extension detected'];
        }

        if (!in_array($extension, $opts['allowed_types'], true)) {
            return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $opts['allowed_types'])];
        }

        $signatureResult = validateImageSignature($tmpPath, $extension);
        if (!$signatureResult['valid']) {
            return ['success' => false, 'error' => $signatureResult['error']];
        }

        $mimeResult = validateMimeType($tmpPath, $opts['allowed_types']);
        if (!$mimeResult['valid']) {
            return ['success' => false, 'error' => $mimeResult['error']];
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            return ['success' => false, 'error' => 'Not a valid image'];
        }

        $sizeResult = validateImageSize($imageInfo, $opts);
        if (!$sizeResult['valid']) {
            return ['success' => false, 'error' => $sizeResult['error']];
        }

        if (function_exists('exif_imagetype')) {
            $exifType = @exif_imagetype($tmpPath);
            $expectedTypes = [
                'jpg' => IMAGETYPE_JPEG,
                'jpeg' => IMAGETYPE_JPEG,
                'png' => IMAGETYPE_PNG,
                'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : null
            ];

            $expectedType = $expectedTypes[$extension] ?? null;
            if ($expectedType !== null && $exifType !== $expectedType) {
                return ['success' => false, 'error' => 'Image type mismatch'];
            }
        }

        if ($opts['check_malicious']) {
            $maliciousResult = checkMaliciousContent($tmpPath);
            if (!$maliciousResult['valid']) {
                return ['success' => false, 'error' => $maliciousResult['error']];
            }
        }

        $outputFormat = $opts['output_format'];
        if ($outputFormat === 'original') {
            $outputFormat = $extension;
        }

        $outputFilename = generateSecureFilename($opts['prefix'], $outputFormat);
        $outputPath = rtrim($opts['upload_path'], '/') . '/' . $outputFilename;

        $sourceImage = createImageResource($tmpPath, $extension);
        if (!$sourceImage) {
            return ['success' => false, 'error' => 'Cannot process image'];
        }

        $saved = processAndSaveImage($sourceImage, $outputPath, $outputFormat, $opts['quality'], $opts);

        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to save processed image'];
        }

        if (!file_exists($outputPath)) {
            return ['success' => false, 'error' => 'Output file not created'];
        }

        $finalImageInfo = getimagesize($outputPath);

        return [
            'success' => true,
            'filename' => $outputFilename,
            'path' => $outputPath,
            'size' => filesize($outputPath),
            'dimensions' => $finalImageInfo ? ['width' => $finalImageInfo[0], 'height' => $finalImageInfo[1]] : null,
            'type' => $mimeResult['mime_type'],
            'format' => $outputFormat,
            'reprocessed' => true
        ];

    } catch (Exception $e) {
        error_log('Image upload error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Processing failed'];
    }
}

?>