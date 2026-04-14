<?php
class ClienteQueries {
    private $conexion;
    
    public function __construct($conexion) {
        $this->conexion = $conexion;
    }
    
    public function obtenerTodos() {
        try {
            $stmt = $this->conexion->query("SELECT id_cliente, nombre FROM clientes ORDER BY nombre");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function obtenerPorId($id_cliente) {
        try {
            $stmt = $this->conexion->prepare("SELECT id_cliente, nombre FROM clientes WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    public function contarTotal() {
        try {
            $stmt = $this->conexion->query("SELECT COUNT(*) as total FROM clientes");
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch(PDOException $e) {
            return 0;
        }
    }
    
    public function acumularPuntos($id_cliente, $puntos) {
        try {
            $stmt = $this->conexion->prepare("UPDATE clientes SET puntos_acumulados = puntos_acumulados + ? WHERE id_cliente = ?");
            return $stmt->execute([$puntos, $id_cliente]);
        } catch(PDOException $e) {
            return false;
        }
    }
}
?>