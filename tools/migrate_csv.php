<?php

$db = new PDO("sqlite:data/mt5200_patrol.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$file = fopen("data/tags.csv", "r");

if (!$file) {
    die("CSV not found");
}

$header = fgetcsv($file);

// ล้าง BOM และช่องว่างหัวตาราง
$header = array_map(function ($h) {
    return trim(str_replace("\xEF\xBB\xBF", "", $h));
}, $header);

$count = 0;

while (($row = fgetcsv($file)) !== false) {

    if (count($row) !== count($header)) {
        continue;
    }

    $data = array_combine($header, $row);

    $id = $data["id"] ?? time() . $count;

    $sql = $db->prepare("
        INSERT OR REPLACE INTO tags (
            id, x, y, productionLine, description, solution,
            status, image, imageAfter, zone, createdAt,
            is_deleted, updatedAt, pic, category, factory, inspectionType
        ) VALUES (
            :id, :x, :y, :productionLine, :description, :solution,
            :status, :image, :imageAfter, :zone, :createdAt,
            :is_deleted, :updatedAt, :pic, :category, :factory, :inspectionType
        )
    ");

    $sql->execute([
        ":id" => $id,
        ":x" => $data["x"] ?? 0,
        ":y" => $data["y"] ?? 0,
        ":productionLine" => $data["productionLine"] ?? "",
        ":description" => $data["description"] ?? "",
        ":solution" => $data["solution"] ?? "",
        ":status" => $data["status"] ?? "Open",
        ":image" => $data["image"] ?? "",
        ":imageAfter" => $data["imageAfter"] ?? "",
        ":zone" => $data["zone"] ?? "",
        ":createdAt" => $data["createdAt"] ?? date("c"),
        ":is_deleted" => $data["is_deleted"] ?? 0,
        ":updatedAt" => $data["updatedAt"] ?? date("c"),
        ":pic" => $data["pic"] ?? "",
        ":category" => $data["category"] ?? "",
        ":factory" => $data["factory"] ?? "1st Floor",
        ":inspectionType" => $data["inspectionType"] ?? ""
    ]);

    $count++;
}

fclose($file);

echo "<h2>Import Complete</h2>";
echo "Imported : " . $count . " records";