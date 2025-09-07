Quiero que me crees el modulo de generacion de guardia
este modulo contara con lo siguiente:
es un proceso fifo el primero que este en la lista pasa al ultimo de la lista hasta que le vuelva a tocar su respectiva guardia
los primeros seran los que tengan el grado mas alto tambien el legajo juega el papel de si hay dos con el mismo rango alto con el legajo se vera quien estara arriba de quien en su lugar de guardia y s
- De Domingo a Jueves seran los de region central
- Viernes y Sabado seran los de region regional
La logica de las guardias seran las siguientes:
-  Los Domingos los que tienen el lugar de guardia numero de guardia y Jefe de Servicio, y la observacion en su : tabla policias, observaciones: GRUPO DOMINGO
solo hacen guardia los domingos, no tienen permitido hacer otro día que no sea domingo esa guardia
luego lo demás es norma
 Atención telefónica solo son de lunes a sábado
 al momento de generar la guardia se debe cumplir obligatoriamente que solamente los personales que tengan el mismo  comisionamientos (dos) puedan hacer guardia, a excepcion de los que tengan el comisionamiento de VENTANILLA, que pueden ser hasta cuatro personales por guardia si es que por ahi llegase a coincidir los cuatro o los dos de las otras comisionamientos 
los que tienen ausencias al volver de su ausencia ya sea por vacaciones o x razon
tienen prioridad para hacer guardia y entrara en guardia entre la posicion primera a quinta y los que estan abajo o arriba de el bajaran un escalon
al generar la guardia
todos esos datos se colocaran 
aqui un ejemplo modificalo como tenga que ser

// 1. Conexión a la base de datos
$mysqli = new mysqli('localhost', 'usuario', 'password', 'sistema_rh_policia');

// 2. Crear el generador
$generador = new OrdenDelDiaGenerator($mysqli);

// 3. Generar orden del día
$fecha_servicio = '2025-01-02'; // o date('Y-m-d', strtotime('+1 day'))
$generador->generarOrdenDelDia($fecha_servicio);

// 4. Descargar PDF
$generador->Output('D', 'Orden_del_Dia_' . date('Y-m-d') . '.pdf');

<?php
require_once('fpdf/fpdf.php');

class OrdenDelDiaGenerator extends FPDF
{
    private $db;
    
    public function __construct($db_connection)
    {
        parent::__construct();
        $this->db = $db_connection;
    }
    
    // Encabezado del documento
    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        
        // Fecha en la esquina superior derecha
        $fecha = "Asunción, " . date('d') . " de " . $this->getMesEspanol(date('n')) . " de " . date('Y') . ".-";
        $this->Cell(0, 10, utf8_decode($fecha), 0, 1, 'R');
        
        $this->Ln(5);
        
        // SEDE CENTRAL (subrayado)
        $this->SetFont('Arial', 'BU', 14);
        $this->Cell(0, 10, '[SEDE CENTRAL]', 0, 1, 'C');
        
        $this->Ln(3);
        
        // ORDEN DEL DÍA (subrayado)
        $orden_num = "ORDEN DEL DÍA Nº " . $this->getNumeroOrden() . "/" . date('Y');
        $this->Cell(0, 10, utf8_decode("[$orden_num]"), 0, 1, 'C');
        
        $this->Ln(5);
        
        // Descripción del propósito
        $this->SetFont('Arial', '', 11);
        $texto_proposito = "POR LA QUE SE DESIGNA PERSONAL DE GUARDIA Y PERSONAL PARA CUMPLIR\n";
        $texto_proposito .= "FUNCIONES ADMINISTRATIVAS (TELEFONISTA) PARA EL ";
        
        // Obtener fecha del servicio
        $fecha_servicio = $this->getFechaServicio();
        $texto_proposito .= strtoupper($fecha_servicio['dia_texto']) . " " . str_pad($fecha_servicio['dia'], 2, '0', STR_PAD_LEFT);
        $texto_proposito .= " DE " . strtoupper($fecha_servicio['mes_texto']) . " DE " . $fecha_servicio['año'];
        $texto_proposito .= " DESDE LAS 07:00 HS. HASTA EL " . strtoupper($fecha_servicio['dia_siguiente_texto']);
        $texto_proposito .= " " . str_pad($fecha_servicio['dia_siguiente'], 2, '0', STR_PAD_LEFT) . " DE ";
        $texto_proposito .= strtoupper($fecha_servicio['mes_siguiente_texto']) . " DE " . $fecha_servicio['año_siguiente'];
        $texto_proposito .= "; 07:00 HS. A LOS SIGUIENTES:";
        
        $this->MultiCell(0, 6, utf8_decode($texto_proposito), 0, 'J');
        $this->Ln(5);
    }
    
    // Pie de página
    function Footer()
    {
        $this->SetY(-40);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 10, 'CUMPLIDO ARCHIVAR.', 0, 1, 'C');
        
        $this->Ln(5);
        
        // Firma
        $this->Cell(0, 5, 'SILVIA ACOSTA DE GIMENEZ', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Comisario MGAP.', 0, 1, 'C');
        $this->Cell(0, 5, utf8_decode('Jefa División RR.HH. - Dpto. de Identificaciones'), 0, 1, 'C');
        
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, utf8_decode('V.Bº'), 0, 1, 'C');
    }
    
    // Generar el cuerpo del documento con los datos de la guardia
    public function generarOrdenDelDia($fecha_servicio)
    {
        $this->AddPage();
        
        // Obtener datos de la guardia para la fecha específica
        $guardia_data = $this->obtenerDatosGuardia($fecha_servicio);
        
        // JEFE DE SERVICIO
        $this->agregarPersonal("JEFE DE SERVICIO:", $guardia_data['jefe_servicio']);
        
        // JEFE DE CUARTEL  
        $this->agregarPersonal("JEFE DE CUARTEL:", $guardia_data['jefe_cuartel']);
        
        // OFICIAL DE GUARDIA
        $this->agregarPersonal("OFICIAL DE GUARDIA:", $guardia_data['oficial_guardia']);
        
        $this->Ln(3);
        
        // ATENCIÓN TELEFÓNICA EXCLUSIVA
        $this->SetFont('Arial', 'BU', 11);
        $this->Cell(0, 8, utf8_decode('ATENCIÓN TELEFÓNICA EXCLUSIVA (DE 06:00 HORAS A 18:00 HORAS)'), 0, 1, 'L');
        $this->Ln(2);
        
        // TELEFONISTA
        if (!empty($guardia_data['telefonista'])) {
            foreach ($guardia_data['telefonista'] as $telefonista) {
                $this->agregarPersonalDetalle($telefonista);
            }
        }
        
        $this->Ln(3);
        
        // NUMERO DE GUARDIA
        $this->SetFont('Arial', 'BU', 11);
        $this->Cell(0, 8, 'NUMERO DE GUARDIA', 0, 1, 'L');
        $this->Ln(2);
        
        if (!empty($guardia_data['numero_guardia'])) {
            foreach ($guardia_data['numero_guardia'] as $personal) {
                $this->agregarPersonalDetalle($personal);
            }
        }
        
        // CONDUCTOR DE GUARDIA
        $this->Ln(3);
        $this->SetFont('Arial', 'BU', 11);
        $this->Cell(0, 8, 'CONDUCTOR DE GUARDIA', 0, 1, 'L');
        $this->Ln(2);
        
        $this->agregarPersonalDetalle($guardia_data['conductor_guardia']);
        
        // DATA CENTER
        $this->Ln(3);
        $this->SetFont('Arial', 'BU', 11);
        $this->Cell(0, 8, utf8_decode('DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE:'), 0, 1, 'L');
        $this->Ln(2);
        
        $this->agregarPersonalDetalle($guardia_data['data_center']);
        
        // TENIDA
        $this->Ln(3);
        $this->SetFont('Arial', 'BU', 11);
        $this->Cell(0, 8, 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA:', 0, 1, 'L');
        $this->Ln(2);
        
        if (!empty($guardia_data['tenida'])) {
            foreach ($guardia_data['tenida'] as $personal) {
                $this->agregarPersonalDetalle($personal);
            }
        }
        
        // SANIDAD DE GUARDIA
        $this->Ln(3);
        $this->SetFont('Arial', 'BU', 11);
        $this->Cell(0, 8, 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE:', 0, 1, 'L');
        $this->Ln(2);
        
        if (!empty($guardia_data['sanidad'])) {
            foreach ($guardia_data['sanidad'] as $personal) {
                $this->agregarPersonalDetalle($personal);
            }
        }
        
        // Instrucciones finales
        $this->agregarInstrucciones();
    }
    
    // Agregar personal principal (Jefe de Servicio, Jefe de Cuartel, etc.)
    private function agregarPersonal($cargo, $personal_data)
    {
        if (empty($personal_data)) return;
        
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(40, 8, utf8_decode($cargo), 0, 0, 'L');
        
        $this->SetFont('Arial', '', 10);
        $grado_nombre = strtoupper($personal_data['grado_abrev']) . " " . 
                       (isset($personal_data['especialidad']) ? $personal_data['especialidad'] . ". " : "") .
                       strtoupper($personal_data['nombre_completo']);
        
        $comisionamiento = !empty($personal_data['comisionamiento']) ? " (" . strtoupper($personal_data['comisionamiento']) . ")" : "";
        $telefono = !empty($personal_data['telefono']) ? " (" . $personal_data['telefono'] . ")" : "";
        
        $linea_texto = $grado_nombre . $comisionamiento . $telefono;
        
        // Agregar puntos suspensivos
        $linea_texto .= str_repeat('.', max(1, 50 - strlen($linea_texto)));
        
        $this->Cell(0, 8, utf8_decode($linea_texto), 0, 1, 'L');
        $this->Ln(2);
    }
    
    // Agregar personal detallado
    private function agregarPersonalDetalle($personal_data)
    {
        if (empty($personal_data)) return;
        
        $this->SetFont('Arial', '', 10);
        
        $grado_nombre = strtoupper($personal_data['grado_abrev']) . " " . 
                       (isset($personal_data['especialidad']) ? $personal_data['especialidad'] . ". " : "") .
                       strtoupper($personal_data['nombre_completo']);
        
        $comisionamiento = !empty($personal_data['comisionamiento']) ? " (" . strtoupper($personal_data['comisionamiento']) . ")" : "";
        $telefono = !empty($personal_data['telefono']) ? " (" . $personal_data['telefono'] . ")" : "";
        
        // Agregar horarios si existen
        $horario = isset($personal_data['horario']) ? " " . $personal_data['horario'] : "";
        
        $linea_texto = $grado_nombre . $comisionamiento . $telefono . $horario;
        
        // Agregar puntos suspensivos
        $linea_texto .= str_repeat('.', max(1, 60 - strlen($linea_texto)));
        
        $this->Cell(0, 6, utf8_decode($linea_texto), 0, 1, 'L');
        $this->Ln(1);
    }
    
    // Agregar instrucciones finales
    private function agregarInstrucciones()
    {
        $this->Ln(5);
        
        $instrucciones = [
            "FORMACIÓN GUARDIA ENTRANTE 06:30 HS.-",
            "JEFE DE SERVICIO: UNIFORME DE SERVICIO \"B\", BIRRETE Y ARMA REGLAMENTARIA.-",
            "JEFE DE CUARTEL Y DEMAS COMPONENTES DE LA GUARDIA: CON UNIFORME DE SERVICIO \"C\" Y TODOS LOS ACCESORIOS (ARMA REGLAMENTARIA, PORTANOMBRE, PLACA, BOLÍGRAFO Y AGENDA.-",
            "EL JEFE DE SERVICIO Y JEFE DE CUARTEL, SON RESPONSABLES DIRECTO ANTE EL JEFE DEL DEPARTAMENTO, DEL CONTROL, DISTRIBUCIÓN Y VERIFICACIÓN EN FORMA PERMANENTE DE LA GUARDIA.-",
            "EL JEFE DE SERVICIO DESIGNARÁ UN PERSONAL DE LA GUARDIA, QUIEN SERÁ EL ENCARGADO DEL CONTROL DE LA LLAVE DE LA DIVISIÓN DE ARCHIVOS DE LA SEDE CENTRAL DEL DEPARTAMENTO Y ASENTARA EN EL LIBRO DE LA GUARDIA.-",
            "PROHIBIR EL INGRESO A PERSONAS AJENAS AL DEPARTAMENTO DE IDENTIFICACIONES FUERA DEL HORARIO DE ATENCIÓN AL PÚBLICO, SALVO CASO DEBIDAMENTE JUSTIFICADA Y AUTORIZADA POR EL JEFE DE SERVICIO, LA CUAL DEBERÁ SER ASENTADA EN EL LIBRO DE NOVEDADES DE LA OFICINA DE GUARDIA.-"
        ];
        
        $this->SetFont('Arial', 'B', 9);
        foreach ($instrucciones as $instruccion) {
            $this->Cell(5, 5, utf8_decode('• '), 0, 0, 'L');
            $this->MultiCell(0, 5, utf8_decode($instruccion), 0, 'L');
            $this->Ln(2);
        }
    }
    
    // Obtener datos de guardia desde la base de datos
    private function obtenerDatosGuardia($fecha_servicio)
    {
        $datos = [];
        
        // Query principal para obtener asignaciones del servicio
        $sql = "SELECT 
                    p.nombre, p.apellido, p.telefono, p.comisionamiento,
                    tg.abreviatura as grado_abrev, tg.nombre as grado_nombre,
                    e.nombre as especialidad,
                    asig.puesto, asig.lugar, asig.hora_inicio, asig.hora_fin
                FROM asignaciones_servicios asig
                INNER JOIN servicios s ON asig.servicio_id = s.id
                INNER JOIN policias p ON asig.policia_id = p.id
                INNER JOIN tipo_grados tg ON p.grado_id = tg.id
                LEFT JOIN especialidades e ON p.especialidad_id = e.id
                WHERE s.fecha_servicio = ?
                ORDER BY tg.nivel_jerarquia ASC, p.apellido ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $fecha_servicio);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $personal = [
                'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
                'grado_abrev' => $row['grado_abrev'],
                'especialidad' => $row['especialidad'],
                'telefono' => $row['telefono'],
                'comisionamiento' => $row['comisionamiento'],
                'horario' => $this->formatearHorario($row['hora_inicio'], $row['hora_fin'])
            ];
            
            // Clasificar según el puesto
            switch (strtoupper($row['puesto'])) {
                case 'JEFE DE SERVICIO':
                    $datos['jefe_servicio'] = $personal;
                    break;
                case 'JEFE DE CUARTEL':
                    $datos['jefe_cuartel'] = $personal;
                    break;
                case 'OFICIAL DE GUARDIA':
                    $datos['oficial_guardia'] = $personal;
                    break;
                case 'TELEFONISTA':
                    $datos['telefonista'][] = $personal;
                    break;
                case 'NUMERO DE GUARDIA':
                    $datos['numero_guardia'][] = $personal;
                    break;
                case 'CONDUCTOR DE GUARDIA':
                    $datos['conductor_guardia'] = $personal;
                    break;
                case 'DATA CENTER':
                    $datos['data_center'] = $personal;
                    break;
                case 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA':
                    $datos['tenida'][] = $personal;
                    break;
                case 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE':
                    $datos['sanidad'][] = $personal;
                    break;
            }
        }
        
        return $datos;
    }
    
    // Funciones auxiliares
    private function getMesEspanol($numero_mes)
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        return $meses[$numero_mes];
    }
    
    private function getDiaEspanol($numero_dia)
    {
        $dias = [
            0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
        ];
        return $dias[$numero_dia];
    }
    
    private function getNumeroOrden()
    {
        // Obtener el siguiente número de orden del día
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(nombre, ' ', -1) AS UNSIGNED)) as ultimo_numero 
                FROM servicios 
                WHERE nombre LIKE 'ORDEN DEL DÍA%' 
                AND YEAR(fecha_servicio) = YEAR(CURDATE())";
        
        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        
        return ($row['ultimo_numero'] ?? 0) + 1;
    }
    
    private function getFechaServicio()
    {
        // Por defecto, mañana
        $fecha = date('Y-m-d', strtotime('+1 day'));
        $timestamp = strtotime($fecha);
        
        $fecha_siguiente = date('Y-m-d', strtotime('+1 day', $timestamp));
        $timestamp_siguiente = strtotime($fecha_siguiente);
        
        return [
            'dia' => date('j', $timestamp),
            'mes_texto' => $this->getMesEspanol(date('n', $timestamp)),
            'año' => date('Y', $timestamp),
            'dia_texto' => $this->getDiaEspanol(date('w', $timestamp)),
            'dia_siguiente' => date('j', $timestamp_siguiente),
            'mes_siguiente_texto' => $this->getMesEspanol(date('n', $timestamp_siguiente)),
            'año_siguiente' => date('Y', $timestamp_siguiente),
            'dia_siguiente_texto' => $this->getDiaEspanol(date('w', $timestamp_siguiente))
        ];
    }
    
    private function formatearHorario($hora_inicio, $hora_fin)
    {
        if (empty($hora_inicio) && empty($hora_fin)) return '';
        
        $horario = '';
        if (!empty($hora_inicio)) {
            $horario .= date('H:i', strtotime($hora_inicio)) . ' A ';
        }
        if (!empty($hora_fin)) {
            $horario .= date('H:i', strtotime($hora_fin)) . ' Hs';
        }
        
        return $horario;
    }
}
quiero que antes de darle al boton de generar guardia la primera vez pregunte que orden del dia seria luego ya serian automaticos y seran acorde a lo que se coloco en la primera vez ejemplo orden : 34/2025
automaticamente ya tendria que poner 35/2025 y asi sucesivamente
en la parte de data center depende si es suboficial el horario es hasta las 22:00 hs y si es funcionario es hasta las 18:00hs esas cosas el label debe cambiarse dependiendo del personal
