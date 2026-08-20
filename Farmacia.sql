-- =============================================================================
-- SISTEMA GESTOR DE FARMACIAS - SCRIPT COMPLETO (CON DIRECCIONES GENERALES)
-- =============================================================================

-- =============================================================================
-- 1. TABLAS BASE (sin dependencias externas)
-- =============================================================================

CREATE TABLE IF NOT EXISTS empresa (
    id_empresa SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    rnc VARCHAR(30),
    direccion VARCHAR(200),   -- Dirección principal (para compatibilidad)
    logo_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS sucursales (
    id_sucursal SERIAL PRIMARY KEY,
    id_empresa INT REFERENCES empresa(id_empresa) ON DELETE CASCADE,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(200),   -- Dirección principal
    telefono VARCHAR(30),
    estado BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS configuracion_sistema (
    id_config SERIAL PRIMARY KEY,
    clave VARCHAR(100) UNIQUE,
    valor TEXT,
    descripcion TEXT
);

CREATE TABLE IF NOT EXISTS roles (
    id_rol SERIAL PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT
);

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    id_rol INT REFERENCES roles(id_rol),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    estado BOOLEAN DEFAULT TRUE,
    imagen_url TEXT
);

CREATE TABLE IF NOT EXISTS telefonos (
    id_telefono SERIAL PRIMARY KEY,
    numero VARCHAR(20) NOT NULL,
    tipo VARCHAR(20) DEFAULT 'PRINCIPAL',
    whatsapp BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS correos (
    id_correo SERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) DEFAULT 'PRINCIPAL',
    activo BOOLEAN DEFAULT TRUE,
    verificado BOOLEAN DEFAULT FALSE,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS niveles_cliente (
    id_nivel SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    puntos_minimos INT NOT NULL,
    descuento_porcentaje NUMERIC(5,2) DEFAULT 0,
    beneficio_adicional TEXT
);

CREATE TABLE IF NOT EXISTS unidades_medida (
    id_unidad SERIAL PRIMARY KEY,
    nombre VARCHAR(50),
    abreviatura VARCHAR(10)
);

CREATE TABLE IF NOT EXISTS presentaciones (
    id_presentacion SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    id_unidad INT REFERENCES unidades_medida(id_unidad),
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categorias (
    id_categoria SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS principios_activos (
    id_principio SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS laboratorios (
    id_laboratorio SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    pais VARCHAR(100),
    direccion VARCHAR(200),
    telefono VARCHAR(30),
    email VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    descripcion TEXT,
    telefono2 VARCHAR(30),
    email2 VARCHAR(100),
    contacto_nombre VARCHAR(100),
    contacto_telefono VARCHAR(30),
    website VARCHAR(200),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS proveedores (
    id_proveedor SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(200),
    rnc VARCHAR(30)
);

CREATE TABLE IF NOT EXISTS medicamentos (
    id_medicamento SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    concentracion VARCHAR(50),
    descripcion TEXT,
    id_categoria INT REFERENCES categorias(id_categoria),
    requiere_receta BOOLEAN DEFAULT FALSE,
    stock_minimo INT DEFAULT 5,
    stock_maximo INT,
    punto_reorden INT DEFAULT 10,
    proveedor_preferido INT REFERENCES proveedores(id_proveedor),
    id_unidad INT REFERENCES unidades_medida(id_unidad),
    id_laboratorio INT REFERENCES laboratorios(id_laboratorio),
    exento_itbis BOOLEAN DEFAULT FALSE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_presentacion INT REFERENCES presentaciones(id_presentacion),
    id_producto INT UNIQUE,
    nombre_completo VARCHAR(200)
);

CREATE TABLE IF NOT EXISTS productos (
    id_producto SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    tipo_producto VARCHAR(20) NOT NULL CHECK (tipo_producto IN ('MEDICAMENTO', 'ROPA')),
    precio NUMERIC(10,2) NOT NULL,
    estado BOOLEAN DEFAULT TRUE,
    exento_itbis BOOLEAN DEFAULT FALSE,
    imagen_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS tipo_ropa (
    id_tipo SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS marcas (
    id_marca SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS fabricantes (
    id_fabricante SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    pais VARCHAR(100),
    contacto VARCHAR(100),
    estado BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS colores (
    id_color SERIAL PRIMARY KEY,
    nombre VARCHAR(50),
    estado BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS tallas (
    id_talla SERIAL PRIMARY KEY,
    nombre VARCHAR(10),
    estado BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS ropa_detalle (
    id_ropa SERIAL PRIMARY KEY,
    id_producto INT UNIQUE REFERENCES productos(id_producto),
    talla VARCHAR(10),
    color VARCHAR(30),
    marca VARCHAR(50),
    id_tipo INT REFERENCES tipo_ropa(id_tipo),
    id_marca INT REFERENCES marcas(id_marca),
    id_fabricante INT REFERENCES fabricantes(id_fabricante),
    id_color INT REFERENCES colores(id_color),
    id_talla INT REFERENCES tallas(id_talla)
);


-- =============================================
-- 2. INSERCIÓN DE DATOS (ENFOQUE MÉDICO)
-- =============================================

-- Sembrado seguro: solo si la tabla está completamente vacía. Antes esto
-- llevaba un TRUNCATE ... CASCADE que, al volver a correr este archivo
-- sobre una base ya en uso, BORRABA todos los productos de ropa reales
-- que ya tuvieras cargados (el CASCADE se llevaba también ropa_detalle).

INSERT INTO tipo_ropa (nombre, descripcion)
SELECT * FROM (VALUES
    ('Uniforme Médico', 'Batas, pijamas y uniformes para personal médico'),
    ('Maternidad', 'Ropa para embarazadas y lactancia'),
    ('Ortopedia', 'Fajas, soportes y prendas ortopédicas'),
    ('Calzado', 'Zapatos clínicos y ortopédicos'),
    ('Accesorios', 'Gorros, mascarillas y otros accesorios')
) AS v(nombre, descripcion)
WHERE NOT EXISTS (SELECT 1 FROM tipo_ropa);

-- Marcas reconocidas en el sector salud
INSERT INTO marcas (nombre, descripcion)
SELECT * FROM (VALUES
    ('Figs', 'Línea premium de scrubs con diseño técnico.'),
    ('Cherokee', 'Estándar mundial en uniformes de alta durabilidad.'),
    ('Dickies Medical', 'Ropa de trabajo médica funcional y resistente.'),
    ('Grey''s Anatomy', 'Uniformes de tela suave y diseño elegante para profesionales.'),
    ('Healing Hands', 'Marca enfocada en comodidad y telas elásticas.')
) AS v(nombre, descripcion)
WHERE NOT EXISTS (SELECT 1 FROM marcas);

-- Fabricantes de textiles médicos
INSERT INTO fabricantes (nombre, pais, contacto)
SELECT * FROM (VALUES
    ('Medline Industries', 'Estados Unidos', 'sales@medline.com'),
    ('Barco Uniforms', 'Estados Unidos', 'info@barcouniforms.com'),
    ('Textiles Médicos S.A.', 'Colombia', 'ventas@textilesmedicos.co'),
    ('Global Scrub Corp', 'México', 'contacto@globalscrub.mx'),
    ('EuroUniforms', 'España', 'atencion@eurouniforms.es')
) AS v(nombre, pais, contacto)
WHERE NOT EXISTS (SELECT 1 FROM fabricantes);

-- Colores institucionales y de especialidad
INSERT INTO colores (nombre)
SELECT * FROM (VALUES
    ('Azul Navy'), ('Azul Quirúrgico'), ('Verde Caribe'), ('Blanco Clínico'),
    ('Gris Oxford'), ('Vino (Burgundy)'), ('Verde Quirúrgico')
) AS v(nombre)
WHERE NOT EXISTS (SELECT 1 FROM colores);

-- Tallas estándar
INSERT INTO tallas (nombre)
SELECT * FROM (VALUES ('XXS'), ('XS'), ('S'), ('M'), ('L'), ('XL'), ('XXL')) AS v(nombre)
WHERE NOT EXISTS (SELECT 1 FROM tallas);

CREATE TABLE IF NOT EXISTS clientes (
    id_cliente SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(200),   -- Dirección principal (para compatibilidad)
    barrio VARCHAR(100),
    ciudad VARCHAR(100) DEFAULT 'Santiago',
    referencia_direccion TEXT,
    latitud DECIMAL(10,8),
    longitud DECIMAL(11,8),
    fecha_registro DATE DEFAULT CURRENT_DATE,
    permite_credito BOOLEAN DEFAULT FALSE,
    saldo_pendiente NUMERIC(12,2) DEFAULT 0,
    fecha_ultima_compra_credito TIMESTAMP,
    dias_mora INT DEFAULT 0,
    tiene_seguro BOOLEAN DEFAULT FALSE,
    id_nivel INT REFERENCES niveles_cliente(id_nivel) DEFAULT 1,
    puntos_acumulados NUMERIC(10,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS repartidores (
    id_repartidor SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono_emergencia VARCHAR(20),
    tipo_identificacion VARCHAR(20),
    numero_identificacion VARCHAR(30) UNIQUE,
    direccion VARCHAR(200),   -- Dirección principal
    fecha_ingreso DATE DEFAULT CURRENT_DATE,
    fecha_salida DATE,
    activo BOOLEAN DEFAULT TRUE,
    foto_url VARCHAR(255),
    licencia_conducir VARCHAR(50),
    fecha_vencimiento_licencia DATE,
    observaciones TEXT
);

-- =============================================================================
-- 2. TABLAS DE DIRECCIONES (GENERAL, COMO TELEFONOS)
-- =============================================================================

CREATE TABLE IF NOT EXISTS direcciones (
    id_direccion SERIAL PRIMARY KEY,
    direccion VARCHAR(200) NOT NULL,
    barrio VARCHAR(100),
    ciudad VARCHAR(100) DEFAULT 'Santiago',
    referencia TEXT,
    latitud DECIMAL(10,8),
    longitud DECIMAL(11,8),
    activo BOOLEAN DEFAULT TRUE,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tablas puente para direcciones (similar a telefonos)

CREATE TABLE IF NOT EXISTS cliente_direccion (
    id_cliente INT REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    id_direccion INT REFERENCES direcciones(id_direccion) ON DELETE CASCADE,
    predeterminada BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (id_cliente, id_direccion)
);

CREATE TABLE IF NOT EXISTS proveedor_direccion (
    id_proveedor INT REFERENCES proveedores(id_proveedor) ON DELETE CASCADE,
    id_direccion INT REFERENCES direcciones(id_direccion) ON DELETE CASCADE,
    predeterminada BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (id_proveedor, id_direccion)
);

CREATE TABLE IF NOT EXISTS sucursal_direccion (
    id_sucursal INT REFERENCES sucursales(id_sucursal) ON DELETE CASCADE,
    id_direccion INT REFERENCES direcciones(id_direccion) ON DELETE CASCADE,
    predeterminada BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (id_sucursal, id_direccion)
);

CREATE TABLE IF NOT EXISTS empresa_direccion (
    id_empresa INT REFERENCES empresa(id_empresa) ON DELETE CASCADE,
    id_direccion INT REFERENCES direcciones(id_direccion) ON DELETE CASCADE,
    predeterminada BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (id_empresa, id_direccion)
);

CREATE TABLE IF NOT EXISTS repartidor_direccion (
    id_repartidor INT REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    id_direccion INT REFERENCES direcciones(id_direccion) ON DELETE CASCADE,
    predeterminada BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (id_repartidor, id_direccion)
);

-- Opcional: usuario_direccion si se necesita
CREATE TABLE IF NOT EXISTS usuario_direccion (
    id_usuario INT REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    id_direccion INT REFERENCES direcciones(id_direccion) ON DELETE CASCADE,
    predeterminada BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (id_usuario, id_direccion)
);

-- =============================================================================
-- 3. TABLAS CON DEPENDENCIAS (relaciones N:M)
-- =============================================================================

CREATE TABLE IF NOT EXISTS categoria_presentacion (
    id_categoria INT REFERENCES categorias(id_categoria) ON DELETE CASCADE,
    id_presentacion INT REFERENCES presentaciones(id_presentacion) ON DELETE CASCADE,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_categoria, id_presentacion)
);

CREATE TABLE IF NOT EXISTS medicamento_principio (
    id_medicamento INT REFERENCES medicamentos(id_medicamento),
    id_principio INT REFERENCES principios_activos(id_principio),
    PRIMARY KEY (id_medicamento, id_principio)
);

CREATE TABLE IF NOT EXISTS cliente_telefono (
    id_cliente INT REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    id_telefono INT REFERENCES telefonos(id_telefono) ON DELETE CASCADE,
    PRIMARY KEY (id_cliente, id_telefono)
);

CREATE TABLE IF NOT EXISTS cliente_correo (
    id_cliente INT REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    id_correo INT REFERENCES correos(id_correo) ON DELETE CASCADE,
    PRIMARY KEY (id_cliente, id_correo)
);

CREATE TABLE IF NOT EXISTS proveedor_telefono (
    id_proveedor INT REFERENCES proveedores(id_proveedor) ON DELETE CASCADE,
    id_telefono INT REFERENCES telefonos(id_telefono) ON DELETE CASCADE,
    PRIMARY KEY (id_proveedor, id_telefono)
);

CREATE TABLE IF NOT EXISTS proveedor_correo (
    id_proveedor INT REFERENCES proveedores(id_proveedor) ON DELETE CASCADE,
    id_correo INT REFERENCES correos(id_correo) ON DELETE CASCADE,
    PRIMARY KEY (id_proveedor, id_correo)
);

CREATE TABLE IF NOT EXISTS usuario_telefono (
    id_usuario INT REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    id_telefono INT REFERENCES telefonos(id_telefono) ON DELETE CASCADE,
    PRIMARY KEY (id_usuario, id_telefono)
);

CREATE TABLE IF NOT EXISTS usuario_correo (
    id_usuario INT REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    id_correo INT REFERENCES correos(id_correo) ON DELETE CASCADE,
    PRIMARY KEY (id_usuario, id_correo)
);

CREATE TABLE IF NOT EXISTS repartidor_telefono (
    id_repartidor INT REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    id_telefono INT REFERENCES telefonos(id_telefono) ON DELETE CASCADE,
    PRIMARY KEY (id_repartidor, id_telefono)
);

CREATE TABLE IF NOT EXISTS repartidor_correo (
    id_repartidor INT REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    id_correo INT REFERENCES correos(id_correo) ON DELETE CASCADE,
    PRIMARY KEY (id_repartidor, id_correo)
);

CREATE TABLE IF NOT EXISTS empresa_telefono (
    id_empresa INT REFERENCES empresa(id_empresa) ON DELETE CASCADE,
    id_telefono INT REFERENCES telefonos(id_telefono) ON DELETE CASCADE,
    PRIMARY KEY (id_empresa, id_telefono)
);

CREATE TABLE IF NOT EXISTS empresa_correo (
    id_empresa INT REFERENCES empresa(id_empresa) ON DELETE CASCADE,
    id_correo INT REFERENCES correos(id_correo) ON DELETE CASCADE,
    PRIMARY KEY (id_empresa, id_correo)
);

CREATE TABLE IF NOT EXISTS tarjetas_fidelidad (
    id_tarjeta SERIAL PRIMARY KEY,
    id_cliente INT REFERENCES clientes(id_cliente),
    codigo VARCHAR(50) UNIQUE NOT NULL,
    puntos_acumulados NUMERIC(10,2) DEFAULT 0,
    fecha_emision DATE DEFAULT CURRENT_DATE,
    fecha_vencimiento DATE,
    activo BOOLEAN DEFAULT TRUE
);

-- =============================================================================
-- 4. TABLAS DE COMPRAS, LOTES, INVENTARIO
-- =============================================================================

CREATE TABLE IF NOT EXISTS estado_compra (
    id_estado SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS compras (
    id_compra SERIAL PRIMARY KEY,
    numero_documento VARCHAR(20) UNIQUE NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_proveedor INT REFERENCES proveedores(id_proveedor),
    id_usuario INT REFERENCES usuarios(id_usuario),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    id_estado INT REFERENCES estado_compra(id_estado) DEFAULT 1,
    fecha_esperada DATE,
    observaciones TEXT,
    subtotal NUMERIC(10,2) DEFAULT 0,
    descuento NUMERIC(10,2) DEFAULT 0,
    itbis NUMERIC(10,2) DEFAULT 0,
    total NUMERIC(10,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS lotes (
    id_lote SERIAL PRIMARY KEY,
    id_medicamento INT REFERENCES medicamentos(id_medicamento),
    numero_lote VARCHAR(50) UNIQUE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    cantidad_inicial INT NOT NULL DEFAULT 0,
    cantidad_actual INT NOT NULL DEFAULT 0,
    costo_unitario NUMERIC(10,2),
    codigo_barras VARCHAR(100),
    ubicacion VARCHAR(100),
    observaciones TEXT,
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_retiro DATE,
    motivo_retiro TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registro_por INT REFERENCES usuarios(id_usuario),
    costo_lote NUMERIC(10,2),
    CONSTRAINT chk_lote_estado CHECK (estado IN ('ACTIVO', 'VENCIDO', 'RETIRADO', 'MERMA', 'DAÑADO'))
);

CREATE TABLE IF NOT EXISTS detalle_compra (
    id_detalle SERIAL PRIMARY KEY,
    id_compra INT REFERENCES compras(id_compra) ON DELETE CASCADE,
    id_lote INT REFERENCES lotes(id_lote),
    cantidad INT NOT NULL,
    precio_unitario NUMERIC(10,2) NOT NULL,
    descuento_unitario NUMERIC(10,2) DEFAULT 0,
    itbis_unitario NUMERIC(10,2) DEFAULT 0,
    id_medicamento INT REFERENCES medicamentos(id_medicamento),
    cantidad_recibida INT DEFAULT 0,
    numero_lote_solicitado VARCHAR(50),
    fecha_vencimiento_solicitada DATE
);

-- 5. Extender detalle_compra para soportar productos (ropa)
ALTER TABLE detalle_compra 
ADD COLUMN IF NOT EXISTS id_producto INT REFERENCES productos(id_producto),
ADD COLUMN IF NOT EXISTS id_talla INT REFERENCES tallas(id_talla),
ADD COLUMN IF NOT EXISTS id_color INT REFERENCES colores(id_color);


CREATE TABLE IF NOT EXISTS inventario (
    id_inventario SERIAL PRIMARY KEY,
    id_lote INT REFERENCES lotes(id_lote),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    cantidad INT NOT NULL DEFAULT 0 CHECK (cantidad >= 0),
    UNIQUE(id_lote, id_sucursal)
);

CREATE TABLE IF NOT EXISTS movimiento_inventario (
    id_movimiento SERIAL PRIMARY KEY,
    id_lote INT REFERENCES lotes(id_lote),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('ENTRADA', 'SALIDA', 'AJUSTE', 'TRANSFERENCIA')),
    cantidad INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT,
    referencia VARCHAR(100),
    id_usuario INT REFERENCES usuarios(id_usuario),
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS inventario_productos (
    id_inventario SERIAL PRIMARY KEY,
    id_producto INT REFERENCES productos(id_producto),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    cantidad INT DEFAULT 0,
    UNIQUE(id_producto, id_sucursal)
);

-- =============================================================================
-- MEJORA DE inventario_productos PARA SOPORTAR ROPA CON TALLAS Y COLORES
-- =============================================================================

-- 1. Extender inventario_productos con campos para talla, color y controles de stock
ALTER TABLE inventario_productos 
ADD COLUMN IF NOT EXISTS id_talla INT REFERENCES tallas(id_talla),
ADD COLUMN IF NOT EXISTS id_color INT REFERENCES colores(id_color),
ADD COLUMN IF NOT EXISTS stock_minimo INT DEFAULT 2,
ADD COLUMN IF NOT EXISTS stock_maximo INT DEFAULT 50,
ADD COLUMN IF NOT EXISTS punto_reorden INT DEFAULT 5;

-- 2. Modificar la UNIQUE constraint para incluir talla y color
-- (Primero eliminar la existente, luego crear una nueva)
ALTER TABLE inventario_productos DROP CONSTRAINT IF EXISTS inventario_productos_id_producto_id_sucursal_key;
ALTER TABLE inventario_productos DROP CONSTRAINT IF EXISTS inventario_productos_unique;
ALTER TABLE inventario_productos ADD CONSTRAINT inventario_productos_unique 
UNIQUE (id_producto, id_sucursal, id_talla, id_color);

-- 3. Crear tabla de movimientos para inventario_productos (auditoría)
CREATE TABLE IF NOT EXISTS movimiento_inventario_productos (
    id_movimiento SERIAL PRIMARY KEY,
    id_producto INT REFERENCES productos(id_producto),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    id_talla INT REFERENCES tallas(id_talla),
    id_color INT REFERENCES colores(id_color),
    tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('ENTRADA', 'SALIDA', 'AJUSTE', 'TRANSFERENCIA')),
    cantidad INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT,
    referencia VARCHAR(100),
    id_usuario INT REFERENCES usuarios(id_usuario),
    observaciones TEXT
);

-- 4. Función para actualizar stock de productos al recibir una compra
-- . Trigger para ROPA (solo cuando hay id_producto y NO hay lote)
CREATE OR REPLACE FUNCTION trg_compra_actualiza_inventario_productos()
RETURNS TRIGGER AS $$
DECLARE
    v_sucursal INT;
    v_usuario INT;
BEGIN
    -- Validar que es ropa (tiene producto y NO tiene lote)
    IF NEW.id_producto IS NULL OR NEW.id_lote IS NOT NULL THEN
        RETURN NEW;
    END IF;
    
    -- Validar cantidad
    IF NEW.cantidad_recibida <= 0 THEN
        RETURN NEW;
    END IF;
    
    -- Obtener sucursal y usuario
    SELECT id_sucursal, id_usuario INTO v_sucursal, v_usuario
    FROM compras WHERE id_compra = NEW.id_compra;
    
    -- Solo registrar auditoría (NO actualizar inventario)
    INSERT INTO movimiento_inventario_productos (
        id_producto, id_sucursal, id_talla, id_color, tipo, 
        cantidad, motivo, referencia, id_usuario
    ) VALUES (
        NEW.id_producto, v_sucursal, NEW.id_talla, NEW.id_color, 'ENTRADA',
        NEW.cantidad_recibida, 'Compra de proveedor', NEW.id_compra::VARCHAR, v_usuario
    );
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Recrear trigger SOLO para ropa
DROP TRIGGER IF EXISTS tg_detalle_compra_productos_ai ON detalle_compra;
CREATE TRIGGER tg_detalle_compra_productos_ai 
AFTER INSERT OR UPDATE OF cantidad_recibida ON detalle_compra
FOR EACH ROW
WHEN (NEW.id_producto IS NOT NULL AND NEW.id_lote IS NULL AND NEW.cantidad_recibida > 0)
EXECUTE FUNCTION trg_compra_actualiza_inventario_productos();


-- 1. Encuentra los duplicados
SELECT id_producto, id_sucursal, COUNT(*), SUM(cantidad)
FROM inventario_productos
GROUP BY id_producto, id_sucursal
HAVING COUNT(*) > 1;

-- 2. Elimina los duplicados (deja solo uno por producto/sucursal)
DELETE FROM inventario_productos a
USING inventario_productos b
WHERE a.id_inventario < b.id_inventario
  AND a.id_producto = b.id_producto
  AND a.id_sucursal = b.id_sucursal;
  




-- 7. Función para actualizar stock de productos al vender
-- =====================================================
-- CORRECCIÓN PARA ROPA (SOLO VALIDAR, NO ACTUALIZAR)
-- =====================================================

-- 1. Reemplazar la función para que solo valide stock
CREATE OR REPLACE FUNCTION trg_venta_actualiza_inventario_productos()
RETURNS TRIGGER AS $$
DECLARE
    v_usuario INTEGER;
    v_sucursal INTEGER;
    v_cantidad_actual INTEGER;
BEGIN
    -- Validar cantidad
    IF NEW.cantidad IS NULL OR NEW.cantidad <= 0 THEN
        RAISE EXCEPTION 'Cantidad inválida: %', NEW.cantidad;
    END IF;

    -- Obtener usuario y sucursal desde la venta
    SELECT id_usuario, id_sucursal
    INTO v_usuario, v_sucursal
    FROM ventas
    WHERE id_venta = NEW.id_venta;

    IF v_sucursal IS NULL THEN
        RAISE EXCEPTION 'Venta no encontrada o sin sucursal';
    END IF;

    -- ✅ Buscar stock SIN considerar talla y color (solo producto y sucursal)
    SELECT COALESCE(SUM(cantidad), 0) INTO v_cantidad_actual
    FROM inventario_productos
    WHERE id_producto = NEW.id_producto 
      AND id_sucursal = v_sucursal;

    IF v_cantidad_actual = 0 THEN
        RAISE EXCEPTION 'No existe inventario para el producto % en sucursal %', NEW.id_producto, v_sucursal;
    END IF;

    -- Validar stock suficiente
    IF v_cantidad_actual < NEW.cantidad THEN
        RAISE EXCEPTION 'Stock insuficiente. Disponible: %, requerido: %',
            v_cantidad_actual, NEW.cantidad;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;




-- 10. Vista unificada de inventario (medicamentos + productos)
CREATE OR REPLACE VIEW vista_inventario_unificado AS
-- Medicamentos (con lotes)
SELECT 
    'MEDICAMENTO' AS tipo,
    m.id_medicamento AS id_item,
    m.nombre_completo AS nombre,
    l.numero_lote,
    l.fecha_vencimiento,
    NULL AS talla,
    NULL AS color,
    i.cantidad,
    m.stock_minimo,
    i.id_sucursal,
    s.nombre AS sucursal,
    CASE 
        WHEN l.fecha_vencimiento < CURRENT_DATE THEN 'VENCIDO'
        WHEN l.fecha_vencimiento <= CURRENT_DATE + INTERVAL '30 days' THEN 'PROXIMO A VENCER'
        WHEN i.cantidad <= m.stock_minimo THEN 'STOCK_CRITICO'
        ELSE 'NORMAL'
    END AS estado_alerta
FROM inventario i
JOIN lotes l ON i.id_lote = l.id_lote
JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
JOIN sucursales s ON i.id_sucursal = s.id_sucursal

UNION ALL

-- Productos (ropa, etc.)
SELECT 
    'PRODUCTO' AS tipo,
    p.id_producto AS id_item,
    p.nombre AS nombre,
    NULL AS numero_lote,
    NULL AS fecha_vencimiento,
    t.nombre AS talla,
    c.nombre AS color,
    ip.cantidad,
    COALESCE(ip.stock_minimo, 2) AS stock_minimo,
    ip.id_sucursal,
    s.nombre AS sucursal,
    CASE 
        WHEN ip.cantidad <= COALESCE(ip.stock_minimo, 2) THEN 'STOCK_CRITICO'
        WHEN ip.cantidad <= COALESCE(ip.punto_reorden, 5) THEN 'STOCK_BAJO'
        ELSE 'NORMAL'
    END AS estado_alerta
FROM inventario_productos ip
JOIN productos p ON ip.id_producto = p.id_producto
JOIN sucursales s ON ip.id_sucursal = s.id_sucursal
LEFT JOIN tallas t ON ip.id_talla = t.id_talla
LEFT JOIN colores c ON ip.id_color = c.id_color
WHERE p.tipo_producto = 'ROPA';

-- 11. Función para obtener stock unificado
CREATE OR REPLACE FUNCTION obtener_stock_unificado(
    p_tipo VARCHAR,
    p_id_item INT,
    p_id_sucursal INT,
    p_id_talla INT DEFAULT NULL,
    p_id_color INT DEFAULT NULL
)
RETURNS INT AS $$
DECLARE
    v_stock INT;
BEGIN
    IF p_tipo = 'MEDICAMENTO' THEN
        -- Buscar stock de medicamento (sumando todos los lotes activos)
        SELECT COALESCE(SUM(i.cantidad), 0) INTO v_stock
        FROM inventario i
        JOIN lotes l ON i.id_lote = l.id_lote
        WHERE l.id_medicamento = p_id_item
          AND i.id_sucursal = p_id_sucursal
          AND l.estado = 'ACTIVO'
          AND l.fecha_vencimiento >= CURRENT_DATE;
    ELSE
        -- Buscar stock de producto (ropa)
        SELECT COALESCE(SUM(cantidad), 0) INTO v_stock
        FROM inventario_productos
        WHERE id_producto = p_id_item
          AND id_sucursal = p_id_sucursal
          AND (p_id_talla IS NULL OR id_talla = p_id_talla)
          AND (p_id_color IS NULL OR id_color = p_id_color);
    END IF;
    
    RETURN v_stock;
END;
$$ LANGUAGE plpgsql;

-- 12. Función para generar alertas de stock (unificada)
CREATE OR REPLACE FUNCTION generar_alertas_stock_unificado()
RETURNS TABLE (
    tipo_alerta VARCHAR(50),
    item_nombre VARCHAR(200),
    sucursal VARCHAR(100),
    cantidad INT,
    stock_minimo INT,
    talla VARCHAR(10),
    color VARCHAR(30)
) AS $$
BEGIN
    -- Alertas de medicamentos
    RETURN QUERY
    SELECT 
        'STOCK_CRITICO_MEDICAMENTO'::VARCHAR(50),
        m.nombre_completo::VARCHAR(200),
        s.nombre::VARCHAR(100),
        i.cantidad::INT,
        m.stock_minimo::INT,
        NULL::VARCHAR(10),
        NULL::VARCHAR(30)
    FROM inventario i
    JOIN lotes l ON i.id_lote = l.id_lote
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    JOIN sucursales s ON i.id_sucursal = s.id_sucursal
    WHERE l.estado = 'ACTIVO' 
      AND l.fecha_vencimiento >= CURRENT_DATE
      AND i.cantidad <= m.stock_minimo;
    
    -- Alertas de productos (ropa)
    RETURN QUERY
    SELECT 
        'STOCK_CRITICO_PRODUCTO'::VARCHAR(50),
        p.nombre::VARCHAR(200),
        s.nombre::VARCHAR(100),
        ip.cantidad::INT,
        COALESCE(ip.stock_minimo, 2)::INT,
        t.nombre::VARCHAR(10),
        c.nombre::VARCHAR(30)
    FROM inventario_productos ip
    JOIN productos p ON ip.id_producto = p.id_producto
    JOIN sucursales s ON ip.id_sucursal = s.id_sucursal
    LEFT JOIN tallas t ON ip.id_talla = t.id_talla
    LEFT JOIN colores c ON ip.id_color = c.id_color
    WHERE p.tipo_producto = 'ROPA'
      AND ip.cantidad <= COALESCE(ip.stock_minimo, 2);
END;
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS historial_estado_lote (
    id_historial SERIAL PRIMARY KEY,
    id_lote INT REFERENCES lotes(id_lote) ON DELETE CASCADE,
    estado_anterior VARCHAR(20) NOT NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    motivo TEXT NOT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT REFERENCES usuarios(id_usuario),
    observaciones TEXT
);

-- =============================================================================
-- 5. TABLAS DE VENTAS, PAGOS, CONDICIONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS condicion_pago (
    id_condicion SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    dias_plazo INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS metodos_pago (
    id_metodo SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS ventas (
    id_venta SERIAL PRIMARY KEY,
    numero_documento VARCHAR(20) UNIQUE NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT REFERENCES usuarios(id_usuario) NOT NULL,
    id_cliente INT REFERENCES clientes(id_cliente),
    id_condicion INT REFERENCES condicion_pago(id_condicion),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    subtotal NUMERIC(10,2) NOT NULL DEFAULT 0,
    descuento_total NUMERIC(10,2) DEFAULT 0,
    itbis_total NUMERIC(10,2) DEFAULT 0,
    total NUMERIC(10,2) NOT NULL,
    ncf VARCHAR(19),
    rnc_cliente VARCHAR(11),
    tipo_comprobante VARCHAR(5),
    estado_fiscal VARCHAR(20) DEFAULT 'PENDIENTE',
    autorizacion VARCHAR(50),
    fecha_envio_fiscal TIMESTAMP,
    es_credito BOOLEAN DEFAULT FALSE,
    fecha_vencimiento_pago DATE,
    fecha_pago_real TIMESTAMP,
    estado_pago VARCHAR(20) DEFAULT 'PENDIENTE',
    abonos_acumulados NUMERIC(12,2) DEFAULT 0,
    usa_seguro BOOLEAN DEFAULT FALSE,
    monto_cubre_seguro NUMERIC(10,2) DEFAULT 0,
    monto_paga_paciente NUMERIC(10,2) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS detalle_venta (
    id_detalle SERIAL PRIMARY KEY,
    id_venta INT REFERENCES ventas(id_venta) ON DELETE CASCADE,
    id_lote INT REFERENCES lotes(id_lote),
    id_producto INT REFERENCES productos(id_producto),
    cantidad INT NOT NULL,
    precio_unitario NUMERIC(10,2) NOT NULL,
    descuento_unitario NUMERIC(10,2) DEFAULT 0,
    itbis_unitario NUMERIC(10,2) DEFAULT 0,
    subtotal NUMERIC(10,2) NOT NULL,
    CONSTRAINT chk_detalle_origen CHECK (id_lote IS NOT NULL OR id_producto IS NOT NULL)
);

-- 8. Extender detalle_venta para soportar productos (ropa)
ALTER TABLE detalle_venta 
ADD COLUMN IF NOT EXISTS id_talla INT REFERENCES tallas(id_talla),
ADD COLUMN IF NOT EXISTS id_color INT REFERENCES colores(id_color);

-- 9. Trigger para ventas de productos (ropa)
DROP TRIGGER IF EXISTS tg_detalle_venta_productos_ai ON detalle_venta;
CREATE TRIGGER tg_detalle_venta_productos_ai
AFTER INSERT ON detalle_venta
FOR EACH ROW
WHEN (NEW.id_producto IS NOT NULL AND NEW.id_lote IS NULL)
EXECUTE FUNCTION trg_venta_actualiza_inventario_productos();


CREATE TABLE IF NOT EXISTS pagos (
    id_pago SERIAL PRIMARY KEY,
    id_venta INT REFERENCES ventas(id_venta) ON DELETE CASCADE,
    id_metodo INT REFERENCES metodos_pago(id_metodo),
    monto NUMERIC(10,2) NOT NULL,
    id_aseguradora INT,
    id_autorizacion INT,
    id_poliza INT,
    monto_seguro NUMERIC(10,2) DEFAULT 0,
    monto_paciente NUMERIC(10,2) DEFAULT 0,
    numero_autorizacion VARCHAR(50),
    fecha_autorizacion TIMESTAMP,
    estado_seguro VARCHAR(20) DEFAULT 'PENDIENTE',
    referencia_seguro VARCHAR(100),
    fecha_pago_seguro TIMESTAMP,
    referencia VARCHAR(100),
    fecha_transaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'COMPLETADO'
);

-- =============================================================================
-- 6. TABLAS DE DESCUENTOS, CUPONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS tipo_descuento (
    id_tipo SERIAL PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL UNIQUE,
    descripcion TEXT,
    requiere_validacion_adicional BOOLEAN DEFAULT FALSE,
    requiere_articulo_gratis BOOLEAN DEFAULT FALSE,
    codigo_interno VARCHAR(10)
);

CREATE TABLE IF NOT EXISTS descuentos (
    id_descuento SERIAL PRIMARY KEY,
    codigo VARCHAR(30) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    id_tipo_descuento INT REFERENCES tipo_descuento(id_tipo),
    valor_descuento NUMERIC(10,2) NOT NULL,
    es_porcentaje BOOLEAN DEFAULT TRUE,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    horario_inicio TIME,
    horario_fin TIME,
    dias_semana VARCHAR(100) DEFAULT 'LUNES,MARTES,MIERCOLES,JUEVES,VIERNES,SABADO,DOMINGO',
    aplica_todos_medicamentos BOOLEAN DEFAULT FALSE,
    aplica_todas_categorias BOOLEAN DEFAULT FALSE,
    aplica_medicamentos_especificos BOOLEAN DEFAULT FALSE,
    aplica_todos_productos BOOLEAN DEFAULT FALSE,
    limite_por_cliente INT,
    limite_por_venta INT,
    monto_minimo_compra NUMERIC(10,2),
    monto_maximo_descuento NUMERIC(10,2),
    cantidad_minima_unidades INT,
    prioridad INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    combinable BOOLEAN DEFAULT FALSE,
    requiere_aprobacion BOOLEAN DEFAULT FALSE,
    segmento_cliente VARCHAR(50),
    aplica_primer_compra BOOLEAN DEFAULT FALSE,
    creado_por INT REFERENCES usuarios(id_usuario),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_por INT REFERENCES usuarios(id_usuario),
    fecha_modificacion TIMESTAMP
);

CREATE TABLE IF NOT EXISTS venta_descuento (
    id_venta_descuento SERIAL PRIMARY KEY,
    id_venta INT REFERENCES ventas(id_venta) ON DELETE CASCADE,
    id_descuento INT REFERENCES descuentos(id_descuento),
    monto_descuento NUMERIC(10,2) NOT NULL,
    id_medicamento_gratis INT REFERENCES medicamentos(id_medicamento),
    cantidad_gratis INT,
    fecha_aplicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT REFERENCES usuarios(id_usuario),
    aprobado_por INT REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS descuento_medicamento (
    id_descuento INT REFERENCES descuentos(id_descuento) ON DELETE CASCADE,
    id_medicamento INT REFERENCES medicamentos(id_medicamento),
    PRIMARY KEY (id_descuento, id_medicamento)
);

CREATE TABLE IF NOT EXISTS descuento_categoria (
    id_descuento INT REFERENCES descuentos(id_descuento) ON DELETE CASCADE,
    id_categoria INT REFERENCES categorias(id_categoria),
    PRIMARY KEY (id_descuento, id_categoria)
);

CREATE TABLE IF NOT EXISTS descuento_cliente (
    id_descuento INT REFERENCES descuentos(id_descuento) ON DELETE CASCADE,
    id_cliente INT REFERENCES clientes(id_cliente),
    PRIMARY KEY (id_descuento, id_cliente)
);

CREATE TABLE IF NOT EXISTS cupones (
    id_cupon SERIAL PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    id_descuento INT REFERENCES descuentos(id_descuento),
    id_cliente_especifico INT REFERENCES clientes(id_cliente),
    uso_maximo INT DEFAULT 1,
    usado_veces INT DEFAULT 0,
    fecha_expiracion DATE,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS descuento_uso (
    id_uso SERIAL PRIMARY KEY,
    id_descuento INT REFERENCES descuentos(id_descuento),
    id_cliente INT REFERENCES clientes(id_cliente),
    id_venta INT REFERENCES ventas(id_venta),
    fecha_uso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    veces_usadas INT DEFAULT 1
);

-- =============================================================================
-- 7. TABLAS DE SEGUROS MÉDICOS
-- =============================================================================

CREATE TABLE IF NOT EXISTS aseguradoras (
    id_aseguradora SERIAL PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    rnc VARCHAR(30),
    telefono VARCHAR(30),
    email VARCHAR(100),
    direccion VARCHAR(200),
    contacto_nombre VARCHAR(100),
    contacto_telefono VARCHAR(30),
    porcentaje_cobertura_default NUMERIC(5,2) DEFAULT 80.00,
    cobertura_global BOOLEAN DEFAULT TRUE,
    requiere_autorizacion BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP,
    creado_por INT REFERENCES usuarios(id_usuario),
    modificado_por INT REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS polizas (
    id_poliza SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    id_aseguradora INT NOT NULL REFERENCES aseguradoras(id_aseguradora),
    numero_poliza VARCHAR(50) NOT NULL,
    numero_carnet VARCHAR(50),
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    cobertura_porcentaje NUMERIC(5,2),
    copago_fijo NUMERIC(10,2) DEFAULT 0,
    deducible_anual NUMERIC(10,2) DEFAULT 0,
    deducible_usado NUMERIC(10,2) DEFAULT 0,
    tope_anual NUMERIC(10,2),
    tope_usado NUMERIC(10,2) DEFAULT 0,
    beneficiario_titular BOOLEAN DEFAULT TRUE,
    nombre_beneficiario VARCHAR(150),
    parentesco VARCHAR(50),
    activo BOOLEAN DEFAULT TRUE,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP,
    creado_por INT REFERENCES usuarios(id_usuario),
    modificado_por INT REFERENCES usuarios(id_usuario),
    CONSTRAINT chk_poliza_fechas CHECK (fecha_inicio <= fecha_fin),
    CONSTRAINT chk_deducible CHECK (deducible_usado <= deducible_anual),
    CONSTRAINT chk_tope CHECK (tope_usado <= tope_anual)
);

CREATE TABLE IF NOT EXISTS cobertura_medicamentos (
    id_cobertura SERIAL PRIMARY KEY,
    id_poliza INT NOT NULL REFERENCES polizas(id_poliza) ON DELETE CASCADE,
    id_medicamento INT NOT NULL REFERENCES medicamentos(id_medicamento),
    cobertura_porcentaje NUMERIC(5,2) NOT NULL,
    copago_fijo NUMERIC(10,2) DEFAULT 0,
    requiere_autorizacion BOOLEAN DEFAULT FALSE,
    cantidad_maxima_mensual INT,
    cantidad_maxima_anual INT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_cobertura_porcentaje CHECK (cobertura_porcentaje >= 0 AND cobertura_porcentaje <= 100),
    UNIQUE(id_poliza, id_medicamento)
);

CREATE TABLE IF NOT EXISTS autorizaciones_seguro (
    id_autorizacion SERIAL PRIMARY KEY,
    numero_autorizacion VARCHAR(50) UNIQUE NOT NULL,
    id_cliente INT NOT NULL REFERENCES clientes(id_cliente),
    id_aseguradora INT NOT NULL REFERENCES aseguradoras(id_aseguradora),
    id_medicamento INT REFERENCES medicamentos(id_medicamento),
    id_poliza INT REFERENCES polizas(id_poliza),
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_autorizacion TIMESTAMP,
    fecha_expiracion DATE,
    cantidad_autorizada INT NOT NULL,
    cantidad_usada INT DEFAULT 0,
    diagnostico TEXT,
    medico_que_autoriza VARCHAR(150),
    numero_autorizacion_medico VARCHAR(50),
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    motivo_rechazo TEXT,
    observaciones TEXT,
    solicitado_por INT REFERENCES usuarios(id_usuario),
    autorizado_por INT REFERENCES usuarios(id_usuario),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP,
    CONSTRAINT chk_autorizacion_estado CHECK (estado IN ('PENDIENTE', 'APROBADO', 'RECHAZADO', 'EXPIRADO', 'USADO')),
    CONSTRAINT chk_cantidad_usada CHECK (cantidad_usada <= cantidad_autorizada)
);

CREATE TABLE IF NOT EXISTS sucursal_aseguradora (
    id_sucursal INT NOT NULL REFERENCES sucursales(id_sucursal) ON DELETE CASCADE,
    id_aseguradora INT NOT NULL REFERENCES aseguradoras(id_aseguradora) ON DELETE CASCADE,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_sucursal, id_aseguradora)
);

CREATE TABLE IF NOT EXISTS facturas_seguro (
    id_factura_seguro SERIAL PRIMARY KEY,
    numero_factura VARCHAR(50) UNIQUE NOT NULL,
    id_aseguradora INT NOT NULL REFERENCES aseguradoras(id_aseguradora),
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_desde DATE NOT NULL,
    fecha_hasta DATE NOT NULL,
    subtotal NUMERIC(12,2) NOT NULL,
    descuento NUMERIC(12,2) DEFAULT 0,
    total NUMERIC(12,2) NOT NULL,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_envio TIMESTAMP,
    fecha_pago TIMESTAMP,
    numero_comprobante_pago VARCHAR(50),
    observaciones TEXT,
    creado_por INT REFERENCES usuarios(id_usuario),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_por INT REFERENCES usuarios(id_usuario),
    fecha_modificacion TIMESTAMP,
    CONSTRAINT chk_factura_estado CHECK (estado IN ('PENDIENTE', 'ENVIADA', 'PAGADA', 'ANULADA'))
);

CREATE TABLE IF NOT EXISTS detalle_factura_seguro (
    id_detalle_factura SERIAL PRIMARY KEY,
    id_factura_seguro INT NOT NULL REFERENCES facturas_seguro(id_factura_seguro) ON DELETE CASCADE,
    id_pago INT NOT NULL REFERENCES pagos(id_pago),
    id_venta INT NOT NULL REFERENCES ventas(id_venta),
    id_cliente INT NOT NULL REFERENCES clientes(id_cliente),
    monto_seguro NUMERIC(10,2) NOT NULL,
    fecha_venta TIMESTAMP,
    CONSTRAINT chk_detalle_factura UNIQUE(id_factura_seguro, id_pago)
);

-- =============================================================================
-- 8. TABLAS DE CRÉDITO CLIENTES
-- =============================================================================

CREATE TABLE IF NOT EXISTS limites_credito_cliente (
    id_limite SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    limite_maximo NUMERIC(12,2) NOT NULL DEFAULT 0,
    limite_utilizado NUMERIC(12,2) DEFAULT 0,
    dias_plazo_maximo INT DEFAULT 30,
    activo BOOLEAN DEFAULT TRUE,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_por INT REFERENCES usuarios(id_usuario),
    observaciones TEXT,
    CONSTRAINT chk_limite_credito CHECK (limite_utilizado <= limite_maximo)
);

CREATE TABLE IF NOT EXISTS configuracion_credito (
    id_config SERIAL PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descripcion TEXT,
    modificado_por INT REFERENCES usuarios(id_usuario),
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS historial_credito_cliente (
    id_historial SERIAL PRIMARY KEY,
    id_cliente INT NOT NULL REFERENCES clientes(id_cliente) ON DELETE CASCADE,
    id_venta INT REFERENCES ventas(id_venta),
    tipo_movimiento VARCHAR(20) NOT NULL,
    monto NUMERIC(12,2) NOT NULL,
    saldo_anterior NUMERIC(12,2) NOT NULL,
    saldo_nuevo NUMERIC(12,2) NOT NULL,
    fecha_vencimiento DATE,
    fecha_pago DATE,
    referencia VARCHAR(100),
    observaciones TEXT,
    creado_por INT REFERENCES usuarios(id_usuario),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_tipo_movimiento CHECK (tipo_movimiento IN ('CARGO', 'ABONO', 'AJUSTE'))
);

CREATE TABLE IF NOT EXISTS abonos_credito (
    id_abono SERIAL PRIMARY KEY,
    id_venta INT NOT NULL REFERENCES ventas(id_venta) ON DELETE CASCADE,
    monto NUMERIC(12,2) NOT NULL,
    fecha_abono TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_metodo_pago INT REFERENCES metodos_pago(id_metodo),
    referencia VARCHAR(100),
    observaciones TEXT,
    creado_por INT REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS acumulacion_puntos (
    id_acumulacion SERIAL PRIMARY KEY,
    id_venta INT REFERENCES ventas(id_venta),
    id_cliente INT REFERENCES clientes(id_cliente),
    puntos_ganados NUMERIC(10,2) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- 9. TABLAS DE DELIVERY
-- =============================================================================

CREATE TABLE IF NOT EXISTS estado_entrega (
    id_estado SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS vehiculos (
    id_vehiculo SERIAL PRIMARY KEY,
    id_repartidor INT REFERENCES repartidores(id_repartidor),
    tipo VARCHAR(50) NOT NULL,
    placa VARCHAR(20),
    marca VARCHAR(50),
    modelo VARCHAR(50),
    color VARCHAR(30),
    seguro_empresa VARCHAR(100),
    fecha_vencimiento_seguro DATE,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS entregas (
    id_entrega SERIAL PRIMARY KEY,
    id_venta INT UNIQUE REFERENCES ventas(id_venta),
    id_cliente INT REFERENCES clientes(id_cliente),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    id_repartidor INT REFERENCES repartidores(id_repartidor),
    id_estado INT REFERENCES estado_entrega(id_estado) DEFAULT 1,
    numero_seguimiento VARCHAR(30) UNIQUE NOT NULL,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_programada TIMESTAMP,
    fecha_asignada TIMESTAMP,
    fecha_inicio TIMESTAMP,
    fecha_entrega TIMESTAMP,
    direccion_entrega VARCHAR(200) NOT NULL,
    barrio_entrega VARCHAR(100),
    ciudad_entrega VARCHAR(100) DEFAULT 'Santiago',
    referencia_entrega TEXT,
    latitud_entrega DECIMAL(10,8),
    longitud_entrega DECIMAL(11,8),
    costo_entrega NUMERIC(10,2) NOT NULL DEFAULT 0,
    propina NUMERIC(10,2) DEFAULT 0,
    total_cobrado_cliente NUMERIC(10,2),
    telefono_contacto VARCHAR(20),
    nombre_quien_recibe VARCHAR(100),
    identificacion_quien_recibe VARCHAR(30),
    firma_url VARCHAR(255),
    calificacion INT CHECK (calificacion >= 1 AND calificacion <= 5),
    comentario_cliente TEXT,
    incidencia TEXT,
    distancia_km NUMERIC(8,2),
    tiempo_estimado_minutos INT,
    prioridad INT DEFAULT 1,
    creado_por INT REFERENCES usuarios(id_usuario),
    modificado_por INT REFERENCES usuarios(id_usuario),
    fecha_modificacion TIMESTAMP,
    cliente_nombre VARCHAR(30),
    fecha_entrega_real TIMESTAMP,
    estado BOOLEAN,
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS historial_entrega (
    id_historial SERIAL PRIMARY KEY,
    id_entrega INT REFERENCES entregas(id_entrega),
    id_estado INT REFERENCES estado_entrega(id_estado),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacion TEXT,
    latitud DECIMAL(10,8),
    longitud DECIMAL(11,8),
    id_usuario INT REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS tipo_incidencia_delivery (
    id_tipo_incidencia SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    nivel_gravedad INT DEFAULT 1 CHECK (nivel_gravedad BETWEEN 1 AND 5),
    requiere_documentacion BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS incidencias_entrega (
    id_incidencia SERIAL PRIMARY KEY,
    id_entrega INT REFERENCES entregas(id_entrega),
    id_tipo_incidencia INT REFERENCES tipo_incidencia_delivery(id_tipo_incidencia),
    fecha_incidencia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    descripcion TEXT,
    foto_url VARCHAR(255),
    documento_url VARCHAR(255),
    reportado_por INT REFERENCES usuarios(id_usuario),
    reportado_por_repartidor BOOLEAN DEFAULT FALSE,
    resuelto BOOLEAN DEFAULT FALSE,
    fecha_resolucion TIMESTAMP,
    resolucion TEXT,
    resuelto_por INT REFERENCES usuarios(id_usuario),
    afecta_calificacion BOOLEAN DEFAULT TRUE,
    compensacion_cliente NUMERIC(10,2) DEFAULT 0,
    compensacion_repartidor NUMERIC(10,2) DEFAULT 0,
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS calificaciones_entrega (
    id_calificacion SERIAL PRIMARY KEY,
    id_entrega INT REFERENCES entregas(id_entrega),
    id_cliente INT REFERENCES clientes(id_cliente),
    fecha_calificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    puntualidad INT CHECK (puntualidad BETWEEN 1 AND 5),
    atencion INT CHECK (atencion BETWEEN 1 AND 5),
    estado_producto INT CHECK (estado_producto BETWEEN 1 AND 5),
    presentacion INT CHECK (presentacion BETWEEN 1 AND 5),
    puntuacion_general INT CHECK (puntuacion_general BETWEEN 1 AND 5),
    comentario TEXT,
    comentario_publico BOOLEAN DEFAULT FALSE,
    recomendaria BOOLEAN DEFAULT TRUE,
    respuesta_negocio TEXT,
    fecha_respuesta TIMESTAMP,
    respondido_por INT REFERENCES usuarios(id_usuario),
    verificada BOOLEAN DEFAULT FALSE,
    fecha_verificacion TIMESTAMP,
    verificada_por INT REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS reportes_accidentes (
    id_reporte SERIAL PRIMARY KEY,
    id_incidencia INT REFERENCES incidencias_entrega(id_incidencia),
    id_repartidor INT REFERENCES repartidores(id_repartidor),
    fecha_accidente TIMESTAMP NOT NULL,
    ubicacion VARCHAR(200),
    latitud DECIMAL(10,8),
    longitud DECIMAL(11,8),
    descripcion TEXT NOT NULL,
    daños_repartidor TEXT,
    daños_vehiculo TEXT,
    daños_productos TEXT,
    daños_terceros TEXT,
    parte_policial_url VARCHAR(255),
    parte_medico_url VARCHAR(255),
    fotos_url TEXT[],
    seguro_activo BOOLEAN DEFAULT FALSE,
    numero_siniestro VARCHAR(50),
    compañia_seguro VARCHAR(100),
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_resolucion TIMESTAMP,
    resolucion TEXT,
    monto_compensado NUMERIC(10,2),
    reportado_por INT REFERENCES usuarios(id_usuario),
    fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bitacora_repartidor (
    id_bitacora SERIAL PRIMARY KEY,
    id_repartidor INT REFERENCES repartidores(id_repartidor),
    fecha DATE DEFAULT CURRENT_DATE,
    hora_inicio TIME,
    hora_fin TIME,
    kilometraje_inicio INT,
    kilometraje_fin INT,
    entregas_realizadas INT DEFAULT 0,
    entregas_fallidas INT DEFAULT 0,
    observaciones TEXT,
    creado_por INT REFERENCES usuarios(id_usuario),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS gastos_repartidor (
    id_gasto SERIAL PRIMARY KEY,
    id_repartidor INT REFERENCES repartidores(id_repartidor),
    fecha DATE DEFAULT CURRENT_DATE,
    tipo_gasto VARCHAR(50) NOT NULL,
    monto NUMERIC(10,2) NOT NULL,
    descripcion TEXT,
    comprobante_url VARCHAR(255),
    reembolsado BOOLEAN DEFAULT FALSE,
    fecha_reembolso TIMESTAMP,
    reembolsado_por INT REFERENCES usuarios(id_usuario),
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS horarios_repartidor (
    id_horario SERIAL PRIMARY KEY,
    id_repartidor INT REFERENCES repartidores(id_repartidor),
    dia_semana INT CHECK (dia_semana BETWEEN 1 AND 7),
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    observaciones TEXT
);

CREATE TABLE IF NOT EXISTS tracking_repartidor (
    id_tracking SERIAL PRIMARY KEY,
    id_repartidor INT REFERENCES repartidores(id_repartidor),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latitud DECIMAL(10,8) NOT NULL,
    longitud DECIMAL(11,8) NOT NULL,
    velocidad DECIMAL(5,2),
    bateria INT,
    fuente VARCHAR(20) DEFAULT 'GPS',
    id_entrega INT REFERENCES entregas(id_entrega)
);

CREATE TABLE IF NOT EXISTS notificaciones_cliente_entrega (
    id_notificacion SERIAL PRIMARY KEY,
    id_entrega INT REFERENCES entregas(id_entrega),
    id_cliente INT REFERENCES clientes(id_cliente),
    tipo VARCHAR(30) NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metodo VARCHAR(20) DEFAULT 'PUSH',
    estado_envio VARCHAR(20) DEFAULT 'ENVIADO',
    leida BOOLEAN DEFAULT FALSE,
    fecha_lectura TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tarifas_envio (
    id_tarifa SERIAL PRIMARY KEY,
    distancia_min_km NUMERIC(5,2) NOT NULL,
    distancia_max_km NUMERIC(5,2) NOT NULL,
    costo NUMERIC(10,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE
);

-- =============================================================================
-- 10. TABLAS DE CAJA, COMISIONES, MÉTRICAS, ALERTAS, DEVOLUCIONES, ATENCIÓN, NOTIFICACIONES, REPORTES
-- =============================================================================

CREATE TABLE IF NOT EXISTS caja (
    id_caja SERIAL PRIMARY KEY,
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    id_usuario INT REFERENCES usuarios(id_usuario),
    fecha_apertura TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre TIMESTAMP,
    monto_inicial NUMERIC(10,2) NOT NULL DEFAULT 0,
    monto_final NUMERIC(10,2),
    estado VARCHAR(20) DEFAULT 'ABIERTA',
    observaciones TEXT,
    observaciones_cierre TEXT
);

CREATE TABLE IF NOT EXISTS movimiento_caja (
    id_movimiento SERIAL PRIMARY KEY,
    id_caja INT REFERENCES caja(id_caja),
    tipo VARCHAR(20),
    monto NUMERIC(10,2) NOT NULL,
    concepto VARCHAR(200),
    id_venta INT REFERENCES ventas(id_venta),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS config_comisiones (
    id_config SERIAL PRIMARY KEY,
    id_rol INT REFERENCES roles(id_rol),
    porcentaje NUMERIC(5,2) NOT NULL,
    aplica_sobre VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS comisiones (
    id_comision SERIAL PRIMARY KEY,
    id_usuario INT REFERENCES usuarios(id_usuario),
    id_venta INT REFERENCES ventas(id_venta),
    monto_venta NUMERIC(10,2),
    porcentaje_aplicado NUMERIC(5,2),
    monto_comision NUMERIC(10,2),
    fecha_calculo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pagada BOOLEAN DEFAULT FALSE,
    fecha_pago TIMESTAMP
);

CREATE TABLE IF NOT EXISTS limite_descuento_rol (
    id_limite SERIAL PRIMARY KEY,
    id_rol INT REFERENCES roles(id_rol) ON DELETE CASCADE,
    porcentaje_maximo NUMERIC(5,2) NOT NULL,
    monto_maximo NUMERIC(10,2),
    requiere_aprobacion BOOLEAN DEFAULT FALSE,
    aprobador_requerido INT REFERENCES roles(id_rol),
    activo BOOLEAN DEFAULT TRUE,
    descripcion TEXT,
    creado_por INT REFERENCES usuarios(id_usuario),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_por INT REFERENCES usuarios(id_usuario),
    fecha_modificacion TIMESTAMP
);

CREATE TABLE IF NOT EXISTS historial_limites_descuento (
    id_historial SERIAL PRIMARY KEY,
    id_limite INT REFERENCES limite_descuento_rol(id_limite),
    id_rol INT REFERENCES roles(id_rol),
    porcentaje_anterior NUMERIC(5,2),
    porcentaje_nuevo NUMERIC(5,2),
    monto_anterior NUMERIC(10,2),
    monto_nuevo NUMERIC(10,2),
    requiere_aprobacion_anterior BOOLEAN,
    requiere_aprobacion_nuevo BOOLEAN,
    modificado_por INT REFERENCES usuarios(id_usuario),
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT
);

CREATE TABLE IF NOT EXISTS aprobacion_descuento (
    id_aprobacion SERIAL PRIMARY KEY,
    id_venta INT REFERENCES ventas(id_venta),
    id_descuento INT REFERENCES descuentos(id_descuento),
    id_solicitante INT REFERENCES usuarios(id_usuario),
    porcentaje_solicitado NUMERIC(5,2),
    monto_solicitado NUMERIC(10,2),
    motivo TEXT,
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_aprobador INT REFERENCES usuarios(id_usuario),
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_aprobacion TIMESTAMP,
    comentario_aprobador TEXT,
    CONSTRAINT chk_estado_aprobacion CHECK (estado IN ('PENDIENTE', 'APROBADO', 'RECHAZADO'))
);

CREATE TABLE IF NOT EXISTS metricas_diarias (
    fecha DATE PRIMARY KEY,
    total_ventas NUMERIC(12,2),
    total_compras NUMERIC(12,2),
    cantidad_ventas INT,
    cantidad_clientes_nuevos INT,
    total_gastos_delivery NUMERIC(10,2),
    promedio_ticket NUMERIC(10,2),
    tasa_conversion NUMERIC(5,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS alertas_sanitarias (
    id_alerta SERIAL PRIMARY KEY,
    numero_alerta VARCHAR(50) UNIQUE NOT NULL,
    fecha_notificacion DATE NOT NULL,
    fecha_publicacion DATE,
    entidad_emisora VARCHAR(150),
    descripcion TEXT,
    nivel_riesgo VARCHAR(20) DEFAULT 'ALTO',
    documento_url VARCHAR(255),
    creado_por INT REFERENCES usuarios(id_usuario),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'ACTIVA',
    CONSTRAINT chk_alerta_estado CHECK (estado IN ('ACTIVA', 'EN_INVESTIGACION', 'RESUELTA'))
);

CREATE TABLE IF NOT EXISTS alerta_lote (
    id_alerta INT REFERENCES alertas_sanitarias(id_alerta) ON DELETE CASCADE,
    id_lote INT REFERENCES lotes(id_lote) ON DELETE CASCADE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_alerta, id_lote)
);

CREATE TABLE IF NOT EXISTS tipo_devolucion (
    id_tipo SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
);

CREATE TABLE IF NOT EXISTS estado_devolucion (
    id_estado SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS devoluciones (
    id_devolucion SERIAL PRIMARY KEY,
    numero_documento VARCHAR(20) UNIQUE NOT NULL,
    id_venta INT REFERENCES ventas(id_venta),
    id_proveedor INT REFERENCES proveedores(id_proveedor),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion TIMESTAMP,
    fecha_completada TIMESTAMP,
    motivo TEXT,
    id_usuario INT REFERENCES usuarios(id_usuario),
    aprobado_por INT REFERENCES usuarios(id_usuario),
    id_tipo INT REFERENCES tipo_devolucion(id_tipo) DEFAULT 1,
    id_estado INT REFERENCES estado_devolucion(id_estado) DEFAULT 1,
    monto_reembolso NUMERIC(10,2),
    id_alerta INT REFERENCES alertas_sanitarias(id_alerta),
    es_por_recall BOOLEAN DEFAULT FALSE,
    id_cliente INT REFERENCES clientes(id_cliente)
);

CREATE TABLE IF NOT EXISTS detalle_devolucion (
    id_detalle SERIAL PRIMARY KEY,
    id_devolucion INT REFERENCES devoluciones(id_devolucion) ON DELETE CASCADE,
    id_lote INT REFERENCES lotes(id_lote),
    cantidad INT NOT NULL,
    precio_unitario NUMERIC(10,2)
);

CREATE TABLE IF NOT EXISTS reembolsos (
    id_reembolso SERIAL PRIMARY KEY,
    id_devolucion INT REFERENCES devoluciones(id_devolucion),
    id_venta INT REFERENCES ventas(id_venta),
    monto NUMERIC(10,2) NOT NULL,
    id_metodo_pago INT REFERENCES metodos_pago(id_metodo),
    fecha_reembolso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT REFERENCES usuarios(id_usuario),
    comprobante VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS casos_atencion_cliente (
    id_caso SERIAL PRIMARY KEY,
    id_cliente INT REFERENCES clientes(id_cliente),
    tipo VARCHAR(30),
    asunto VARCHAR(200),
    descripcion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20) DEFAULT 'ABIERTO',
    prioridad INT DEFAULT 1,
    asignado_a INT REFERENCES usuarios(id_usuario),
    fecha_resolucion TIMESTAMP,
    resolucion TEXT,
    calificacion_solucion INT CHECK (calificacion_solucion BETWEEN 1 AND 5)
);

CREATE TABLE IF NOT EXISTS plantillas_notificacion (
    id_plantilla SERIAL PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE,
    titulo VARCHAR(200),
    cuerpo TEXT,
    canal VARCHAR(20),
    variables JSONB
);

CREATE TABLE IF NOT EXISTS preferencias_notificacion_cliente (
    id_cliente INT REFERENCES clientes(id_cliente),
    canal VARCHAR(20),
    activo BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (id_cliente, canal)
);

CREATE TABLE IF NOT EXISTS notificaciones_sistema (
    id_notificacion SERIAL PRIMARY KEY,
    id_usuario INT REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(50) DEFAULT 'pago',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    leida BOOLEAN DEFAULT FALSE,
    fecha_lectura TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transacciones_pago (
    id_transaccion SERIAL PRIMARY KEY,
    id_venta INT REFERENCES ventas(id_venta),
    gateway VARCHAR(30),
    referencia_externa VARCHAR(100),
    monto NUMERIC(10,2),
    estado VARCHAR(20),
    fecha_transaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    respuesta_gateway JSONB
);

CREATE TABLE IF NOT EXISTS reportes_programados (
    id_reporte SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(30),
    parametros JSONB,
    formato VARCHAR(10),
    frecuencia VARCHAR(20),
    hora_programacion TIME,
    destinatarios TEXT[],
    ultima_ejecucion TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS historial_reportes (
    id_historial SERIAL PRIMARY KEY,
    id_reporte INT REFERENCES reportes_programados(id_reporte),
    fecha_ejecucion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(20),
    archivo_url VARCHAR(255),
    error_log TEXT
);

-- =============================================================================
-- 11. TABLAS ADICIONALES (AUDITORÍA, SESIONES, HISTORIAL DE ACCESOS, LOGS, VERSIONES, BACKUPS, CONFIGURACIONES)
-- =============================================================================

CREATE TABLE IF NOT EXISTS historial_accesos (
    id_historial SERIAL PRIMARY KEY,
    id_usuario INT REFERENCES usuarios(id_usuario),
    ventana VARCHAR(100),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45)
);

CREATE TABLE IF NOT EXISTS auditoria_cambios (
    id_auditoria SERIAL PRIMARY KEY,
    tabla_afectada VARCHAR(100) NOT NULL,
    id_registro INT NOT NULL,
    accion VARCHAR(20) NOT NULL,
    datos_anteriores JSONB,
    datos_nuevos JSONB,
    id_usuario INT REFERENCES usuarios(id_usuario),
    ip VARCHAR(45),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sesiones (
    id_sesion SERIAL PRIMARY KEY,
    id_usuario INT REFERENCES usuarios(id_usuario),
    token VARCHAR(255) UNIQUE,
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP,
    fecha_cierre TIMESTAMP,
    ip VARCHAR(45),
    user_agent TEXT,
    activa BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS logs_sistema (
    id_log SERIAL PRIMARY KEY,
    nivel VARCHAR(20),
    mensaje TEXT,
    contexto JSONB,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS modulos (
    id_modulo SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50),
    orden INT DEFAULT 0,
    padre_id INT REFERENCES modulos(id_modulo),
    ruta VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS permisos (
    id_permiso SERIAL PRIMARY KEY,
    id_modulo INT REFERENCES modulos(id_modulo),
    accion VARCHAR(20) NOT NULL,
    nombre VARCHAR(100),
    tipo_accion VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS rol_permiso (
    id_rol INT REFERENCES roles(id_rol),
    id_permiso INT REFERENCES permisos(id_permiso),
    PRIMARY KEY (id_rol, id_permiso)
);

CREATE TABLE IF NOT EXISTS usuario_permiso (
    id_usuario INT REFERENCES usuarios(id_usuario),
    id_permiso INT REFERENCES permisos(id_permiso),
    permitido BOOLEAN DEFAULT TRUE,
    PRIMARY KEY (id_usuario, id_permiso)
);

CREATE TABLE IF NOT EXISTS version_esquema (
    id_version SERIAL PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    fecha_aplicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    descripcion TEXT,
    script_aplicado TEXT
);

CREATE TABLE IF NOT EXISTS configuracion_backup (
    id_config SERIAL PRIMARY KEY,
    frecuencia VARCHAR(20),
    hora_programada TIME,
    ruta_destino VARCHAR(255),
    ultimo_backup TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS historial_backup (
    id_backup SERIAL PRIMARY KEY,
    fecha_backup TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo VARCHAR(20),
    tamano_bytes BIGINT,
    estado VARCHAR(20),
    error_log TEXT,
    realizado_por INT REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS config_itbis (
    id_config SERIAL PRIMARY KEY,
    porcentaje NUMERIC(5,2) NOT NULL DEFAULT 18.00,
    aplica_medicamentos_sin_receta BOOLEAN DEFAULT TRUE,
    aplica_medicamentos_con_receta BOOLEAN DEFAULT FALSE,
    aplica_ropa BOOLEAN DEFAULT TRUE,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE,
    activo BOOLEAN DEFAULT TRUE,
    modificado_por INT REFERENCES usuarios(id_usuario),
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- 12. TABLAS FALTANTES (ordenes_compra, detalle_orden_compra, intentos_login)
-- =============================================================================

CREATE TABLE IF NOT EXISTS ordenes_compra (
    id_orden SERIAL PRIMARY KEY,
    numero_orden VARCHAR(20) UNIQUE NOT NULL,
    fecha DATE NOT NULL,
    id_proveedor INT REFERENCES proveedores(id_proveedor),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    id_usuario INT REFERENCES usuarios(id_usuario),
    fecha_esperada DATE,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    observaciones TEXT,
    subtotal NUMERIC(12,2) DEFAULT 0,
    descuento NUMERIC(12,2) DEFAULT 0,
    itbis NUMERIC(12,2) DEFAULT 0,
    total NUMERIC(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS detalle_orden_compra (
    id_detalle SERIAL PRIMARY KEY,
    id_orden INT REFERENCES ordenes_compra(id_orden) ON DELETE CASCADE,
    id_medicamento INT REFERENCES medicamentos(id_medicamento),
    cantidad INT NOT NULL,
    precio_unitario NUMERIC(10,2) NOT NULL,
    descuento_unitario NUMERIC(10,2) DEFAULT 0,
    itbis_unitario NUMERIC(10,2) DEFAULT 0,
    subtotal NUMERIC(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS intentos_login (
    id_intento SERIAL PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    usuario_intentado VARCHAR(50) NOT NULL,
    fecha_intento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- 13. ÍNDICES (todos después de las tablas)
-- =============================================================================

CREATE INDEX IF NOT EXISTS idx_usuario_login ON usuarios(usuario);
CREATE INDEX IF NOT EXISTS idx_lotes_vencimiento ON lotes(fecha_vencimiento);
CREATE INDEX IF NOT EXISTS idx_lotes_estado ON lotes(estado);
CREATE INDEX IF NOT EXISTS idx_lotes_numero ON lotes(numero_lote);
CREATE INDEX IF NOT EXISTS idx_inventario_lote ON inventario(id_lote);
CREATE INDEX IF NOT EXISTS idx_movimiento_lote ON movimiento_inventario(id_lote);
CREATE INDEX IF NOT EXISTS idx_ventas_numero_doc ON ventas(numero_documento);
CREATE INDEX IF NOT EXISTS idx_ventas_fecha ON ventas(fecha);
CREATE INDEX IF NOT EXISTS idx_ventas_cliente ON ventas(id_cliente);
CREATE INDEX IF NOT EXISTS idx_ventas_credito ON ventas(es_credito, estado_pago);
CREATE INDEX IF NOT EXISTS idx_detalle_venta_venta ON detalle_venta(id_venta);
CREATE INDEX IF NOT EXISTS idx_pagos_venta ON pagos(id_venta);
CREATE INDEX IF NOT EXISTS idx_compras_numero_doc ON compras(numero_documento);
CREATE INDEX IF NOT EXISTS idx_devoluciones_numero_doc ON devoluciones(numero_documento);
CREATE INDEX IF NOT EXISTS idx_entregas_seguimiento ON entregas(numero_seguimiento);
CREATE INDEX IF NOT EXISTS idx_entregas_repartidor ON entregas(id_repartidor);
CREATE INDEX IF NOT EXISTS idx_entregas_estado ON entregas(id_estado);
CREATE INDEX IF NOT EXISTS idx_descuentos_fechas ON descuentos(fecha_inicio, fecha_fin);
CREATE INDEX IF NOT EXISTS idx_descuentos_activo ON descuentos(activo);
CREATE INDEX IF NOT EXISTS idx_cupones_codigo ON cupones(codigo);
CREATE INDEX IF NOT EXISTS idx_venta_descuento ON venta_descuento(id_venta);
CREATE INDEX IF NOT EXISTS idx_descuento_uso_cliente ON descuento_uso(id_cliente);
CREATE INDEX IF NOT EXISTS idx_alertas_lote ON alerta_lote(id_lote);
CREATE INDEX IF NOT EXISTS idx_tracking_repartidor_fecha ON tracking_repartidor(id_repartidor, fecha DESC);
CREATE INDEX IF NOT EXISTS idx_incidencias_entrega ON incidencias_entrega(id_entrega);
CREATE INDEX IF NOT EXISTS idx_calificaciones_cliente ON calificaciones_entrega(id_cliente);
CREATE INDEX IF NOT EXISTS idx_horarios_repartidor ON horarios_repartidor(id_repartidor, dia_semana);
CREATE INDEX IF NOT EXISTS idx_sesiones_token ON sesiones(token);
CREATE INDEX IF NOT EXISTS idx_auditoria_fecha ON auditoria_cambios(fecha);
CREATE INDEX IF NOT EXISTS idx_caja_estado ON caja(estado);
CREATE INDEX IF NOT EXISTS idx_casos_cliente ON casos_atencion_cliente(id_cliente, estado);
CREATE INDEX IF NOT EXISTS idx_telefonos_numero ON telefonos(numero);
CREATE INDEX IF NOT EXISTS idx_correos_email ON correos(email);
CREATE INDEX IF NOT EXISTS idx_sucursales_estado ON sucursales(estado);
CREATE INDEX IF NOT EXISTS idx_medicamentos_presentacion ON medicamentos(id_presentacion);
CREATE INDEX IF NOT EXISTS idx_medicamentos_concentracion ON medicamentos(concentracion);
CREATE INDEX IF NOT EXISTS idx_medicamentos_fecha_registro ON medicamentos(fecha_registro);
CREATE INDEX IF NOT EXISTS idx_medicamentos_categoria ON medicamentos(id_categoria);
CREATE INDEX IF NOT EXISTS idx_aseguradoras_nombre ON aseguradoras(nombre);
CREATE INDEX IF NOT EXISTS idx_aseguradoras_activo ON aseguradoras(activo);
CREATE INDEX IF NOT EXISTS idx_polizas_cliente ON polizas(id_cliente);
CREATE INDEX IF NOT EXISTS idx_polizas_aseguradora ON polizas(id_aseguradora);
CREATE INDEX IF NOT EXISTS idx_polizas_activo ON polizas(activo);
CREATE INDEX IF NOT EXISTS idx_cobertura_poliza ON cobertura_medicamentos(id_poliza);
CREATE INDEX IF NOT EXISTS idx_cobertura_medicamento ON cobertura_medicamentos(id_medicamento);
CREATE INDEX IF NOT EXISTS idx_autorizaciones_cliente ON autorizaciones_seguro(id_cliente);
CREATE INDEX IF NOT EXISTS idx_autorizaciones_estado ON autorizaciones_seguro(estado);
CREATE INDEX IF NOT EXISTS idx_pagos_aseguradora ON pagos(id_aseguradora);
CREATE INDEX IF NOT EXISTS idx_clientes_permite_credito ON clientes(permite_credito);
CREATE INDEX IF NOT EXISTS idx_clientes_saldo ON clientes(saldo_pendiente);
CREATE INDEX IF NOT EXISTS idx_ventas_vencimiento ON ventas(fecha_vencimiento_pago);
CREATE INDEX IF NOT EXISTS idx_historial_credito_cliente ON historial_credito_cliente(id_cliente, fecha_creacion);
CREATE INDEX IF NOT EXISTS idx_limites_credito_cliente ON limites_credito_cliente(id_cliente, activo);
CREATE INDEX IF NOT EXISTS idx_abonos_credito_venta ON abonos_credito(id_venta);
CREATE INDEX IF NOT EXISTS idx_historial_estado_lote ON historial_estado_lote(id_lote, fecha_cambio);
CREATE INDEX IF NOT EXISTS idx_notificaciones_usuario ON notificaciones_sistema(id_usuario, leida, fecha_creacion DESC);
CREATE INDEX IF NOT EXISTS idx_ordenes_compra_numero ON ordenes_compra(numero_orden);
CREATE INDEX IF NOT EXISTS idx_ordenes_compra_estado ON ordenes_compra(estado);
CREATE INDEX IF NOT EXISTS idx_ordenes_compra_proveedor ON ordenes_compra(id_proveedor);
CREATE INDEX IF NOT EXISTS idx_detalle_orden_compra_orden ON detalle_orden_compra(id_orden);
CREATE INDEX IF NOT EXISTS idx_intentos_usuario ON intentos_login(usuario_intentado);

-- Índices para direcciones (tablas puente)
CREATE INDEX IF NOT EXISTS idx_cliente_direccion ON cliente_direccion(id_cliente);
CREATE INDEX IF NOT EXISTS idx_proveedor_direccion ON proveedor_direccion(id_proveedor);
CREATE INDEX IF NOT EXISTS idx_sucursal_direccion ON sucursal_direccion(id_sucursal);
CREATE INDEX IF NOT EXISTS idx_empresa_direccion ON empresa_direccion(id_empresa);
CREATE INDEX IF NOT EXISTS idx_repartidor_direccion ON repartidor_direccion(id_repartidor);
CREATE INDEX IF NOT EXISTS idx_usuario_direccion ON usuario_direccion(id_usuario);
CREATE INDEX IF NOT EXISTS idx_direcciones_activo ON direcciones(activo);

-- =============================================================================
-- 14. FUNCIONES Y TRIGGERS (después de los índices)
-- =============================================================================

CREATE OR REPLACE FUNCTION registrar_movimiento_inventario(
    p_id_lote INT,
    p_id_sucursal INT,
    p_tipo VARCHAR,
    p_cantidad INT,
    p_motivo TEXT,
    p_referencia VARCHAR,
    p_id_usuario INT
) RETURNS VOID AS $$
BEGIN
    INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario)
    VALUES (p_id_lote, p_id_sucursal, p_tipo, p_cantidad, p_motivo, p_referencia, p_id_usuario);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION actualizar_stock_lote()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE lotes 
    SET cantidad_actual = (
        SELECT COALESCE(SUM(cantidad), 0) 
        FROM inventario 
        WHERE id_lote = COALESCE(NEW.id_lote, OLD.id_lote)
    )
    WHERE id_lote = COALESCE(NEW.id_lote, OLD.id_lote);
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_actualizar_stock_lote ON inventario;
CREATE TRIGGER tg_actualizar_stock_lote
AFTER INSERT OR UPDATE OR DELETE ON inventario
FOR EACH ROW EXECUTE FUNCTION actualizar_stock_lote();



-- =====================================================
-- CORRECCIÓN COMPLETA PARA COMPRAS (MEDICAMENTOS Y ROPA)
-- =====================================================

-- 1. Trigger para MEDICAMENTOS (solo cuando hay id_lote)
CREATE OR REPLACE FUNCTION trg_compra_actualiza_inventario()
RETURNS TRIGGER AS $$
DECLARE
    v_sucursal INT;
    v_usuario INT;
BEGIN
    -- Validar cantidad
    IF NEW.cantidad <= 0 THEN
        RAISE EXCEPTION 'Cantidad inválida en compra: %', NEW.cantidad;
    END IF;
    
    -- Obtener sucursal y usuario de la compra
    SELECT id_sucursal, id_usuario INTO v_sucursal, v_usuario 
    FROM compras WHERE id_compra = NEW.id_compra;
    
    -- Validar que el lote existe
    IF NOT EXISTS (SELECT 1 FROM lotes WHERE id_lote = NEW.id_lote) THEN
        RAISE EXCEPTION 'El lote con ID % no existe', NEW.id_lote;
    END IF;
    
    -- Solo registrar movimiento de auditoría (NO actualizar inventario)
    INSERT INTO movimiento_inventario (
        id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario
    ) VALUES (
        NEW.id_lote, v_sucursal, 'ENTRADA', NEW.cantidad, 
        'Compra de proveedor', NEW.id_compra::VARCHAR, v_usuario
    );
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Recrear trigger SOLO para medicamentos
DROP TRIGGER IF EXISTS tg_detalle_compra_ai ON detalle_compra;
CREATE TRIGGER tg_detalle_compra_ai 
AFTER INSERT ON detalle_compra
FOR EACH ROW
WHEN (NEW.id_lote IS NOT NULL)  -- ✅ Solo cuando hay lote (medicamentos)
EXECUTE FUNCTION trg_compra_actualiza_inventario();



DROP TRIGGER IF EXISTS tg_detalle_compra_ai ON detalle_compra;
CREATE TRIGGER tg_detalle_compra_ai AFTER INSERT ON detalle_compra
FOR EACH ROW EXECUTE FUNCTION trg_compra_actualiza_inventario();






-- =====================================================
-- CORRECCIÓN DEFINITIVA DEL PROBLEMA DE DOBLE RESTA
-- =====================================================

-- 1. Eliminar trigger problemático de medicamentos
DROP TRIGGER IF EXISTS tg_detalle_venta_ai ON detalle_venta;

-- 2. Modificar función de medicamentos para que NO actualice stock
CREATE OR REPLACE FUNCTION trg_venta_actualiza_inventario()
RETURNS TRIGGER AS $$
DECLARE
    v_usuario INT;
    v_sucursal INT;
    v_estado_lote VARCHAR(20);
    v_cantidad_actual INTEGER;
BEGIN
    IF NEW.cantidad <= 0 THEN
        RAISE EXCEPTION 'Cantidad inválida en venta';
    END IF;
    
    SELECT id_usuario, id_sucursal INTO v_usuario, v_sucursal 
    FROM ventas WHERE id_venta = NEW.id_venta;
    
    IF NEW.id_lote IS NOT NULL THEN
        -- Solo validar stock, NO actualizar (el PHP ya lo hace)
        SELECT l.estado, i.cantidad INTO v_estado_lote, v_cantidad_actual
        FROM lotes l
        JOIN inventario i ON l.id_lote = i.id_lote AND i.id_sucursal = v_sucursal
        WHERE l.id_lote = NEW.id_lote;
        
        IF v_estado_lote != 'ACTIVO' THEN
            RAISE EXCEPTION 'Lote no disponible (Estado: %)', v_estado_lote;
        END IF;
        
        IF v_cantidad_actual < NEW.cantidad THEN
            RAISE EXCEPTION 'Stock insuficiente. Disponible: %, requerido: %', 
                v_cantidad_actual, NEW.cantidad;
        END IF;
        
        -- Solo registrar movimiento de auditoría
        INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, referencia, id_usuario)
        VALUES (NEW.id_lote, v_sucursal, 'SALIDA', NEW.cantidad, 'Venta', NEW.id_venta::VARCHAR, v_usuario);
    END IF;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 3. Recrear el trigger (ahora solo valida, no actualiza stock)
CREATE TRIGGER tg_detalle_venta_ai 
AFTER INSERT ON detalle_venta
FOR EACH ROW
WHEN (NEW.id_lote IS NOT NULL)
EXECUTE FUNCTION trg_venta_actualiza_inventario();

-- 4. Verificar que todo está correcto
SELECT 
    tgname AS trigger_name,
    proname AS function_name,
    prosrc AS function_code
FROM pg_trigger t
JOIN pg_class c ON t.tgrelid = c.oid
LEFT JOIN pg_proc p ON t.tgfoid = p.oid
WHERE c.relname = 'detalle_venta'
AND tgname = 'tg_detalle_venta_ai';









-- =============================================================================
-- ELIMINAR TODOS LOS TRIGGERS EXISTENTES
-- =============================================================================
DROP TRIGGER IF EXISTS tg_detalle_venta_ai ON detalle_venta;
DROP TRIGGER IF EXISTS tg_detalle_venta_productos_ai ON detalle_venta;
DROP TRIGGER IF EXISTS tg_detalle_venta_ropa_ai ON detalle_venta;

-- =============================================================================
-- CREAR TRIGGER CORRECTO PARA MEDICAMENTOS (SOLO CON LOTE)
-- =============================================================================
CREATE TRIGGER tg_detalle_venta_ai 
AFTER INSERT ON detalle_venta
FOR EACH ROW
WHEN (NEW.id_lote IS NOT NULL)
EXECUTE FUNCTION trg_venta_actualiza_inventario();

-- =============================================================================
-- CREAR TRIGGER CORRECTO PARA ROPA (SOLO SIN LOTE)
-- =============================================================================
CREATE TRIGGER tg_detalle_venta_productos_ai 
AFTER INSERT ON detalle_venta
FOR EACH ROW
WHEN (NEW.id_lote IS NULL AND NEW.id_producto IS NOT NULL)
EXECUTE FUNCTION trg_venta_actualiza_inventario_productos();

-- =============================================================================
-- VERIFICAR LOS TRIGGERS EXISTENTES
-- =============================================================================
SELECT tgname, tgtype, tgfoid::regproc, tgenabled 
FROM pg_trigger 
WHERE tgrelid = 'detalle_venta'::regclass;













DROP TRIGGER IF EXISTS tg_detalle_venta_ai ON detalle_venta;
CREATE TRIGGER tg_detalle_venta_ai AFTER INSERT ON detalle_venta
FOR EACH ROW EXECUTE FUNCTION trg_venta_actualiza_inventario();

CREATE OR REPLACE FUNCTION trg_venta_recalcular_totales()
RETURNS TRIGGER AS $$
DECLARE
    v_subtotal NUMERIC;
    v_itbis NUMERIC;
BEGIN
    SELECT COALESCE(SUM(cantidad * precio_unitario - descuento_unitario), 0),
           COALESCE(SUM(itbis_unitario), 0)
    INTO v_subtotal, v_itbis
    FROM detalle_venta WHERE id_venta = NEW.id_venta;
    UPDATE ventas 
    SET subtotal = v_subtotal,
        itbis_total = v_itbis,
        total = v_subtotal - COALESCE(descuento_total, 0) + v_itbis
    WHERE id_venta = NEW.id_venta;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_venta_after_insert ON detalle_venta;
CREATE TRIGGER tg_venta_after_insert AFTER INSERT ON detalle_venta
FOR EACH ROW EXECUTE FUNCTION trg_venta_recalcular_totales();

CREATE OR REPLACE FUNCTION actualizar_nombre_completo()
RETURNS TRIGGER AS $$
BEGIN
    NEW.nombre_completo := TRIM(CONCAT_WS(' ', NEW.nombre, NEW.concentracion, 
        (SELECT abreviatura FROM unidades_medida WHERE id_unidad = NEW.id_unidad),
        (SELECT nombre FROM presentaciones WHERE id_presentacion = NEW.id_presentacion)));
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_actualizar_nombre_completo ON medicamentos;
CREATE TRIGGER trg_actualizar_nombre_completo
BEFORE INSERT OR UPDATE ON medicamentos
FOR EACH ROW EXECUTE FUNCTION actualizar_nombre_completo();

CREATE OR REPLACE FUNCTION asegurar_presentacion()
RETURNS TRIGGER AS $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM presentaciones WHERE id_presentacion = NEW.id_presentacion) THEN
        RAISE EXCEPTION 'La presentación con ID % no existe', NEW.id_presentacion;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_asegurar_presentacion ON medicamentos;
CREATE TRIGGER trg_asegurar_presentacion
BEFORE INSERT ON medicamentos
FOR EACH ROW EXECUTE FUNCTION asegurar_presentacion();

CREATE OR REPLACE FUNCTION actualizar_saldo_cliente()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' AND NEW.es_credito = TRUE THEN
        UPDATE clientes 
        SET saldo_pendiente = saldo_pendiente + (NEW.total - COALESCE(NEW.abonos_acumulados, 0)),
            fecha_ultima_compra_credito = NEW.fecha,
            dias_mora = CASE 
                WHEN NEW.fecha_vencimiento_pago < CURRENT_DATE 
                THEN CURRENT_DATE - NEW.fecha_vencimiento_pago 
                ELSE 0 
            END
        WHERE id_cliente = NEW.id_cliente;
        INSERT INTO historial_credito_cliente (
            id_cliente, id_venta, tipo_movimiento, monto, 
            saldo_anterior, saldo_nuevo, fecha_vencimiento, creado_por
        )
        SELECT 
            NEW.id_cliente, NEW.id_venta, 'CARGO', NEW.total,
            COALESCE((SELECT saldo_pendiente FROM clientes WHERE id_cliente = NEW.id_cliente) - NEW.total, 0),
            COALESCE((SELECT saldo_pendiente FROM clientes WHERE id_cliente = NEW.id_cliente), 0),
            NEW.fecha_vencimiento_pago, NEW.id_usuario;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_venta_actualiza_saldo_cliente ON ventas;
CREATE TRIGGER tg_venta_actualiza_saldo_cliente
AFTER INSERT ON ventas
FOR EACH ROW EXECUTE FUNCTION actualizar_saldo_cliente();

CREATE OR REPLACE FUNCTION actualizar_saldo_abono()
RETURNS TRIGGER AS $$
DECLARE
    v_id_cliente INT;
    v_total_venta NUMERIC;
    v_abonos_totales NUMERIC;
    v_saldo_anterior NUMERIC;
    v_nuevo_saldo NUMERIC;
BEGIN
    SELECT id_cliente, total, COALESCE(abonos_acumulados, 0) 
    INTO v_id_cliente, v_total_venta, v_abonos_totales
    FROM ventas WHERE id_venta = NEW.id_venta;
    v_saldo_anterior := v_total_venta - v_abonos_totales;
    v_nuevo_saldo := v_saldo_anterior - NEW.monto;
    UPDATE ventas 
    SET abonos_acumulados = abonos_acumulados + NEW.monto,
        estado_pago = CASE 
            WHEN abonos_acumulados + NEW.monto >= total THEN 'PAGADO'
            ELSE 'PARCIAL'
        END,
        fecha_pago_real = CASE 
            WHEN abonos_acumulados + NEW.monto >= total THEN CURRENT_TIMESTAMP
            ELSE fecha_pago_real
        END
    WHERE id_venta = NEW.id_venta;
    UPDATE clientes 
    SET saldo_pendiente = GREATEST(saldo_pendiente - NEW.monto, 0)
    WHERE id_cliente = v_id_cliente;
    INSERT INTO historial_credito_cliente (
        id_cliente, id_venta, tipo_movimiento, monto, 
        saldo_anterior, saldo_nuevo, fecha_pago, creado_por
    ) VALUES (
        v_id_cliente, NEW.id_venta, 'ABONO', NEW.monto,
        v_saldo_anterior, v_nuevo_saldo,
        CURRENT_TIMESTAMP, NEW.creado_por
    );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_abono_actualiza_saldo ON abonos_credito;
CREATE TRIGGER tg_abono_actualiza_saldo
AFTER INSERT ON abonos_credito
FOR EACH ROW EXECUTE FUNCTION actualizar_saldo_abono();

CREATE OR REPLACE FUNCTION validar_credito_cliente(
    p_id_cliente INT,
    p_monto NUMERIC
) RETURNS TABLE (
    permitido BOOLEAN,
    mensaje TEXT,
    saldo_actual NUMERIC,
    limite_disponible NUMERIC
) AS $$
DECLARE
    v_cliente RECORD;
    v_limite RECORD;
    v_config_activo BOOLEAN;
BEGIN
    SELECT valor::BOOLEAN INTO v_config_activo FROM configuracion_credito WHERE clave = 'credito_activo';
    IF NOT v_config_activo THEN
        RETURN QUERY SELECT FALSE, 'El sistema de crédito está desactivado'::TEXT, 0::NUMERIC, 0::NUMERIC;
        RETURN;
    END IF;
    SELECT * INTO v_cliente FROM clientes WHERE id_cliente = p_id_cliente;
    IF v_cliente.id_cliente IS NULL THEN
        RETURN QUERY SELECT FALSE, 'Cliente no encontrado'::TEXT, 0::NUMERIC, 0::NUMERIC;
        RETURN;
    END IF;
    IF NOT v_cliente.permite_credito THEN
        RETURN QUERY SELECT FALSE, 'Este cliente no tiene habilitado el crédito'::TEXT, v_cliente.saldo_pendiente, 0::NUMERIC;
        RETURN;
    END IF;
    SELECT * INTO v_limite FROM limites_credito_cliente WHERE id_cliente = p_id_cliente AND activo = TRUE;
    IF v_limite.id_limite IS NULL THEN
        SELECT valor::NUMERIC INTO v_limite.limite_maximo FROM configuracion_credito WHERE clave = 'limite_credito_default';
        v_limite.limite_utilizado := v_cliente.saldo_pendiente;
    END IF;
    IF (v_cliente.saldo_pendiente + p_monto) <= v_limite.limite_maximo THEN
        RETURN QUERY SELECT TRUE, 'Crédito disponible'::TEXT, v_cliente.saldo_pendiente, v_limite.limite_maximo - v_cliente.saldo_pendiente;
    ELSE
        RETURN QUERY SELECT FALSE, 'El cliente excede su límite de crédito. Límite: RD$ ' || 
            TO_CHAR(v_limite.limite_maximo, 'FM999,999,999.00') || 
            ', Saldo actual: RD$ ' || TO_CHAR(v_cliente.saldo_pendiente, 'FM999,999,999.00')::TEXT, 
            v_cliente.saldo_pendiente, v_limite.limite_maximo - v_cliente.saldo_pendiente;
    END IF;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION calcular_cobertura_seguro(
    p_id_cliente INT,
    p_id_medicamento INT,
    p_precio NUMERIC,
    p_cantidad INT
) RETURNS TABLE (
    cubre_seguro NUMERIC,
    paga_paciente NUMERIC,
    porcentaje_cobertura NUMERIC,
    requiere_autorizacion BOOLEAN,
    id_autorizacion_necesaria INT
) AS $$
DECLARE
    v_poliza RECORD;
    v_cobertura RECORD;
    v_autorizacion RECORD;
    v_monto_total NUMERIC;
BEGIN
    v_monto_total := p_precio * p_cantidad;
    SELECT p.*, a.requiere_autorizacion as aseguradora_requiere_autorizacion
    INTO v_poliza
    FROM polizas p
    JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
    WHERE p.id_cliente = p_id_cliente 
      AND p.activo = TRUE 
      AND CURRENT_DATE BETWEEN p.fecha_inicio AND p.fecha_fin
    LIMIT 1;
    IF v_poliza.id_poliza IS NULL THEN
        RETURN QUERY SELECT 0::NUMERIC, v_monto_total::NUMERIC, 0::NUMERIC, FALSE::BOOLEAN, NULL::INT;
        RETURN;
    END IF;
    SELECT * INTO v_cobertura FROM cobertura_medicamentos 
    WHERE id_poliza = v_poliza.id_poliza AND id_medicamento = p_id_medicamento AND activo = TRUE;
    IF v_cobertura.id_cobertura IS NOT NULL THEN
        IF v_cobertura.requiere_autorizacion THEN
            SELECT * INTO v_autorizacion FROM autorizaciones_seguro 
            WHERE id_cliente = p_id_cliente 
              AND id_medicamento = p_id_medicamento 
              AND id_poliza = v_poliza.id_poliza
              AND estado = 'APROBADO'
              AND (fecha_expiracion IS NULL OR fecha_expiracion >= CURRENT_DATE)
              AND (cantidad_usada + p_cantidad) <= cantidad_autorizada
            LIMIT 1;
            IF v_autorizacion.id_autorizacion IS NULL THEN
                RETURN QUERY SELECT 0::NUMERIC, v_monto_total::NUMERIC, 0::NUMERIC, TRUE::BOOLEAN, NULL::INT;
                RETURN;
            END IF;
            RETURN QUERY SELECT 
                ROUND((v_monto_total * v_cobertura.cobertura_porcentaje / 100), 2)::NUMERIC,
                ROUND((v_monto_total - (v_monto_total * v_cobertura.cobertura_porcentaje / 100)), 2)::NUMERIC,
                v_cobertura.cobertura_porcentaje::NUMERIC,
                FALSE::BOOLEAN,
                v_autorizacion.id_autorizacion::INT;
            RETURN;
        END IF;
        RETURN QUERY SELECT 
            ROUND((v_monto_total * v_cobertura.cobertura_porcentaje / 100), 2)::NUMERIC,
            ROUND((v_monto_total - (v_monto_total * v_cobertura.cobertura_porcentaje / 100)), 2)::NUMERIC,
            v_cobertura.cobertura_porcentaje::NUMERIC,
            FALSE::BOOLEAN,
            NULL::INT;
        RETURN;
    END IF;
    RETURN QUERY SELECT 
        ROUND((v_monto_total * COALESCE(v_poliza.cobertura_porcentaje, 
            (SELECT porcentaje_cobertura_default FROM aseguradoras WHERE id_aseguradora = v_poliza.id_aseguradora), 80) / 100), 2)::NUMERIC,
        ROUND((v_monto_total - (v_monto_total * COALESCE(v_poliza.cobertura_porcentaje, 
            (SELECT porcentaje_cobertura_default FROM aseguradoras WHERE id_aseguradora = v_poliza.id_aseguradora), 80) / 100)), 2)::NUMERIC,
        COALESCE(v_poliza.cobertura_porcentaje, 
            (SELECT porcentaje_cobertura_default FROM aseguradoras WHERE id_aseguradora = v_poliza.id_aseguradora), 80)::NUMERIC,
        v_poliza.aseguradora_requiere_autorizacion::BOOLEAN,
        NULL::INT;
    RETURN;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION calcular_descuentos_aplicables(
    p_id_cliente INT,
    p_subtotal NUMERIC,
    p_items JSONB
) RETURNS TABLE (
    id_descuento INT,
    nombre VARCHAR,
    monto NUMERIC,
    prioridad INT,
    combinable BOOLEAN
) AS $$
DECLARE
    v_descuento RECORD;
    v_monto_aplicable NUMERIC;
    v_total_descuentos NUMERIC := 0;
BEGIN
    FOR v_descuento IN 
        SELECT * FROM descuentos 
        WHERE activo = TRUE 
          AND CURRENT_DATE BETWEEN fecha_inicio AND fecha_fin
          AND (fecha_fin IS NULL OR fecha_fin >= CURRENT_DATE)
          AND (monto_minimo_compra IS NULL OR p_subtotal >= monto_minimo_compra)
          AND (limite_por_cliente IS NULL OR (
              SELECT COUNT(*) FROM descuento_uso 
              WHERE id_descuento = descuentos.id_descuento 
                AND id_cliente = p_id_cliente 
                AND fecha_uso >= CURRENT_DATE - INTERVAL '1 year'
          ) < limite_por_cliente)
        ORDER BY prioridad DESC, valor_descuento DESC
    LOOP
        IF v_descuento.segmento_cliente IS NOT NULL THEN
            IF NOT EXISTS (
                SELECT 1 FROM clientes c 
                JOIN niveles_cliente n ON c.id_nivel = n.id_nivel 
                WHERE c.id_cliente = p_id_cliente 
                  AND n.nombre = v_descuento.segmento_cliente
            ) THEN
                CONTINUE;
            END IF;
        END IF;
        IF v_descuento.es_porcentaje THEN
            v_monto_aplicable := p_subtotal * (v_descuento.valor_descuento / 100);
        ELSE
            v_monto_aplicable := v_descuento.valor_descuento;
        END IF;
        IF v_descuento.monto_maximo_descuento IS NOT NULL THEN
            v_monto_aplicable := LEAST(v_monto_aplicable, v_descuento.monto_maximo_descuento);
        END IF;
        IF NOT v_descuento.combinable AND v_total_descuentos > 0 THEN
            CONTINUE;
        END IF;
        v_total_descuentos := v_total_descuentos + v_monto_aplicable;
        RETURN QUERY SELECT 
            v_descuento.id_descuento,
            v_descuento.nombre,
            v_monto_aplicable,
            v_descuento.prioridad,
            v_descuento.combinable;
    END LOOP;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION obtener_repartidores_disponibles()
RETURNS TABLE (
    id_repartidor INT,
    nombre VARCHAR,
    entregas_activas INT,
    ultima_entrega TIMESTAMP,
    telefono VARCHAR
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        r.id_repartidor,
        r.nombre,
        COUNT(CASE WHEN e.id_estado NOT IN (4, 5, 7) THEN 1 END)::INT as entregas_activas,
        MAX(e.fecha_asignada) as ultima_entrega,
        t.numero as telefono
    FROM repartidores r
    LEFT JOIN entregas e ON r.id_repartidor = e.id_repartidor 
        AND e.fecha_pedido > CURRENT_DATE - INTERVAL '1 day'
    LEFT JOIN repartidor_telefono rt ON r.id_repartidor = rt.id_repartidor
    LEFT JOIN telefonos t ON rt.id_telefono = t.id_telefono AND t.activo = TRUE
    WHERE r.activo = TRUE
    GROUP BY r.id_repartidor, r.nombre, t.numero
    ORDER BY entregas_activas ASC, ultima_entrega ASC NULLS FIRST;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION generar_alertas_inventario()
RETURNS TABLE (
    tipo_alerta VARCHAR(50),
    medicamento_nombre VARCHAR(200),
    numero_lote VARCHAR(50),
    cantidad INT,
    stock_minimo INT,
    fecha_vencimiento DATE,
    dias_restantes INT,
    nivel_riesgo VARCHAR(20),
    entidad_emisora VARCHAR(150),
    numero_alerta VARCHAR(50),
    url_destino VARCHAR(100)
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        'VENCIMIENTO'::VARCHAR(50),
        m.nombre_completo::VARCHAR(200),
        l.numero_lote::VARCHAR(50),
        NULL::INT, NULL::INT, l.fecha_vencimiento,
        (l.fecha_vencimiento - CURRENT_DATE)::INT, NULL, NULL, NULL,
        'vencimientos'::VARCHAR(100)
    FROM lotes l
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    WHERE l.estado = 'ACTIVO' AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'
    ORDER BY l.fecha_vencimiento ASC LIMIT 20;
    
    RETURN QUERY
    SELECT 
        'STOCK_CRITICO'::VARCHAR(50),
        m.nombre_completo::VARCHAR(200),
        l.numero_lote::VARCHAR(50),
        i.cantidad::INT, m.stock_minimo::INT,
        NULL::DATE, NULL, NULL, NULL, NULL,
        'alertas_stock'::VARCHAR(100)
    FROM inventario i
    JOIN lotes l ON i.id_lote = l.id_lote
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    WHERE l.estado = 'ACTIVO' AND i.cantidad <= m.stock_minimo AND i.cantidad > 0
    ORDER BY i.cantidad ASC LIMIT 20;
    
    RETURN QUERY
    SELECT 
        'STOCK_AGOTADO'::VARCHAR(50),
        m.nombre_completo::VARCHAR(200),
        l.numero_lote::VARCHAR(50),
        i.cantidad::INT, m.stock_minimo::INT,
        NULL::DATE, NULL, NULL, NULL, NULL,
        'alertas_stock'::VARCHAR(100)
    FROM inventario i
    JOIN lotes l ON i.id_lote = l.id_lote
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    WHERE l.estado = 'ACTIVO' AND i.cantidad = 0
    ORDER BY m.nombre ASC LIMIT 20;
    
    RETURN QUERY
    SELECT 
        'RECALL'::VARCHAR(50),
        a.descripcion::VARCHAR(200),
        NULL::VARCHAR(50),
        NULL::INT, NULL::INT, NULL::DATE, NULL,
        a.nivel_riesgo::VARCHAR(20),
        a.entidad_emisora::VARCHAR(150),
        a.numero_alerta::VARCHAR(50),
        'recall'::VARCHAR(100)
    FROM alertas_sanitarias a
    WHERE a.estado = 'ACTIVA'
    ORDER BY a.fecha_notificacion DESC LIMIT 20;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION set_audit_vars(p_id_usuario INT, p_ip VARCHAR)
RETURNS VOID AS $$
BEGIN
    PERFORM set_config('myapp.id_usuario', p_id_usuario::TEXT, false);
    PERFORM set_config('myapp.ip_address', p_ip, false);
END;
$$ LANGUAGE plpgsql;

-- =============================================================================
-- 15. VISTAS
-- =============================================================================

CREATE OR REPLACE VIEW vista_inventario_actual AS
SELECT 
    l.numero_lote, l.fecha_vencimiento, l.estado,
    m.nombre AS medicamento, m.concentracion, u.abreviatura AS unidad,
    p.nombre AS presentacion, c.nombre AS categoria,
    i.cantidad,
    CASE 
        WHEN l.fecha_vencimiento < CURRENT_DATE THEN 'VENCIDO'
        WHEN l.fecha_vencimiento <= CURRENT_DATE + INTERVAL '30 days' THEN 'PROXIMO A VENCER'
        ELSE 'OK'
    END AS alerta_vencimiento,
    CASE 
        WHEN i.cantidad <= 5 THEN 'CRITICO'
        WHEN i.cantidad <= 10 THEN 'BAJO'
        ELSE 'NORMAL'
    END AS alerta_stock
FROM inventario i
JOIN lotes l ON i.id_lote = l.id_lote
JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
LEFT JOIN categorias c ON m.id_categoria = c.id_categoria;

CREATE OR REPLACE VIEW vista_medicamentos_completo AS
SELECT 
    m.id_medicamento, m.nombre, m.concentracion,
    u.nombre as unidad, u.abreviatura,
    p.nombre as presentacion, c.nombre as categoria,
    l.nombre as laboratorio, m.requiere_receta, m.exento_itbis,
    prod.precio,
    CONCAT_WS(' ', m.nombre, m.concentracion, u.abreviatura, p.nombre) as nombre_completo,
    m.stock_minimo, m.stock_maximo, m.punto_reorden, m.fecha_registro
FROM medicamentos m
LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
LEFT JOIN categorias c ON m.id_categoria = c.id_categoria
LEFT JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
LEFT JOIN productos prod ON m.id_producto = prod.id_producto;

CREATE OR REPLACE VIEW vista_clientes_credito AS
SELECT 
    c.id_cliente, c.nombre, c.permite_credito, c.saldo_pendiente,
    COALESCE(lc.limite_maximo, (SELECT valor::NUMERIC FROM configuracion_credito WHERE clave = 'limite_credito_default')) AS limite_maximo,
    COALESCE(lc.limite_maximo, (SELECT valor::NUMERIC FROM configuracion_credito WHERE clave = 'limite_credito_default')) - c.saldo_pendiente AS limite_disponible,
    c.fecha_ultima_compra_credito, c.dias_mora
FROM clientes c
LEFT JOIN limites_credito_cliente lc ON c.id_cliente = lc.id_cliente AND lc.activo = TRUE
WHERE c.permite_credito = TRUE;

CREATE OR REPLACE VIEW vista_ventas_credito_pendientes AS
SELECT 
    v.id_venta, v.numero_documento, v.fecha, v.total, v.abonos_acumulados,
    v.total - v.abonos_acumulados AS saldo_pendiente,
    v.fecha_vencimiento_pago, v.estado_pago,
    CASE 
        WHEN v.fecha_vencimiento_pago < CURRENT_DATE AND v.estado_pago != 'PAGADO' 
        THEN CURRENT_DATE - v.fecha_vencimiento_pago 
        ELSE 0 
    END AS dias_mora,
    c.id_cliente, c.nombre AS cliente_nombre, u.nombre AS vendedor_nombre
FROM ventas v
JOIN clientes c ON v.id_cliente = c.id_cliente
JOIN usuarios u ON v.id_usuario = u.id_usuario
WHERE v.es_credito = TRUE AND v.estado_pago != 'PAGADO'
ORDER BY v.fecha_vencimiento_pago ASC;

CREATE OR REPLACE VIEW vista_clientes_seguro AS
SELECT 
    c.id_cliente, c.nombre, c.tiene_seguro,
    p.id_poliza, p.numero_poliza,
    a.id_aseguradora, a.nombre AS aseguradora,
    a.porcentaje_cobertura_default, p.cobertura_porcentaje,
    p.fecha_inicio, p.fecha_fin
FROM clientes c
LEFT JOIN polizas p ON c.id_cliente = p.id_cliente AND p.activo = TRUE
LEFT JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
WHERE c.tiene_seguro = TRUE;

CREATE OR REPLACE VIEW vista_contactos_clientes AS
SELECT 
    c.id_cliente, c.nombre,
    t.numero AS telefono, t.tipo AS tipo_telefono, t.whatsapp,
    cor.email, cor.tipo AS tipo_email
FROM clientes c
LEFT JOIN cliente_telefono ct ON c.id_cliente = ct.id_cliente
LEFT JOIN telefonos t ON ct.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN cliente_correo cc ON c.id_cliente = cc.id_cliente
LEFT JOIN correos cor ON cc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_proveedores AS
SELECT 
    p.id_proveedor, p.nombre,
    t.numero AS telefono, t.tipo AS tipo_telefono,
    cor.email AS email, cor.tipo AS tipo_email
FROM proveedores p
LEFT JOIN proveedor_telefono pt ON p.id_proveedor = pt.id_proveedor
LEFT JOIN telefonos t ON pt.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN proveedor_correo pc ON p.id_proveedor = pc.id_proveedor
LEFT JOIN correos cor ON pc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_usuarios AS
SELECT 
    u.id_usuario, u.nombre, u.usuario,
    t.numero AS telefono, cor.email AS email
FROM usuarios u
LEFT JOIN usuario_telefono ut ON u.id_usuario = ut.id_usuario
LEFT JOIN telefonos t ON ut.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN usuario_correo uc ON u.id_usuario = uc.id_usuario
LEFT JOIN correos cor ON uc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_repartidores AS
SELECT 
    r.id_repartidor, r.nombre, r.telefono_emergencia,
    t.numero AS telefono, t.tipo AS tipo_telefono,
    cor.email AS email
FROM repartidores r
LEFT JOIN repartidor_telefono rt ON r.id_repartidor = rt.id_repartidor
LEFT JOIN telefonos t ON rt.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN repartidor_correo rc ON r.id_repartidor = rc.id_repartidor
LEFT JOIN correos cor ON rc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_empresa AS
SELECT 
    e.id_empresa, e.nombre,
    t.numero AS telefono, cor.email AS email
FROM empresa e
LEFT JOIN empresa_telefono et ON e.id_empresa = et.id_empresa
LEFT JOIN telefonos t ON et.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN empresa_correo ec ON e.id_empresa = ec.id_empresa
LEFT JOIN correos cor ON ec.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_presentaciones_completo AS
SELECT 
    p.id_presentacion, p.nombre as presentacion, p.descripcion, p.activo, p.fecha_registro,
    u.id_unidad, u.nombre as unidad, u.abreviatura,
    COUNT(DISTINCT m.id_medicamento) as total_medicamentos,
    array_agg(DISTINCT c.id_categoria) as categorias_ids,
    array_agg(DISTINCT c.nombre) as categorias_nombres
FROM presentaciones p
LEFT JOIN unidades_medida u ON p.id_unidad = u.id_unidad
LEFT JOIN categoria_presentacion cp ON p.id_presentacion = cp.id_presentacion
LEFT JOIN categorias c ON cp.id_categoria = c.id_categoria
LEFT JOIN medicamentos m ON p.id_presentacion = m.id_presentacion
GROUP BY p.id_presentacion, p.nombre, p.descripcion, p.activo, p.fecha_registro, u.id_unidad, u.nombre, u.abreviatura
ORDER BY p.nombre;

-- =============================================================================
-- 16. DATOS DE PRUEBA (con ON CONFLICT)
-- =============================================================================

-- GUARDA DE RE-EJECUCIÓN: todo este bloque de datos de ejemplo asume una
-- base de datos recién creada (usa IDs 1,2,3... a mano para relacionar
-- clientes, ventas, lotes, etc. entre sí). Por eso NO se puede volver a
-- correr fila por fila con ON CONFLICT como el resto del script: al
-- reinsertar, los IDs nuevos (autogenerados) ya no coinciden con los IDs
-- viejos, así que en vez de omitir la fila, o la duplica o choca contra
-- una restricción UNIQUE real (nombre, código, número de documento, etc.).
-- Se soluciona ejecutando este bloque UNA sola vez: se usa version_esquema
-- como marca de que esta base ya tiene los datos de ejemplo cargados.
DO $$
BEGIN
IF NOT EXISTS (SELECT 1 FROM version_esquema WHERE version = '3.0.0') THEN

INSERT INTO unidades_medida (nombre, abreviatura) VALUES
('Miligramos', 'mg'), ('Gramos', 'g'), ('Mililitros', 'ml'), ('Tabletas', 'tab'), ('Cápsulas', 'cap')
ON CONFLICT (id_unidad) DO NOTHING;

INSERT INTO presentaciones (nombre, id_unidad, activo) VALUES
('Tabletas', 4, TRUE), ('Cápsulas', 5, TRUE), ('Jarabe', 3, TRUE), ('Inyección', 3, TRUE), ('Gotas', 3, TRUE)
ON CONFLICT (id_presentacion) DO NOTHING;

INSERT INTO categorias (nombre, activo) VALUES
('Analgésicos', TRUE), ('Antibióticos', TRUE), ('Vitaminas', TRUE), ('Jarabes', TRUE), ('Antialérgicos', TRUE)
ON CONFLICT (id_categoria) DO NOTHING;

INSERT INTO categoria_presentacion (id_categoria, id_presentacion)
SELECT c.id_categoria, p.id_presentacion
FROM (VALUES (1,1),(1,2),(1,5),(2,1),(2,2),(2,3),(3,1),(3,2),(3,5),(4,3),(5,1),(5,2)) AS tmp(cat_id, pres_id)
JOIN categorias c ON c.id_categoria = tmp.cat_id
JOIN presentaciones p ON p.id_presentacion = tmp.pres_id
WHERE NOT EXISTS (SELECT 1 FROM categoria_presentacion WHERE id_categoria = c.id_categoria AND id_presentacion = p.id_presentacion);

INSERT INTO telefonos (numero, tipo, whatsapp) VALUES
('8091110000', 'PRINCIPAL', TRUE), ('8092220000', 'PRINCIPAL', FALSE),
('8093330000', 'PRINCIPAL', FALSE), ('8094440000', 'TRABAJO', FALSE), ('8095550000', 'PRINCIPAL', TRUE)
ON CONFLICT (id_telefono) DO NOTHING;

INSERT INTO correos (email, tipo, verificado) VALUES
('pedro@mail.com', 'PRINCIPAL', TRUE), ('laura@mail.com', 'PRINCIPAL', TRUE),
('miguel@mail.com', 'PRINCIPAL', FALSE), ('ana@farmacia.com', 'TRABAJO', TRUE), ('admin@farmacia.com', 'PRINCIPAL', TRUE)
ON CONFLICT (id_correo) DO NOTHING;

INSERT INTO empresa (nombre, rnc, direccion) VALUES ('Farmacia Salud+', '101010101', 'Av Central #1')
ON CONFLICT (id_empresa) DO NOTHING;

INSERT INTO empresa_telefono (id_empresa, id_telefono) VALUES (1,4) ON CONFLICT DO NOTHING;
INSERT INTO empresa_correo (id_empresa, id_correo) VALUES (1,5) ON CONFLICT DO NOTHING;

INSERT INTO sucursales (id_empresa, nombre, direccion, telefono, estado) VALUES
(1, 'Sucursal Principal', 'Calle 10, Edificio A', '809-555-0001', TRUE)
ON CONFLICT (id_sucursal) DO NOTHING;

INSERT INTO roles (nombre, descripcion) VALUES
('Administrador', 'Acceso total'), ('Cajero', 'Realiza ventas'),
('Inventario', 'Gestiona stock'), ('Supervisor', 'Revisa reportes'), ('Soporte', 'Mantenimiento')
ON CONFLICT (id_rol) DO NOTHING;

INSERT INTO usuarios (nombre, usuario, contrasena, id_rol, id_sucursal, imagen_url) VALUES
('Administrador General', 'admin', 'admin123', 1, 1, '/sistema-gestor-de-farmacias/assets/img/usuarios/69d18f4aeede4_admin.png'),
('Ana Lopez', 'ana', '1234', 4, 1, NULL), ('Carlos Ruiz', 'carlos', '1234', 5, 1, NULL),
('Maria Gomez', 'maria', '1234', 2, 1, NULL), ('Luis Torres', 'luis', '1234', 3, 1, NULL)
ON CONFLICT (id_usuario) DO NOTHING;

INSERT INTO usuario_telefono (id_usuario, id_telefono) VALUES (1,4), (2,5) ON CONFLICT DO NOTHING;
INSERT INTO usuario_correo (id_usuario, id_correo) VALUES (1,5), (2,4) ON CONFLICT DO NOTHING;

INSERT INTO laboratorios (nombre, pais) VALUES
('Laboratorios ABC', 'República Dominicana'),
('Pharma International', 'Estados Unidos'),
('Medicamentos del Caribe', 'República Dominicana')
ON CONFLICT (id_laboratorio) DO NOTHING;

INSERT INTO proveedores (nombre, direccion, rnc) VALUES
('Distribuidora ABC', 'Av. Principal #123', '101010101'),
('FarmaSupply', 'Calle Secundaria #45', '202020202')
ON CONFLICT (id_proveedor) DO NOTHING;

INSERT INTO proveedor_telefono (id_proveedor, id_telefono) VALUES (1,4), (2,5) ON CONFLICT DO NOTHING;
INSERT INTO proveedor_correo (id_proveedor, id_correo) VALUES (1,4), (2,5) ON CONFLICT DO NOTHING;

INSERT INTO medicamentos (nombre, concentracion, descripcion, id_categoria, requiere_receta, id_unidad, id_laboratorio, exento_itbis, fecha_registro, id_presentacion) VALUES
('Paracetamol', '500', 'Dolor y fiebre', 1, false, 1, 1, false, CURRENT_TIMESTAMP, 1),
('Paracetamol', '250', 'Dolor y fiebre', 1, false, 1, 1, false, CURRENT_TIMESTAMP, 1),
('Ibuprofeno', '400', 'Antiinflamatorio', 1, false, 1, 2, false, CURRENT_TIMESTAMP, 1),
('Amoxicilina', '500', 'Antibiótico', 2, true, 1, 3, true, CURRENT_TIMESTAMP, 1),
('Amoxicilina', '250', 'Antibiótico', 2, true, 1, 3, true, CURRENT_TIMESTAMP, 1),
('Vitamina C', '1000', 'Suplemento vitamínico', 3, false, 1, 1, false, CURRENT_TIMESTAMP, 1),
('Loratadina', '10', 'Antialérgico', 5, false, 1, 2, false, CURRENT_TIMESTAMP, 1)
ON CONFLICT (id_medicamento) DO NOTHING;

INSERT INTO productos (nombre, tipo_producto, precio, exento_itbis) VALUES
('Paracetamol 500mg', 'MEDICAMENTO', 150.00, false),
('Paracetamol 250mg', 'MEDICAMENTO', 80.00, false),
('Ibuprofeno 400mg', 'MEDICAMENTO', 180.00, false),
('Amoxicilina 500mg', 'MEDICAMENTO', 250.00, true),
('Amoxicilina 250mg', 'MEDICAMENTO', 150.00, true),
('Vitamina C 1000mg', 'MEDICAMENTO', 120.00, false),
('Loratadina 10mg', 'MEDICAMENTO', 100.00, false)
ON CONFLICT (id_producto) DO NOTHING;

UPDATE medicamentos SET id_producto = 1 WHERE id_medicamento = 1 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 2 WHERE id_medicamento = 2 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 3 WHERE id_medicamento = 3 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 4 WHERE id_medicamento = 4 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 5 WHERE id_medicamento = 5 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 6 WHERE id_medicamento = 6 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 7 WHERE id_medicamento = 7 AND id_producto IS NULL;

INSERT INTO niveles_cliente (nombre, puntos_minimos, descuento_porcentaje) VALUES
('BRONCE', 0, 0), ('PLATA', 500, 5), ('ORO', 1500, 10), ('PLATINO', 5000, 15)
ON CONFLICT (id_nivel) DO NOTHING;

INSERT INTO clientes (nombre, direccion, barrio, id_nivel, permite_credito, tiene_seguro) VALUES
('Pedro Martinez', 'Calle 1 #23', 'Los Jardines', 1, TRUE, TRUE),
('Laura Diaz', 'Av. Independencia #45', 'Centro', 2, TRUE, TRUE),
('Miguel Santos', 'Calle 2 #67', 'Bella Vista', 1, FALSE, FALSE)
ON CONFLICT (id_cliente) DO NOTHING;

-- Insertar direcciones genéricas y asociarlas a clientes
INSERT INTO direcciones (direccion, barrio, ciudad) VALUES
('Calle 1 #23', 'Los Jardines', 'Santiago'),
('Av. Las Carreras #45', 'Centro', 'Santiago'),
('Av. Independencia #45', 'Centro', 'Santiago'),
('Calle 2 #67', 'Bella Vista', 'Santiago')
ON CONFLICT (id_direccion) DO NOTHING;

-- Asociar direcciones a clientes
INSERT INTO cliente_direccion (id_cliente, id_direccion, predeterminada) VALUES
(1, 1, TRUE),
(1, 2, FALSE),
(2, 3, TRUE),
(3, 4, TRUE)
ON CONFLICT DO NOTHING;

UPDATE clientes SET id_nivel = 1 WHERE id_nivel IS NULL;

INSERT INTO cliente_telefono (id_cliente, id_telefono) VALUES (1,1), (2,2), (3,3) ON CONFLICT DO NOTHING;
INSERT INTO cliente_correo (id_cliente, id_correo) VALUES (1,1), (2,2), (3,3) ON CONFLICT DO NOTHING;

INSERT INTO tarjetas_fidelidad (id_cliente, codigo, puntos_acumulados) VALUES
(1, 'TARJ-001', 150), (2, 'TARJ-002', 650), (3, 'TARJ-003', 50)
ON CONFLICT (id_tarjeta) DO NOTHING;

INSERT INTO estado_compra (nombre) VALUES ('PENDIENTE'), ('ENVIADA'), ('PARCIAL'), ('COMPLETADA'), ('ANULADA')
ON CONFLICT (id_estado) DO NOTHING;

INSERT INTO condicion_pago (nombre, dias_plazo) VALUES ('Contado', 0), ('15 días', 15), ('30 días', 30)
ON CONFLICT (id_condicion) DO NOTHING;

INSERT INTO metodos_pago (nombre) VALUES ('Efectivo'), ('Tarjeta'), ('Transferencia')
ON CONFLICT (id_metodo) DO NOTHING;

INSERT INTO tipo_devolucion (nombre) VALUES ('CLIENTE'), ('PROVEEDOR'), ('MERMA'), ('AJUSTE')
ON CONFLICT (id_tipo) DO NOTHING;

INSERT INTO estado_devolucion (nombre) VALUES ('SOLICITADA'), ('APROBADA'), ('RECHAZADA'), ('COMPLETADA'), ('ANULADA')
ON CONFLICT (id_estado) DO NOTHING;

INSERT INTO repartidores (nombre, telefono_emergencia, tipo_identificacion, numero_identificacion, licencia_conducir) VALUES
('Juan Pérez', '8097770000', 'CEDULA', '001-1234567-8', 'LIC-001'),
('Carlos Gómez', '8097770000', 'CEDULA', '002-7654321-9', 'LIC-002')
ON CONFLICT (id_repartidor) DO NOTHING;

INSERT INTO repartidor_telefono (id_repartidor, id_telefono) VALUES (1,1), (2,2) ON CONFLICT DO NOTHING;
INSERT INTO repartidor_correo (id_repartidor, id_correo) VALUES (1,1), (2,2) ON CONFLICT DO NOTHING;

INSERT INTO vehiculos (id_repartidor, tipo, placa, marca, modelo) VALUES
(1, 'MOTO', 'M001-ABC', 'Yamaha', 'FZ16'), (2, 'BICICLETA', NULL, 'GW', 'Ranger')
ON CONFLICT (id_vehiculo) DO NOTHING;

INSERT INTO horarios_repartidor (id_repartidor, dia_semana, hora_inicio, hora_fin) VALUES
(1,1,'08:00','17:00'),(1,2,'08:00','17:00'),(1,3,'08:00','17:00'),(1,4,'08:00','17:00'),(1,5,'08:00','17:00'),
(2,1,'09:00','18:00'),(2,2,'09:00','18:00'),(2,3,'09:00','18:00'),(2,4,'09:00','18:00'),(2,5,'09:00','18:00')
ON CONFLICT (id_horario) DO NOTHING;

INSERT INTO tarifas_envio (distancia_min_km, distancia_max_km, costo) VALUES
(0,2,50.00), (2,5,80.00), (5,10,120.00), (10,999,150.00)
ON CONFLICT (id_tarifa) DO NOTHING;

INSERT INTO tipo_descuento (nombre, descripcion, codigo_interno) VALUES
('PORCENTAJE', 'Descuento porcentual', 'PCT'),
('MONTO_FIJO', 'Descuento fijo', 'FIX'),
('X_Y', 'Lleva X paga Y', 'X_Y'),
('VOLUMEN', 'Descuento por cantidad', 'VOL'),
('ESPECIAL', 'Promociones especiales', 'SPC')
ON CONFLICT (id_tipo) DO NOTHING;

INSERT INTO limite_descuento_rol (id_rol, porcentaje_maximo, monto_maximo, requiere_aprobacion, aprobador_requerido, descripcion, creado_por) VALUES
(1, 100.00, NULL, FALSE, NULL, 'Administrador', 1),
(4, 20.00, 5000.00, FALSE, 1, 'Supervisor hasta 20%', 1),
(2, 10.00, 2000.00, TRUE, 4, 'Cajero hasta 10%', 1),
(3, 5.00, 1000.00, FALSE, NULL, 'Inventario hasta 5%', 1),
(5, 0.00, 0.00, FALSE, NULL, 'Soporte sin descuentos', 1)
ON CONFLICT (id_limite) DO NOTHING;

INSERT INTO descuentos (codigo, nombre, descripcion, id_tipo_descuento, valor_descuento, es_porcentaje,
    fecha_inicio, fecha_fin, aplica_todas_categorias, activo, combinable, creado_por) VALUES 
('VIT10', '10% OFF Vitaminas', '10% descuento en vitaminas', 1, 10, TRUE, '2026-01-01', '2026-12-31', TRUE, TRUE, TRUE, 1),
('DIA15', '15% OFF Día', '15% descuento en toda la tienda', 1, 15, TRUE, '2026-01-01', '2026-12-31', TRUE, TRUE, FALSE, 1),
('BIENVENIDA', 'Bienvenida', '20% primera compra', 1, 20, TRUE, '2026-01-01', '2026-12-31', TRUE, TRUE, TRUE, 1)
ON CONFLICT (id_descuento) DO NOTHING;

INSERT INTO descuento_categoria (id_descuento, id_categoria) VALUES (1,3) ON CONFLICT DO NOTHING;

INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, estado, costo_unitario, cantidad_inicial, cantidad_actual) VALUES
(1, 'L001', '2027-01-01', 'ACTIVO', 100.00, 100, 100),
(3, 'L002', '2027-02-01', 'ACTIVO', 120.00, 50, 50),
(4, 'L003', '2026-12-01', 'ACTIVO', 180.00, 30, 30)
ON CONFLICT (id_lote) DO NOTHING;

INSERT INTO compras (numero_documento, id_proveedor, id_usuario, id_sucursal, id_estado, subtotal, total) VALUES
('COMP-0001', 1, 1, 1, 4, 500.00, 590.00),
('COMP-0002', 2, 2, 1, 4, 480.00, 566.40)
ON CONFLICT (id_compra) DO NOTHING;

INSERT INTO detalle_compra (id_compra, id_lote, cantidad, precio_unitario) VALUES
(1, 1, 50, 10.00), (1, 2, 40, 12.00), (2, 3, 30, 25.00)
ON CONFLICT (id_detalle) DO NOTHING;

INSERT INTO inventario (id_lote, id_sucursal, cantidad) VALUES
(1, 1, 100), (2, 1, 50), (3, 1, 30)
ON CONFLICT (id_lote, id_sucursal) DO NOTHING;

INSERT INTO ventas (numero_documento, id_usuario, id_cliente, id_condicion, id_sucursal, subtotal, itbis_total, total) VALUES
('FAC-0001', 4, 1, 1, 1, 60.00, 10.80, 70.80),
('FAC-0002', 4, 2, 1, 1, 18.00, 3.24, 21.24)
ON CONFLICT (id_venta) DO NOTHING;

INSERT INTO detalle_venta (id_venta, id_lote, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 2, 15.00, 30.00), (1, 2, 1, 18.00, 18.00), (2, 2, 1, 18.00, 18.00)
ON CONFLICT (id_detalle) DO NOTHING;

INSERT INTO pagos (id_venta, id_metodo, monto) VALUES (1, 1, 70.80), (2, 2, 21.24)
ON CONFLICT (id_pago) DO NOTHING;

INSERT INTO acumulacion_puntos (id_venta, id_cliente, puntos_ganados) VALUES (1, 1, 70.80), (2, 2, 21.24)
ON CONFLICT (id_acumulacion) DO NOTHING;

INSERT INTO devoluciones (numero_documento, id_venta, id_sucursal, id_usuario, id_tipo, id_estado, motivo, monto_reembolso)
VALUES ('DEV-0001', 1, 1, 4, 1, 4, 'Producto defectuoso', 15.00)
ON CONFLICT (id_devolucion) DO NOTHING;

INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario) VALUES (1, 1, 1, 15.00)
ON CONFLICT (id_detalle) DO NOTHING;

INSERT INTO reembolsos (id_devolucion, id_venta, monto, id_metodo_pago, id_usuario) VALUES (1, 1, 15.00, 1, 4)
ON CONFLICT (id_reembolso) DO NOTHING;

INSERT INTO estado_entrega (nombre) VALUES
('PENDIENTE'), ('ASIGNADA'), ('EN_CAMINO'), ('ENTREGADA'), ('CANCELADA'), ('REPROGRAMADA'), ('FALLIDA'), ('ACCIDENTE')
ON CONFLICT (id_estado) DO NOTHING;

INSERT INTO tipo_incidencia_delivery (nombre, descripcion, nivel_gravedad) VALUES
('CLIENTE AUSENTE', 'Cliente no estaba', 2),
('DIRECCION INCORRECTA', 'Dirección mal', 2),
('RETRASO', 'Entrega con retraso', 1),
('PRODUCTO DAÑADO', 'Producto dañado', 3),
('ACCIDENTE', 'Accidente del repartidor', 5)
ON CONFLICT (id_tipo_incidencia) DO NOTHING;

INSERT INTO entregas (id_venta, id_cliente, id_sucursal, numero_seguimiento, direccion_entrega, barrio_entrega, costo_entrega, creado_por, distancia_km) VALUES
(1, 1, 1, 'DEL-20250315-001', 'Calle 1 #23', 'Los Jardines', 100.00, 4, 3.5)
ON CONFLICT (id_entrega) DO NOTHING;

INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, estado, costo_unitario, cantidad_inicial, cantidad_actual) VALUES 
(1, 'LOTE-VENCIDO-99', CURRENT_DATE - INTERVAL '10 days', 'VENCIDO', 100.00, 50, 0),
(2, 'LOTE-PROX-88', CURRENT_DATE + INTERVAL '5 days', 'ACTIVO', 120.00, 25, 0),
(3, 'LOTE-STOCK-77', CURRENT_DATE + INTERVAL '1 year', 'ACTIVO', 180.00, 200, 200)
ON CONFLICT (id_lote) DO NOTHING;

INSERT INTO inventario (id_lote, id_sucursal, cantidad) SELECT id_lote, 1, cantidad_actual FROM lotes WHERE numero_lote = 'LOTE-STOCK-77' ON CONFLICT DO NOTHING;

INSERT INTO configuracion_credito (clave, valor, descripcion) VALUES
('credito_activo', 'true', 'Activar crédito'),
('limite_credito_default', '5000', 'Límite por defecto')
ON CONFLICT (id_config) DO NOTHING;

INSERT INTO limites_credito_cliente (id_cliente, limite_maximo, dias_plazo_maximo) VALUES (1, 10000.00, 30), (2, 5000.00, 15)
ON CONFLICT (id_limite) DO NOTHING;

INSERT INTO aseguradoras (codigo, nombre, porcentaje_cobertura_default) VALUES
('ARS001', 'ARS Humano', 80.00), ('ARS002', 'ARS Universal', 75.00), ('ARS003', 'ARS Palic', 85.00)
ON CONFLICT (id_aseguradora) DO NOTHING;

INSERT INTO polizas (id_cliente, id_aseguradora, numero_poliza, numero_carnet, fecha_inicio, fecha_fin, cobertura_porcentaje, activo) VALUES
(1, 1, 'POL-001-2025', 'CARNET-001', '2025-01-01', '2027-12-31', 80.00, TRUE),
(2, 2, 'POL-002-2025', 'CARNET-002', '2025-01-01', '2027-12-31', 75.00, TRUE)
ON CONFLICT (id_poliza) DO NOTHING;

INSERT INTO version_esquema (version, descripcion) VALUES ('3.0.0', 'Versión completa con lotes corregidos')
ON CONFLICT (id_version) DO NOTHING;

END IF;
END $$;

-- =============================================================================
-- 17. ACTUALIZACIONES FINALES
-- =============================================================================

UPDATE medicamentos SET nombre_completo = TRIM(CONCAT_WS(' ', nombre, concentracion, 
    (SELECT abreviatura FROM unidades_medida WHERE id_unidad = medicamentos.id_unidad),
    (SELECT nombre FROM presentaciones WHERE id_presentacion = medicamentos.id_presentacion)))
WHERE nombre_completo IS NULL;

ALTER TABLE lotes DROP CONSTRAINT IF EXISTS chk_cantidades;
ALTER TABLE lotes DROP CONSTRAINT IF EXISTS chk_cantidad_actual_positiva;
ALTER TABLE lotes ADD CONSTRAINT chk_cantidad_actual_positiva CHECK (cantidad_actual >= 0);

ALTER TABLE detalle_compra ALTER COLUMN id_lote DROP NOT NULL;
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS id_medicamento INT REFERENCES medicamentos(id_medicamento);
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS cantidad_recibida INT DEFAULT 0;
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS numero_lote_solicitado VARCHAR(50);
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS fecha_vencimiento_solicitada DATE;

UPDATE detalle_compra SET cantidad_recibida = cantidad WHERE id_lote IS NOT NULL;

INSERT INTO tipo_devolucion (id_tipo, nombre) VALUES 
(3, 'Dañado'),
(4, 'Vencido'),
(5, 'Otro')
ON CONFLICT (id_tipo) DO NOTHING;

ALTER TABLE roles ADD COLUMN IF NOT EXISTS estado BOOLEAN DEFAULT TRUE;
-- =============================================================================
-- FIN DEL SCRIPT


-- =============================================================================
-- PATCH 1/2 — MÓDULO DE DELIVERY (rol Repartidor, agenda, despacho, conciliación)
-- =============================================================================

-- ============================================================
-- PATCH COMPLETO — Módulo de Delivery SGF
-- Ejecutar TODO de una vez en pgAdmin (Query Tool → Run F5)
-- ============================================================

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 1: ROL Y VINCULACIÓN
-- ─────────────────────────────────────────────────────────────

INSERT INTO roles (nombre, descripcion)
VALUES ('Repartidor', 'Personal de entrega a domicilio')
ON CONFLICT (nombre) DO NOTHING;

ALTER TABLE repartidores
    ADD COLUMN IF NOT EXISTS id_usuario INTEGER REFERENCES usuarios(id_usuario) ON DELETE SET NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_repartidor_usuario
    ON repartidores(id_usuario) WHERE id_usuario IS NOT NULL;

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 2: MEJORAS A TABLAS EXISTENTES
-- ─────────────────────────────────────────────────────────────

ALTER TABLE entregas
    ADD COLUMN IF NOT EXISTS nombre_receptor_autorizado VARCHAR(150),
    ADD COLUMN IF NOT EXISTS cedula_receptor_autorizado VARCHAR(20),
    ADD COLUMN IF NOT EXISTS nombre_quien_recibe        VARCHAR(150),
    ADD COLUMN IF NOT EXISTS estado_recepcion           VARCHAR(20) DEFAULT 'PENDIENTE'
        CHECK (estado_recepcion IN ('PENDIENTE','CONFIRMADO','EN_DISPUTA'));

COMMENT ON COLUMN entregas.nombre_receptor_autorizado IS
    'Persona autorizada por el cliente para recibir el pedido si el no esta disponible';
COMMENT ON COLUMN entregas.cedula_receptor_autorizado IS
    'Cedula de la persona autorizada, para verificacion por el repartidor';
COMMENT ON COLUMN entregas.estado_recepcion IS
    'PENDIENTE=sin confirmar, CONFIRMADO=receptor correcto, EN_DISPUTA=receptor no coincide';

ALTER TABLE calificaciones_entrega
    ADD COLUMN IF NOT EXISTS token              VARCHAR(64) UNIQUE,
    ADD COLUMN IF NOT EXISTS token_usado        BOOLEAN     DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS token_expira_en    TIMESTAMP,
    ADD COLUMN IF NOT EXISTS rapidez            SMALLINT    CHECK (rapidez BETWEEN 1 AND 5),
    ADD COLUMN IF NOT EXISTS amabilidad         SMALLINT    CHECK (amabilidad BETWEEN 1 AND 5),
    ADD COLUMN IF NOT EXISTS estado_pedido_cal  SMALLINT    CHECK (estado_pedido_cal BETWEEN 1 AND 5),
    ADD COLUMN IF NOT EXISTS fecha_calificacion TIMESTAMP;

COMMENT ON COLUMN calificaciones_entrega.token IS
    'Token unico generado al confirmar la entrega, enviado al cliente por WhatsApp/SMS';
COMMENT ON COLUMN calificaciones_entrega.token_expira_en IS
    'El link de calificacion expira 48 horas despues de generado';

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 3: TABLAS NUEVAS DEL MÓDULO DELIVERY
-- ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS agenda_delivery (
    id_agenda            SERIAL PRIMARY KEY,
    id_repartidor        INT  NOT NULL REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    id_vehiculo          INT  REFERENCES vehiculos(id_vehiculo),
    fecha                DATE NOT NULL DEFAULT CURRENT_DATE,
    hora_inicio          TIME,
    hora_fin_estimada    TIME,
    estado               VARCHAR(20) NOT NULL DEFAULT 'PROGRAMADA'
                         CHECK (estado IN ('PROGRAMADA','EN_CURSO','FINALIZADA','CANCELADA')),
    total_entregas       INT  DEFAULT 0,
    entregas_completadas INT  DEFAULT 0,
    entregas_fallidas    INT  DEFAULT 0,
    observaciones        TEXT,
    creado_por           INT  REFERENCES usuarios(id_usuario),
    fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_agenda_repartidor_dia UNIQUE (id_repartidor, fecha)
);

CREATE TABLE IF NOT EXISTS agenda_delivery_entrega (
    id_detalle           SERIAL PRIMARY KEY,
    id_agenda            INT  NOT NULL REFERENCES agenda_delivery(id_agenda) ON DELETE CASCADE,
    id_entrega           INT  NOT NULL REFERENCES entregas(id_entrega),
    orden_visita         INT  NOT NULL DEFAULT 1,
    hora_estimada        TIME,
    estado               VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                         CHECK (estado IN ('PENDIENTE','EN_CAMINO','COMPLETADA','FALLIDA','OMITIDA')),
    fecha_actualizacion  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones        TEXT,
    CONSTRAINT uq_agenda_entrega UNIQUE (id_agenda, id_entrega)
);

CREATE TABLE IF NOT EXISTS despacho_entrega (
    id_despacho          SERIAL PRIMARY KEY,
    id_entrega           INT  NOT NULL REFERENCES entregas(id_entrega) ON DELETE CASCADE,
    id_usuario_cajero    INT  NOT NULL REFERENCES usuarios(id_usuario),
    fecha_despacho       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observaciones        TEXT,
    CONSTRAINT uq_despacho_entrega UNIQUE (id_entrega)
);

CREATE TABLE IF NOT EXISTS detalle_despacho (
    id_detalle           SERIAL PRIMARY KEY,
    id_despacho          INT  NOT NULL REFERENCES despacho_entrega(id_despacho) ON DELETE CASCADE,
    id_lote              INT  REFERENCES lotes(id_lote),
    id_producto          INT  REFERENCES productos(id_producto),
    cantidad_despachada  INT  NOT NULL CHECK (cantidad_despachada > 0),
    observaciones        TEXT,
    CONSTRAINT chk_det_despacho_origen
        CHECK (id_lote IS NOT NULL OR id_producto IS NOT NULL)
);

CREATE TABLE IF NOT EXISTS conciliacion_entrega (
    id_conciliacion          SERIAL PRIMARY KEY,
    id_entrega               INT  NOT NULL REFERENCES entregas(id_entrega) ON DELETE CASCADE,
    fecha_conciliacion       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tiene_diferencia         BOOLEAN NOT NULL DEFAULT FALSE,
    observaciones_sistema    TEXT,
    observaciones_repartidor TEXT,
    validado_por             INT  REFERENCES usuarios(id_usuario),
    fecha_validacion         TIMESTAMP,
    estado                   VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE'
                             CHECK (estado IN ('PENDIENTE','VALIDADO','RECHAZADO','REQUERIDO_AJUSTE')),
    CONSTRAINT uq_conciliacion_entrega UNIQUE (id_entrega)
);

CREATE TABLE IF NOT EXISTS detalle_conciliacion (
    id_detalle           SERIAL PRIMARY KEY,
    id_conciliacion      INT  NOT NULL REFERENCES conciliacion_entrega(id_conciliacion) ON DELETE CASCADE,
    id_lote              INT  REFERENCES lotes(id_lote),
    id_producto          INT  REFERENCES productos(id_producto),
    cantidad_despachada  INT  NOT NULL DEFAULT 0,
    cantidad_entregada   INT  NOT NULL DEFAULT 0,
    diferencia           INT  GENERATED ALWAYS AS (cantidad_despachada - cantidad_entregada) STORED,
    motivo_diferencia    TEXT,
    CONSTRAINT chk_det_concil_origen
        CHECK (id_lote IS NOT NULL OR id_producto IS NOT NULL)
);

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 4: ÍNDICES DE PERFORMANCE
-- ─────────────────────────────────────────────────────────────

CREATE INDEX IF NOT EXISTS idx_agenda_rep_fecha      ON agenda_delivery(id_repartidor, fecha DESC);
CREATE INDEX IF NOT EXISTS idx_agenda_estado         ON agenda_delivery(estado);
CREATE INDEX IF NOT EXISTS idx_agenda_ent_agenda     ON agenda_delivery_entrega(id_agenda);
CREATE INDEX IF NOT EXISTS idx_agenda_ent_entrega    ON agenda_delivery_entrega(id_entrega);
CREATE INDEX IF NOT EXISTS idx_agenda_ent_estado     ON agenda_delivery_entrega(estado);
CREATE INDEX IF NOT EXISTS idx_despacho_entrega      ON despacho_entrega(id_entrega);
CREATE INDEX IF NOT EXISTS idx_despacho_cajero       ON despacho_entrega(id_usuario_cajero);
CREATE INDEX IF NOT EXISTS idx_det_despacho          ON detalle_despacho(id_despacho);
CREATE INDEX IF NOT EXISTS idx_concil_entrega        ON conciliacion_entrega(id_entrega);
CREATE INDEX IF NOT EXISTS idx_concil_estado         ON conciliacion_entrega(estado);
CREATE INDEX IF NOT EXISTS idx_det_concil            ON detalle_conciliacion(id_conciliacion);
CREATE INDEX IF NOT EXISTS idx_calificacion_token    ON calificaciones_entrega(token) WHERE token IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_entregas_estado_rec   ON entregas(estado_recepcion);

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 5: VINCULAR REPARTIDOR A USUARIO (ejecutar manualmente
-- despues de crear el usuario con rol Repartidor)
-- ─────────────────────────────────────────────────────────────
-- UPDATE repartidores
--     SET id_usuario = (SELECT id_usuario FROM usuarios WHERE usuario = 'nombre_usuario')
--     WHERE id_repartidor = X;

-- ============================================================
-- RESUMEN:
--   Tablas nuevas   : 6  (agenda_delivery, agenda_delivery_entrega,
--                         despacho_entrega, detalle_despacho,
--                         conciliacion_entrega, detalle_conciliacion)
--   Tablas alteradas: 3  (repartidores, entregas, calificaciones_entrega)
--   Indices creados : 13
-- ============================================================

-- =============================================================================
-- PATCH 2/2 — REDISEÑO DELIVERY: estados nuevos, vehículos, habilidades,
-- receta médica y tipo de despacho en facturación
-- =============================================================================

-- ============================================================
-- PATCH v2 — Rediseño módulo Delivery + Receta + Despacho SGF
-- Ejecutar DESPUÉS de Farmacia.sql y del patch v1 (si ya lo corriste)
-- Seguro de re-ejecutar (usa IF NOT EXISTS / ON CONFLICT en todo)
-- ============================================================

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 1: Nuevos estados de entrega (se agregan a los 8 que
-- ya existen: PENDIENTE, ASIGNADA, EN_CAMINO, ENTREGADA,
-- CANCELADA, REPROGRAMADA, FALLIDA, ACCIDENTE)
-- ─────────────────────────────────────────────────────────────
INSERT INTO estado_entrega (nombre) VALUES
    ('INTERRUMPIDA'),  -- accidente, robo, o cualquier interrupción en el camino
    ('PARCIAL')        -- se entregó solo una parte del pedido
ON CONFLICT (nombre) DO NOTHING;

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 2: Estado granular de vehículos
-- ─────────────────────────────────────────────────────────────
ALTER TABLE vehiculos
    ADD COLUMN IF NOT EXISTS estado VARCHAR(20) NOT NULL DEFAULT 'DISPONIBLE'
        CHECK (estado IN ('DISPONIBLE','EN_USO','DAÑADO','TALLER','INACTIVO'));

-- Backfill: si activo=false, marcar como INACTIVO
UPDATE vehiculos SET estado = 'INACTIVO' WHERE activo = FALSE AND estado = 'DISPONIBLE';

COMMENT ON COLUMN vehiculos.estado IS
    'Estado operativo del vehiculo. EN_USO se actualiza automaticamente al asignar/cerrar una entrega.';

CREATE INDEX IF NOT EXISTS idx_vehiculos_estado ON vehiculos(estado);

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 3: Habilidades del repartidor por tipo de vehículo
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS repartidor_habilidad (
    id_habilidad     SERIAL PRIMARY KEY,
    id_repartidor    INT NOT NULL REFERENCES repartidores(id_repartidor) ON DELETE CASCADE,
    tipo_vehiculo    VARCHAR(50) NOT NULL,
    nivel            VARCHAR(20) DEFAULT 'COMPETENTE'
                     CHECK (nivel IN ('BASICO','COMPETENTE','EXPERTO')),
    CONSTRAINT uq_repartidor_tipo UNIQUE (id_repartidor, tipo_vehiculo)
);
CREATE INDEX IF NOT EXISTS idx_hab_repartidor ON repartidor_habilidad(id_repartidor);

COMMENT ON TABLE repartidor_habilidad IS
    'Tipos de vehiculo que cada repartidor esta habilitado para manejar (Motocicleta, Carro, Bicicleta, etc.)';

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 4: Facturación — despacho, receta médica
-- ─────────────────────────────────────────────────────────────
ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS tipo_despacho       VARCHAR(20)
        CHECK (tipo_despacho IN ('RETIRO_PERSONAL','DELIVERY')),
    ADD COLUMN IF NOT EXISTS con_receta          BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS receta_imagen_path  VARCHAR(255);

COMMENT ON COLUMN ventas.tipo_despacho IS
    'RETIRO_PERSONAL = cliente se lo llevo en el momento. DELIVERY = se genero registro en tabla entregas.';
COMMENT ON COLUMN ventas.con_receta IS
    'TRUE si la venta incluye al menos un medicamento que requiere receta (medicamentos.requiere_receta)';
COMMENT ON COLUMN ventas.receta_imagen_path IS
    'Ruta del archivo de la foto de la receta adjunta, si aplica';

CREATE INDEX IF NOT EXISTS idx_ventas_despacho ON ventas(tipo_despacho);

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 5: Entregas — interrupción y entrega parcial
-- ─────────────────────────────────────────────────────────────
ALTER TABLE entregas
    ADD COLUMN IF NOT EXISTS motivo_interrupcion TEXT,
    ADD COLUMN IF NOT EXISTS es_entrega_parcial  BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS detalle_parcial     TEXT,
    ADD COLUMN IF NOT EXISTS id_vehiculo         INT REFERENCES vehiculos(id_vehiculo);

COMMENT ON COLUMN entregas.id_vehiculo IS
    'Vehiculo especifico usado en esta entrega (para saber que vehiculo esta ocupado y por quien)';

COMMENT ON COLUMN entregas.motivo_interrupcion IS
    'Descripcion libre: accidente, robo, vehiculo dañado en ruta, etc. Se llena cuando id_estado = INTERRUMPIDA';
COMMENT ON COLUMN entregas.detalle_parcial IS
    'Que se entrego y que no, cuando id_estado = PARCIAL';

-- ─────────────────────────────────────────────────────────────
-- SECCIÓN 6: Datos de ejemplo — habilidades (ajustar IDs si aplica)
-- ─────────────────────────────────────────────────────────────
-- Ejemplo: repartidor 1 puede manejar motocicleta y carro
-- INSERT INTO repartidor_habilidad (id_repartidor, tipo_vehiculo) VALUES
--     (1, 'Motocicleta'), (1, 'Carro')
-- ON CONFLICT (id_repartidor, tipo_vehiculo) DO NOTHING;

-- ============================================================
-- RESUMEN:
--   Tablas nuevas    : 1  (repartidor_habilidad)
--   Tablas alteradas : 4  (estado_entrega, vehiculos, ventas, entregas)
--   Estados nuevos   : 2  (INTERRUMPIDA, PARCIAL)
-- ============================================================


-- =============================================================================
-- PATCH 3/3 — CATÁLOGO DE MOTIVOS DE ENTREGA FALLIDA
-- =============================================================================

-- ============================================================
-- PATCH — Catálogo de motivos de entrega fallida
-- Ejecutar despues de Farmacia_FINAL.sql (aditivo, seguro re-ejecutar)
-- ============================================================

CREATE TABLE IF NOT EXISTS motivo_fallida (
    id_motivo   SERIAL PRIMARY KEY,
    nombre      VARCHAR(100) UNIQUE NOT NULL,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    orden       INT DEFAULT 0
);

INSERT INTO motivo_fallida (nombre, orden) VALUES
    ('Cliente ausente',                          1),
    ('Cliente rechazó el pedido',                 2),
    ('Cliente no contesta el teléfono',            3),
    ('Dirección incorrecta o no encontrada',       4),
    ('Zona de difícil acceso o insegura',          5),
    ('Producto dañado en tránsito',                6),
    ('Vehículo averiado',                          7),
    ('Accidente de tránsito',                      8),
    ('Robo o asalto',                              9),
    ('Pedido cancelado por el cliente',           10),
    ('Horario de entrega vencido',                11),
    ('Otro',                                      99)
ON CONFLICT (nombre) DO NOTHING;

ALTER TABLE entregas
    ADD COLUMN IF NOT EXISTS id_motivo_fallida INT REFERENCES motivo_fallida(id_motivo);

COMMENT ON COLUMN entregas.id_motivo_fallida IS
    'Motivo catalogado de por que la entrega no se pudo completar (solo aplica cuando el estado es FALLIDA). El detalle libre adicional sigue guardandose en observaciones.';

CREATE INDEX IF NOT EXISTS idx_entregas_motivo_fallida ON entregas(id_motivo_fallida);


-- =============================================================================
-- PATCH 4/4 — LICENCIA POR TIPO DE VEHÍCULO (una licencia distinta por cada
-- tipo que el repartidor esté habilitado a manejar)
-- =============================================================================

-- ============================================================
-- PATCH — Licencia por tipo de vehículo (una licencia distinta
-- por cada tipo que el repartidor esté habilitado a manejar)
-- Ejecutar después de Farmacia_FINAL.sql (aditivo, seguro re-ejecutar)
-- ============================================================

ALTER TABLE repartidor_habilidad
    ADD COLUMN IF NOT EXISTS numero_licencia            VARCHAR(50),
    ADD COLUMN IF NOT EXISTS fecha_vencimiento_licencia  DATE;

COMMENT ON COLUMN repartidor_habilidad.numero_licencia IS
    'Número de la licencia específica para este tipo de vehículo (cada categoría —motocicleta, carro, camión— es una licencia distinta)';
COMMENT ON COLUMN repartidor_habilidad.fecha_vencimiento_licencia IS
    'Vencimiento de esa licencia específica, para poder alertar cuando esté por vencer';

-- Nota: repartidores.licencia_conducir y repartidores.fecha_vencimiento_licencia
-- (las columnas genéricas de antes) se dejan intactas por compatibilidad,
-- pero ya no se usan — ahora la licencia vive por tipo de vehículo en
-- repartidor_habilidad.


-- =============================================================================
-- PATCH 5/5 — ESTADO LABORAL DEL REPARTIDOR (vacaciones, licencia médica,
-- hospitalizado, luto, otro — más allá de activo/inactivo)
-- =============================================================================

-- ============================================================
-- PATCH — Estado laboral del repartidor (más allá de activo/inactivo)
-- Ejecutar después de Farmacia_FINAL.sql (aditivo, seguro re-ejecutar)
-- ============================================================

ALTER TABLE repartidores
    ADD COLUMN IF NOT EXISTS estado_laboral VARCHAR(30) NOT NULL DEFAULT 'ACTIVO'
        CHECK (estado_laboral IN ('ACTIVO','VACACIONES','LICENCIA_MEDICA','HOSPITALIZADO','LUTO','OTRO'));

COMMENT ON COLUMN repartidores.estado_laboral IS
    'Estado laboral actual del repartidor. "activo" (booleano) sigue significando si sigue perteneciendo a la empresa; este campo describe su disponibilidad temporal aunque siga activo.';

CREATE INDEX IF NOT EXISTS idx_repartidores_estado_laboral ON repartidores(estado_laboral);


-- =============================================================================
-- PATCH 6/6 — NORMALIZAR TIPOS DE VEHÍCULO EXISTENTES (MOTO -> Motocicleta,
-- para que coincidan con el combobox de licencias)
-- =============================================================================

-- ============================================================
-- PATCH — Normalizar tipos de vehículo existentes
-- Ejecutar después de Farmacia_FINAL.sql (aditivo, seguro re-ejecutar)
-- ============================================================
--
-- Problema encontrado: el combobox de licencias (repartidor_habilidad)
-- usa "Motocicleta", "Carro", "Camión" — pero los vehículos que ya
-- existían en la base de datos usaban otros nombres ("MOTO",
-- "BICICLETA"), así que nunca hacían match con ninguna licencia y el
-- sistema decía "no hay repartidores disponibles" aunque sí los hubiera.

UPDATE vehiculos SET tipo = 'Motocicleta' WHERE tipo = 'MOTO';

-- NOTA IMPORTANTE: el vehículo con tipo 'BICICLETA' (el de Carlos
-- Gómez) NO se tocó, porque "Bicicleta" no es una de las 3 categorías
-- de licencia que definiste (Motocicleta, Carro, Camión). Tal como
-- quedó, ese vehículo no se le puede asignar a nadie hasta que:
--   (a) le cambies el tipo manualmente a uno de los 3 válidos, o
--   (b) me digas si quieres agregar "Bicicleta" como una 4ta opción
--       de licencia en el combobox.
-- Puedes ver ese vehículo con:
--   SELECT * FROM vehiculos WHERE tipo = 'BICICLETA';


-- =============================================================================
-- PATCH 7/7 — Retirar de la flota el vehículo tipo "Bicicleta" (no es una
-- categoría de licencia válida). Se marca INACTIVO, no se borra, para no
-- perder el historial si alguna entrega vieja lo usó.
-- =============================================================================

UPDATE vehiculos
SET estado = 'INACTIVO', activo = FALSE
WHERE tipo = 'BICICLETA';


-- =============================================================================
-- PATCH 8/8 — AUDITORÍA REAL: función de trigger genérica enganchada a las
-- tablas de negocio de todos los módulos (antes existía la tabla y la
-- pantalla, pero ningún trigger escribía en ella)
-- =============================================================================

-- ============================================================
-- PATCH — Activar la auditoría de verdad en todo el sistema
-- Ejecutar después del resto del script (aditivo, seguro re-ejecutar)
-- ============================================================
--
-- Lo que encontré revisando el repo: la tabla auditoria_cambios, la
-- pantalla de Auditoría y hasta la función set_audit_vars() YA
-- EXISTÍAN — pero nada los conectaba. No había ni un solo trigger
-- escribiendo en auditoria_cambios, y ningún archivo PHP llamaba a
-- set_audit_vars(). Por eso el filtro de "módulo" solo mostraba lo
-- poco que hubiera (o nada).
--
-- Este patch agrega:
--   1. Una función de trigger genérica que registra INSERT/UPDATE/
--      DELETE de cualquier tabla a la que se le enganche.
--   2. Esa función enganchada a las tablas de negocio de todos los
--      módulos (Seguridad/permisos, Administración, Inventario,
--      Ventas, Compras, Delivery, Caja, Clientes).
--
-- (La otra mitad — que conexion.php llame a set_audit_vars() con el
-- usuario real de la sesión — va en un archivo PHP aparte, porque
-- eso no es SQL.)

-- ─────────────────────────────────────────────────────────────
-- 1. Función de trigger genérica
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION fn_auditoria_generica()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
    v_id_registro INT;
    v_pk_column TEXT := TG_ARGV[0];
BEGIN
    BEGIN
        v_id_usuario := NULLIF(current_setting('myapp.id_usuario', true), '')::INT;
    EXCEPTION WHEN OTHERS THEN
        v_id_usuario := NULL;
    END;

    BEGIN
        v_ip := NULLIF(current_setting('myapp.ip_address', true), '');
    EXCEPTION WHEN OTHERS THEN
        v_ip := NULL;
    END;

    IF TG_OP = 'INSERT' THEN
        EXECUTE format('SELECT ($1).%I', v_pk_column) INTO v_id_registro USING NEW;
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES (TG_TABLE_NAME, v_id_registro, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
        RETURN NEW;

    ELSIF TG_OP = 'UPDATE' THEN
        EXECUTE format('SELECT ($1).%I', v_pk_column) INTO v_id_registro USING NEW;
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES (TG_TABLE_NAME, v_id_registro, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
        RETURN NEW;

    ELSIF TG_OP = 'DELETE' THEN
        EXECUTE format('SELECT ($1).%I', v_pk_column) INTO v_id_registro USING OLD;
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES (TG_TABLE_NAME, v_id_registro, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
        RETURN OLD;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- ─────────────────────────────────────────────────────────────
-- 2. Enganchar la función a las tablas de negocio de cada módulo
--    (nombre de trigger, tabla, columna de llave primaria)
-- ─────────────────────────────────────────────────────────────

-- Seguridad / Administración
DROP TRIGGER IF EXISTS trg_auditoria_usuarios ON usuarios;
CREATE TRIGGER trg_auditoria_usuarios AFTER INSERT OR UPDATE OR DELETE ON usuarios
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_usuario');

DROP TRIGGER IF EXISTS trg_auditoria_roles ON roles;
CREATE TRIGGER trg_auditoria_roles AFTER INSERT OR UPDATE OR DELETE ON roles
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_rol');

DROP TRIGGER IF EXISTS trg_auditoria_permisos ON permisos;
CREATE TRIGGER trg_auditoria_permisos AFTER INSERT OR UPDATE OR DELETE ON permisos
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_permiso');

DROP TRIGGER IF EXISTS trg_auditoria_rol_permiso ON rol_permiso;
CREATE TRIGGER trg_auditoria_rol_permiso AFTER INSERT OR UPDATE OR DELETE ON rol_permiso
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_rol');

DROP TRIGGER IF EXISTS trg_auditoria_usuario_permiso ON usuario_permiso;
CREATE TRIGGER trg_auditoria_usuario_permiso AFTER INSERT OR UPDATE OR DELETE ON usuario_permiso
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_usuario');

DROP TRIGGER IF EXISTS trg_auditoria_configuracion_sistema ON configuracion_sistema;
CREATE TRIGGER trg_auditoria_configuracion_sistema AFTER INSERT OR UPDATE OR DELETE ON configuracion_sistema
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_config');

DROP TRIGGER IF EXISTS trg_auditoria_sucursales ON sucursales;
CREATE TRIGGER trg_auditoria_sucursales AFTER INSERT OR UPDATE OR DELETE ON sucursales
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_sucursal');

-- Inventario
DROP TRIGGER IF EXISTS trg_auditoria_medicamentos ON medicamentos;
CREATE TRIGGER trg_auditoria_medicamentos AFTER INSERT OR UPDATE OR DELETE ON medicamentos
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_medicamento');

DROP TRIGGER IF EXISTS trg_auditoria_productos ON productos;
CREATE TRIGGER trg_auditoria_productos AFTER INSERT OR UPDATE OR DELETE ON productos
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_producto');

DROP TRIGGER IF EXISTS trg_auditoria_lotes ON lotes;
CREATE TRIGGER trg_auditoria_lotes AFTER INSERT OR UPDATE OR DELETE ON lotes
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_lote');

DROP TRIGGER IF EXISTS trg_auditoria_inventario ON inventario;
CREATE TRIGGER trg_auditoria_inventario AFTER INSERT OR UPDATE OR DELETE ON inventario
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_inventario');

DROP TRIGGER IF EXISTS trg_auditoria_inventario_productos ON inventario_productos;
CREATE TRIGGER trg_auditoria_inventario_productos AFTER INSERT OR UPDATE OR DELETE ON inventario_productos
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_inventario');

DROP TRIGGER IF EXISTS trg_auditoria_movimiento_inventario ON movimiento_inventario;
CREATE TRIGGER trg_auditoria_movimiento_inventario AFTER INSERT OR UPDATE OR DELETE ON movimiento_inventario
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_movimiento');

-- Ventas
DROP TRIGGER IF EXISTS trg_auditoria_ventas ON ventas;
CREATE TRIGGER trg_auditoria_ventas AFTER INSERT OR UPDATE OR DELETE ON ventas
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_venta');

DROP TRIGGER IF EXISTS trg_auditoria_detalle_venta ON detalle_venta;
CREATE TRIGGER trg_auditoria_detalle_venta AFTER INSERT OR UPDATE OR DELETE ON detalle_venta
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_detalle');

DROP TRIGGER IF EXISTS trg_auditoria_pagos ON pagos;
CREATE TRIGGER trg_auditoria_pagos AFTER INSERT OR UPDATE OR DELETE ON pagos
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_pago');

DROP TRIGGER IF EXISTS trg_auditoria_abonos_credito ON abonos_credito;
CREATE TRIGGER trg_auditoria_abonos_credito AFTER INSERT OR UPDATE OR DELETE ON abonos_credito
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_abono');

DROP TRIGGER IF EXISTS trg_auditoria_descuentos ON descuentos;
CREATE TRIGGER trg_auditoria_descuentos AFTER INSERT OR UPDATE OR DELETE ON descuentos
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_descuento');

DROP TRIGGER IF EXISTS trg_auditoria_cupones ON cupones;
CREATE TRIGGER trg_auditoria_cupones AFTER INSERT OR UPDATE OR DELETE ON cupones
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_cupon');

-- Compras
DROP TRIGGER IF EXISTS trg_auditoria_compras ON compras;
CREATE TRIGGER trg_auditoria_compras AFTER INSERT OR UPDATE OR DELETE ON compras
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_compra');

DROP TRIGGER IF EXISTS trg_auditoria_detalle_compra ON detalle_compra;
CREATE TRIGGER trg_auditoria_detalle_compra AFTER INSERT OR UPDATE OR DELETE ON detalle_compra
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_detalle');

DROP TRIGGER IF EXISTS trg_auditoria_proveedores ON proveedores;
CREATE TRIGGER trg_auditoria_proveedores AFTER INSERT OR UPDATE OR DELETE ON proveedores
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_proveedor');

-- Clientes
DROP TRIGGER IF EXISTS trg_auditoria_clientes ON clientes;
CREATE TRIGGER trg_auditoria_clientes AFTER INSERT OR UPDATE OR DELETE ON clientes
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_cliente');

-- Delivery
DROP TRIGGER IF EXISTS trg_auditoria_repartidores ON repartidores;
CREATE TRIGGER trg_auditoria_repartidores AFTER INSERT OR UPDATE OR DELETE ON repartidores
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_repartidor');

DROP TRIGGER IF EXISTS trg_auditoria_vehiculos ON vehiculos;
CREATE TRIGGER trg_auditoria_vehiculos AFTER INSERT OR UPDATE OR DELETE ON vehiculos
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_vehiculo');

DROP TRIGGER IF EXISTS trg_auditoria_entregas ON entregas;
CREATE TRIGGER trg_auditoria_entregas AFTER INSERT OR UPDATE OR DELETE ON entregas
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_entrega');

DROP TRIGGER IF EXISTS trg_auditoria_devoluciones ON devoluciones;
CREATE TRIGGER trg_auditoria_devoluciones AFTER INSERT OR UPDATE OR DELETE ON devoluciones
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_devolucion');

DROP TRIGGER IF EXISTS trg_auditoria_tarifas_envio ON tarifas_envio;
CREATE TRIGGER trg_auditoria_tarifas_envio AFTER INSERT OR UPDATE OR DELETE ON tarifas_envio
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_tarifa');

-- Caja
DROP TRIGGER IF EXISTS trg_auditoria_caja ON caja;
CREATE TRIGGER trg_auditoria_caja AFTER INSERT OR UPDATE OR DELETE ON caja
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_caja');

DROP TRIGGER IF EXISTS trg_auditoria_movimiento_caja ON movimiento_caja;
CREATE TRIGGER trg_auditoria_movimiento_caja AFTER INSERT OR UPDATE OR DELETE ON movimiento_caja
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_movimiento');

-- Calificaciones de clientes (la nota que deja el cliente por su entrega)
DROP TRIGGER IF EXISTS trg_auditoria_calificaciones_entrega ON calificaciones_entrega;
CREATE TRIGGER trg_auditoria_calificaciones_entrega AFTER INSERT OR UPDATE OR DELETE ON calificaciones_entrega
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_calificacion');

-- Despacho de entregas (lo que sale de la sucursal)
DROP TRIGGER IF EXISTS trg_auditoria_despacho_entrega ON despacho_entrega;
CREATE TRIGGER trg_auditoria_despacho_entrega AFTER INSERT OR UPDATE OR DELETE ON despacho_entrega
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_despacho');

DROP TRIGGER IF EXISTS trg_auditoria_detalle_despacho ON detalle_despacho;
CREATE TRIGGER trg_auditoria_detalle_despacho AFTER INSERT OR UPDATE OR DELETE ON detalle_despacho
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_detalle');

-- Conciliación de entregas (lo que regresa el repartidor)
DROP TRIGGER IF EXISTS trg_auditoria_conciliacion_entrega ON conciliacion_entrega;
CREATE TRIGGER trg_auditoria_conciliacion_entrega AFTER INSERT OR UPDATE OR DELETE ON conciliacion_entrega
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_conciliacion');

DROP TRIGGER IF EXISTS trg_auditoria_detalle_conciliacion ON detalle_conciliacion;
CREATE TRIGGER trg_auditoria_detalle_conciliacion AFTER INSERT OR UPDATE OR DELETE ON detalle_conciliacion
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_detalle');

-- Incidencias durante una entrega
DROP TRIGGER IF EXISTS trg_auditoria_incidencias_entrega ON incidencias_entrega;
CREATE TRIGGER trg_auditoria_incidencias_entrega AFTER INSERT OR UPDATE OR DELETE ON incidencias_entrega
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_incidencia');

-- Crédito de clientes
DROP TRIGGER IF EXISTS trg_auditoria_limites_credito_cliente ON limites_credito_cliente;
CREATE TRIGGER trg_auditoria_limites_credito_cliente AFTER INSERT OR UPDATE OR DELETE ON limites_credito_cliente
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_limite');

DROP TRIGGER IF EXISTS trg_auditoria_configuracion_credito ON configuracion_credito;
CREATE TRIGGER trg_auditoria_configuracion_credito AFTER INSERT OR UPDATE OR DELETE ON configuracion_credito
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_config');

-- Seguros médicos de clientes
DROP TRIGGER IF EXISTS trg_auditoria_polizas ON polizas;
CREATE TRIGGER trg_auditoria_polizas AFTER INSERT OR UPDATE OR DELETE ON polizas
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_poliza');

DROP TRIGGER IF EXISTS trg_auditoria_autorizaciones_seguro ON autorizaciones_seguro;
CREATE TRIGGER trg_auditoria_autorizaciones_seguro AFTER INSERT OR UPDATE OR DELETE ON autorizaciones_seguro
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_autorizacion');

-- Órdenes de compra (antes de que se conviertan en una compra recibida)
DROP TRIGGER IF EXISTS trg_auditoria_ordenes_compra ON ordenes_compra;
CREATE TRIGGER trg_auditoria_ordenes_compra AFTER INSERT OR UPDATE OR DELETE ON ordenes_compra
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_orden');

DROP TRIGGER IF EXISTS trg_auditoria_detalle_orden_compra ON detalle_orden_compra;
CREATE TRIGGER trg_auditoria_detalle_orden_compra AFTER INSERT OR UPDATE OR DELETE ON detalle_orden_compra
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_detalle');


-- =============================================================================
-- PATCH 9/9 — CATÁLOGO DE MÓDULOS Y PERMISOS (estaban completamente vacíos,
-- por eso el sistema de Permisos de Usuario nunca guardaba nada)
-- =============================================================================

-- ============================================================
-- PATCH — Sembrar el catálogo de módulos y permisos
-- Ejecutar después del resto del script (aditivo, seguro re-ejecutar)
-- ============================================================
--
-- Encontré que las tablas modulos y permisos estaban COMPLETAMENTE
-- VACÍAS. Esto rompía todo el sistema de permisos de dos formas:
--   1. procesar_permisos.php busca el id_permiso por nombre para
--      poder guardar — como la tabla estaba vacía, nunca encontraba
--      nada y NUNCA se guardaba ni un solo permiso, sin importar qué
--      marcaras en la pantalla.
--   2. Aunque se hubiera guardado algo, no había catálogo con el que
--      relacionarlo.
--
-- Los 49 nombres de aquí abajo son EXACTAMENTE los que ya usa tu
-- pantalla permisos_usuarios.php (los saqué directo del HTML, no me
-- los inventé) — por eso van a encajar sin tocar esa pantalla.

-- ─────────────────────────────────────────────────────────────
-- 0. Restricciones UNIQUE necesarias para poder re-ejecutar este
--    patch sin duplicar filas (no existían antes)
-- ─────────────────────────────────────────────────────────────
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_modulos_nombre') THEN
        ALTER TABLE modulos ADD CONSTRAINT uq_modulos_nombre UNIQUE (nombre);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_permisos_nombre') THEN
        ALTER TABLE permisos ADD CONSTRAINT uq_permisos_nombre UNIQUE (nombre);
    END IF;
END $$;

-- ─────────────────────────────────────────────────────────────
-- 1. Módulos
-- ─────────────────────────────────────────────────────────────
INSERT INTO modulos (nombre, icono, orden) VALUES
    ('Dashboard', 'dashboard', 0),
    ('Ventas', 'point_of_sale', 1),
    ('Inventario', 'inventory_2', 2),
    ('Compras', 'shopping_cart', 3),
    ('Clientes', 'group', 4),
    ('Caja', 'payments', 6),
    ('Ropa', 'checkroom', 7),
    ('Administración', 'admin_panel_settings', 8),
    ('Seguridad', 'shield_person', 9),
    ('Reportes', 'bar_chart', 10)
ON CONFLICT (nombre) DO NOTHING;

-- ─────────────────────────────────────────────────────────────
-- 2. Permisos — nivel módulo (los 10 interruptores grandes)
-- ─────────────────────────────────────────────────────────────
INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, 'ventas', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Ventas'
UNION ALL SELECT id_modulo, 'inventario', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Inventario'
UNION ALL SELECT id_modulo, 'compras', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Compras'
UNION ALL SELECT id_modulo, 'clientes', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Clientes'
UNION ALL SELECT id_modulo, 'delivery', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Delivery'
UNION ALL SELECT id_modulo, 'caja', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Caja'
UNION ALL SELECT id_modulo, 'ropa', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Ropa'
UNION ALL SELECT id_modulo, 'administracion', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Administración'
UNION ALL SELECT id_modulo, 'seguridad', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Seguridad'
UNION ALL SELECT id_modulo, 'reportes', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Reportes'
ON CONFLICT (nombre) DO NOTHING;

-- ─────────────────────────────────────────────────────────────
-- 3. Permisos — nivel sub-módulo (los 39 interruptores finos)
-- ─────────────────────────────────────────────────────────────
INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('registrar_venta'),('historial_ventas'),('pagos'),('facturacion')
) AS x(nombre) WHERE modulos.nombre='Ventas'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('medicamentos'),('categorias'),('lotes'),('stock'),('vencimientos')
) AS x(nombre) WHERE modulos.nombre='Inventario'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('registrar_compra'),('historial_compras'),('proveedores')
) AS x(nombre) WHERE modulos.nombre='Compras'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('clientes_lista'),('historial_cliente')
) AS x(nombre) WHERE modulos.nombre='Clientes'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('repartidores'),('entregas'),('vehiculos'),('tracking'),('incidencias_delivery')
) AS x(nombre) WHERE modulos.nombre='Delivery'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('apertura_caja'),('cierre_caja')
) AS x(nombre) WHERE modulos.nombre='Caja'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('gestion_ropa'),('tipo_ropa'),('marcas'),('fabricantes'),('colores'),('tallas')
) AS x(nombre) WHERE modulos.nombre='Ropa'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('sucursales'),('empresa'),('usuarios'),('roles'),('permisos_usuarios'),('desbloqueo_usuarios')
) AS x(nombre) WHERE modulos.nombre='Administración'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('sesiones'),('auditoria'),('logs')
) AS x(nombre) WHERE modulos.nombre='Seguridad'
UNION ALL
SELECT id_modulo, x.nombre, 'ACCESO', 'SUBMODULO' FROM modulos, (VALUES
    ('reporte_ventas'),('reporte_inventario'),('reporte_vencimientos')
) AS x(nombre) WHERE modulos.nombre='Reportes'
ON CONFLICT (nombre) DO NOTHING;


-- =============================================================================
-- PATCH 10/10 — COSTO DE DELIVERY POR KM RECORRIDO (coordenadas de
-- sucursales + costo por km configurable, en vez de costo fijo escrito
-- a mano en cada venta)
-- =============================================================================

-- ============================================================
-- PATCH — Costo de delivery por km recorrido
-- Ejecutar después del resto del script (aditivo, seguro re-ejecutar)
-- ============================================================

-- Coordenadas de cada sucursal (para calcular distancia desde ahí)
ALTER TABLE sucursales
    ADD COLUMN IF NOT EXISTS latitud  DECIMAL(10,8),
    ADD COLUMN IF NOT EXISTS longitud DECIMAL(11,8);

COMMENT ON COLUMN sucursales.latitud IS
    'Coordenada de la sucursal, para calcular la distancia hasta la dirección de entrega. Sin esto, el sistema usa el costo de envío por defecto en vez de calcular por km.';

-- Nueva clave de configuración: cuánto se cobra por cada km recorrido
INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES
    ('costo_por_km', '15', 'Costo en RD$ que se cobra por cada kilómetro de distancia entre la sucursal y la dirección de entrega')
ON CONFLICT (clave) DO NOTHING;


-- =============================================================================
-- PATCH 11/11 — PORTAL DE CLIENTES (simulación): login propio para clientes,
-- ver sus entregas con todo el detalle, cancelar con motivo, y confirmar +
-- calificar en 3 bloques (repartidor, medicamento, general) cuando el
-- repartidor marque la entrega como completada.
-- =============================================================================

-- ============================================================
-- PATCH — Portal de Clientes (simulación): login, ver sus entregas,
-- cancelar con motivo, confirmar y calificar cuando el repartidor
-- marque la entrega como completada.
-- Ejecutar después del resto del script (aditivo, seguro re-ejecutar)
-- ============================================================

-- ─────────────────────────────────────────────────────────────
-- 1. Acceso al portal para el cliente (usuario/contraseña propios,
--    separados de los usuarios del staff)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS usuario_portal    VARCHAR(50) UNIQUE,
    ADD COLUMN IF NOT EXISTS contrasena_portal VARCHAR(255);

COMMENT ON COLUMN clientes.usuario_portal IS
    'Usuario para que el cliente entre al portal de seguimiento/calificación. NULL = todavía no tiene acceso.';

-- ─────────────────────────────────────────────────────────────
-- 2. Catálogo de motivos de cancelación (el combobox que ve el
--    cliente al cancelar su propia entrega)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS motivo_cancelacion_cliente (
    id_motivo SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    activo BOOLEAN DEFAULT TRUE,
    orden INT DEFAULT 0
);

INSERT INTO motivo_cancelacion_cliente (nombre, orden) VALUES
    ('Ya no necesito el pedido', 1),
    ('Me equivoqué al pedir', 2),
    ('Encontré el medicamento en otro lugar', 3),
    ('El tiempo de espera es muy largo', 4),
    ('Cambié de dirección de entrega', 5),
    ('Ya no voy a estar disponible para recibirlo', 6),
    ('Otro motivo', 99)
ON CONFLICT (nombre) DO NOTHING;

-- ─────────────────────────────────────────────────────────────
-- 3. Campos en "entregas" para la cancelación y confirmación del
--    cliente (independiente del flujo normal del repartidor)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE entregas
    ADD COLUMN IF NOT EXISTS cancelado_por            VARCHAR(20),
    ADD COLUMN IF NOT EXISTS id_motivo_cancelacion     INT REFERENCES motivo_cancelacion_cliente(id_motivo),
    ADD COLUMN IF NOT EXISTS comentario_cancelacion    TEXT,
    ADD COLUMN IF NOT EXISTS confirmado_por_cliente    BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS fecha_confirmacion_cliente TIMESTAMP;

COMMENT ON COLUMN entregas.cancelado_por IS
    'Quién canceló: CLIENTE o el usuario del staff que lo hizo. NULL si no está cancelada.';
COMMENT ON COLUMN entregas.confirmado_por_cliente IS
    'El repartidor ya puede haber marcado ENTREGADA — esto es aparte: que el propio cliente lo haya confirmado y calificado en el portal.';

-- ─────────────────────────────────────────────────────────────
-- 4. Completar calificaciones_entrega con la dimensión de
--    "persona correcta" (bloque general que pediste)
-- ─────────────────────────────────────────────────────────────
ALTER TABLE calificaciones_entrega
    ADD COLUMN IF NOT EXISTS persona_correcta BOOLEAN;

COMMENT ON COLUMN calificaciones_entrega.persona_correcta IS
    'Bloque general: ¿se entregó a la persona correcta?';


-- =============================================================================
-- PATCH 12/12 — CONECTAR "PERMISOS DE USUARIO" CON EL ACCESO REAL
-- Agrega 'dashboard' y 'agenda' como permisos reales, corrige el nombre
-- 'entregas' -> 'entrega', y siembra los valores por defecto de Dashboard y
-- Delivery para cada rol que ya existe.
-- =============================================================================

-- ============================================================
-- PATCH — Conectar "Permisos de Usuario" con el control de acceso
-- real, para Dashboard y Delivery (incluye la nueva Mi Agenda).
--
-- Hasta ahora, la pantalla de Permisos guardaba en usuario_permiso /
-- rol_permiso, pero el acceso real de cada quien lo decidía un
-- arreglo fijo en PHP (menu_por_rol) que nunca leía esas tablas —
-- por eso mover los interruptores no cambiaba nada.
--
-- Este patch:
--   1. Agrega 'dashboard' como permiso real (antes no existía, por
--      eso nunca se podía quitar).
--   2. Agrega 'agenda' como submódulo de Delivery (la pantalla del
--      repartidor).
--   3. Corrige 'entregas' -> 'entrega' para que coincida con el
--      nombre real que usa el sistema (menuprincipal.php?mod=entrega).
--   4. Siembra rol_permiso para Dashboard y Delivery en los 6 roles
--      que ya existen, para que el comportamiento de hoy no cambie
--      de golpe — solo a partir de aquí, cambiar un interruptor en
--      Permisos sí tiene efecto real.
-- ============================================================

-- 1. Dashboard como permiso real (antes no existía)
INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, 'dashboard', 'ACCESO', 'MODULO' FROM modulos WHERE nombre='Dashboard'
ON CONFLICT (nombre) DO NOTHING;

-- 2. Agenda como submódulo de Delivery
INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, 'agenda', 'ACCESO', 'SUBMODULO' FROM modulos WHERE nombre='Delivery'
ON CONFLICT (nombre) DO NOTHING;

-- 3. Corregir el nombre para que coincida con el real del sistema
UPDATE permisos SET nombre = 'entrega' WHERE nombre = 'entregas';

-- 4. Sembrar rol_permiso — Dashboard (todos menos Repartidor)
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r, permisos p
WHERE p.nombre = 'dashboard'
  AND r.nombre IN ('Administrador', 'Cajero', 'Vendedor', 'Encargado Inventario', 'Gestor Compras')
ON CONFLICT DO NOTHING;

-- 5. Sembrar rol_permiso — Delivery, módulo (Administrador, Cajero, Repartidor)
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r, permisos p
WHERE p.nombre = 'delivery'
  AND r.nombre IN ('Administrador', 'Cajero', 'Repartidor')
ON CONFLICT DO NOTHING;

-- 6. Sembrar rol_permiso — Delivery, submódulos administrativos
--    (Administrador y Cajero ven la gestión completa; el repartidor no)
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r, permisos p
WHERE p.nombre IN ('repartidores', 'entrega', 'vehiculos')
  AND r.nombre IN ('Administrador', 'Cajero')
ON CONFLICT DO NOTHING;

-- 7. Sembrar rol_permiso — Delivery, "Mi Agenda" (solo el Repartidor)
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso
FROM roles r, permisos p
WHERE p.nombre = 'agenda'
  AND r.nombre = 'Repartidor'
ON CONFLICT DO NOTHING;


-- =============================================================================
-- PATCH 13/13 — PERMISOS DE USUARIO COMPLETO: los 10 módulos ya controlan
-- acceso real (antes solo Dashboard+Delivery). Corrige 2 bugs reales del
-- archivo permisos_usuarios.php (Dashboard fijo en "checked disabled", y
-- "Configuración" que en realidad compartía el checkbox de "Sucursales").
-- Siembra rol_permiso completo para los 6 roles reales.
-- =============================================================================

-- ============================================================
-- PATCH — Conectar TODO "Permisos de Usuario" con el acceso real
-- (los 10 módulos, no solo Delivery), y corregir 2 bugs reales que
-- tenía el archivo original de esa pantalla.
--
-- Bugs encontrados en permisos_usuarios.php (el archivo real):
--   1. El interruptor de "Dashboard" tenía checked disabled en el
--      HTML — nunca se podía apagar, sin importar qué guardara el
--      backend.
--   2. La fila que dice "Configuración" en Administración en
--      realidad tenía el checkbox de "Sucursales" duplicado (mismo
--      id, mismo data-permiso) — Configuración nunca fue un permiso
--      real, aunque se veía en pantalla.
--   3. El botón "Cargar permisos por defecto del rol" tenía sus
--      propios valores por defecto escritos directo en JavaScript,
--      con roles que ni existen en la base de datos ('Vendedor',
--      'Gestor Delivery', etc.) y sin ninguna entrada para
--      'Repartidor' — si lo usabas con un repartidor, cargaba por
--      error los permisos de Cajero.
-- ============================================================

-- 1. "entrega" vuelve a llamarse "entregas" — así se llama en el
--    archivo real de Permisos (permisos_usuarios.php usa ese nombre
--    en 15+ lugares); es más seguro ajustar la base de datos que
--    reescribir ese archivo entero.
UPDATE permisos SET nombre = 'entregas' WHERE nombre = 'entrega';

-- 2. "configuracion" como permiso real y propio (antes compartía,
--    por error, el mismo checkbox que "sucursales")
INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, 'configuracion', 'ACCESO', 'SUBMODULO' FROM modulos WHERE nombre='Administración'
ON CONFLICT (nombre) DO NOTHING;

-- ============================================================
-- Sembrar rol_permiso completo, para los 6 roles reales que existen
-- (Administrador, Cajero, Inventario, Supervisor, Soporte,
-- Repartidor) — de aquí en adelante, esto es lo único que decide
-- accesos por defecto; ya no el arreglo de PHP ni el de JavaScript.
-- ============================================================

-- Administrador: absolutamente todo
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p WHERE r.nombre = 'Administrador'
ON CONFLICT DO NOTHING;

-- Cajero: ventas, clientes, caja, delivery completo (igual que ya
-- tenía el sistema antes de este cambio)
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE r.nombre = 'Cajero'
  AND p.nombre IN (
    'dashboard',
    'ventas', 'registrar_venta', 'historial_ventas', 'pagos', 'facturacion',
    'clientes', 'clientes_lista', 'historial_cliente',
    'caja', 'apertura_caja', 'cierre_caja',
    'delivery', 'repartidores', 'entregas', 'vehiculos', 'tracking', 'incidencias_delivery'
  )
ON CONFLICT DO NOTHING;

-- Inventario (rol): dashboard + módulo de inventario + reportes
-- relacionados
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE r.nombre = 'Inventario'
  AND p.nombre IN (
    'dashboard',
    'inventario', 'medicamentos', 'categorias', 'lotes', 'stock', 'vencimientos',
    'reportes', 'reporte_inventario', 'reporte_vencimientos'
  )
ON CONFLICT DO NOTHING;

-- Supervisor: dashboard + reportes de todo, sin poder operar nada
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE r.nombre = 'Supervisor'
  AND p.nombre IN ('dashboard', 'reportes', 'reporte_ventas', 'reporte_inventario', 'reporte_vencimientos')
ON CONFLICT DO NOTHING;

-- Soporte: dashboard + seguridad + administración de usuarios
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE r.nombre = 'Soporte'
  AND p.nombre IN (
    'dashboard',
    'seguridad', 'sesiones', 'auditoria', 'logs',
    'administracion', 'usuarios', 'desbloqueo_usuarios'
  )
ON CONFLICT DO NOTHING;

-- Repartidor: solo su Agenda — nada de Dashboard, nada de la
-- gestión administrativa de Delivery
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE r.nombre = 'Repartidor'
  AND p.nombre IN ('delivery', 'agenda')
ON CONFLICT DO NOTHING;


-- =============================================================================
-- PATCH 14/14 — Permiso propio para "Accesos de Clientes" (antes reutilizaba
-- por error el permiso de "Usuarios", así que nunca aparecía como interruptor
-- independiente en Permisos de Usuarios).
-- =============================================================================

-- ============================================================
-- PATCH — "Accesos de Clientes" necesita su propio permiso, no
-- compartir el de "Usuarios" (personal del sistema). Sin esto,
-- nunca aparecía como interruptor independiente en la pantalla de
-- Permisos de Usuarios.
-- ============================================================

INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, 'usuarios_clientes', 'ACCESO', 'SUBMODULO' FROM modulos WHERE nombre='Administración'
ON CONFLICT (nombre) DO NOTHING;

-- Por ahora, solo el Administrador lo tiene por defecto
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE p.nombre = 'usuarios_clientes' AND r.nombre = 'Administrador'
ON CONFLICT DO NOTHING;


-- =============================================================================
-- PATCH 15/15 — "Agendas de Repartidores": pantalla separada para que el
-- Administrador vea la agenda de CUALQUIER repartidor (filtrando por quien
-- quiera), sin mezclarla con "Mi Agenda" (esa sigue siendo solo la del propio
-- repartidor logueado). Ambas pantallas quedan totalmente separadas, cada
-- una con su propio permiso.
-- =============================================================================

INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, 'agendas_repartidores', 'ACCESO', 'SUBMODULO' FROM modulos WHERE nombre='Delivery'
ON CONFLICT (nombre) DO NOTHING;

-- Por ahora, solo el Administrador la ve por defecto
INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE p.nombre = 'agendas_repartidores' AND r.nombre = 'Administrador'
ON CONFLICT DO NOTHING;

-- =============================================================================
-- PATCH 16/16 — Redespacho tras entrega PARCIAL: una entrega puede pasar por
-- varias RONDAS de despacho/confirmación hasta quedar completamente
-- entregada (o fallida/cancelada). despacho_entrega y conciliacion_entrega
-- dejan de ser "1 fila por entrega" para pasar a "1 fila por RONDA",
-- conservando el historial completo de qué se despachó y qué se entregó
-- en cada intento (importante para trazabilidad de medicamentos por lote).
-- =============================================================================

ALTER TABLE despacho_entrega
    ADD COLUMN IF NOT EXISTS id_ronda INT NOT NULL DEFAULT 1;
ALTER TABLE despacho_entrega DROP CONSTRAINT IF EXISTS uq_despacho_entrega;
ALTER TABLE despacho_entrega DROP CONSTRAINT IF EXISTS uq_despacho_entrega_ronda;
ALTER TABLE despacho_entrega
    ADD CONSTRAINT uq_despacho_entrega_ronda UNIQUE (id_entrega, id_ronda);

ALTER TABLE conciliacion_entrega
    ADD COLUMN IF NOT EXISTS id_ronda INT NOT NULL DEFAULT 1;
ALTER TABLE conciliacion_entrega DROP CONSTRAINT IF EXISTS uq_conciliacion_entrega;
ALTER TABLE conciliacion_entrega DROP CONSTRAINT IF EXISTS uq_conciliacion_entrega_ronda;
ALTER TABLE conciliacion_entrega
    ADD CONSTRAINT uq_conciliacion_entrega_ronda UNIQUE (id_entrega, id_ronda);

-- =============================================================================
-- PATCH 17/17 — Preguntas de calificación configurables: el administrador
-- puede agregar/editar/desactivar las preguntas del formulario que el
-- cliente llena al calificar su entrega (por estrellas o texto libre), en
-- vez de tenerlas fijas en el código. Se agrega también una pantalla para
-- ver todas las calificaciones hechas por los clientes.
-- =============================================================================

CREATE TABLE IF NOT EXISTS preguntas_calificacion (
    id_pregunta     SERIAL PRIMARY KEY,
    categoria       VARCHAR(50)  NOT NULL DEFAULT 'General',
    texto           VARCHAR(300) NOT NULL,
    tipo_respuesta  VARCHAR(20)  NOT NULL DEFAULT 'ESTRELLAS' CHECK (tipo_respuesta IN ('ESTRELLAS','TEXTO')),
    obligatoria     BOOLEAN      NOT NULL DEFAULT TRUE,
    orden           INT          NOT NULL DEFAULT 0,
    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
    fecha_creacion  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS respuestas_calificacion (
    id_respuesta      SERIAL PRIMARY KEY,
    id_calificacion   INT NOT NULL REFERENCES calificaciones_entrega(id_calificacion) ON DELETE CASCADE,
    id_pregunta       INT REFERENCES preguntas_calificacion(id_pregunta) ON DELETE SET NULL,
    -- Se guarda una "foto" de la pregunta al momento de responder, para que
    -- si el administrador la edita o la borra después, la respuesta vieja
    -- siga mostrando exactamente lo que se le preguntó al cliente ese día.
    texto_pregunta    VARCHAR(300) NOT NULL,
    categoria         VARCHAR(50)  NOT NULL DEFAULT 'General',
    tipo_respuesta    VARCHAR(20)  NOT NULL,
    valor_estrellas   SMALLINT CHECK (valor_estrellas BETWEEN 1 AND 5),
    valor_texto       TEXT,
    fecha_respuesta   TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_respuestas_calificacion_calificacion ON respuestas_calificacion(id_calificacion);

-- Sembrar las preguntas que ya existían fijas en el formulario, para que
-- el comportamiento no cambie hasta que el administrador decida editarlas.
-- Solo se siembra si la tabla está completamente vacía (primera vez).
INSERT INTO preguntas_calificacion (categoria, texto, tipo_respuesta, obligatoria, orden)
SELECT * FROM (VALUES
    ('Repartidor',  'Atención y trato',                        'ESTRELLAS', TRUE,  1),
    ('Medicamento', 'Estado en que llegó el producto',         'ESTRELLAS', TRUE,  2),
    ('Medicamento', 'Presentación / empaque',                  'ESTRELLAS', TRUE,  3),
    ('General',     '¿Llegó a tiempo?',                        'ESTRELLAS', TRUE,  4),
    ('General',     'Calificación general del servicio',       'ESTRELLAS', TRUE,  5),
    ('General',     '¿Algo más que quieras contarnos?',        'TEXTO',     FALSE, 6)
) AS v(categoria, texto, tipo_respuesta, obligatoria, orden)
WHERE NOT EXISTS (SELECT 1 FROM preguntas_calificacion);

-- Permiso para la pantalla nueva "Calificaciones de Clientes" (Delivery)
INSERT INTO permisos (id_modulo, nombre, accion, tipo_accion)
SELECT id_modulo, 'calificaciones_clientes', 'ACCESO', 'SUBMODULO' FROM modulos WHERE nombre='Delivery'
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO rol_permiso (id_rol, id_permiso)
SELECT r.id_rol, p.id_permiso FROM roles r, permisos p
WHERE p.nombre = 'calificaciones_clientes' AND r.nombre = 'Administrador'
ON CONFLICT DO NOTHING;

-- =============================================================================
-- PATCH 18/18 — Secciones de calificación administrables: "categoría" deja
-- de ser texto libre por pregunta y pasa a ser su propia tabla
-- (categorias_calificacion), para que el administrador pueda crear,
-- renombrar y eliminar secciones completas (por ejemplo: Repartidor,
-- Producto, Envío), y cada pregunta se asigna a una de esas secciones.
-- =============================================================================

CREATE TABLE IF NOT EXISTS categorias_calificacion (
    id_categoria SERIAL PRIMARY KEY,
    nombre       VARCHAR(50) UNIQUE NOT NULL,
    orden        INT NOT NULL DEFAULT 0,
    activo       BOOLEAN NOT NULL DEFAULT TRUE
);

ALTER TABLE preguntas_calificacion
    ADD COLUMN IF NOT EXISTS id_categoria INT REFERENCES categorias_calificacion(id_categoria);

-- Sembrar las 3 secciones por defecto, solo si no hay ninguna todavía.
INSERT INTO categorias_calificacion (nombre, orden)
SELECT * FROM (VALUES ('Repartidor', 1), ('Producto', 2), ('Envío', 3)) AS v(nombre, orden)
WHERE NOT EXISTS (SELECT 1 FROM categorias_calificacion);

-- Vincular las preguntas que ya existan (de PATCH 17, con categoría en
-- texto libre "Repartidor"/"Medicamento"/"General") a la sección nueva
-- que le corresponde. Si una pregunta ya tiene id_categoria, no se toca.
UPDATE preguntas_calificacion pc
SET id_categoria = cc.id_categoria
FROM categorias_calificacion cc
WHERE pc.id_categoria IS NULL
  AND (
        (pc.categoria = 'Repartidor'  AND cc.nombre = 'Repartidor') OR
        (pc.categoria = 'Medicamento' AND cc.nombre = 'Producto')   OR
        (pc.categoria = 'General'     AND cc.nombre = 'Envío')
      );

-- Cualquier pregunta que se haya quedado sin sección (categoría con un
-- texto distinto a los 3 casos de arriba) se manda a la primera sección
-- activa, para que no quede huérfana.
UPDATE preguntas_calificacion pc
SET id_categoria = (SELECT id_categoria FROM categorias_calificacion WHERE activo = TRUE ORDER BY orden LIMIT 1)
WHERE pc.id_categoria IS NULL;

-- =============================================================================
-- PATCH 19/19 — AUDITORÍA: agregar a la lista de tablas vigiladas el
-- módulo de calificaciones de clientes (preguntas, secciones y respuestas),
-- que se agregó en PATCH 17/18 después de que se conectó fn_auditoria_generica
-- por primera vez, así que se había quedado afuera. También se conecta
-- set_audit_vars() desde PHP (ver backend/conexion.php) para que estas filas
-- de auditoría, y las de todo el resto del sistema, queden con el usuario y
-- la IP real de quien hizo el cambio, en vez de en blanco.
-- =============================================================================

DROP TRIGGER IF EXISTS trg_auditoria_preguntas_calificacion ON preguntas_calificacion;
CREATE TRIGGER trg_auditoria_preguntas_calificacion AFTER INSERT OR UPDATE OR DELETE ON preguntas_calificacion
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_pregunta');

DROP TRIGGER IF EXISTS trg_auditoria_categorias_calificacion ON categorias_calificacion;
CREATE TRIGGER trg_auditoria_categorias_calificacion AFTER INSERT OR UPDATE OR DELETE ON categorias_calificacion
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_categoria');

DROP TRIGGER IF EXISTS trg_auditoria_respuestas_calificacion ON respuestas_calificacion;
CREATE TRIGGER trg_auditoria_respuestas_calificacion AFTER INSERT OR UPDATE OR DELETE ON respuestas_calificacion
    FOR EACH ROW EXECUTE FUNCTION fn_auditoria_generica('id_respuesta');

-- =============================================================================
-- PATCH 20/20 — DEVOLUCIÓN EN LA ENTREGA: el repartidor ahora puede marcar
-- que el cliente devolvió uno o más productos en el momento de la entrega
-- (ej: llegó vencido y el cliente se dio cuenta ahí mismo). Se reutiliza el
-- MISMO sistema de Devoluciones que ya existe en Inventario (tabla
-- devoluciones/detalle_devolucion) — no se crea un módulo aparte — para que
-- lo que registre el repartidor aparezca automáticamente en Inventario >
-- Devoluciones, tal como ya pasa con las devoluciones que registra un cajero.
-- =============================================================================

-- Catálogo de razones específicas para una devolución hecha en el momento
-- de la entrega (independiente de tipo_devolucion, que es la clasificación
-- general CLIENTE/PROVEEDOR/MERMA/AJUSTE que ya usa el resto del sistema).
CREATE TABLE IF NOT EXISTS motivo_devolucion_delivery (
    id_motivo SERIAL PRIMARY KEY,
    nombre    VARCHAR(100) NOT NULL UNIQUE,
    activo    BOOLEAN NOT NULL DEFAULT TRUE,
    orden     INT DEFAULT 0
);

INSERT INTO motivo_devolucion_delivery (nombre, orden) VALUES
    ('Producto vencido o próximo a vencer',      1),
    ('Producto dañado o en mal estado',          2),
    ('Empaque abierto o manipulado',             3),
    ('Producto equivocado (no es lo que pidió)', 4),
    ('Cliente cambió de opinión',                5),
    ('Otro',                                    99)
ON CONFLICT (nombre) DO NOTHING;

-- Trazabilidad: de qué entrega y de qué razón del catálogo salió la
-- devolución, sin dejar de llenar el campo "motivo" en texto libre que ya
-- usan las pantallas existentes de Devoluciones (Inventario y Delivery).
ALTER TABLE devoluciones
    ADD COLUMN IF NOT EXISTS id_entrega INT REFERENCES entregas(id_entrega),
    ADD COLUMN IF NOT EXISTS id_motivo_devolucion_delivery INT REFERENCES motivo_devolucion_delivery(id_motivo);

CREATE INDEX IF NOT EXISTS idx_devoluciones_id_entrega ON devoluciones(id_entrega);

-- =============================================================================
-- PATCH 21/21 — Estado propio para "todo el pedido fue devuelto": el
-- cierre automático de PATCH 20 usaba 'PARCIAL', pero PARCIAL en el resto
-- del sistema significa "se entregó una parte y falta redespachar el
-- resto" — get_agenda_repartidor.php la trata como entrega ACTIVA (sigue
-- apareciendo en "Mi Agenda" esperando una segunda ronda que en este caso
-- nunca va a llegar, porque el cliente no quiere nada del pedido). Se
-- agrega 'DEVUELTA' como estado terminal aparte, para que quede claro que
-- ahí no hay nada más que hacer del lado de logística.
-- =============================================================================

INSERT INTO estado_entrega (nombre) VALUES ('DEVUELTA')
ON CONFLICT (nombre) DO NOTHING;

-- =============================================================================
-- PATCH 22/22 — VERIFICACIÓN DE DEVOLUCIONES: hasta ahora, pasar una
-- devolución de SOLICITADA a APROBADA/RECHAZADA se hacía con un simple
-- combo box genérico (backend/inventario/guardar_devolucion.php), sin
-- pedir ninguna nota de qué se verificó físicamente ni quién de verdad
-- revisó el producto. Se agrega una columna para guardar esa nota — el
-- "aprobado_por"/"fecha_aprobacion" que ya existían quedan como el
-- responsable y la fecha de la verificación (se reutilizan tal cual).
-- =============================================================================

ALTER TABLE devoluciones
    ADD COLUMN IF NOT EXISTS observaciones_validacion TEXT;

-- =============================================================================
-- PATCH 23/23 — CONFIRMACIÓN DEL CLIENTE ANTES QUE EL CAJERO: cuando el
-- repartidor registra una devolución de una entrega, ya no le toca
-- directamente al cajero decidir — primero se le pregunta al cliente (desde
-- su Portal) si es cierto que devolvió esos productos. Solo cuando el
-- cliente responde (Sí o No) es que la devolución queda disponible para
-- que el cajero/Inventario la apruebe o rechace en
-- backend/inventario/validar_devolucion.php.
--   confirmado_por_cliente: NULL = todavía no responde, TRUE = confirma
--   que sí devolvió, FALSE = dice que no es cierto (queda como disputa,
--   pero igual se le muestra al cajero para que la resuelva).
-- =============================================================================

ALTER TABLE devoluciones
    ADD COLUMN IF NOT EXISTS confirmado_por_cliente BOOLEAN,
    ADD COLUMN IF NOT EXISTS fecha_confirmacion_cliente TIMESTAMP,
    ADD COLUMN IF NOT EXISTS nota_cliente TEXT;

-- =============================================================================
-- PATCH 24/24 — Renombrar el módulo "Delivery" a "Envíos" en todas partes.
-- Este módulo se sacó del INSERT masivo de arriba (PATCH 9) porque ese
-- INSERT usa ON CONFLICT (nombre) DO NOTHING: si se dejaba ahí con el
-- nombre nuevo, en una base ya poblada (que todavía tiene la fila vieja
-- 'Delivery') se insertaría una fila 'Envíos' A PARTE, sin tocar la vieja
-- — y si se dejaba con el nombre viejo, cada vez que se corriera este
-- archivo DESPUÉS de renombrar iba a volver a sembrar 'Delivery' de
-- nuevo, porque para ese momento 'Delivery' ya no existe y el ON
-- CONFLICT no tiene nada que evitar. Por eso este módulo se maneja aparte,
-- con su propia guarda "insértalo solo si no existe ninguno de los dos
-- nombres", seguida de un UPDATE por si todavía queda la fila vieja.
-- =============================================================================

INSERT INTO modulos (nombre, icono, orden)
SELECT 'Envíos', 'local_shipping', 5
WHERE NOT EXISTS (SELECT 1 FROM modulos WHERE nombre IN ('Envíos', 'Delivery'));

UPDATE modulos SET nombre = 'Envíos' WHERE nombre = 'Delivery';
