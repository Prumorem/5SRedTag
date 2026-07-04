<?php
date_default_timezone_set('Asia/Bangkok');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow local testing if needed



$pwFile = $configDir . '/pw.txt';

// Ensure directories exist
if (!file_exists($dataDir))
    mkdir($dataDir, 0777, true);
if (!file_exists($uploadsDir))
    mkdir($uploadsDir, 0777, true);
if (!file_exists($configDir))
    mkdir($configDir, 0777, true);



if (!file_exists($pwFile)) {
    file_put_contents($pwFile, '5555');
}

$action = $_GET['action'] ?? '';

if ($action === 'check_password') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pwd = $input['password'] ?? '';

    $storedPwd = trim(file_get_contents($pwFile));

    if ($pwd === $storedPwd) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

$action = $_GET['action'] ?? '';





    // DEBUG LOGGING DISABLED
    // $logEntry = date('Y-m-d H:i:s') . " SAVE INPUT: " . json_encode($input) . "\n";
    // file_put_contents(__DIR__ . '/debug_log.txt', $logEntry, FILE_APPEND);

    $tags = [];
    $found = false;
    $success = false;

    // Use c+ to open for reading and writing, keeping pointer at beginning
    if (($handle = fopen($csvFile, "c+")) !== FALSE) {
        // Acquire exclusive lock - waits if another user is writing
        if (flock($handle, LOCK_EX)) {

            // Read headers
            $headers = fgetcsv($handle);

            // Read all existing tags
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Determine length for correct field mapping
                $len = count($data);

                if ($data[0] == $input['id']) {
                    // Update existing
                    // Need to preserve original createdAt
                    $originalCreatedAt = '';

                    // Determine createdAt based on row length
                    // V1 (9 cols): createdAt at 8
                    // V2 (10 cols): createdAt at 9
                    // V3 (11 cols): createdAt at 10
                    // V4 (12 cols): createdAt at 10 (is_deleted at 11)
                    // V5 (13 cols): createdAt at 10 (updatedAt at 12)

                    if ($len == 9) {
                        $originalCreatedAt = $data[8];
                    } elseif ($len == 10) {
                        $originalCreatedAt = $data[9];
                    } elseif ($len >= 11) {
                        // For V3+, createdAt is at index 10
                        $originalCreatedAt = $data[10];
                    }

                    // Fallback to current time if for some reason it's empty or 0 (likely data corruption)
                    if (empty($originalCreatedAt) || $originalCreatedAt === '0') {
                        $originalCreatedAt = date('Y-m-d H:i:s');
                    }

                    $updatedRow = [
                        $input['id'],
                        $input['x'],
                        $input['y'],
                        $input['productionLine'],
                        $input['description'],
                        $input['solution'],
                        $input['status'],
                        $input['image'],
                        $input['imageAfter'] ?? '',
                        $input['zone'] ?? '',
                        $originalCreatedAt, // Preserve
                        '0', // is_deleted
                        date('Y-m-d H:i:s'), // updatedAt - NEW value
                        $input['pic'] ?? '', // PIC (new last col)
                        $input['category'] ?? '', // Category
                        $input['factory'] ?? '1st Floor', // Factory
                        $input['inspectionType'] ?? '5S',
                    ];
                    $tags[] = $updatedRow;
                    $found = true;
                } else {
                    // Migration: Normalize to 16 cols
                    // Current max cols is 16 (id..zone, created, deleted, updated, pic, cat, factory)
                    if ($len < 17) {
                        $newRow = array_slice($data, 0, 8); // 0-7 are standard

                        // 8 is imageAfter (if len >= 10) OR createdAt (if len == 9)
                        $imgAfter = ($len >= 10) ? $data[8] : '';

                        // Zone
                        $zone = ($len >= 11) ? $data[9] : '';

                        // CreatedAt logic mirroring above
                        $createdAt = '';
                        if ($len == 9)
                            $createdAt = $data[8];
                        elseif ($len == 10)
                            $createdAt = $data[9];
                        elseif ($len >= 11)
                            $createdAt = $data[10];

                        // is_deleted logic
                        $is_deleted = '0';
                        if ($len >= 12 && isset($data[11]))
                            $is_deleted = $data[11];

                        // updatedAt logic
                        $updatedAt = '';
                        // If previously had modifiedAt (written by delete action in V4-ish), it might be at index 12
                        if ($len >= 13 && isset($data[12]))
                            $updatedAt = $data[12];

                        // PIC logic - new column at 13
                        $pic = '';
                        if ($len >= 14 && isset($data[13]))
                            $pic = $data[13];

                        $category = '';
                        if ($len >= 15 && isset($data[14]))
                            $category = $data[14];

                        $factoryCol = '';
                        if ($len >= 16 && isset($data[15]))
                            $factoryCol = $data[15];

                        $newRow[] = $imgAfter; // 8
                        $newRow[] = $zone;     // 9
                        $newRow[] = $createdAt; // 10
                        $newRow[] = $is_deleted; // 11
                        $newRow[] = $updatedAt; // 12
                        $newRow[] = $pic;       // 13
                        $newRow[] = $category;  // 14
                        $newRow[] = ($factoryCol === '') ? '1st Floor' : $factoryCol; // 15 Default old tags to 1st Floor directly on save
                        $newRow[] = '5S';
                        $tags[] = $newRow;
                    } else {
                        $tags[] = $data;
                    }
                }
            }

            // If new, append
            if (!$found) {
                $maxId = 0;
                foreach ($tags as $t) {
                    $currId = (int) $t[0];
                    if ($currId > $maxId) {
                        $maxId = $currId;
                    }
                }
                $nextId = $maxId + 1;

                $tags[] = [
                    $nextId,
                    $input['x'],
                    $input['y'],
                    $input['productionLine'],
                    $input['description'],
                    $input['solution'],
                    $input['status'],
                    $input['image'],
                    $input['imageAfter'] ?? '',
                    $input['zone'] ?? '',
                    date('Y-m-d H:i:s'), // createdAt
                    '0', // is_deleted
                    '', // updatedAt (empty on creation)
                    $input['pic'] ?? '', // PIC
                    $input['category'] ?? '', // Category
                    $input['factory'] ?? '', // Factory
                    $input['inspectionType'] ?? '5S',
                ];
            }

            // Truncate file to 0 and rewind
            ftruncate($handle, 0);
            rewind($handle);

            // Write BOM for Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // Write all back
            fputcsv($handle, ['id', 'x', 'y', 'productionLine', 'description', 'solution', 'status', 'image', 'imageAfter', 'zone', 'createdAt', 'is_deleted', 'updatedAt', 'pic', 'category', 'factory', 'inspectionType']);
            foreach ($tags as $tag) {

                    // Should be handled by migration block above, but double check
                    while (count($tag) < 17) {
                        $tag[] = ''; 
                    }
                    $tag = array_slice($tag, 0, 17);
                
                    fputcsv($handle, $tag);
            }

            fflush($handle); // Flush output before releasing lock
            flock($handle, LOCK_UN); // Release lock
            $success = true;
        } else {
            $errorMsg = "Could not lock file (in use?)";
        }
        fclose($handle);
    } else {
        $errorMsg = "Could not open file (permissions?)";
    }

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $errorMsg ?? 'Unknown save error']);
    }
    exit;
}



function compressAndResizeImage($filepath, $maxWidth, $quality)
{
    if (!extension_loaded('gd'))
        return false;

    $info = @getimagesize($filepath);
    if (!$info)
        return false;

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
    if (!$img)
        return false;

    $width = $info[0];
    $height = $info[1];

    // EXIF orientation
    if ($mime == 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($filepath);
        if ($exif && isset($exif['Orientation'])) {
            $orientation = $exif['Orientation'];
            if ($orientation != 1) {
                $deg = 0;
                switch ($orientation) {
                    case 3:
                        $deg = 180;
                        break;
                    case 6:
                        $deg = 270;
                        break;
                    case 8:
                        $deg = 90;
                        break;
                }
                if ($deg) {
                    $rotatedImg = @imagerotate($img, $deg, 0);
                    if ($rotatedImg) {
                        imagedestroy($img);
                        $img = $rotatedImg;
                        if ($deg == 90 || $deg == 270) {
                            $tmp = $width;
                            $width = $height;
                            $height = $tmp;
                        }
                    }
                }
            }
        }
    }

    $newWidth = $width;
    $newHeight = $height;

    // Apply the 600/450 logic ONLY if the function is not being used for floor plan (which passes 2560)
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
            $newHeight = floor($height * ($maxWidth / $width));
        }
    }

    $newImg = imagecreatetruecolor($newWidth, $newHeight);

    // Transparency
    if ($mime == 'image/png' || $mime == 'image/gif' || $mime == 'image/webp') {
        imagecolortransparent($newImg, imagecolorallocatealpha($newImg, 0, 0, 0, 127));
        imagealphablending($newImg, false);
        imagesavealpha($newImg, true);
    }

    imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $success = false;
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $success = imagejpeg($newImg, $filepath, $quality);
            break;
        case 'png':
            $pngQuality = floor((100 - $quality) / 10);
            if ($pngQuality < 0)
                $pngQuality = 0;
            if ($pngQuality > 9)
                $pngQuality = 9;
            $success = imagepng($newImg, $filepath, $pngQuality);
            break;
        case 'gif':
            $success = imagegif($newImg, $filepath);
            break;
        case 'webp':
            $success = imagewebp($newImg, $filepath, $quality);
            break;
    }

    imagedestroy($img);
    imagedestroy($newImg);

    return $success;
}

if ($action === 'upload') {
    // Check if upload failed due to post_max_size
    if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        http_response_code(413); // Payload Too Large
        echo json_encode(['error' => 'File is too large (exceeds server POST limit). Please try a smaller file.']);
        exit;
    }

    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['file'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Upload failed';
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msg = 'File is too large (server limit)';
                break;
            case UPLOAD_ERR_PARTIAL:
                $msg = 'File was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $msg = 'No file was uploaded';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $msg = 'Missing a temporary folder';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $msg = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $msg = 'File upload stopped by extension';
                break;
        }
        echo json_encode(['error' => $msg]);
        exit;
    }

    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array(strtolower($ext), $allowed)) {
        echo json_encode(['error' => 'Invalid file type']);
        exit;
    }

    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    $filename = uniqid() . '.' . $ext;
    $targetPath = $uploadsDir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Start at 80% quality — excellent visual quality, aiming for under 150KB
        compressAndResizeImage($targetPath, 600, 80);

        // Ensure file stays under 150KB by gradually reducing quality if necessary
        $quality = 80;
        clearstatcache(true, $targetPath);
        while (filesize($targetPath) > 150 * 1024 && $quality >= 10 && in_array(strtolower($ext), ['jpg', 'jpeg', 'webp'])) {
            $quality -= 5;
            compressAndResizeImage($targetPath, 600, $quality);
            clearstatcache(true, $targetPath);
        }

        echo json_encode(['path' => $targetPath]);
    } else {
        echo json_encode(['error' => 'Upload failed']);
    }
    exit;
}

if ($action === 'upload_floor_plan') {
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file']);
        exit;
    }

    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array(strtolower($ext), $allowed)) {
        echo json_encode(['error' => 'Invalid file type']);
        exit;
    }

    $factory = $_POST['factory'] ?? '1st Floor';
    $factoryDir = $configDir . '/' . $factory;
    if (!file_exists($factoryDir))
        mkdir($factoryDir, 0777, true);

    // Archive existing in Factory CONFIG dir
    $existing = glob($factoryDir . '/FloorPlan.*');
    foreach ($existing as $oldFile) {
        // Skip files that already have timestamp in name (simple check)
        if (preg_match('/FloorPlan_\d{4}-\d{2}-\d{2}_/', basename($oldFile)))
            continue;

        $oldExt = pathinfo($oldFile, PATHINFO_EXTENSION);
        $timestamp = date('Y-m-d_H-i-s', filemtime($oldFile));
        rename($oldFile, $factoryDir . '/FloorPlan_' . $timestamp . '.' . $oldExt);
    }

    // Save new as standard name in Factory CONFIG dir
    $targetPath = $factoryDir . '/FloorPlan.' . $ext;

    // Remove any collision if extension changed
    $others = glob($factoryDir . '/FloorPlan.*');
    foreach ($others as $o) {
        if ($o !== $targetPath && !preg_match('/FloorPlan_\d{4}/', basename($o))) {
            unlink($o);
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        compressAndResizeImage($targetPath, 2560, 80);
        echo json_encode(['path' => $targetPath, 'success' => true]);
    } else {
        echo json_encode(['error' => 'Upload failed']);
    }
    exit;
}
