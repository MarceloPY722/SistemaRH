<?php
/**
 * Configuración centralizada de fechas del sistema
 * Este archivo maneja todas las fechas del sistema para garantizar consistencia
 * en las restricciones de guardias y rotaciones
 */

class FechaSistema {
    private static $fechaSimulada = null;
    private static $zonaHoraria = 'America/Asuncion'; // Ajustar según ubicación
    
    /**
     * Obtiene la fecha actual del sistema
     * @return DateTime
     */
    public static function obtenerFechaActual() {
        if (self::$fechaSimulada !== null) {
            return clone self::$fechaSimulada;
        }
        
        $fecha = new DateTime('now', new DateTimeZone(self::$zonaHoraria));
        return $fecha;
    }
    
    /**
     * Obtiene la fecha actual en formato Y-m-d para SQL
     * @return string
     */
    public static function obtenerFechaSQL() {
        return self::obtenerFechaActual()->format('Y-m-d');
    }
    
    /**
     * Obtiene la fecha y hora actual en formato Y-m-d H:i:s para SQL
     * @return string
     */
    public static function obtenerFechaHoraSQL() {
        return self::obtenerFechaActual()->format('Y-m-d H:i:s');
    }
    
    /**
     * Obtiene la fecha actual formateada para mostrar
     * @param string $formato
     * @return string
     */
    public static function obtenerFechaFormateada($formato = 'd/m/Y') {
        return self::obtenerFechaActual()->format($formato);
    }
    
    /**
     * Obtiene el primer día del mes actual
     * @return DateTime
     */
    public static function obtenerPrimerDiaMes() {
        $fecha = self::obtenerFechaActual();
        return $fecha->modify('first day of this month')->setTime(0, 0, 0);
    }
    
    /**
     * Obtiene el último día del mes actual
     * @return DateTime
     */
    public static function obtenerUltimoDiaMes() {
        $fecha = self::obtenerFechaActual();
        return $fecha->modify('last day of this month')->setTime(23, 59, 59);
    }
    
    /**
     * Verifica si una fecha está en el mes actual
     * @param string $fecha Fecha en formato Y-m-d
     * @return bool
     */
    public static function estaEnMesActual($fecha) {
        if (empty($fecha)) return false;
        
        $fechaComparar = new DateTime($fecha);
        $fechaActual = self::obtenerFechaActual();
        
        return $fechaComparar->format('Y-m') === $fechaActual->format('Y-m');
    }
    
    /**
     * Calcula días transcurridos desde una fecha
     * @param string $fecha Fecha en formato Y-m-d
     * @return int
     */
    public static function diasTranscurridos($fecha) {
        if (empty($fecha)) return 999; // Valor alto para indicar "nunca"
        
        $fechaComparar = new DateTime($fecha);
        $fechaActual = self::obtenerFechaActual();
        
        $diferencia = $fechaActual->diff($fechaComparar);
        return $diferencia->days;
    }
    
    /**
     * Obtiene la próxima fecha disponible para guardia REGIONAL (próximo mes)
     * @param string $ultimaGuardia Fecha de última guardia en formato Y-m-d
     * @return DateTime
     */
    public static function obtenerProximaFechaDisponibleRegional($ultimaGuardia) {
        if (empty($ultimaGuardia)) {
            return self::obtenerFechaActual(); // Disponible inmediatamente
        }
        
        $fechaUltimaGuardia = new DateTime($ultimaGuardia);
        $proximaFechaDisponible = clone $fechaUltimaGuardia;
        $proximaFechaDisponible->modify('first day of next month');
        
        return $proximaFechaDisponible;
    }
    
    /**
     * Obtiene la próxima fecha disponible para guardia CENTRAL (cada 15 días)
     * @param string $ultimaGuardia Fecha de última guardia en formato Y-m-d
     * @return DateTime
     */
    public static function obtenerProximaFechaDisponibleCentral($ultimaGuardia) {
        if (empty($ultimaGuardia)) {
            return self::obtenerFechaActual(); // Disponible inmediatamente
        }
        
        $fechaUltimaGuardia = new DateTime($ultimaGuardia);
        $proximaFechaDisponible = clone $fechaUltimaGuardia;
        $proximaFechaDisponible->modify('+15 days');
        
        return $proximaFechaDisponible;
    }
    
    /**
     * Verifica si un policía REGIONAL puede hacer guardia (una vez por mes)
     * @param string $ultimaGuardia Fecha de última guardia en formato Y-m-d
     * @return bool
     */
    public static function puedeHacerGuardiaRegional($ultimaGuardia) {
        if (empty($ultimaGuardia)) return true;
        
        return !self::estaEnMesActual($ultimaGuardia);
    }
    
    /**
     * Verifica si un policía CENTRAL puede hacer guardia (cada 15 días)
     * @param string $ultimaGuardia Fecha de última guardia en formato Y-m-d
     * @return bool
     */
    public static function puedeHacerGuardiaCentral($ultimaGuardia) {
        if (empty($ultimaGuardia)) return true;
        
        return self::diasTranscurridos($ultimaGuardia) >= 15;
    }
    
    /**
     * Establece una fecha simulada para testing
     * @param string $fecha Fecha en formato Y-m-d
     */
    public static function simularFecha($fecha) {
        self::$fechaSimulada = new DateTime($fecha, new DateTimeZone(self::$zonaHoraria));
    }
    
    /**
     * Limpia la fecha simulada
     */
    public static function limpiarSimulacion() {
        self::$fechaSimulada = null;
    }
    
    /**
     * Establece la zona horaria del sistema
     * @param string $zonaHoraria
     */
    public static function establecerZonaHoraria($zonaHoraria) {
        self::$zonaHoraria = $zonaHoraria;
    }
}

// Configuración por defecto
FechaSistema::establecerZonaHoraria('America/Asuncion'); // Ajustar según ubicación
?>