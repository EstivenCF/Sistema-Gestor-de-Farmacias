<?php
class ProductoQueries {
    private $conexion;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
    }
    
    public function obtenerProductosDisponibles($limite = 100) {
        try {
            $limite = intval($limite);
            $stmt = $this->conexion->prepare("
                SELECT 
                    m.id_medicamento,
                    m.nombre,
                    m.requiere_receta,
                    l.id_lote,
                    l.numero_lote,
                    i.cantidad AS stock,
                    p.precio
                FROM medicamentos m
                JOIN lotes l ON m.id_medicamento = l.id_medicamento
                JOIN inventario i ON l.id_lote = i.id_lote
                JOIN productos p ON m.id_producto = p.id_producto
                WHERE l.estado = 'ACTIVO'
                AND l.fecha_vencimiento > CURRENT_DATE
                AND i.cantidad > 0
                AND p.estado = true
                ORDER BY m.nombre
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error obtenerProductosDisponibles: " . $e->getMessage());
            return [];
        }
    }
    
    public function buscarProductos($termino, $limite = 10) {
        try {
            $limite = intval($limite);
            $stmt = $this->conexion->prepare("
                SELECT 
                    m.id_medicamento,
                    m.nombre,
                    m.requiere_receta,
                    l.id_lote,
                    l.numero_lote,
                    i.cantidad AS stock,
                    p.precio
                FROM medicamentos m
                JOIN lotes l ON m.id_medicamento = l.id_medicamento
                JOIN inventario i ON l.id_lote = i.id_lote
                JOIN productos p ON m.id_producto = p.id_producto
                WHERE l.estado = 'ACTIVO'
                  AND l.fecha_vencimiento > CURRENT_DATE
                  AND i.cantidad > 0
                  AND (m.nombre ILIKE ? OR l.numero_lote ILIKE ?)
                ORDER BY m.nombre
                LIMIT $limite
            ");
            $stmt->execute(["%$termino%", "%$termino%"]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Error buscarProductos: " . $e->getMessage());
            return [];
        }
    }
    
    public function obtenerStockBajo() {
        try {
            $stmt = $this->conexion->query("
                SELECT COUNT(*) as total FROM inventario i 
                JOIN lotes l ON i.id_lote = l.id_lote 
                WHERE i.cantidad <= 5 AND l.estado = 'ACTIVO'
            ");
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    public function obtenerPorVencer($dias = 30) {
        try {
            // PostgreSQL: INTERVAL no acepta parámetros bind, se usa intval para seguridad
            $dias = intval($dias);
            $stmt = $this->conexion->query("
                SELECT COUNT(*) as total FROM lotes 
                WHERE fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '$dias days'
                AND estado = 'ACTIVO'
            ");
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
}
?>