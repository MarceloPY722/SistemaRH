<?php
require_once '../config/config_fecha_sistema.php';

echo "<h2>Prueba del Sistema de Fechas</h2>";

// Fecha actual
echo "<p><strong>Fecha actual:</strong> " . FechaSistema::obtenerFechaFormateada() . "</p>";
echo "<p><strong>Fecha SQL:</strong> " . FechaSistema::obtenerFechaSQL() . "</p>";

// Pruebas de restricciones
echo "<h3>Pruebas de Restricciones</h3>";

// Simular fecha para pruebas
FechaSistema::simularFecha('2024-02-15');
echo "<p><em>Simulando fecha: 2024-02-15</em></p>";

// Prueba REGIONAL
$ultimaGuardiaRegional = '2024-02-01';
echo "<p><strong>REGIONAL - Última guardia:</strong> $ultimaGuardiaRegional</p>";
echo "<p><strong>¿Puede hacer guardia?</strong> " . (FechaSistema::puedeHacerGuardiaRegional($ultimaGuardiaRegional) ? 'SÍ' : 'NO') . "</p>";
echo "<p><strong>Próxima fecha disponible:</strong> " . FechaSistema::obtenerProximaFechaDisponibleRegional($ultimaGuardiaRegional)->format('d/m/Y') . "</p>";

// Prueba CENTRAL
$ultimaGuardiaCentral = '2024-02-01';
echo "<p><strong>CENTRAL - Última guardia:</strong> $ultimaGuardiaCentral</p>";
echo "<p><strong>¿Puede hacer guardia?</strong> " . (FechaSistema::puedeHacerGuardiaCentral($ultimaGuardiaCentral) ? 'SÍ' : 'NO') . "</p>";
echo "<p><strong>Próxima fecha disponible:</strong> " . FechaSistema::obtenerProximaFechaDisponibleCentral($ultimaGuardiaCentral)->format('d/m/Y') . "</p>";

// Limpiar simulación
FechaSistema::limpiarSimulacion();
echo "<p><em>Simulación limpiada</em></p>";
echo "<p><strong>Fecha actual real:</strong> " . FechaSistema::obtenerFechaFormateada() . "</p>";
?>