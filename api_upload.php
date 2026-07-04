<?php
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$uploadsDir = __DIR__ . '/uploads';
$configDir  = __DIR__ . '/config';
$pwFile     = $configDir . '/pw.txt';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

if (!is_dir($configDir)) {
    mkdir($configDir, 0777, true);
}

if (!file_exists($pwFile)) {
    file_put_contents($pwFile, '5555');
}

$action = $_GET['action'] ?? '';

function jsonOut($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function safeFactoryName($factory) {
    $allowed = ['1st Floor', '2nd Floor'];

    if (in_array($factory, $allowed, true)) {
        return $factory;
    }

    return '1st Floor';
}

function compressAndResizeImage($filepath, $maxWidth, $quality) {
    if (!extension_loaded('gd')) {
        return false;
    }

    $info = @getimagesize($filepath);

    if (!$info) {
        return false;
    }

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($filepath);
            break;

        case 'image/png':
            $img = @imagecreatefrompng($filepath);
            break;

        case 'image/gif':
            $img = @imagecreatefromgif($filepath);
            break;

        case 'image/webp':
            $img = @imagecreatefromwebp($filepath);
            break;

        default:
            return false;
    }

    if (!$img) {
        return false;
    }

    $width = imagesx($img);
    $height = imagesy($img);

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($filepath);

        if ($exif && isset($exif['Orientation'])) {
            $orientation = $exif['Orientation'];
            $degrees = 0;

            if ($orientation == 3) {
                $degrees = 180;
            } elseif ($orientation == 6) {
                $degrees = 270;
            } elseif ($orientation == 8) {
                $degrees = 90;
            }

            if ($degrees) {
                $rotated = @imagerotate($img, $degrees, 0);

                if ($rotated) {
                    imagedestroy($img);
                    $img = $rotated;
                    $width = imagesx($img);
                    $height = imagesy($img);
                }
            }
        }
    }

    $newWidth = $width;
    $newHeight = $height;

    if ($maxWidth == 600) {
        if ($width >= $height) {
            if ($width > 600) {
                $newWidth = 600;
                $newHeight = (int) floor($height * (600 / $width));
            }
        } else {
            if ($height > 450) {
                $newHeight = 450;
                $newWidth = (int) floor($width * (450 / $height));
            }
        }
    } else {
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) floor($height * ($maxWidth / $width));
        }
    }

    $newImg = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
        imagealphablending($newImg, false);
        imagesavealpha($newImg, true);
        $transparent = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
        imagefilledrectangle($newImg, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled(
        $newImg,
        $img,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $width,
        $height
    );

    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $success = false;

    if ($ext === 'jpg' || $ext === 'jpeg') {
        $success = imagejpeg($newImg, $filepath, $quality);
    } elseif ($ext === 'png') {
        $pngQuality = (int) floor((100 - $quality) / 10);
        $pngQuality = max(0, min(9, $pngQuality));
        $success = imagepng($newImg, $filepath, $pngQuality);
    } elseif ($ext === 'gif') {
        $success = imagegif($newImg, $filepath);
    } elseif ($ext === 'webp') {
        $success = imagewebp($newImg, $filepath, $quality);
    }

    imagedestroy($img);
    imagedestroy($newImg);

    return $success;
}

function validateImageUpload() {
    if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        jsonOut([
            'error' => 'File is too large. Please try a smaller file.'
        ], 413);
    }

    if (!isset($_FILES['file'])) {
        jsonOut([
            'error' => 'No file uploaded'
        ], 400);
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload failed';

        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msg = 'File is too large';
                break;

            case UPLOAD_ERR_PARTIAL:
                $msg = 'File was only partially uploaded';
                break;

            case UPLOAD_ERR_NO_FILE:
                $msg = 'No file was uploaded';
                break;

            case UPLOAD_ERR_NO_TMP_DIR:
                $msg = 'Missing temporary folder';
                break;

            case UPLOAD_ERR_CANT_WRITE:
                $msg = 'Failed to write file to disk';
                break;

            case UPLOAD_ERR_EXTENSION:
                $msg = 'File upload stopped by extension';
                break;
        }

        jsonOut([
            'error' => $msg
        ], 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        jsonOut([
            'error' => 'Invalid file type'
        ], 400);
    }

    return [$file, $ext];
}

if ($action === 'check_password') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pwd = $input['password'] ?? '';

    $storedPwd = trim(file_get_contents($pwFile));

    jsonOut([
        'success' => $pwd === $storedPwd
    ]);
}

if ($action === 'upload') {
    [$file, $ext] = validateImageUpload();

    $filename = uniqid('', true) . '.' . $ext;
    $targetPath = $uploadsDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonOut([
            'error' => 'Upload failed'
        ], 500);
    }

    compressAndResizeImage($targetPath, 600, 80);

    $quality = 80;

    clearstatcache(true, $targetPath);

    while (
        filesize($targetPath) > 150 * 1024 &&
        $quality >= 10 &&
        in_array($ext, ['jpg', 'jpeg', 'webp'], true)
    ) {
        $quality -= 5;
        compressAndResizeImage($targetPath, 600, $quality);
        clearstatcache(true, $targetPath);
    }

    $relativePath = 'uploads/' . $filename;

    jsonOut([
        'success' => true,
        'path' => $relativePath
    ]);
}

if ($action === 'upload_floor_plan') {
    [$file, $ext] = validateImageUpload();

    $factory = safeFactoryName($_POST['factory'] ?? '1st Floor');
    $factoryDir = $configDir . '/' . $factory;

    if (!is_dir($factoryDir)) {
        mkdir($factoryDir, 0777, true);
    }

    $existing = glob($factoryDir . '/FloorPlan.*');

    foreach ($existing as $oldFile) {
        if (preg_match('/FloorPlan_\d{4}-\d{2}-\d{2}_/', basename($oldFile))) {
            continue;
        }

        $oldExt = pathinfo($oldFile, PATHINFO_EXTENSION);
        $timestamp = date('Y-m-d_H-i-s', filemtime($oldFile));
        $archivePath = $factoryDir . '/FloorPlan_' . $timestamp . '.' . $oldExt;

        @rename($oldFile, $archivePath);
    }

    $targetPath = $factoryDir . '/FloorPlan.' . $ext;

    $others = glob($factoryDir . '/FloorPlan.*');

    foreach ($others as $otherFile) {
        if ($otherFile !== $targetPath && !preg_match('/FloorPlan_\d{4}/', basename($otherFile))) {
            @unlink($otherFile);
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonOut([
            'error' => 'Upload floor plan failed'
        ], 500);
    }

    compressAndResizeImage($targetPath, 2560, 80);

    $relativePath = 'config/' . rawurlencode($factory) . '/FloorPlan.' . $ext . '?v=' . filemtime($targetPath);

    jsonOut([
        'success' => true,
        'path' => $relativePath
    ]);
}

jsonOut([
    'success' => false,
    'error' => 'Invalid action'
], 400);