<?php
/**
 * DescuentoQueries.php - Consultas relacionadas con descuentos (múltiples descuentos)
 */

class DescuentoQueries {
    private $conexion;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
    }
    
    /**
     * Obtener todos los descuentos activos
     */
    public function obtenerTodosActivos() {
        try {
            $stmt = $this->conexion->query("
                SELECT d.*, td.nombre as tipo_nombre
                FROM descuentos d
                JOIN tipo_descuento td ON d.id_tipo_descuento = td.id_tipo
                WHERE d.activo = TRUE 
                  AND CURRENT_DATE BETWEEN d.fecha_inicio AND d.fecha_fin
                ORDER BY d.prioridad DESC, d.valor_descuento DESC
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtener descuento por ID
     */
    public function obtenerPorId($id_descuento) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT d.*, td.nombre as tipo_nombre
                FROM descuentos d
                JOIN tipo_descuento td ON d.id_tipo_descuento = td.id_tipo
                WHERE d.id_descuento = ?
            ");
            $stmt->execute([$id_descuento]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Calcular monto de descuento
     */
    public function calcularMontoDescuento($id_descuento, $subtotal) {
        $descuento = $this->obtenerPorId($id_descuento);
        if (!$descuento) return 0;
        
        if ($descuento['es_porcentaje']) {
            $monto = $subtotal * ($descuento['valor_descuento'] / 100);
        } else {
            $monto = $descuento['valor_descuento'];
        }
        
        // Aplicar límite máximo
        if ($descuento['monto_maximo_descuento']) {
            $monto = min($monto, $descuento['monto_maximo_descuento']);
        }
        
        return min($monto, $subtotal);
    }
    
    /**
     * Calcular descuentos aplicables a una venta (múltiples descuentos)
     */
    public function calcularDescuentosAplicables($id_cliente, $subtotal, $items) {
        try {
            $itemsJson = json_encode($items);
            $stmt = $this->conexion->prepare("
                SELECT * FROM calcular_descuentos_aplicables(?, ?, ?::jsonb)
                ORDER BY prioridad DESC
            ");
            $stmt->execute([$id_cliente, $subtotal, $itemsJson]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Aplicar descuento a venta
     */
    public function aplicarDescuentoVenta($id_venta, $id_descuento, $monto, $id_usuario, $id_aprobador = null) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO venta_descuento (id_venta, id_descuento, monto_descuento, id_usuario, aprobado_por)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id_venta, $id_descuento, $monto, $id_usuario, $id_aprobador]);
            
            // Registrar uso del descuento
            $stmt2 = $this->conexion->prepare("
                INSERT INTO descuento_uso (id_descuento, id_cliente, id_venta)
                SELECT ?, id_cliente, ? FROM ventas WHERE id_venta = ?
            ");
            $stmt2->execute([$id_descuento, $id_venta, $id_venta]);
            
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Validar si un descuento es aplicable a un cliente
     */
    public function validarDescuentoCliente($id_descuento, $id_cliente) {
        $descuento = $this->obtenerPorId($id_descuento);
        if (!$descuento) return false;
        
        // Verificar si aplica a cliente específico
        if ($descuento['segmento_cliente']) {
            $stmt = $this->conexion->prepare("
                SELECT 1 FROM clientes c
                JOIN niveles_cliente n ON c.id_nivel = n.id_nivel
                WHERE c.id_cliente = ? AND n.nombre = ?
            ");
            $stmt->execute([$id_cliente, $descuento['segmento_cliente']]);
            if (!$stmt->fetch()) return false;
        }
        
        // Verificar límite por cliente
        if ($descuento['limite_por_cliente']) {
            $stmt = $this->conexion->prepare("
                SELECT COUNT(*) as usos FROM descuento_uso
                WHERE id_descuento = ? AND id_cliente = ?
                  AND fecha_uso >= CURRENT_DATE - INTERVAL '1 year'
            ");
            $stmt->execute([$id_descuento, $id_cliente]);
            $usos = $stmt->fetch()['usos'];
            if ($usos >= $descuento['limite_por_cliente']) return false;
        }
        
        return true;
    }
    
    /**
     * Obtener descuentos disponibles para un cliente
     */
    public function obtenerDescuentosDisponiblesCliente($id_cliente) {
        try {
            $stmt = $this->conexion->prepare("
                SELECT d.*, td.nombre as tipo_nombre
                FROM descuentos d
                JOIN tipo_descuento td ON d.id_tipo_descuento = td.id_tipo
                WHERE d.activo = TRUE 
                  AND CURRENT_DATE BETWEEN d.fecha_inicio AND d.fecha_fin
                  AND (d.segmento_cliente IS NULL OR d.segmento_cliente IN (
                      SELECT n.nombre FROM clientes c
                      JOIN niveles_cliente n ON c.id_nivel = n.id_nivel
                      WHERE c.id_cliente = ?
                  ))
                  AND (d.limite_por_cliente IS NULL OR (
                      SELECT COUNT(*) FROM descuento_uso
                      WHERE id_descuento = d.id_descuento AND id_cliente = ?
                  ) < d.limite_por_cliente)
                ORDER BY d.prioridad DESC, d.valor_descuento DESC
            ");
            $stmt->execute([$id_cliente, $id_cliente]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    /**
     * Crear nuevo descuento
     */
    public function crear($data, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO descuentos (
                    codigo, nombre, descripcion, id_tipo_descuento, valor_descuento,
                    es_porcentaje, fecha_inicio, fecha_fin, horario_inicio, horario_fin,
                    dias_semana, aplica_todos_medicamentos, aplica_todas_categorias,
                    aplica_medicamentos_especificos, aplica_todos_productos,
                    limite_por_cliente, limite_por_venta, monto_minimo_compra,
                    monto_maximo_descuento, cantidad_minima_unidades, prioridad,
                    activo, combinable, requiere_aprobacion, segmento_cliente,
                    aplica_primer_compra, creado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id_descuento
            ");
            $stmt->execute([
                $data['codigo'],
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['id_tipo_descuento'],
                $data['valor_descuento'],
                $data['es_porcentaje'] ?? true,
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['horario_inicio'] ?? null,
                $data['horario_fin'] ?? null,
                $data['dias_semana'] ?? 'LUNES,MARTES,MIERCOLES,JUEVES,VIERNES,SABADO,DOMINGO',
                $data['aplica_todos_medicamentos'] ?? false,
                $data['aplica_todas_categorias'] ?? false,
                $data['aplica_medicamentos_especificos'] ?? false,
                $data['aplica_todos_productos'] ?? true,
                $data['limite_por_cliente'] ?? null,
                $data['limite_por_venta'] ?? null,
                $data['monto_minimo_compra'] ?? null,
                $data['monto_maximo_descuento'] ?? null,
                $data['cantidad_minima_unidades'] ?? null,
                $data['prioridad'] ?? 0,
                $data['activo'] ?? true,
                $data['combinable'] ?? false,
                $data['requiere_aprobacion'] ?? false,
                $data['segmento_cliente'] ?? null,
                $data['aplica_primer_compra'] ?? false,
                $id_usuario
            ]);
            $result = $stmt->fetch();
            return $result['id_descuento'];
        } catch(PDOException $e) {
            return false;
        }
    }
    
    /**
     * Actualizar descuento
     */
    public function actualizar($id_descuento, $data, $id_usuario) {
        try {
            $stmt = $this->conexion->prepare("
                UPDATE descuentos SET
                    codigo = ?, nombre = ?, descripcion = ?, id_tipo_descuento = ?,
                    valor_descuento = ?, es_porcentaje = ?, fecha_inicio = ?, fecha_fin = ?,
                    horario_inicio = ?, horario_fin = ?, dias_semana = ?,
                    aplica_todos_medicamentos = ?, aplica_todas_categorias = ?,
                    aplica_medicamentos_especificos = ?, aplica_todos_productos = ?,
                    limite_por_cliente = ?, limite_por_venta = ?, monto_minimo_compra = ?,
                    monto_maximo_descuento = ?, cantidad_minima_unidades = ?, prioridad = ?,
                    activo = ?, combinable = ?, requiere_aprobacion = ?, segmento_cliente = ?,
                    aplica_primer_compra = ?, modificado_por = ?, fecha_modificacion = CURRENT_TIMESTAMP
                WHERE id_descuento = ?
            ");
            return $stmt->execute([
                $data['codigo'],
                $data['nombre'],
                $data['descripcion'] ?? null,
                $data['id_tipo_descuento'],
                $data['valor_descuento'],
                $data['es_porcentaje'] ?? true,
                $data['fecha_inicio'],
                $data['fecha_fin'],
                $data['horario_inicio'] ?? null,
                $data['horario_fin'] ?? null,
                $data['dias_semana'] ?? 'LUNES,MARTES,MIERCOLES,JUEVES,VIERNES,SABADO,DOMINGO',
                $data['aplica_todos_medicamentos'] ?? false,
                $data['aplica_todas_categorias'] ?? false,
                $data['aplica_medicamentos_especificos'] ?? false,
                $data['aplica_todos_productos'] ?? true,
                $data['limite_por_cliente'] ?? null,
                $data['limite_por_venta'] ?? null,
                $data['monto_minimo_compra'] ?? null,
                $data['monto_maximo_descuento'] ?? null,
                $data['cantidad_minima_unidades'] ?? null,
                $data['prioridad'] ?? 0,
                $data['activo'] ?? true,
                $data['combinable'] ?? false,
                $data['requiere_aprobacion'] ?? false,
                $data['segmento_cliente'] ?? null,
                $data['aplica_primer_compra'] ?? false,
                $id_usuario,
                $id_descuento
            ]);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>