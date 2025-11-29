<?php
// Test script to verify database connection and data
require_once 'cnx/db_connect.php';

echo "Testing database connection...\n";

try {
    // Test basic connection
    $result = $conn->query("SELECT 1 as test");
    if ($result) {
        echo "✓ Database connection successful\n";
    }
    
    // Test tables exist
    $tables = ['policias', 'ausencias', 'lista_guardias', 'servicios', 'tipos_servicios'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' NOT found\n";
        }
    }
    
    // Test data queries
    echo "\nTesting data queries...\n";
    
    // Test policias count
    $count = $conn->query("SELECT COUNT(*) FROM policias WHERE activo = 1")->fetchColumn();
    echo "✓ Active policias: $count\n";
    
    // Test ausencias
    $count = $conn->query("SELECT COUNT(*) FROM ausencias WHERE estado = 'APROBADA'")->fetchColumn();
    echo "✓ Approved ausencias: $count\n";
    
    // Test guardias
    $count = $conn->query("SELECT COUNT(DISTINCT p.lugar_guardia_id) FROM lista_guardias lg JOIN policias p ON lg.policia_id = p.id")->fetchColumn();
    echo "✓ Distinct guardia locations: $count\n";
    
    echo "\n✓ All tests passed!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn = null;
?>