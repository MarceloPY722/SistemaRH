<?php
// Database connection is passed through constructor
// FPDF is only needed for PDF generation, not for FIFO testing

class GeneradorGuardias {
    private $conn;
    
    // Configuración de puestos requeridos
    private $puestos_requeridos = [
        'JEFE_SERVICIO' => 1,
        'JEFE_CUARTEL' => 1,
        'OFICIAL_GUARDIA' => 1,
        'ATENCION_TELEFONICA_EXCLUSIVA' => 1,
        'NUMERO_GUARDIA' => 4,
        'CONDUCTOR_GUARDIA' => 1,
        'GUARDIA_06_30_22_00' => 1,
        'TENIDA_REGLAMENTO' => 1,
        'SANIDAD_GUARDIA' => 3
    ];
    
    // Límites de comisionamiento por día
    private $limites_comisionamiento = [
        'VENTANILLA' => 4,
        'default' => 2
    ];
    
    public function __construct($conexion) {
        $this->conn = $conexion;
    }
    
    /**
     * Determina la región según el día de la semana
     */
    public function determinarRegion($fecha) {
        $dia_semana = date('w', strtotime($fecha)); // 0=domingo, 1=lunes, ..., 6=sábado
        
        // Viernes (5) y Sábado (6) = REGIONAL
        // Domingo (0) a Jueves (4) = CENTRAL
        return ($dia_semana == 5 || $dia_semana == 6) ? 'REGIONAL' : 'CENTRAL';
    }
    
    /**
     * Verifica si una fecha ya tiene guardia asignada
     */
    public function fechaTieneGuardia($fecha) {
        $stmt = $this->conn->prepare("SELECT id FROM guardias_generadas WHERE fecha_guardia = ?");
        $stmt->execute([$fecha]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Verifica si un número de orden ya existe
     */
    public function ordenDiaExiste($orden_dia) {
        $stmt = $this->conn->prepare("SELECT id FROM orden_dia WHERE numero_orden = ?");
        $stmt->execute([$orden_dia]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Obtiene sugerencias de orden del día basado en los últimos 3
     */
    public function obtenerSugerenciasOrdenDia() {
        $stmt = $this->conn->prepare("
            SELECT numero_orden, año, numero 
            FROM orden_dia 
            ORDER BY año DESC, numero DESC 
            LIMIT 3
        ");
        $stmt->execute();
        
        $sugerencias = [];
        $ordenes = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ordenes[] = $row;
        }
        
        if (!empty($ordenes)) {
            $ultimo = $ordenes[0];
            $año_actual = $ultimo['año'];
            $siguiente_numero = $ultimo['numero'] + 1;
            
            // Generar 3 sugerencias consecutivas
            for ($i = 0; $i < 3; $i++) {
                $sugerencias[] = ($siguiente_numero + $i) . '/' . $año_actual;
            }
        } else {
            // Si no hay órdenes previas, empezar con 1/año_actual
            $año_actual = date('Y');
            for ($i = 1; $i <= 3; $i++) {
                $sugerencias[] = $i . '/' . $año_actual;
            }
        }
        
        return [
            'sugerencias' => $sugerencias,
            'historial' => array_reverse($ordenes)
        ];
    }
    
    /**
     * Obtiene policías disponibles según criterios
     */
    public function obtenerPolicias($fecha, $region) {
        $dia_semana = date('w', strtotime($fecha));
        $es_domingo = ($dia_semana == 0);
        
        $sql = "
            SELECT 
                p.id,
                p.legajo,
                p.nombre,
                p.apellido,
                p.cin,
                g.nombre as grado,
                g.nivel_jerarquia,
                e.nombre as especialidad,
                p.cargo,
                p.telefono,
                lg.nombre as lugar_guardia,
                lg.zona as region,
                p.comisionamiento,
                p.created_at as fecha_ingreso,
                DATEDIFF(CURDATE(), p.created_at) as antiguedad_dias,
                CASE 
                    WHEN a.id IS NOT NULL AND a.fecha_fin >= ? THEN 'CON_AUSENCIA'
                    WHEN p.fecha_disponible > ? THEN 'NO_DISPONIBLE_15_DIAS'
                    ELSE 'DISPONIBLE'
                END as disponibilidad,
                a.fecha_fin as fecha_fin_ausencia,
                p.fecha_disponible,
                hgp.fecha_guardia as ultima_guardia,
                COALESCE(DATEDIFF(?, hgp.fecha_guardia), 999) as dias_desde_ultima_guardia,
                COALESCE(lg_lista.posicion, 999) as posicion_fifo
            FROM policias p
            LEFT JOIN grados g ON p.grado_id = g.id
            LEFT JOIN especialidades e ON p.especialidad_id = e.id
            LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
            LEFT JOIN lista_guardias lg_lista ON p.id = lg_lista.policia_id
            LEFT JOIN ausencias a ON p.id = a.policia_id 
                AND a.fecha_inicio <= ? 
                AND a.fecha_fin >= ?
            LEFT JOIN (
                SELECT ggd.policia_id, MAX(gg.fecha_guardia) as fecha_guardia
                FROM guardias_generadas_detalle ggd
                JOIN guardias_generadas gg ON ggd.guardia_generada_id = gg.id
                GROUP BY ggd.policia_id
            ) hgp ON p.id = hgp.policia_id
            WHERE p.activo = 1
            AND p.estado = 'DISPONIBLE'
            AND lg.zona = ?
            ORDER BY 
                CASE WHEN a.id IS NOT NULL THEN 2
                     WHEN p.fecha_disponible > ? THEN 3
                     ELSE 1 END,
                COALESCE(lg_lista.posicion, 999) ASC,
                COALESCE(DATEDIFF(?, hgp.fecha_guardia), 999) DESC,
                g.nivel_jerarquia ASC,
                p.legajo ASC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$fecha, $fecha, $fecha, $fecha, $fecha, $region, $fecha, $fecha]);
        $policias = $stmt->fetchAll();
        
        return $policias;
    }
    
    /**
     * Verifica límites de comisionamiento
     */
    public function verificarLimiteComisionamiento($comisionamiento, $asignados) {
        if (empty($comisionamiento)) return true;
        
        $limite = isset($this->limites_comisionamiento[$comisionamiento]) 
            ? $this->limites_comisionamiento[$comisionamiento] 
            : $this->limites_comisionamiento['default'];
        
        $count = 0;
        foreach ($asignados as $asignado) {
            if ($asignado['comisionamiento'] == $comisionamiento) {
                $count++;
            }
        }
        
        return $count < $limite;
    }
    
    /**
     * Asigna personal a puestos específicos
     */
    public function asignarPersonal($policias, $fecha) {
        $dia_semana = date('w', strtotime($fecha));
        $es_domingo = ($dia_semana == 0);
        $es_lunes_sabado = ($dia_semana >= 1 && $dia_semana <= 6);
        
        $asignaciones = [];
        $policias_asignados = [];
        $policias_disponibles = array_filter($policias, function($p) {
            return $p['disponibilidad'] == 'DISPONIBLE';
        });
        $policias_con_ausencia = array_filter($policias, function($p) {
            return $p['disponibilidad'] == 'CON_AUSENCIA';
        });
        $policias_no_disponibles_15_dias = array_filter($policias, function($p) {
            return $p['disponibilidad'] == 'NO_DISPONIBLE_15_DIAS';
        });
        
        // Función para encontrar el mejor candidato
        $encontrarCandidato = function($puesto, $numero = null) use (&$policias_disponibles, &$policias_con_ausencia, &$policias_asignados, $es_domingo) {
            $candidatos = $policias_disponibles;
            
            // Para domingo, priorizar GRUPO DOMINGO en JEFE_SERVICIO y NUMERO_GUARDIA
            if ($es_domingo && ($puesto == 'JEFE_SERVICIO' || $puesto == 'NUMERO_GUARDIA')) {
                $grupo_domingo = array_filter($candidatos, function($p) {
                    return $p['comisionamiento'] == 'GRUPO DOMINGO';
                });
                if (!empty($grupo_domingo)) {
                    $candidatos = $grupo_domingo;
                }
            }
            
            // Filtrar por límites de comisionamiento
            $candidatos = array_filter($candidatos, function($p) use ($policias_asignados) {
                return $this->verificarLimiteComisionamiento($p['comisionamiento'], $policias_asignados);
            });
            
            if (empty($candidatos)) {
                // Si no hay disponibles, usar los que tienen ausencia
                $candidatos = array_filter($policias_con_ausencia, function($p) use ($policias_asignados) {
                    return $this->verificarLimiteComisionamiento($p['comisionamiento'], $policias_asignados);
                });
            }
            
            if (empty($candidatos)) return null;
            
            // Ordenar por sistema FIFO: posición en lista, luego jerarquía, luego días desde última guardia
            usort($candidatos, function($a, $b) {
                // Primero por posición FIFO (menor posición = más tiempo esperando)
                if ($a['posicion_fifo'] != $b['posicion_fifo']) {
                    return $a['posicion_fifo'] - $b['posicion_fifo'];
                }
                // Luego por jerarquía
                if ($a['nivel_jerarquia'] != $b['nivel_jerarquia']) {
                    return $a['nivel_jerarquia'] - $b['nivel_jerarquia'];
                }
                // Finalmente por días desde última guardia
                return $b['dias_desde_ultima_guardia'] - $a['dias_desde_ultima_guardia'];
            });
            
            return $candidatos[0];
        };
        
        // Asignar puestos en orden de prioridad
        $orden_asignacion = [
            'JEFE_SERVICIO',
            'JEFE_CUARTEL', 
            'OFICIAL_GUARDIA',
            'ATENCION_TELEFONICA_EXCLUSIVA',
            'CONDUCTOR_GUARDIA',
            'GUARDIA_06_30_22_00',
            'TENIDA_REGLAMENTO'
        ];
        
        // Asignar puestos únicos
        foreach ($orden_asignacion as $puesto) {
            $candidato = $encontrarCandidato($puesto);
            if ($candidato) {
                $asignaciones[] = [
                    'puesto' => $puesto,
                    'numero_puesto' => null,
                    'policia' => $candidato
                ];
                $policias_asignados[] = $candidato;
                
                // Remover de disponibles
                $policias_disponibles = array_filter($policias_disponibles, function($p) use ($candidato) {
                    return $p['id'] != $candidato['id'];
                });
                $policias_con_ausencia = array_filter($policias_con_ausencia, function($p) use ($candidato) {
                    return $p['id'] != $candidato['id'];
                });
            }
        }
        
        // Asignar NUMERO_GUARDIA (4 puestos)
        for ($i = 1; $i <= 4; $i++) {
            $candidato = $encontrarCandidato('NUMERO_GUARDIA', $i);
            if ($candidato) {
                $asignaciones[] = [
                    'puesto' => 'NUMERO_GUARDIA',
                    'numero_puesto' => $i,
                    'policia' => $candidato
                ];
                $policias_asignados[] = $candidato;
                
                // Remover de disponibles
                $policias_disponibles = array_filter($policias_disponibles, function($p) use ($candidato) {
                    return $p['id'] != $candidato['id'];
                });
                $policias_con_ausencia = array_filter($policias_con_ausencia, function($p) use ($candidato) {
                    return $p['id'] != $candidato['id'];
                });
            }
        }
        
        // Asignar SANIDAD_GUARDIA (3 puestos)
        for ($i = 1; $i <= 3; $i++) {
            $candidato = $encontrarCandidato('SANIDAD_GUARDIA', $i);
            if ($candidato) {
                $asignaciones[] = [
                    'puesto' => 'SANIDAD_GUARDIA',
                    'numero_puesto' => $i,
                    'policia' => $candidato
                ];
                $policias_asignados[] = $candidato;
                
                // Remover de disponibles
                $policias_disponibles = array_filter($policias_disponibles, function($p) use ($candidato) {
                    return $p['id'] != $candidato['id'];
                });
                $policias_con_ausencia = array_filter($policias_con_ausencia, function($p) use ($candidato) {
                    return $p['id'] != $candidato['id'];
                });
            }
        }
        
        return $asignaciones;
    }
    
    /**
     * Mapea un puesto a su lugar_guardia_id correspondiente
     */
    private function obtenerLugarGuardiaId($puesto) {
        $mapeo_puestos = [
            'JEFE_SERVICIO' => 1,                        // JEFE DE SERVICIO
            'JEFE_CUARTEL' => 2,                         // JEFE DE CUARTEL
            'OFICIAL_GUARDIA' => 3,                      // OFICIAL DE GUARDIA
            'ATENCION_TELEFONICA_EXCLUSIVA' => 4,        // ATENCIÓN TELEFÓNICA EXCLUSIVA
            'NUMERO_GUARDIA' => 5,                       // NUMERO DE GUARDIA
            'CONDUCTOR_GUARDIA' => 6,                    // CONDUCTOR DE GUARDIA
            'GUARDIA_06_30_22_00' => 7,                  // DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE
            'TENIDA_REGLAMENTO' => 8,                    // TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA
            'SANIDAD_GUARDIA' => 9                       // SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE
        ];
        
        return $mapeo_puestos[$puesto] ?? 1; // Default a JEFE DE SERVICIO si no se encuentra
    }
    
    /**
     * Actualiza las posiciones FIFO de los policías asignados y los marca como no disponibles por 15 días
     */
    private function actualizarPosicionesFIFO($asignaciones, $fecha) {
        try {
            // Para cada policía asignado, moverlo al final de la lista general
            foreach ($asignaciones as $asignacion) {
                $policia_id = $asignacion['policia']['id'];
                
                // Obtener la posición máxima actual en la lista de guardias
                $stmt = $this->conn->prepare("
                    SELECT COALESCE(MAX(posicion), 0) as max_posicion
                    FROM lista_guardias
                ");
                $stmt->execute();
                $max_posicion = $stmt->fetch()['max_posicion'];
                
                // Actualizar la posición del policía al final de la lista
                $nueva_posicion = $max_posicion + 1;
                
                $stmt = $this->conn->prepare("
                    UPDATE lista_guardias 
                    SET posicion = ?, ultima_guardia_fecha = ?
                    WHERE policia_id = ?
                ");
                $stmt->execute([$nueva_posicion, $fecha, $policia_id]);
                
                // Si no existe en lista_guardias, insertarlo
                if ($stmt->rowCount() == 0) {
                    $stmt = $this->conn->prepare("
                        INSERT INTO lista_guardias (policia_id, posicion, ultima_guardia_fecha)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$policia_id, $nueva_posicion, $fecha]);
                }
                
                // Marcar al policía como no disponible por 15 días
                $fecha_disponible = date('Y-m-d', strtotime($fecha . ' + 15 days'));
                $stmt = $this->conn->prepare("
                    UPDATE policias 
                    SET fecha_disponible = ?, 
                        ultima_guardia_fecha = ?
                    WHERE id = ?
                ");
                $stmt->execute([$fecha_disponible, $fecha, $policia_id]);
            }
        } catch (Exception $e) {
            // Log del error pero no interrumpir el proceso principal
            error_log("Error actualizando posiciones FIFO: " . $e->getMessage());
        }
    }
    
    /**
     * Genera la guardia completa
     */
    public function generarGuardia($fecha, $orden_dia) {
        try {
            $this->conn->beginTransaction();
            
            // Verificar que la fecha no tenga guardia
            if ($this->fechaTieneGuardia($fecha)) {
                throw new Exception("Esta fecha ya tiene una guardia asignada");
            }
            
            // Verificar que el orden del día no exista
            if ($this->ordenDiaExiste($orden_dia)) {
                throw new Exception("Este número de orden del día ya existe");
            }
            
            $region = $this->determinarRegion($fecha);
            $policias = $this->obtenerPolicias($fecha, $region);
            $asignaciones = $this->asignarPersonal($policias, $fecha);
            
            // Insertar guardia generada
            $partes = explode('/', $orden_dia);
            $numero = intval($partes[0]);
            $año = intval($partes[1]);
            
            $stmt = $this->conn->prepare("
                INSERT INTO guardias_generadas (fecha_guardia, orden_dia, region) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$fecha, $orden_dia, $region]);
            $guardia_id = $this->conn->lastInsertId();
            
            // Insertar orden del día
            $partes = explode('/', $orden_dia);
            $numero = intval($partes[0]);
            $año = intval($partes[1]);
            
            $stmt = $this->conn->prepare("
                INSERT INTO orden_dia (numero_orden, año, numero) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$orden_dia, $año, $numero]);
            
            // Insertar asignaciones con información completa del puesto
            foreach ($asignaciones as $index => $asignacion) {
                $puesto_completo = $asignacion['puesto'];
                if ($asignacion['numero_puesto']) {
                    $puesto_completo .= '_' . $asignacion['numero_puesto'];
                }
                
                $lugar_guardia_id = $this->obtenerLugarGuardiaId($asignacion['puesto']);
                
                $stmt = $this->conn->prepare("
                    INSERT INTO guardias_generadas_detalle (
                        guardia_generada_id, 
                        policia_id, 
                        lugar_guardia_id, 
                        posicion_asignacion,
                        posicion_lista_original,
                        observaciones_asignacion
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $guardia_id, 
                    $asignacion['policia']['id'], 
                    $lugar_guardia_id,
                    $index + 1,
                    $index + 1, // posicion_lista_original igual a posicion_asignacion por ahora
                    $puesto_completo
                ]);
            }
            
            // Implementar algoritmo FIFO: mover policías asignados al final de la lista
            $this->actualizarPosicionesFIFO($asignaciones, $fecha);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'guardia_id' => $guardia_id,
                'asignaciones' => $asignaciones,
                'region' => $region
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Genera PDF de la guardia
     */
    public function generarPDF($guardia_id) {
        // Obtener datos de la guardia
        $stmt = $this->conn->prepare("
            SELECT gg.*, 
                   DATE_FORMAT(gg.fecha_guardia, '%d/%m/%Y') as fecha_formateada,
                   CASE gg.region 
                       WHEN 'CENTRAL' THEN 'Central'
                       WHEN 'REGIONAL' THEN 'Regional'
                   END as region_nombre
            FROM guardias_generadas gg 
            WHERE gg.id = ?
        ");
        $stmt->execute([$guardia_id]);
        $guardia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$guardia) {
            throw new Exception("Guardia no encontrada");
        }
        
        // Obtener asignaciones
        $stmt = $this->conn->prepare("
            SELECT ggd.posicion_asignacion,
                   p.nombre, p.apellido, p.cin, p.legajo, p.telefono,
                   g.abreviatura as grado,
                   lg.nombre as lugar_guardia
            FROM guardias_generadas_detalle ggd
            JOIN policias p ON ggd.policia_id = p.id
            LEFT JOIN grados g ON p.grado_id = g.id
            LEFT JOIN lugares_guardias lg ON ggd.lugar_guardia_id = lg.id
            WHERE ggd.guardia_generada_id = ?
            ORDER BY lg.nombre, ggd.posicion_asignacion
        ");
        $stmt->execute([$guardia_id]);
        $asignaciones = $stmt->fetchAll();
        
        // Crear PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        
        // Título
        $pdf->Cell(0, 10, 'ORDEN DEL DIA N° ' . $guardia['orden_dia'], 0, 1, 'C');
        $pdf->Cell(0, 10, 'GUARDIA ' . strtoupper($guardia['region_nombre']), 0, 1, 'C');
        $pdf->Cell(0, 10, 'Fecha: ' . $guardia['fecha_formateada'], 0, 1, 'C');
        $pdf->Ln(10);
        
        // Contenido
        $pdf->SetFont('Arial', '', 12);
        
        $lugar_actual = '';
        foreach ($asignaciones as $index => $asignacion) {
            // Mostrar lugar de guardia si cambió
            if ($asignacion['lugar_guardia'] != $lugar_actual) {
                $lugar_actual = $asignacion['lugar_guardia'];
                $pdf->SetFont('Arial', 'B', 14);
                $pdf->Cell(0, 10, strtoupper($lugar_actual), 0, 1);
                $pdf->SetFont('Arial', '', 12);
                $pdf->Ln(2);
            }
            
            $puesto_nombre = 'Posición ' . $asignacion['posicion_asignacion'];
            $nombre_completo = $asignacion['grado'] . ' ' . $asignacion['nombre'] . ' ' . $asignacion['apellido'];
            
            $pdf->Cell(0, 8, $puesto_nombre . ': ' . $nombre_completo, 0, 1);
            $pdf->Cell(0, 6, '    C.I.: ' . $asignacion['cin'] . ' - Legajo: ' . $asignacion['legajo'], 0, 1);
            $pdf->Cell(0, 6, '    Teléfono: ' . ($asignacion['telefono'] ?: 'No registrado'), 0, 1);
            $pdf->Ln(3);
        }
        
        return $pdf;
    }
    
    /**
     * Obtiene el nombre legible del puesto
     */
    private function obtenerNombrePuesto($puesto, $numero = null) {
        $nombres = [
            'JEFE_SERVICIO' => 'JEFE DE SERVICIO',
            'JEFE_CUARTEL' => 'JEFE DE CUARTEL',
            'OFICIAL_GUARDIA' => 'OFICIAL DE GUARDIA',
            'ATENCION_TELEFONICA_EXCLUSIVA' => 'ATENCIÓN TELEFÓNICA EXCLUSIVA',
            'NUMERO_GUARDIA' => 'NÚMERO DE GUARDIA',
            'CONDUCTOR_GUARDIA' => 'CONDUCTOR DE GUARDIA',
            'GUARDIA_06_30_22_00' => 'DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE',
            'TENIDA_REGLAMENTO' => 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA',
            'SANIDAD_GUARDIA' => 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE'
        ];
        
        $nombre = $nombres[$puesto] ?? $puesto;
        
        if ($numero && ($puesto == 'NUMERO_GUARDIA' || $puesto == 'SANIDAD_GUARDIA')) {
            $nombre .= ' ' . $numero;
        }
        
        return $nombre;
    }
}
?>