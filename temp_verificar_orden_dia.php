<?php
require_once 'cnx/db_connect.php';

$query = 'DESCRIBE orden_dia';
$result = $conn->query($query);

if ($result) {
    echo 'Columnas de la tabla orden_dia:\n';
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo ' - ' . $row['Field'] . ' (' . $row['Type'] . ')' . '\n';
    }
} else {
    echo 'Error al describir la tabla: ' . $conn->errorInfo()[2] . '\n';
}
?>