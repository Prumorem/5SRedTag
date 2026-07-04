<?php
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

function getDb() {
    $db = new PDO("sqlite:" . __DIR__ . "/data/mt5200_patrol.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'load') {
    $factory = $_GET['factory'] ?? '1st Floor';

    $db = getDb();

    $stmt = $db->prepare("
        SELECT *
        FROM tags
        WHERE is_deleted = 0
        AND factory = :factory
        ORDER BY createdAt DESC
    ");

    $stmt->execute([
        ':factory' => $factory
    ]);

    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = null;
    $factoryDir = __DIR__ . "/config/" . $factory;

    if (is_dir($factoryDir)) {
        $files = glob($factoryDir . "/FloorPlan.*");
        if ($files) {
            foreach ($files as $file) {
                if (!preg_match('/FloorPlan_\d{4}/', basename($file))) {
                    $map = "config/" . rawurlencode($factory) . "/" . basename($file) . "?v=" . filemtime($file);
                    break;
                }
            }
        }
    }

    jsonResponse([
        'success' => true,
        'tags' => $tags,
        'map' => $map
    ]);
}
if ($action === 'save') {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input["id"])) {
        jsonResponse([
            "success" => false,
            "error" => "Missing input or id"
        ]);
    }

    $db = getDb();

    $check = $db->prepare("SELECT COUNT(*) FROM tags WHERE id=:id");
    $check->execute([
        ":id"=>$input["id"]
    ]);

    $exists = $check->fetchColumn();

    if($exists){

        $sql = $db->prepare("
            UPDATE tags SET

            x=:x,
            y=:y,
            productionLine=:productionLine,
            description=:description,
            solution=:solution,
            status=:status,
            image=:image,
            imageAfter=:imageAfter,
            zone=:zone,
            updatedAt=:updatedAt,
            pic=:pic,
            category=:category,
            factory=:factory,
            inspectionType=:inspectionType

            WHERE id=:id
        ");

    }else{

        $sql = $db->prepare("
            INSERT INTO tags(

                id,
                x,
                y,
                productionLine,
                description,
                solution,
                status,
                image,
                imageAfter,
                zone,
                createdAt,
                is_deleted,
                updatedAt,
                pic,
                category,
                factory,
                inspectionType

            )

            VALUES(

                :id,
                :x,
                :y,
                :productionLine,
                :description,
                :solution,
                :status,
                :image,
                :imageAfter,
                :zone,
                :createdAt,
                0,
                :updatedAt,
                :pic,
                :category,
                :factory,
                :inspectionType

            )
        ");
    }

    $now = date("Y-m-d H:i:s");

    $params = [

        ":id"=>$input["id"],
        ":x"=>$input["x"],
        ":y"=>$input["y"],
        ":productionLine"=>$input["productionLine"],
        ":description"=>$input["description"],
        ":solution"=>$input["solution"],
        ":status"=>$input["status"],
        ":image"=>$input["image"],
        ":imageAfter"=>$input["imageAfter"],
        ":zone"=>$input["zone"],
        ":updatedAt"=>$now,
        ":pic"=>$input["pic"],
        ":category"=>$input["category"],
        ":factory"=>$input["factory"],
        ":inspectionType"=>$input["inspectionType"]

    ];

    if(!$exists){
        $params[":createdAt"] = $input["createdAt"] ?? $now;
    }

    $sql->execute($params);

    jsonResponse([
        "success"=>true
    ]);
}
if ($action === 'delete') {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input || !isset($input["id"])) {
        jsonResponse([
            "success" => false,
            "error" => "Missing id"
        ]);
    }

    $db = getDb();

    $sql = $db->prepare("
        UPDATE tags
        SET
            is_deleted = 1,
            updatedAt = :updatedAt
        WHERE id = :id
    ");

    $sql->execute([
        ":id" => $input["id"],
        ":updatedAt" => date("Y-m-d H:i:s")
    ]);

    jsonResponse([
        "success" => true
    ]);
}
if ($action === 'export') {

    $db = getDb();

    $stmt = $db->prepare("
        SELECT
            id, x, y, productionLine, description, solution,
            status, image, imageAfter, zone, createdAt,
            is_deleted, updatedAt, pic, category, factory, inspectionType
        FROM tags
        WHERE is_deleted = 0
        ORDER BY createdAt DESC
    ");

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mt5200_patrol_export.csv"');

    $output = fopen('php://output', 'w');

    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'id', 'x', 'y', 'productionLine', 'description', 'solution',
        'status', 'image', 'imageAfter', 'zone', 'createdAt',
        'is_deleted', 'updatedAt', 'pic', 'category', 'factory', 'inspectionType'
    ]);

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
if ($action === 'backup') {

    $dbFile = __DIR__ . "/data/mt5200_patrol.db";

    if (!file_exists($dbFile)) {
        jsonResponse([
            "success" => false,
            "error" => "Database file not found"
        ]);
    }

    $fileName = "mt5200_patrol_backup_" . date("Ymd_His") . ".db";

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($dbFile));

    readfile($dbFile);
    exit;
}