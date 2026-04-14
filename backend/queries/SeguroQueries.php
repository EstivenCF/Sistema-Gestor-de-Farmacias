<?php
/**
 * SeguroQueries.php - Consultas relacionadas con seguros médicos
 */

class SeguroQueries {
    private $conexion;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
    }
    
    /**
     * Obtener todas las aseguradoras
     */
    public function obtenerAseguradoras() {
        try {
            $stmt = $this->conexion->query("
                SELECT * FROM aseguradoras 
                WHERE activo = TRUE 
                ORDER BY nombre
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener aseguradora por ID
     */
    public function obtenerAseguradoraPorId($id_aseguradora) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT * FROM aseguradoras WHERE id_aseguradora = ?
            ");
            $stmt->execute([$id_aseguradora]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Obtener pólizas de un cliente
     */
    public function obtenerPolizasCliente($id_cliente) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT p.*, a.nombre as aseguradora_nombre, a.codigo as aseguradora_codigo
                FROM polizas p
                JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
                WHERE p.id_cliente = ? AND p.activo = TRUE
                  AND CURRENT_DATE BETWEEN p.fecha_inicio AND p.fecha_fin
                ORDER BY p.fecha_inicio DESC
            ");
            $stmt->execute([$id_cliente]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener póliza activa de un cliente
     */
    public function obtenerPolizaActiva($id_cliente) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT p.*, a.nombre as aseguradora_nombre, a.porcentaje_cobertura_default
                FROM polizas p
                JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
                WHERE p.id_cliente = ? AND p.activo = TRUE
                  AND CURRENT_DATE BETWEEN p.fecha_inicio AND p.fecha_fin
                LIMIT 1
            ");
            $stmt->execute([$id_cliente]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Calcular cobertura de seguro para un medicamento
     */
    public function calcularCobertura($id_cliente, $id_medicamento, $precio, $cantidad) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT * FROM calcular_cobertura_seguro(?, ?, ?, ?)
            ");
            $stmt->execute([$id_cliente, $id_medicamento, $precio, $cantidad]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return [
                'cubre_seguro' => 0,
                'paga_paciente' => $precio * $cantidad,
                'porcentaje_cobertura' => 0,
                'requiere_autorizacion' => false,
                'id_autorizacion_necesaria' => null
            ];
        }
    }
    
    /**
     * Obtener autorizaciones de seguro de un cliente
     */
    public function obtenerAutorizacionesCliente($id_cliente) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT a.*, m.nombre as medicamento_nombre, asg.nombre as aseguradora_nombre
                FROM autorizaciones_seguro a
                LEFT JOIN medicamentos m ON a.id_medicamento = m.id_medicamento
                JOIN aseguradoras asg ON a.id_aseguradora = asg.id_aseguradora
                WHERE a.id_cliente = ? AND a.estado = 'APROBADO'
                  AND (a.fecha_expiracion IS NULL OR a.fecha_expiracion >= CURRENT_DATE)
                ORDER BY a.fecha_expiracion ASC
            ");
            $stmt->execute([$id_cliente]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener cobertura específica para un medicamento en una póliza
     */
    public function obtenerCoberturaEspecifica($id_poliza, $id_medicamento) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT * FROM cobertura_medicamentos
                WHERE id_poliza = ? AND id_medicamento = ? AND activo = TRUE
            ");
            $stmt->execute([$id_poliza, $id_medicamento]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Crear nueva aseguradora
     */
    public function crearAseguradora($data, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO aseguradoras (
                    codigo, nombre, rnc, telefono, email, direccion,
                    contacto_nombre, contacto_telefono, porcentaje_cobertura_default,
                    cobertura_global, requiere_autorizacion, activo, observaciones,
                    creado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id_aseguradora
            ");
            $stmt->execute([
                $data['codigo'],
                $data['nombre'],
                $data['rnc'] ?? null,
                $data['telefono'] ?? null,
                $data['email'] ?? null,
                $data['direccion'] ?? null,
                $data['contacto_nombre'] ?? null,
                $data['contacto_telefono'] ?? null,
                $data['porcentaje_cobertura_default'] ?? 80,
                $data['cobertura_global'] ?? true,
                $data['requiere_autorizacion'] ?? false,
                $data['activo'] ?? true,
                $data['observaciones'] ?? null,
                $id_usuario
            ]);
            $result = $stmt->fetch();
            return $result['id_aseguradora'];
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Crear póliza para cliente
     */
    public function crearPoliza($data, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO polizas (
                    id_cliente, id_aseguradora, numero_poliza, numero_carnet,
                    fecha_inicio, fecha_fin, cobertura_porcentaje, copago_fijo,
                    deducible_anual, tope_anual, beneficiario_titular,
                    nombre_beneficiario, parentesco, activo, observaciones,
                    creado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id_poliza
            ");
            $stmt->execute([
                $data['id_cliente'],
                $data['id_aseguradora'],
                $data['numero_poliza'],
                $data['numero_carnet'] ?? null,
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['cobertura_porcentaje'] ?? null,
                $data['copago_fijo'] ?? 0,
                $data['deducible_anual'] ?? 0,
                $data['tope_anual'] ?? null,
                $data['beneficiario_titular'] ?? true,
                $data['nombre_beneficiario'] ?? null,
                $data['parentesco'] ?? null,
                $data['activo'] ?? true,
                $data['observaciones'] ?? null,
                $id_usuario
            ]);
            $result = $stmt->fetch();
            
            // Actualizar cliente para indicar que tiene seguro
            $stmt2 = $this->conexion->prepare("
                UPDATE clientes SET tiene_seguro = TRUE WHERE id_cliente = ?
            ");
            $stmt2->execute([$data['id_cliente']]);
            
            return $result['id_poliza'];
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Crear autorización de seguro
     */
    public function crearAutorizacion($data, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO autorizaciones_seguro (
                    numero_autorizacion, id_cliente, id_aseguradora, id_medicamento,
                    id_poliza, fecha_expiracion, cantidad_autorizada, diagnostico,
                    medico_que_autoriza, numero_autorizacion_medico, estado,
                    observaciones, solicitado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id_autorizacion
            ");
            $stmt->execute([
                $data['numero_autorizacion'],
                $data['id_cliente'],
                $data['id_aseguradora'],
                $data['id_medicamento'] ?? null,
                $data['id_poliza'] ?? null,
                $data['fecha_expiracion'],
                $data['cantidad_autorizada'],
                $data['diagnostico'] ?? null,
                $data['medico_que_autoriza'] ?? null,
                $data['numero_autorizacion_medico'] ?? null,
                'PENDIENTE',
                $data['observaciones'] ?? null,
                $id_usuario
            ]);
            $result = $stmt->fetch();
            return $result['id_autorizacion'];
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Aprobar autorización de seguro
     */
    public function aprobarAutorizacion($id_autorizacion, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                UPDATE autorizaciones_seguro SET
                    estado = 'APROBADO',
                    fecha_autorizacion = CURRENT_TIMESTAMP,
                    autorizado_por = ?
                WHERE id_autorizacion = ?
            ");
            return $stmt->execute([$id_usuario, $id_autorizacion]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Rechazar autorización de seguro
     */
    public function rechazarAutorizacion($id_autorizacion, $motivo, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                UPDATE autorizaciones_seguro SET
                    estado = 'RECHAZADO',
                    motivo_rechazo = ?,
                    fecha_autorizacion = CURRENT_TIMESTAMP,
                    autorizado_por = ?
                WHERE id_autorizacion = ?
            ");
            return $stmt->execute([$motivo, $id_usuario, $id_autorizacion]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Verificar si una sucursal acepta una aseguradora
     */
    public function sucursalAceptaAseguradora($id_sucursal, $id_aseguradora) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT 1 FROM sucursal_aseguradora
                WHERE id_sucursal = ? AND id_aseguradora = ? AND activo = TRUE
            ");
            $stmt->execute([$id_sucursal, $id_aseguradora]);
            return $stmt->fetch() !== false;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obtener aseguradoras aceptadas por una sucursal
     */
    public function obtenerAseguradorasPorSucursal($id_sucursal) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT a.* FROM aseguradoras a
                JOIN sucursal_aseguradora sa ON a.id_aseguradora = sa.id_aseguradora
                WHERE sa.id_sucursal = ? AND sa.activo = TRUE AND a.activo = TRUE
                ORDER BY a.nombre
            ");
            $stmt->execute([$id_sucursal]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
}
?>