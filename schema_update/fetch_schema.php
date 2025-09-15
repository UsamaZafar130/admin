<?php
// Database credentials
$host = 'localhost';
$user = 'frozofun_usama';
$pass = 'mEnAAl86UsAmA!@';
$dbname = 'frozofun_main';

// Connect to DB
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

$schema = [];
foreach ($tables as $table) {
    $columns = [];
    $col_result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($col = $col_result->fetch_assoc()) {
        $columns[] = $col;
    }
    $schema[$table] = $columns;
}

// Save as JSON
file_put_contents('schema.json', json_encode($schema, JSON_PRETTY_PRINT));
echo "Schema exported!\n";
$conn->close();
?>