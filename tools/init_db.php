<?php

$dbFile = __DIR__ . "/data/mt5200_patrol.db";

try {

    $db = new PDO("sqlite:" . $dbFile);

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "

    CREATE TABLE IF NOT EXISTS tags (

        id INTEGER PRIMARY KEY,

        x REAL,

        y REAL,

        productionLine TEXT,

        description TEXT,

        solution TEXT,

        status TEXT,

        image TEXT,

        imageAfter TEXT,

        zone TEXT,

        createdAt TEXT,

        is_deleted INTEGER DEFAULT 0,

        updatedAt TEXT,

        pic TEXT,

        category TEXT,

        factory TEXT,

        inspectionType TEXT

    );

    ";

    $db->exec($sql);

    echo "<h2>Database created successfully.</h2>";

    echo "<p>";

    echo $dbFile;

    echo "</p>";

} catch (Exception $e) {

    echo $e->getMessage();

}
