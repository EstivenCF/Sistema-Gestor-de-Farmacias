<?php
/**
 * ImpuestoQueries.php - Consultas relacionadas con impuestos
 */

class ImpuestoQueries {
    private $conexion;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
    }
    
    /**
     * Obtener todos los impuestos
     */
    public function obtenerTodos() {
        try {
            $stmt = $this->conexion->query("
                SELECT * FROM impuestos 
                ORDER BY activo DESC, porcentaje DESC
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener impuestos activos
     */
    public function obtenerActivos() {
        try {
            $stmt = $this->conexion->query("
                SELECT * FROM vista_impuestos_activos 
                ORDER BY porcentaje DESC
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener impuesto por ID
     */
    public function obtenerPorId($id_impuesto) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT * FROM impuestos WHERE id_impuesto = ?
            ");
            $stmt->execute([$id_impuesto]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Obtener impuesto aplicable a un medicamento
     */
    public function obtenerImpuestoMedicamento($id_medicamento, $requiere_receta = false) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT i.* FROM impuestos i
                JOIN medicamentos m ON m.id_impuesto = i.id_impuesto
                WHERE m.id_medicamento = ?
                  AND i.activo = TRUE
                  AND CURRENT_DATE BETWEEN i.fecha_inicio AND COALESCE(i.fecha_fin, CURRENT_DATE + INTERVAL '100 years')
                  AND (i.aplica_receta = FALSE OR (i.aplica_receta = TRUE AND ? = TRUE))
                LIMIT 1
            ");
            $stmt->execute([$id_medicamento, $requiere_receta]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Obtener impuesto aplicable a un producto
     */
    public function obtenerImpuestoProducto($id_producto) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT i.* FROM impuestos i
                JOIN productos p ON p.id_impuesto = i.id_impuesto
                WHERE p.id_producto = ?
                  AND i.activo = TRUE
                  AND CURRENT_DATE BETWEEN i.fecha_inicio AND COALESCE(i.fecha_fin, CURRENT_DATE + INTERVAL '100 years')
                LIMIT 1
            ");
            $stmt->execute([$id_producto]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Calcular impuesto para un monto
     */
    public function calcularImpuesto($id_impuesto, $monto) {
        $impuesto = $this->obtenerPorId($id_impuesto);
        if (!$impuesto) return 0;
        return $monto * ($impuesto['porcentaje'] / 100);
    }
    
    /**
     * Crear nuevo impuesto
     */
    public function crear($data, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO impuestos (
                    nombre, codigo, porcentaje, aplica_a, aplica_receta,
                    fecha_inicio, fecha_fin, activo, descripcion, creado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id_impuesto
            ");
            $stmt->execute([
                $data['nombre'],
                $data['codigo'],
                $data['porcentaje'],
                $data['aplica_a'],
                $data['aplica_receta'] ?? false,
                $data['fecha_inicio'],
                $data['fecha_fin'] ?? null,
                $data['activo'] ?? true,
                $data['descripcion'] ?? null,
                $id_usuario
            ]);
            $result = $stmt->fetch();
            return $result['id_impuesto'];
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Actualizar impuesto
     */
    public function actualizar($id_impuesto, $data, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                UPDATE impuestos SET
                    nombre = ?,
                    codigo = ?,
                    porcentaje = ?,
                    aplica_a = ?,
                    aplica_receta = ?,
                    fecha_inicio = ?,
                    fecha_fin = ?,
                    activo = ?,
                    descripcion = ?,
                    modificado_por = ?,
                    fecha_modificacion = CURRENT_TIMESTAMP
                WHERE id_impuesto = ?
            ");
            return $stmt->execute([
                $data['nombre'],
                $data['codigo'],
                $data['porcentaje'],
                $data['aplica_a'],
                $data['aplica_receta'] ?? false,
                $data['fecha_inicio'],
                $data['fecha_fin'] ?? null,
                $data['activo'] ?? true,
                $data['descripcion'] ?? null,
                $id_usuario,
                $id_impuesto
            ]);
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Eliminar impuesto (desactivar)
     */
    public function eliminar($id_impuesto, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                UPDATE impuestos SET activo = FALSE, modificado_por = ?, fecha_modificacion = CURRENT_TIMESTAMP
                WHERE id_impuesto = ?
            ");
            return $stmt->execute([$id_usuario, $id_impuesto]);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>