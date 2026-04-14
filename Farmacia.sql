-- =============================================================================
-- SISTEMA GESTOR DE FARMACIAS - VERSIÓN COMPLETA CON LOTES CORREGIDOS
-- =============================================================================

-- =============================================================================
-- 1. MÓDULO: ADMINISTRACIÓN BASE
-- =============================================================================

CREATE TABLE IF NOT EXISTS empresa (
    id_empresa SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    rnc VARCHAR(30),
    direccion VARCHAR(200),
    logo_url VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS sucursales (
    id_sucursal SERIAL PRIMARY KEY,
    id_empresa INT REFERENCES empresa(id_empresa) ON DELETE CASCADE,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(200),
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
    estado BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS historial_accesos (
    id_historial SERIAL PRIMARY KEY,
    id_usuario INT REFERENCES usuarios(id_usuario),
    ventana VARCHAR(100),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45)
);

-- =============================================================================
-- 2. MÓDULO: AUDITORÍA Y SEGURIDAD
-- =============================================================================

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

-- =============================================================================
-- 3. MÓDULO: PERMISOS GRANULARES
-- =============================================================================

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

-- =============================================================================
-- 4. MÓDULO: GESTIÓN DE VERSIONES Y MIGRACIONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS version_esquema (
    id_version SERIAL PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    fecha_aplicacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    descripcion TEXT,
    script_aplicado TEXT
);

-- =============================================================================
-- 5. MÓDULO: RESPALDOS Y RECUPERACIÓN
-- =============================================================================

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

-- =============================================================================
-- 6. MÓDULO: TELÉFONOS Y CORREOS (NORMALIZADOS)
-- =============================================================================

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

-- =============================================================================
-- 7. MÓDULO: CLIENTES Y RELACIONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS clientes (
    id_cliente SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(200),
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
    tiene_seguro BOOLEAN DEFAULT FALSE
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

-- =============================================================================
-- 8. MÓDULO: PROVEEDORES Y RELACIONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS proveedores (
    id_proveedor SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    direccion VARCHAR(200),
    rnc VARCHAR(30)
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

-- =============================================================================
-- 9. MÓDULO: USUARIOS - TELÉFONOS/CORREOS
-- =============================================================================

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

-- =============================================================================
-- 10. MÓDULO: REPARTIDORES Y RELACIONES
-- =============================================================================

CREATE TABLE IF NOT EXISTS repartidores (
    id_repartidor SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono_emergencia VARCHAR(20),
    tipo_identificacion VARCHAR(20),
    numero_identificacion VARCHAR(30) UNIQUE,
    direccion VARCHAR(200),
    fecha_ingreso DATE DEFAULT CURRENT_DATE,
    fecha_salida DATE,
    activo BOOLEAN DEFAULT TRUE,
    foto_url VARCHAR(255),
    licencia_conducir VARCHAR(50),
    fecha_vencimiento_licencia DATE,
    observaciones TEXT
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

-- =============================================================================
-- 11. MÓDULO: EMPRESA - TELÉFONOS/CORREOS
-- =============================================================================

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

-- =============================================================================
-- 12. MÓDULO: NIVELES DE CLIENTE (FIDELIDAD)
-- =============================================================================

CREATE TABLE IF NOT EXISTS niveles_cliente (
    id_nivel SERIAL PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    puntos_minimos INT NOT NULL,
    descuento_porcentaje NUMERIC(5,2) DEFAULT 0,
    beneficio_adicional TEXT
);

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS id_nivel INT REFERENCES niveles_cliente(id_nivel) DEFAULT 1;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS puntos_acumulados NUMERIC(10,2) DEFAULT 0;

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
-- 13. MÓDULO: CATÁLOGOS (ORDEN CORREGIDO)
-- =============================================================================

-- 1. Unidades de medida (independiente)
CREATE TABLE IF NOT EXISTS unidades_medida (
    id_unidad SERIAL PRIMARY KEY,
    nombre VARCHAR(50),
    abreviatura VARCHAR(10)
);

-- 2. Presentaciones (depende de unidades_medida)
CREATE TABLE IF NOT EXISTS presentaciones (
    id_presentacion SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    id_unidad INT REFERENCES unidades_medida(id_unidad),
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Categorías (independiente)
CREATE TABLE IF NOT EXISTS categorias (
    id_categoria SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. TABLA DE RELACIÓN CATEGORÍA - PRESENTACIÓN (NUEVA)
CREATE TABLE IF NOT EXISTS categoria_presentacion (
    id_categoria INT REFERENCES categorias(id_categoria) ON DELETE CASCADE,
    id_presentacion INT REFERENCES presentaciones(id_presentacion) ON DELETE CASCADE,
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_categoria, id_presentacion)
);

-- 5. Principios activos (independiente)
CREATE TABLE IF NOT EXISTS principios_activos (
    id_principio SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL
);

-- 6. Laboratorios (independiente)
CREATE TABLE IF NOT EXISTS laboratorios (
    id_laboratorio SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    pais VARCHAR(100),
    direccion VARCHAR(200),
    telefono VARCHAR(30),
    email VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE
);

-- =============================================================================
-- 14. MÓDULO: MEDICAMENTOS (CON concentracion)
-- =============================================================================

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
    id_presentacion INT REFERENCES presentaciones(id_presentacion)
);

CREATE TABLE IF NOT EXISTS medicamento_principio (
    id_medicamento INT REFERENCES medicamentos(id_medicamento),
    id_principio INT REFERENCES principios_activos(id_principio),
    PRIMARY KEY (id_medicamento, id_principio)
);

-- =============================================================================
-- 15. MÓDULO: PRODUCTOS GENERALES
-- =============================================================================

CREATE TABLE IF NOT EXISTS productos (
    id_producto SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    tipo_producto VARCHAR(20) NOT NULL CHECK (tipo_producto IN ('MEDICAMENTO', 'ROPA')),
    precio NUMERIC(10,2) NOT NULL,
    estado BOOLEAN DEFAULT TRUE,
    exento_itbis BOOLEAN DEFAULT FALSE
);

-- Agregar columna imagen_url a la tabla productos
ALTER TABLE productos ADD COLUMN IF NOT EXISTS imagen_url VARCHAR(255);

-- Verificar que se agregó correctamente
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'productos' 
ORDER BY ordinal_position;

SELECT * FROM productos

CREATE TABLE IF NOT EXISTS tipo_ropa (
    id_tipo SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL
);

ALTER TABLE tipo_ropa ADD COLUMN descripcion TEXT;
ALTER TABLE tipo_ropa ADD COLUMN estado BOOLEAN DEFAULT TRUE;
SELECT * FROM tipo_ropa 

CREATE TABLE IF NOT EXISTS marcas (
    id_marca SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
);

ALTER TABLE marcas ADD COLUMN estado BOOLEAN DEFAULT TRUE;
SELECT * FROM marcas

CREATE TABLE IF NOT EXISTS fabricantes (
    id_fabricante SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    pais VARCHAR(100),
    contacto VARCHAR(100)
);

ALTER TABLE fabricantes ADD COLUMN estado BOOLEAN DEFAULT TRUE;
SELECT * FROM fabricantes

INSERT INTO fabricantes (nombre, pais, contacto) 
VALUES 
('Textiles del Sur S.A.', 'Colombia', 'ventas@textilessur.co'),
('Nordic Loom Design', 'Suecia', 'info@nordicloom.se');

-- Para ver tus nuevos fabricantes de ropa:
SELECT * FROM fabricantes;

CREATE TABLE IF NOT EXISTS colores (
    id_color SERIAL PRIMARY KEY,
    nombre VARCHAR(50)
);

ALTER TABLE colores ADD COLUMN IF NOT EXISTS estado BOOLEAN DEFAULT TRUE;
SELECT * FROM colores;

CREATE TABLE IF NOT EXISTS tallas (
    id_talla SERIAL PRIMARY KEY,
    nombre VARCHAR(10)
);

ALTER TABLE tallas ADD COLUMN IF NOT EXISTS estado BOOLEAN DEFAULT TRUE;
SELECT * FROM tallas;

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

SELECT * FROM ropa_detalle;

-- =============================================================================
-- AGREGAR LA REFERENCIA DE MEDICAMENTOS A PRODUCTOS
-- =============================================================================

ALTER TABLE medicamentos ADD COLUMN IF NOT EXISTS id_producto INT UNIQUE REFERENCES productos(id_producto);
ALTER TABLE medicamentos ADD COLUMN IF NOT EXISTS nombre_completo VARCHAR(200);

-- =============================================================================
-- 16. MÓDULO: CONFIGURACIÓN ITBIS
-- =============================================================================

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

INSERT INTO config_itbis (porcentaje, aplica_medicamentos_sin_receta, aplica_medicamentos_con_receta, aplica_ropa, fecha_inicio, activo)
SELECT 18.00, TRUE, FALSE, TRUE, '2024-01-01', TRUE
WHERE NOT EXISTS (SELECT 1 FROM config_itbis);

-- =============================================================================
-- 17. MÓDULO: COMPRAS
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

-- =============================================================================
-- 18. MÓDULO: LOTES (ACTUALIZADO CON CANTIDADES)
-- =============================================================================

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
    CONSTRAINT chk_lote_estado CHECK (estado IN ('ACTIVO', 'VENCIDO', 'RETIRADO', 'MERMA')),
    CONSTRAINT chk_cantidades CHECK (cantidad_actual >= 0 AND cantidad_actual <= cantidad_inicial)
);

CREATE TABLE IF NOT EXISTS detalle_compra (
    id_detalle SERIAL PRIMARY KEY,
    id_compra INT REFERENCES compras(id_compra) ON DELETE CASCADE,
    id_lote INT REFERENCES lotes(id_lote),
    cantidad INT NOT NULL,
    precio_unitario NUMERIC(10,2) NOT NULL,
    descuento_unitario NUMERIC(10,2) DEFAULT 0,
    itbis_unitario NUMERIC(10,2) DEFAULT 0
);

-- =============================================================================
-- 19. MÓDULO: INVENTARIO
-- =============================================================================

CREATE TABLE IF NOT EXISTS inventario (
    id_inventario SERIAL PRIMARY KEY,
    id_lote INT REFERENCES lotes(id_lote),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    cantidad INT NOT NULL DEFAULT 0 CHECK (cantidad >= 0),
    UNIQUE(id_lote, id_sucursal)
);

CREATE TABLE IF NOT EXISTS inventario_productos (
    id_inventario SERIAL PRIMARY KEY,
    id_producto INT REFERENCES productos(id_producto),
    id_sucursal INT REFERENCES sucursales(id_sucursal),
    cantidad INT DEFAULT 0,
    UNIQUE(id_producto, id_sucursal)
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
    id_usuario INT REFERENCES usuarios(id_usuario)
);

-- =============================================================================
-- 20. MÓDULO: CONDICIONES DE PAGO Y MÉTODOS
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

-- =============================================================================
-- 21. MÓDULO: VENTAS
-- =============================================================================

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
    fecha_pago_seguro TIMESTAMP
);

-- =============================================================================
-- 22. MÓDULO: DESCUENTOS MÚLTIPLES
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
-- 23. MÓDULO: SEGUROS MÉDICOS
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
-- 24. MÓDULO: CRÉDITO CLIENTES
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

-- =============================================================================
-- 25. MÓDULO: ACUMULACIÓN DE PUNTOS
-- =============================================================================

CREATE TABLE IF NOT EXISTS acumulacion_puntos (
    id_acumulacion SERIAL PRIMARY KEY,
    id_venta INT REFERENCES ventas(id_venta),
    id_cliente INT REFERENCES clientes(id_cliente),
    puntos_ganados NUMERIC(10,2) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- 26. MÓDULO: DELIVERY
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
    fecha_modificacion TIMESTAMP
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
-- 27. MÓDULO: CAJA Y CIERRE DE CAJA
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
    observaciones TEXT
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

-- =============================================================================
-- 28. MÓDULO: COMISIONES
-- =============================================================================

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

-- =============================================================================
-- 29. MÓDULO: CONFIGURACIÓN DE LÍMITES DE DESCUENTO POR ROL
-- =============================================================================

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

-- =============================================================================
-- 30. MÓDULO: MÉTRICAS Y KPI
-- =============================================================================

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

-- =============================================================================
-- 31. MÓDULO: ALERTAS SANITARIAS (RECALL)
-- =============================================================================

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
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS alerta_lote (
    id_alerta INT REFERENCES alertas_sanitarias(id_alerta) ON DELETE CASCADE,
    id_lote INT REFERENCES lotes(id_lote) ON DELETE CASCADE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_alerta, id_lote)
);

-- =============================================================================
-- 32. MÓDULO: DEVOLUCIONES
-- =============================================================================

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
    es_por_recall BOOLEAN DEFAULT FALSE
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

-- =============================================================================
-- 33. MÓDULO: ATENCIÓN AL CLIENTE
-- =============================================================================

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

-- =============================================================================
-- 34. MÓDULO: NOTIFICACIONES
-- =============================================================================

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

-- =============================================================================
-- 35. MÓDULO: PAGOS ELECTRÓNICOS
-- =============================================================================

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

-- =============================================================================
-- 36. MÓDULO: REPORTES PROGRAMADOS
-- =============================================================================

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
-- 37. ÍNDICES GENERALES
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

-- Índices para seguros
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

-- Índices para crédito
CREATE INDEX IF NOT EXISTS idx_clientes_permite_credito ON clientes(permite_credito);
CREATE INDEX IF NOT EXISTS idx_clientes_saldo ON clientes(saldo_pendiente);
CREATE INDEX IF NOT EXISTS idx_ventas_vencimiento ON ventas(fecha_vencimiento_pago);
CREATE INDEX IF NOT EXISTS idx_historial_credito_cliente ON historial_credito_cliente(id_cliente, fecha_creacion);
CREATE INDEX IF NOT EXISTS idx_limites_credito_cliente ON limites_credito_cliente(id_cliente, activo);
CREATE INDEX IF NOT EXISTS idx_abonos_credito_venta ON abonos_credito(id_venta);

-- =============================================================================
-- 38. FUNCIONES Y TRIGGERS
-- =============================================================================

-- Función para actualizar el stock del lote cuando hay cambios en inventario
CREATE OR REPLACE FUNCTION actualizar_stock_lote()
RETURNS TRIGGER AS $$
BEGIN
    -- Actualizar la cantidad_actual del lote sumando todas las sucursales
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

CREATE TRIGGER tg_actualizar_stock_lote
AFTER INSERT OR UPDATE OR DELETE ON inventario
FOR EACH ROW
EXECUTE FUNCTION actualizar_stock_lote();

-- Función para registrar movimiento de inventario
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

-- Trigger para compras (entrada de inventario)
CREATE OR REPLACE FUNCTION trg_compra_actualiza_inventario()
RETURNS TRIGGER AS $$
DECLARE
    v_sucursal INT;
    v_usuario INT;
BEGIN
    IF NEW.cantidad <= 0 THEN
        RAISE EXCEPTION 'Cantidad inválida en compra';
    END IF;

    SELECT id_sucursal, id_usuario INTO v_sucursal, v_usuario
    FROM compras WHERE id_compra = NEW.id_compra;

    -- Actualizar o insertar en inventario
    INSERT INTO inventario (id_lote, id_sucursal, cantidad)
    VALUES (NEW.id_lote, v_sucursal, NEW.cantidad)
    ON CONFLICT (id_lote, id_sucursal)
    DO UPDATE SET cantidad = inventario.cantidad + NEW.cantidad;

    -- Registrar movimiento
    PERFORM registrar_movimiento_inventario(
        NEW.id_lote, v_sucursal, 'ENTRADA', NEW.cantidad, 
        'Compra de proveedor', NEW.id_compra::VARCHAR, v_usuario
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_detalle_compra_ai ON detalle_compra;
CREATE TRIGGER tg_detalle_compra_ai AFTER INSERT ON detalle_compra
FOR EACH ROW EXECUTE FUNCTION trg_compra_actualiza_inventario();

-- Trigger para ventas (salida de inventario)
CREATE OR REPLACE FUNCTION trg_venta_actualiza_inventario()
RETURNS TRIGGER AS $$
DECLARE
    v_usuario INT;
    v_sucursal INT;
    v_estado_lote VARCHAR(20);
BEGIN
    IF NEW.cantidad <= 0 THEN
        RAISE EXCEPTION 'Cantidad inválida en venta';
    END IF;

    SELECT id_usuario, id_sucursal INTO v_usuario, v_sucursal
    FROM ventas WHERE id_venta = NEW.id_venta;

    IF NEW.id_lote IS NOT NULL THEN
        -- Verificar estado del lote
        SELECT estado INTO v_estado_lote FROM lotes WHERE id_lote = NEW.id_lote;
        IF v_estado_lote != 'ACTIVO' THEN
            RAISE EXCEPTION 'Lote no disponible (Estado: %)', v_estado_lote;
        END IF;

        -- Descontar del inventario
        UPDATE inventario 
        SET cantidad = cantidad - NEW.cantidad 
        WHERE id_lote = NEW.id_lote AND id_sucursal = v_sucursal AND cantidad >= NEW.cantidad;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'Stock insuficiente para lote % en sucursal %', NEW.id_lote, v_sucursal;
        END IF;

        -- Registrar movimiento
        PERFORM registrar_movimiento_inventario(
            NEW.id_lote, v_sucursal, 'SALIDA', NEW.cantidad, 
            'Venta', NEW.id_venta::VARCHAR, v_usuario
        );
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS tg_detalle_venta_ai ON detalle_venta;
CREATE TRIGGER tg_detalle_venta_ai AFTER INSERT ON detalle_venta
FOR EACH ROW EXECUTE FUNCTION trg_venta_actualiza_inventario();

-- Función para recalcular totales de venta
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

-- Función para actualizar nombre_completo del medicamento
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
FOR EACH ROW
EXECUTE FUNCTION actualizar_nombre_completo();

-- Función para asegurar que la presentación existe
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
FOR EACH ROW
EXECUTE FUNCTION asegurar_presentacion();

-- =============================================================================
-- 39. FUNCIONES PARA CRÉDITO
-- =============================================================================

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
FOR EACH ROW
EXECUTE FUNCTION actualizar_saldo_cliente();

CREATE OR REPLACE FUNCTION actualizar_saldo_abono()
RETURNS TRIGGER AS $$
DECLARE
    v_id_cliente INT;
    v_total_venta NUMERIC;
    v_abonos_totales NUMERIC;
    v_saldo_anterior NUMERIC;
    v_nuevo_saldo NUMERIC;
BEGIN
    -- Obtener datos de la venta
    SELECT id_cliente, total, COALESCE(abonos_acumulados, 0) 
    INTO v_id_cliente, v_total_venta, v_abonos_totales
    FROM ventas WHERE id_venta = NEW.id_venta;
    
    -- Calcular saldo anterior y nuevo
    v_saldo_anterior := v_total_venta - v_abonos_totales;
    v_nuevo_saldo := v_saldo_anterior - NEW.monto;
    
    -- Actualizar abonos_acumulados en la venta
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
    
    -- Actualizar saldo_pendiente del cliente (solo si el saldo es mayor o igual al abono)
    UPDATE clientes 
    SET saldo_pendiente = GREATEST(saldo_pendiente - NEW.monto, 0)
    WHERE id_cliente = v_id_cliente;
    
    -- Insertar en historial de crédito
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

-- Recrear el trigger
DROP TRIGGER IF EXISTS tg_abono_actualiza_saldo ON abonos_credito;
CREATE TRIGGER tg_abono_actualiza_saldo
AFTER INSERT ON abonos_credito
FOR EACH ROW
EXECUTE FUNCTION actualizar_saldo_abono();

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
    SELECT valor::BOOLEAN INTO v_config_activo 
    FROM configuracion_credito 
    WHERE clave = 'credito_activo';
    
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
    
    SELECT * INTO v_limite FROM limites_credito_cliente 
    WHERE id_cliente = p_id_cliente AND activo = TRUE;
    
    IF v_limite.id_limite IS NULL THEN
        SELECT valor::NUMERIC INTO v_limite.limite_maximo 
        FROM configuracion_credito 
        WHERE clave = 'limite_credito_default';
        v_limite.limite_utilizado := v_cliente.saldo_pendiente;
    END IF;
    
    IF (v_cliente.saldo_pendiente + p_monto) <= v_limite.limite_maximo THEN
        RETURN QUERY SELECT 
            TRUE, 
            'Crédito disponible'::TEXT, 
            v_cliente.saldo_pendiente, 
            v_limite.limite_maximo - v_cliente.saldo_pendiente;
    ELSE
        RETURN QUERY SELECT 
            FALSE, 
            'El cliente excede su límite de crédito. Límite: RD$ ' || 
            TO_CHAR(v_limite.limite_maximo, 'FM999,999,999.00') || 
            ', Saldo actual: RD$ ' || TO_CHAR(v_cliente.saldo_pendiente, 'FM999,999,999.00')::TEXT, 
            v_cliente.saldo_pendiente, 
            v_limite.limite_maximo - v_cliente.saldo_pendiente;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- =============================================================================
-- 40. FUNCIONES PARA SEGUROS MÉDICOS
-- =============================================================================

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
    
    -- Buscar póliza activa
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
    
    -- Buscar cobertura específica
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
    
    -- Usar porcentaje de la póliza o aseguradora
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

-- =============================================================================
-- 41. FUNCIONES PARA MÚLTIPLES DESCUENTOS
-- =============================================================================

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

-- =============================================================================
-- 42. FUNCIONES PARA REPARTIDORES
-- =============================================================================

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

-- =============================================================================
-- 43. VISTAS ÚTILES
-- =============================================================================

CREATE OR REPLACE VIEW vista_inventario_actual AS
SELECT 
    l.numero_lote,
    l.fecha_vencimiento,
    l.estado,
    m.nombre AS medicamento,
    m.concentracion,
    u.abreviatura AS unidad,
    p.nombre AS presentacion,
    c.nombre AS categoria,
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
    m.id_medicamento,
    m.nombre,
    m.concentracion,
    u.nombre as unidad,
    u.abreviatura,
    p.nombre as presentacion,
    c.nombre as categoria,
    l.nombre as laboratorio,
    m.requiere_receta,
    m.exento_itbis,
    prod.precio,
    CONCAT_WS(' ', m.nombre, m.concentracion, u.abreviatura, p.nombre) as nombre_completo,
    m.stock_minimo,
    m.stock_maximo,
    m.punto_reorden,
    m.fecha_registro
FROM medicamentos m
LEFT JOIN unidades_medida u ON m.id_unidad = u.id_unidad
LEFT JOIN presentaciones p ON m.id_presentacion = p.id_presentacion
LEFT JOIN categorias c ON m.id_categoria = c.id_categoria
LEFT JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
LEFT JOIN productos prod ON m.id_producto = prod.id_producto;

CREATE OR REPLACE VIEW vista_clientes_credito AS
SELECT 
    c.id_cliente,
    c.nombre,
    c.permite_credito,
    c.saldo_pendiente,
    COALESCE(lc.limite_maximo, (SELECT valor::NUMERIC FROM configuracion_credito WHERE clave = 'limite_credito_default')) AS limite_maximo,
    COALESCE(lc.limite_maximo, (SELECT valor::NUMERIC FROM configuracion_credito WHERE clave = 'limite_credito_default')) - c.saldo_pendiente AS limite_disponible,
    c.fecha_ultima_compra_credito,
    c.dias_mora
FROM clientes c
LEFT JOIN limites_credito_cliente lc ON c.id_cliente = lc.id_cliente AND lc.activo = TRUE
WHERE c.permite_credito = TRUE;

CREATE OR REPLACE VIEW vista_ventas_credito_pendientes AS
SELECT 
    v.id_venta,
    v.numero_documento,
    v.fecha,
    v.total,
    v.abonos_acumulados,
    v.total - v.abonos_acumulados AS saldo_pendiente,
    v.fecha_vencimiento_pago,
    v.estado_pago,
    CASE 
        WHEN v.fecha_vencimiento_pago < CURRENT_DATE AND v.estado_pago != 'PAGADO' 
        THEN CURRENT_DATE - v.fecha_vencimiento_pago 
        ELSE 0 
    END AS dias_mora,
    c.id_cliente,
    c.nombre AS cliente_nombre,
    u.nombre AS vendedor_nombre
FROM ventas v
JOIN clientes c ON v.id_cliente = c.id_cliente
JOIN usuarios u ON v.id_usuario = u.id_usuario
WHERE v.es_credito = TRUE AND v.estado_pago != 'PAGADO'
ORDER BY v.fecha_vencimiento_pago ASC;

CREATE OR REPLACE VIEW vista_clientes_seguro AS
SELECT 
    c.id_cliente,
    c.nombre,
    c.tiene_seguro,
    p.id_poliza,
    p.numero_poliza,
    a.id_aseguradora,
    a.nombre AS aseguradora,
    a.porcentaje_cobertura_default,
    p.cobertura_porcentaje,
    p.fecha_inicio,
    p.fecha_fin
FROM clientes c
LEFT JOIN polizas p ON c.id_cliente = p.id_cliente AND p.activo = TRUE
LEFT JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
WHERE c.tiene_seguro = TRUE;

CREATE OR REPLACE VIEW vista_contactos_clientes AS
SELECT 
    c.id_cliente,
    c.nombre,
    t.numero AS telefono,
    t.tipo AS tipo_telefono,
    t.whatsapp,
    cor.email,
    cor.tipo AS tipo_email
FROM clientes c
LEFT JOIN cliente_telefono ct ON c.id_cliente = ct.id_cliente
LEFT JOIN telefonos t ON ct.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN cliente_correo cc ON c.id_cliente = cc.id_cliente
LEFT JOIN correos cor ON cc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_proveedores AS
SELECT 
    p.id_proveedor,
    p.nombre,
    t.numero AS telefono,
    t.tipo AS tipo_telefono,
    cor.email AS email,
    cor.tipo AS tipo_email
FROM proveedores p
LEFT JOIN proveedor_telefono pt ON p.id_proveedor = pt.id_proveedor
LEFT JOIN telefonos t ON pt.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN proveedor_correo pc ON p.id_proveedor = pc.id_proveedor
LEFT JOIN correos cor ON pc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_usuarios AS
SELECT 
    u.id_usuario,
    u.nombre,
    u.usuario,
    t.numero AS telefono,
    cor.email AS email
FROM usuarios u
LEFT JOIN usuario_telefono ut ON u.id_usuario = ut.id_usuario
LEFT JOIN telefonos t ON ut.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN usuario_correo uc ON u.id_usuario = uc.id_usuario
LEFT JOIN correos cor ON uc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_repartidores AS
SELECT 
    r.id_repartidor,
    r.nombre,
    r.telefono_emergencia,
    t.numero AS telefono,
    t.tipo AS tipo_telefono,
    cor.email AS email
FROM repartidores r
LEFT JOIN repartidor_telefono rt ON r.id_repartidor = rt.id_repartidor
LEFT JOIN telefonos t ON rt.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN repartidor_correo rc ON r.id_repartidor = rc.id_repartidor
LEFT JOIN correos cor ON rc.id_correo = cor.id_correo AND cor.activo = TRUE;

CREATE OR REPLACE VIEW vista_contactos_empresa AS
SELECT 
    e.id_empresa,
    e.nombre,
    t.numero AS telefono,
    cor.email AS email
FROM empresa e
LEFT JOIN empresa_telefono et ON e.id_empresa = et.id_empresa
LEFT JOIN telefonos t ON et.id_telefono = t.id_telefono AND t.activo = TRUE
LEFT JOIN empresa_correo ec ON e.id_empresa = ec.id_empresa
LEFT JOIN correos cor ON ec.id_correo = cor.id_correo AND cor.activo = TRUE;

-- VISTA PARA PRESENTACIONES CON SUS UNIDADES DE MEDIDA Y CATEGORÍAS ASIGNADAS
CREATE OR REPLACE VIEW vista_presentaciones_completo AS
SELECT 
    p.id_presentacion,
    p.nombre as presentacion,
    p.descripcion,
    p.activo,
    p.fecha_registro,
    u.id_unidad,
    u.nombre as unidad,
    u.abreviatura,
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
-- 44. DATOS DE PRUEBA (con ON CONFLICT para evitar duplicados)
-- =============================================================================

INSERT INTO unidades_medida (nombre, abreviatura) VALUES
('Miligramos', 'mg'),
('Gramos', 'g'),
('Mililitros', 'ml'),
('Tabletas', 'tab'),
('Cápsulas', 'cap')
ON CONFLICT (id_unidad) DO NOTHING;

INSERT INTO presentaciones (nombre, id_unidad, activo) VALUES
('Tabletas', 4, TRUE),
('Cápsulas', 5, TRUE),
('Jarabe', 3, TRUE),
('Inyección', 3, TRUE),
('Gotas', 3, TRUE)
ON CONFLICT (id_presentacion) DO NOTHING;

INSERT INTO categorias (nombre, activo) VALUES
('Analgésicos', TRUE),
('Antibióticos', TRUE),
('Vitaminas', TRUE),
('Jarabes', TRUE),
('Antialérgicos', TRUE)
ON CONFLICT (id_categoria) DO NOTHING;

-- Insertar relaciones categoría-presentación (solo si no existen)
INSERT INTO categoria_presentacion (id_categoria, id_presentacion)
SELECT c.id_categoria, p.id_presentacion
FROM (VALUES 
    (1, 1), (1, 2), (1, 5),  -- Analgésicos: Tabletas, Cápsulas, Gotas
    (2, 1), (2, 2), (2, 3),  -- Antibióticos: Tabletas, Cápsulas, Jarabe
    (3, 1), (3, 2), (3, 5),  -- Vitaminas: Tabletas, Cápsulas, Gotas
    (4, 3),                   -- Jarabes: Jarabe
    (5, 1), (5, 2)           -- Antialérgicos: Tabletas, Cápsulas
) AS tmp(cat_id, pres_id)
JOIN categorias c ON c.id_categoria = tmp.cat_id
JOIN presentaciones p ON p.id_presentacion = tmp.pres_id
WHERE NOT EXISTS (
    SELECT 1 FROM categoria_presentacion cp 
    WHERE cp.id_categoria = c.id_categoria AND cp.id_presentacion = p.id_presentacion
);

INSERT INTO telefonos (numero, tipo, whatsapp) VALUES
('8091110000', 'PRINCIPAL', TRUE),
('8092220000', 'PRINCIPAL', FALSE),
('8093330000', 'PRINCIPAL', FALSE),
('8094440000', 'TRABAJO', FALSE),
('8095550000', 'PRINCIPAL', TRUE)
ON CONFLICT (id_telefono) DO NOTHING;

INSERT INTO correos (email, tipo, verificado) VALUES
('pedro@mail.com', 'PRINCIPAL', TRUE),
('laura@mail.com', 'PRINCIPAL', TRUE),
('miguel@mail.com', 'PRINCIPAL', FALSE),
('ana@farmacia.com', 'TRABAJO', TRUE),
('admin@farmacia.com', 'PRINCIPAL', TRUE)
ON CONFLICT (id_correo) DO NOTHING;

INSERT INTO empresa (nombre, rnc, direccion, logo_url) VALUES
('Farmacia Salud+', '101010101', 'Av Central #1', NULL)
ON CONFLICT (id_empresa) DO NOTHING;

INSERT INTO empresa_telefono (id_empresa, id_telefono) 
SELECT 1, 4 WHERE NOT EXISTS (SELECT 1 FROM empresa_telefono WHERE id_empresa = 1 AND id_telefono = 4);

INSERT INTO empresa_correo (id_empresa, id_correo) 
SELECT 1, 5 WHERE NOT EXISTS (SELECT 1 FROM empresa_correo WHERE id_empresa = 1 AND id_correo = 5);

INSERT INTO sucursales (id_empresa, nombre, direccion, telefono, estado) VALUES
(1, 'Sucursal Principal', 'Calle 10, Edificio A', '809-555-0001', TRUE)
ON CONFLICT (id_sucursal) DO NOTHING;

INSERT INTO roles (nombre, descripcion) VALUES
('Administrador', 'Acceso total al sistema'),
('Cajero', 'Realiza ventas'),
('Inventario', 'Gestiona stock'),
('Supervisor', 'Revisa reportes'),
('Soporte', 'Mantenimiento del sistema')
ON CONFLICT (id_rol) DO NOTHING;

INSERT INTO usuarios (nombre, usuario, contrasena, id_rol, id_sucursal) VALUES
('Administrador General', 'admin', 'admin123', 1, 1),
('Ana Lopez', 'ana', '1234', 4, 1),
('Carlos Ruiz', 'carlos', '1234', 5, 1),
('Maria Gomez', 'maria', '1234', 2, 1),
('Luis Torres', 'luis', '1234', 3, 1)
ON CONFLICT (id_usuario) DO NOTHING;

INSERT INTO usuario_telefono (id_usuario, id_telefono) 
SELECT 1, 4 WHERE NOT EXISTS (SELECT 1 FROM usuario_telefono WHERE id_usuario = 1 AND id_telefono = 4);

INSERT INTO usuario_telefono (id_usuario, id_telefono) 
SELECT 2, 5 WHERE NOT EXISTS (SELECT 1 FROM usuario_telefono WHERE id_usuario = 2 AND id_telefono = 5);

INSERT INTO usuario_correo (id_usuario, id_correo) 
SELECT 1, 5 WHERE NOT EXISTS (SELECT 1 FROM usuario_correo WHERE id_usuario = 1 AND id_correo = 5);

INSERT INTO usuario_correo (id_usuario, id_correo) 
SELECT 2, 4 WHERE NOT EXISTS (SELECT 1 FROM usuario_correo WHERE id_usuario = 2 AND id_correo = 4);

INSERT INTO laboratorios (nombre, pais) VALUES
('Laboratorios ABC', 'República Dominicana'),
('Pharma International', 'Estados Unidos'),
('Medicamentos del Caribe', 'República Dominicana')
ON CONFLICT (id_laboratorio) DO NOTHING;

INSERT INTO proveedores (nombre, direccion, rnc) VALUES
('Distribuidora ABC', 'Av. Principal #123', '101010101'),
('FarmaSupply', 'Calle Secundaria #45', '202020202')
ON CONFLICT (id_proveedor) DO NOTHING;

INSERT INTO proveedor_telefono (id_proveedor, id_telefono) 
SELECT 1, 4 WHERE NOT EXISTS (SELECT 1 FROM proveedor_telefono WHERE id_proveedor = 1 AND id_telefono = 4);

INSERT INTO proveedor_telefono (id_proveedor, id_telefono) 
SELECT 2, 5 WHERE NOT EXISTS (SELECT 1 FROM proveedor_telefono WHERE id_proveedor = 2 AND id_telefono = 5);

INSERT INTO proveedor_correo (id_proveedor, id_correo) 
SELECT 1, 4 WHERE NOT EXISTS (SELECT 1 FROM proveedor_correo WHERE id_proveedor = 1 AND id_correo = 4);

INSERT INTO proveedor_correo (id_proveedor, id_correo) 
SELECT 2, 5 WHERE NOT EXISTS (SELECT 1 FROM proveedor_correo WHERE id_proveedor = 2 AND id_correo = 5);

-- Medicamentos con nombre separado de concentración
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

-- Actualizar id_producto en medicamentos
UPDATE medicamentos SET id_producto = 1 WHERE id_medicamento = 1 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 2 WHERE id_medicamento = 2 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 3 WHERE id_medicamento = 3 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 4 WHERE id_medicamento = 4 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 5 WHERE id_medicamento = 5 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 6 WHERE id_medicamento = 6 AND id_producto IS NULL;
UPDATE medicamentos SET id_producto = 7 WHERE id_medicamento = 7 AND id_producto IS NULL;

INSERT INTO niveles_cliente (nombre, puntos_minimos, descuento_porcentaje) VALUES
('BRONCE', 0, 0),
('PLATA', 500, 5),
('ORO', 1500, 10),
('PLATINO', 5000, 15)
ON CONFLICT (id_nivel) DO NOTHING;

INSERT INTO clientes (nombre, direccion, barrio, id_nivel, permite_credito, tiene_seguro) VALUES
('Pedro Martinez', 'Calle 1 #23', 'Los Jardines', 1, TRUE, TRUE),
('Laura Diaz', 'Av. Independencia #45', 'Centro', 2, TRUE, TRUE),
('Miguel Santos', 'Calle 2 #67', 'Bella Vista', 1, FALSE, FALSE)
ON CONFLICT (id_cliente) DO NOTHING;

INSERT INTO cliente_telefono (id_cliente, id_telefono) 
SELECT 1, 1 WHERE NOT EXISTS (SELECT 1 FROM cliente_telefono WHERE id_cliente = 1 AND id_telefono = 1);

INSERT INTO cliente_telefono (id_cliente, id_telefono) 
SELECT 2, 2 WHERE NOT EXISTS (SELECT 1 FROM cliente_telefono WHERE id_cliente = 2 AND id_telefono = 2);

INSERT INTO cliente_telefono (id_cliente, id_telefono) 
SELECT 3, 3 WHERE NOT EXISTS (SELECT 1 FROM cliente_telefono WHERE id_cliente = 3 AND id_telefono = 3);

INSERT INTO cliente_correo (id_cliente, id_correo) 
SELECT 1, 1 WHERE NOT EXISTS (SELECT 1 FROM cliente_correo WHERE id_cliente = 1 AND id_correo = 1);

INSERT INTO cliente_correo (id_cliente, id_correo) 
SELECT 2, 2 WHERE NOT EXISTS (SELECT 1 FROM cliente_correo WHERE id_cliente = 2 AND id_correo = 2);

INSERT INTO cliente_correo (id_cliente, id_correo) 
SELECT 3, 3 WHERE NOT EXISTS (SELECT 1 FROM cliente_correo WHERE id_cliente = 3 AND id_correo = 3);

INSERT INTO tarjetas_fidelidad (id_cliente, codigo, puntos_acumulados) VALUES
(1, 'TARJ-001', 150),
(2, 'TARJ-002', 650),
(3, 'TARJ-003', 50)
ON CONFLICT (id_tarjeta) DO NOTHING;

INSERT INTO estado_compra (nombre) VALUES
('PENDIENTE'), ('ENVIADA'), ('PARCIAL'), ('COMPLETADA'), ('ANULADA')
ON CONFLICT (id_estado) DO NOTHING;

INSERT INTO condicion_pago (nombre, dias_plazo) VALUES
('Contado', 0), ('15 días', 15), ('30 días', 30)
ON CONFLICT (id_condicion) DO NOTHING;

INSERT INTO metodos_pago (nombre) VALUES
('Efectivo'), ('Tarjeta'), ('Transferencia')
ON CONFLICT (id_metodo) DO NOTHING;

INSERT INTO tipo_devolucion (nombre, descripcion) VALUES
('CLIENTE', 'Devolución realizada por un cliente'),
('PROVEEDOR', 'Devolución al proveedor por compra'),
('MERMA', 'Producto dañado o vencido que se descarta'),
('AJUSTE', 'Ajuste de inventario por otros motivos')
ON CONFLICT (id_tipo) DO NOTHING;

INSERT INTO estado_devolucion (nombre) VALUES
('SOLICITADA'), ('APROBADA'), ('RECHAZADA'), ('COMPLETADA'), ('ANULADA')
ON CONFLICT (id_estado) DO NOTHING;

INSERT INTO repartidores (nombre, telefono_emergencia, tipo_identificacion, numero_identificacion, licencia_conducir) VALUES
('Juan Pérez', '8097770000', 'CEDULA', '001-1234567-8', 'LIC-001'),
('Carlos Gómez', '8097770000', 'CEDULA', '002-7654321-9', 'LIC-002')
ON CONFLICT (id_repartidor) DO NOTHING;

INSERT INTO repartidor_telefono (id_repartidor, id_telefono) 
SELECT 1, 1 WHERE NOT EXISTS (SELECT 1 FROM repartidor_telefono WHERE id_repartidor = 1 AND id_telefono = 1);

INSERT INTO repartidor_telefono (id_repartidor, id_telefono) 
SELECT 2, 2 WHERE NOT EXISTS (SELECT 1 FROM repartidor_telefono WHERE id_repartidor = 2 AND id_telefono = 2);

INSERT INTO repartidor_correo (id_repartidor, id_correo) 
SELECT 1, 1 WHERE NOT EXISTS (SELECT 1 FROM repartidor_correo WHERE id_repartidor = 1 AND id_correo = 1);

INSERT INTO repartidor_correo (id_repartidor, id_correo) 
SELECT 2, 2 WHERE NOT EXISTS (SELECT 1 FROM repartidor_correo WHERE id_repartidor = 2 AND id_correo = 2);

INSERT INTO vehiculos (id_repartidor, tipo, placa, marca, modelo) VALUES
(1, 'MOTO', 'M001-ABC', 'Yamaha', 'FZ16'),
(2, 'BICICLETA', NULL, 'GW', 'Ranger')
ON CONFLICT (id_vehiculo) DO NOTHING;

INSERT INTO horarios_repartidor (id_repartidor, dia_semana, hora_inicio, hora_fin) VALUES
(1, 1, '08:00', '17:00'), (1, 2, '08:00', '17:00'), (1, 3, '08:00', '17:00'),
(1, 4, '08:00', '17:00'), (1, 5, '08:00', '17:00'),
(2, 1, '09:00', '18:00'), (2, 2, '09:00', '18:00'), (2, 3, '09:00', '18:00'),
(2, 4, '09:00', '18:00'), (2, 5, '09:00', '18:00')
ON CONFLICT (id_horario) DO NOTHING;

INSERT INTO tarifas_envio (distancia_min_km, distancia_max_km, costo) VALUES
(0, 2, 50.00),
(2, 5, 80.00),
(5, 10, 120.00),
(10, 999, 150.00)
ON CONFLICT (id_tarifa) DO NOTHING;

INSERT INTO tipo_descuento (nombre, descripcion, requiere_validacion_adicional, requiere_articulo_gratis, codigo_interno) VALUES
('PORCENTAJE', 'Descuento porcentual', FALSE, FALSE, 'PCT'),
('MONTO_FIJO', 'Descuento fijo', FALSE, FALSE, 'FIX'),
('X_Y', 'Lleva X paga Y', TRUE, TRUE, 'X_Y'),
('VOLUMEN', 'Descuento por cantidad', TRUE, FALSE, 'VOL'),
('ESPECIAL', 'Promociones especiales', TRUE, TRUE, 'SPC')
ON CONFLICT (id_tipo) DO NOTHING;

INSERT INTO limite_descuento_rol (id_rol, porcentaje_maximo, monto_maximo, requiere_aprobacion, aprobador_requerido, descripcion, creado_por) VALUES
(1, 100.00, NULL, FALSE, NULL, 'Administrador', 1),
(4, 20.00, 5000.00, FALSE, 1, 'Supervisor hasta 20%', 1),
(2, 10.00, 2000.00, TRUE, 4, 'Cajero hasta 10%', 1),
(3, 5.00, 1000.00, FALSE, NULL, 'Inventario hasta 5%', 1),
(5, 0.00, 0.00, FALSE, NULL, 'Soporte sin descuentos', 1)
ON CONFLICT (id_limite) DO NOTHING;

INSERT INTO descuentos (codigo, nombre, descripcion, id_tipo_descuento, valor_descuento, es_porcentaje,
    fecha_inicio, fecha_fin, aplica_todas_categorias, activo, combinable, creado_por)
VALUES 
('VIT10', '10% OFF Vitaminas', '10% descuento en vitaminas', 1, 10, TRUE, '2026-01-01', '2026-12-31', TRUE, TRUE, TRUE, 1),
('DIA15', '15% OFF Día', '15% descuento en toda la tienda', 1, 15, TRUE, '2026-01-01', '2026-12-31', TRUE, TRUE, FALSE, 1),
('BIENVENIDA', 'Bienvenida', '20% primera compra', 1, 20, TRUE, '2026-01-01', '2026-12-31', TRUE, TRUE, TRUE, 1)
ON CONFLICT (id_descuento) DO NOTHING;

INSERT INTO descuento_categoria (id_descuento, id_categoria) 
SELECT 1, 3 WHERE NOT EXISTS (SELECT 1 FROM descuento_categoria WHERE id_descuento = 1 AND id_categoria = 3);

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
(1, 1, 50, 10.00),
(1, 2, 40, 12.00),
(2, 3, 30, 25.00)
ON CONFLICT (id_detalle) DO NOTHING;

-- Insertar inventario inicial
INSERT INTO inventario (id_lote, id_sucursal, cantidad) VALUES
(1, 1, 100),
(2, 1, 50),
(3, 1, 30)
ON CONFLICT (id_lote, id_sucursal) DO NOTHING;

INSERT INTO ventas (numero_documento, id_usuario, id_cliente, id_condicion, id_sucursal, subtotal, itbis_total, total) VALUES
('FAC-0001', 4, 1, 1, 1, 60.00, 10.80, 70.80),
('FAC-0002', 4, 2, 1, 1, 18.00, 3.24, 21.24)
ON CONFLICT (id_venta) DO NOTHING;

INSERT INTO detalle_venta (id_venta, id_lote, cantidad, precio_unitario, subtotal) VALUES
(1, 1, 2, 15.00, 30.00),
(1, 2, 1, 18.00, 18.00),
(2, 2, 1, 18.00, 18.00)
ON CONFLICT (id_detalle) DO NOTHING;

INSERT INTO pagos (id_venta, id_metodo, monto) VALUES
(1, 1, 70.80),
(2, 2, 21.24)
ON CONFLICT (id_pago) DO NOTHING;

INSERT INTO acumulacion_puntos (id_venta, id_cliente, puntos_ganados) VALUES
(1, 1, 70.80),
(2, 2, 21.24)
ON CONFLICT (id_acumulacion) DO NOTHING;

INSERT INTO devoluciones (numero_documento, id_venta, id_sucursal, id_usuario, id_tipo, id_estado, motivo, monto_reembolso)
VALUES ('DEV-0001', 1, 1, 4, 1, 4, 'Producto defectuoso', 15.00)
ON CONFLICT (id_devolucion) DO NOTHING;

INSERT INTO detalle_devolucion (id_devolucion, id_lote, cantidad, precio_unitario)
VALUES (1, 1, 1, 15.00)
ON CONFLICT (id_detalle) DO NOTHING;

INSERT INTO reembolsos (id_devolucion, id_venta, monto, id_metodo_pago, id_usuario)
VALUES (1, 1, 15.00, 1, 4)
ON CONFLICT (id_reembolso) DO NOTHING;

INSERT INTO estado_entrega (nombre) VALUES
('PENDIENTE'), ('ASIGNADA'), ('EN_CAMINO'), ('ENTREGADA'), 
('CANCELADA'), ('REPROGRAMADA'), ('FALLIDA'), ('ACCIDENTE')
ON CONFLICT (id_estado) DO NOTHING;

INSERT INTO tipo_incidencia_delivery (nombre, descripcion, nivel_gravedad, requiere_documentacion) VALUES
('CLIENTE AUSENTE', 'Cliente no estaba', 2, FALSE),
('DIRECCION INCORRECTA', 'Dirección mal', 2, FALSE),
('RETRASO', 'Entrega con retraso', 1, FALSE),
('PRODUCTO DAÑADO', 'Producto dañado', 3, TRUE),
('ACCIDENTE', 'Accidente del repartidor', 5, TRUE)
ON CONFLICT (id_tipo_incidencia) DO NOTHING;

INSERT INTO entregas (
    id_venta, id_cliente, id_sucursal, numero_seguimiento,
    direccion_entrega, barrio_entrega, costo_entrega, creado_por, distancia_km
) VALUES (
    1, 1, 1, 'DEL-20250315-001',
    'Calle 1 #23', 'Los Jardines', 100.00, 4, 3.5
)
ON CONFLICT (id_entrega) DO NOTHING;

UPDATE entregas SET 
    id_repartidor = 1,
    id_estado = 2,
    fecha_asignada = CURRENT_TIMESTAMP,
    fecha_programada = CURRENT_TIMESTAMP + INTERVAL '30 minutes'
WHERE numero_seguimiento = 'DEL-20250315-001' AND id_repartidor IS NULL;

INSERT INTO historial_entrega (id_entrega, id_estado, observacion, id_usuario)
SELECT id_entrega, 2, 'Pedido asignado a Juan Pérez', 4
FROM entregas WHERE numero_seguimiento = 'DEL-20250315-001'
ON CONFLICT (id_historial) DO NOTHING;

INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, estado, costo_unitario, cantidad_inicial, cantidad_actual) 
VALUES (1, 'LOTE-VENCIDO-99', CURRENT_DATE - INTERVAL '10 days', 'ACTIVO', 100.00, 50, 50)
ON CONFLICT (id_lote) DO NOTHING;

INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, estado, costo_unitario, cantidad_inicial, cantidad_actual) 
VALUES (2, 'LOTE-PROX-88', CURRENT_DATE + INTERVAL '5 days', 'ACTIVO', 120.00, 25, 25)
ON CONFLICT (id_lote) DO NOTHING;

INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, estado, costo_unitario, cantidad_inicial, cantidad_actual) 
VALUES (3, 'LOTE-STOCK-77', CURRENT_DATE + INTERVAL '1 year', 'ACTIVO', 180.00, 200, 200)
ON CONFLICT (id_lote) DO NOTHING;

INSERT INTO inventario (id_lote, id_sucursal, cantidad) 
SELECT id_lote, 1, cantidad_actual FROM lotes WHERE numero_lote = 'LOTE-STOCK-77'
ON CONFLICT (id_lote, id_sucursal) DO NOTHING;

INSERT INTO configuracion_credito (clave, valor, descripcion) VALUES
('credito_activo', 'true', 'Activar/desactivar sistema de crédito globalmente'),
('credito_solo_registrados', 'true', 'Solo clientes registrados pueden tener crédito'),
('dias_mora_interes', '30', 'Días después de vencimiento para aplicar interés'),
('interes_mora_porcentaje', '5', 'Porcentaje de interés por mora'),
('notificar_vencimiento', 'true', 'Notificar clientes antes de vencimiento'),
('dias_notificacion', '5', 'Días antes para notificar vencimiento'),
('limite_credito_default', '5000', 'Límite de crédito por defecto para nuevos clientes')
ON CONFLICT (id_config) DO NOTHING;

INSERT INTO limites_credito_cliente (id_cliente, limite_maximo, dias_plazo_maximo) VALUES
(1, 10000.00, 30),
(2, 5000.00, 15)
ON CONFLICT (id_limite) DO NOTHING;

INSERT INTO aseguradoras (codigo, nombre, rnc, telefono, email, porcentaje_cobertura_default, cobertura_global, activo) VALUES
('ARS001', 'ARS Humano', '101234567', '809-555-0101', 'servicio@arshumano.com', 80.00, TRUE, TRUE),
('ARS002', 'ARS Universal', '102345678', '809-555-0202', 'contacto@arsuniversal.com', 75.00, TRUE, TRUE),
('ARS003', 'ARS Palic', '103456789', '809-555-0303', 'atencion@arspalic.com', 85.00, TRUE, TRUE)
ON CONFLICT (id_aseguradora) DO NOTHING;

INSERT INTO sucursal_aseguradora (id_sucursal, id_aseguradora) 
SELECT 1, 1 WHERE NOT EXISTS (SELECT 1 FROM sucursal_aseguradora WHERE id_sucursal = 1 AND id_aseguradora = 1);

INSERT INTO sucursal_aseguradora (id_sucursal, id_aseguradora) 
SELECT 1, 2 WHERE NOT EXISTS (SELECT 1 FROM sucursal_aseguradora WHERE id_sucursal = 1 AND id_aseguradora = 2);

INSERT INTO sucursal_aseguradora (id_sucursal, id_aseguradora) 
SELECT 1, 3 WHERE NOT EXISTS (SELECT 1 FROM sucursal_aseguradora WHERE id_sucursal = 1 AND id_aseguradora = 3);

INSERT INTO polizas (id_cliente, id_aseguradora, numero_poliza, numero_carnet, fecha_inicio, fecha_fin, cobertura_porcentaje) VALUES
(1, 1, 'POL-001-2024', 'CARNET-001', '2024-01-01', '2024-12-31', 80.00),
(2, 2, 'POL-002-2024', 'CARNET-002', '2024-01-01', '2024-12-31', 75.00)
ON CONFLICT (id_poliza) DO NOTHING;

INSERT INTO cobertura_medicamentos (id_poliza, id_medicamento, cobertura_porcentaje, requiere_autorizacion) VALUES
(1, 1, 90.00, FALSE),
(1, 2, 85.00, FALSE),
(1, 4, 100.00, TRUE),
(2, 6, 50.00, FALSE)
ON CONFLICT (id_cobertura) DO NOTHING;

INSERT INTO autorizaciones_seguro (numero_autorizacion, id_cliente, id_aseguradora, id_medicamento, id_poliza, fecha_expiracion, cantidad_autorizada, estado, autorizado_por)
VALUES 
('AUT-001-2024', 1, 1, 4, 1, '2024-12-31', 30, 'APROBADO', 1),
('AUT-002-2024', 2, 2, 2, 2, '2024-12-31', 20, 'APROBADO', 1)
ON CONFLICT (id_autorizacion) DO NOTHING;

INSERT INTO version_esquema (version, descripcion) VALUES ('3.0.0', 'Versión completa con lotes corregidos (cantidad_inicial y cantidad_actual)')
ON CONFLICT (id_version) DO NOTHING;

-- =============================================================================
-- 45. VERIFICACIÓN FINAL
-- =============================================================================

-- Actualizar nombres completos de medicamentos
UPDATE medicamentos SET nombre_completo = TRIM(CONCAT_WS(' ', nombre, concentracion, 
    (SELECT abreviatura FROM unidades_medida WHERE id_unidad = medicamentos.id_unidad),
    (SELECT nombre FROM presentaciones WHERE id_presentacion = medicamentos.id_presentacion)))
WHERE nombre_completo IS NULL;

-- Agregar columnas faltantes a laboratorios
ALTER TABLE laboratorios ADD COLUMN IF NOT EXISTS descripcion TEXT;
ALTER TABLE laboratorios ADD COLUMN IF NOT EXISTS telefono2 VARCHAR(30);
ALTER TABLE laboratorios ADD COLUMN IF NOT EXISTS email2 VARCHAR(100);
ALTER TABLE laboratorios ADD COLUMN IF NOT EXISTS contacto_nombre VARCHAR(100);
ALTER TABLE laboratorios ADD COLUMN IF NOT EXISTS contacto_telefono VARCHAR(30);
ALTER TABLE laboratorios ADD COLUMN IF NOT EXISTS website VARCHAR(200);
ALTER TABLE laboratorios ADD COLUMN IF NOT EXISTS fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Verificar vistas
SELECT COUNT(*) as total_inventario FROM vista_inventario_actual;
SELECT COUNT(*) as total_medicamentos_completo FROM vista_medicamentos_completo;
SELECT COUNT(*) as total_clientes_credito FROM vista_clientes_credito;
SELECT COUNT(*) as total_ventas_credito FROM vista_ventas_credito_pendientes;
SELECT COUNT(*) as total_clientes_seguro FROM vista_clientes_seguro;
SELECT COUNT(*) as total_contactos_clientes FROM vista_contactos_clientes;
SELECT COUNT(*) as total_contactos_proveedores FROM vista_contactos_proveedores;
SELECT COUNT(*) as total_contactos_usuarios FROM vista_contactos_usuarios;
SELECT COUNT(*) as total_contactos_repartidores FROM vista_contactos_repartidores;
SELECT COUNT(*) as total_contactos_empresa FROM vista_contactos_empresa;
SELECT COUNT(*) as total_repartidores_disponibles FROM obtener_repartidores_disponibles();

-- Verificar vista de presentaciones completa
SELECT COUNT(*) as total_presentaciones_completo FROM vista_presentaciones_completo;

-- Verificar lotes con cantidades
SELECT id_lote, numero_lote, cantidad_inicial, cantidad_actual, estado FROM lotes;

-- Verificar inventario
SELECT l.numero_lote, s.nombre as sucursal, i.cantidad 
FROM inventario i
JOIN lotes l ON i.id_lote = l.id_lote
JOIN sucursales s ON i.id_sucursal = s.id_sucursal;

-- Mostrar resumen final
SELECT 'Total Categorías: ' || COUNT(*)::TEXT as resumen FROM categorias
UNION ALL
SELECT 'Total Presentaciones: ' || COUNT(*)::TEXT FROM presentaciones
UNION ALL
SELECT 'Total Relaciones Categoría-Presentación: ' || COUNT(*)::TEXT FROM categoria_presentacion
UNION ALL
SELECT 'Total Medicamentos: ' || COUNT(*)::TEXT FROM medicamentos
UNION ALL
SELECT 'Total Productos: ' || COUNT(*)::TEXT FROM productos
UNION ALL
SELECT 'Total Lotes: ' || COUNT(*)::TEXT FROM lotes
UNION ALL
SELECT 'Total Stock en Inventario: ' || SUM(cantidad)::TEXT FROM inventario;

ALTER TABLE lotes ADD COLUMN IF NOT EXISTS costo_lote NUMERIC(10,2);

ALTER TABLE lotes DROP CONSTRAINT IF EXISTS chk_lote_estado;
ALTER TABLE lotes ADD CONSTRAINT chk_lote_estado CHECK (estado IN ('ACTIVO', 'VENCIDO', 'RETIRADO', 'MERMA', 'DAÑADO'));

-- Tabla para registrar cambios de estado de lotes
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

-- Índice para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_historial_estado_lote ON historial_estado_lote(id_lote, fecha_cambio);


-- Crear tabla para registrar cambios de estado de lotes
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

-- Índice para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_historial_estado_lote ON historial_estado_lote(id_lote, fecha_cambio);

INSERT INTO historial_estado_lote (id_lote, estado_anterior, estado_nuevo, motivo, id_usuario, observaciones)
VALUES (11, 'ACTIVO', 'DAÑADO', 'Cambio registrado manualmente - historial recuperado', 1, 'El lote fue marcado como dañado previamente');

SELECT * FROM usuarios;
ALTER TABLE usuarios ADD COLUMN imagen_url TEXT;

UPDATE usuarios 
SET imagen_url = '/sistema-gestor-de-farmacias/assets/img/usuarios/69d18f4aeede4_admin.png'
WHERE id_usuario = 1;

-- =============================================================================
-- ACTUALIZACIÓN PARA MOVIMIENTOS DE INVENTARIO
-- =============================================================================

-- 1. Agregar columna observaciones a movimiento_inventario (si no existe)
ALTER TABLE movimiento_inventario ADD COLUMN IF NOT EXISTS observaciones TEXT;

-- 2. Verificar que la columna existe
SELECT column_name 
FROM information_schema.columns 
WHERE table_name = 'movimiento_inventario' 
ORDER BY ordinal_position;

-- 3. Actualizar el constraint de lotes para incluir DAÑADO (si no está)
ALTER TABLE lotes DROP CONSTRAINT IF EXISTS chk_lote_estado;
ALTER TABLE lotes ADD CONSTRAINT chk_lote_estado CHECK (estado IN ('ACTIVO', 'VENCIDO', 'RETIRADO', 'MERMA', 'DAÑADO'));

-- 4. Agregar columna costo_lote a lotes (si no existe)
ALTER TABLE lotes ADD COLUMN IF NOT EXISTS costo_lote NUMERIC(10,2);

-- 5. Mostrar estructura actual de movimiento_inventario
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'movimiento_inventario'
ORDER BY ordinal_position;


-- Agregar columna id_cliente a devoluciones
ALTER TABLE devoluciones ADD COLUMN IF NOT EXISTS id_cliente INT REFERENCES clientes(id_cliente);


-- Verificar que la columna se agregó correctamente
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'devoluciones' 
ORDER BY ordinal_position;

-- 1. Agregar columna estado si no existe
ALTER TABLE alertas_sanitarias ADD COLUMN IF NOT EXISTS estado VARCHAR(20) DEFAULT 'ACTIVA';

-- Agregar constraint con los estados correctos
ALTER TABLE alertas_sanitarias ADD CONSTRAINT chk_alerta_estado 
CHECK (estado IN ('ACTIVA', 'EN_INVESTIGACION', 'RESUELTA'));

-- Actualizar registros existentes (si hay alguno con estado diferente)
UPDATE alertas_sanitarias SET estado = 'ACTIVA' WHERE estado IS NULL OR estado NOT IN ('ACTIVA', 'EN_INVESTIGACION', 'RESUELTA');

-- Establecer estado por defecto
ALTER TABLE alertas_sanitarias ALTER COLUMN estado SET DEFAULT 'ACTIVA';

-- 2. Actualizar registros existentes (si hay alguno)
UPDATE alertas_sanitarias SET estado = 'ACTIVA' WHERE estado IS NULL;

-- 3. Eliminar constraint si existe (para evitar error)
ALTER TABLE alertas_sanitarias DROP CONSTRAINT IF EXISTS chk_alerta_estado;

-- 5. Verificar que la columna existe
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns 
WHERE table_name = 'alertas_sanitarias' 
ORDER BY ordinal_position;

-- =============================================================================
-- FUNCIÓN PARA GENERAR TODAS LAS ALERTAS DEL SISTEMA
-- =============================================================================

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
    -- =============================================================
    -- 1. ALERTAS DE VENCIMIENTO (próximos 30 días)
    -- =============================================================
    RETURN QUERY
    SELECT 
        'VENCIMIENTO'::VARCHAR(50) as tipo_alerta,
        m.nombre_completo::VARCHAR(200) as medicamento_nombre,
        l.numero_lote::VARCHAR(50),
        NULL::INT as cantidad,
        NULL::INT as stock_minimo,
        l.fecha_vencimiento,
        (l.fecha_vencimiento - CURRENT_DATE)::INT as dias_restantes,
        NULL::VARCHAR(20) as nivel_riesgo,
        NULL::VARCHAR(150) as entidad_emisora,
        NULL::VARCHAR(50) as numero_alerta,
        'vencimientos'::VARCHAR(100) as url_destino
    FROM lotes l
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    WHERE l.estado = 'ACTIVO' 
      AND l.fecha_vencimiento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days'
    ORDER BY l.fecha_vencimiento ASC
    LIMIT 20;
    
    -- =============================================================
    -- 2. ALERTAS DE STOCK CRÍTICO (cantidad <= stock_minimo)
    -- =============================================================
    RETURN QUERY
    SELECT 
        'STOCK_CRITICO'::VARCHAR(50) as tipo_alerta,
        m.nombre_completo::VARCHAR(200) as medicamento_nombre,
        l.numero_lote::VARCHAR(50),
        i.cantidad::INT,
        m.stock_minimo::INT,
        NULL::DATE as fecha_vencimiento,
        NULL::INT as dias_restantes,
        NULL::VARCHAR(20) as nivel_riesgo,
        NULL::VARCHAR(150) as entidad_emisora,
        NULL::VARCHAR(50) as numero_alerta,
        'alertas_stock'::VARCHAR(100) as url_destino
    FROM inventario i
    JOIN lotes l ON i.id_lote = l.id_lote
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    WHERE l.estado = 'ACTIVO' 
      AND i.cantidad <= m.stock_minimo
      AND i.cantidad > 0
    ORDER BY i.cantidad ASC
    LIMIT 20;
    
    -- =============================================================
    -- 3. ALERTAS DE STOCK AGOTADO (cantidad = 0)
    -- =============================================================
    RETURN QUERY
    SELECT 
        'STOCK_AGOTADO'::VARCHAR(50) as tipo_alerta,
        m.nombre_completo::VARCHAR(200) as medicamento_nombre,
        l.numero_lote::VARCHAR(50),
        i.cantidad::INT,
        m.stock_minimo::INT,
        NULL::DATE as fecha_vencimiento,
        NULL::INT as dias_restantes,
        NULL::VARCHAR(20) as nivel_riesgo,
        NULL::VARCHAR(150) as entidad_emisora,
        NULL::VARCHAR(50) as numero_alerta,
        'alertas_stock'::VARCHAR(100) as url_destino
    FROM inventario i
    JOIN lotes l ON i.id_lote = l.id_lote
    JOIN medicamentos m ON l.id_medicamento = m.id_medicamento
    WHERE l.estado = 'ACTIVO' 
      AND i.cantidad = 0
    ORDER BY m.nombre ASC
    LIMIT 20;
    
    -- =============================================================
    -- 4. ALERTAS DE RECALL ACTIVAS (alertas sanitarias activas)
    -- =============================================================
    RETURN QUERY
    SELECT 
        'RECALL'::VARCHAR(50) as tipo_alerta,
        a.descripcion::VARCHAR(200) as medicamento_nombre,
        NULL::VARCHAR(50) as numero_lote,
        NULL::INT as cantidad,
        NULL::INT as stock_minimo,
        NULL::DATE as fecha_vencimiento,
        NULL::INT as dias_restantes,
        a.nivel_riesgo::VARCHAR(20),
        a.entidad_emisora::VARCHAR(150),
        a.numero_alerta::VARCHAR(50),
        'recall'::VARCHAR(100) as url_destino
    FROM alertas_sanitarias a
    WHERE a.estado = 'ACTIVA'
    ORDER BY a.fecha_notificacion DESC
    LIMIT 20;
    
END;
$$ LANGUAGE plpgsql;

SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'ventas' 
ORDER BY ordinal_position;

-- Agregar columna referencia a la tabla pagos
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS referencia VARCHAR(100);

-- Agregar columna fecha_transaccion a la tabla pagos (con valor por defecto)
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS fecha_transaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Verificar que las columnas se agregaron correctamente
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'pagos' 
ORDER BY ordinal_position;

-- Agregar columna estado a la tabla pagos si no existe
ALTER TABLE pagos ADD COLUMN IF NOT EXISTS estado VARCHAR(20) DEFAULT 'COMPLETADO';

-- Actualizar registros existentes que tengan estado NULL
UPDATE pagos SET estado = 'COMPLETADO' WHERE estado IS NULL;

-- Verificar que la columna se agregó correctamente
SELECT column_name, data_type, is_nullable, column_default 
FROM information_schema.columns 
WHERE table_name = 'pagos' 
ORDER BY ordinal_position;

-- Verificar si la tabla existe
SELECT EXISTS (
    SELECT FROM information_schema.tables 
    WHERE table_name = 'notificaciones_sistema'
);

-- Si no existe, créala
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

-- Crear índice
CREATE INDEX IF NOT EXISTS idx_notificaciones_usuario ON notificaciones_sistema(id_usuario, leida, fecha_creacion DESC);

-- Insertar notificación de prueba
INSERT INTO notificaciones_sistema (id_usuario, titulo, mensaje, tipo, fecha_creacion, leida)
VALUES (1, 'Pago de prueba', 'Se ha registrado un pago de RD$ 500.00 para la venta FAC-000001', 'pago', NOW(), false);

-- Verificar que se insertó
SELECT * FROM notificaciones_sistema;

SELECT id_usuario, usuario FROM usuarios WHERE usuario = 'admin';

SELECT * FROM usuarios
SELECT * FROM roles


-- Actualizar pólizas con fechas vigentes
UPDATE polizas 
SET fecha_inicio = '2025-01-01', 
    fecha_fin = '2026-12-31' 
WHERE id_cliente IN (1, 2);

-- Verificar que las pólizas ahora están activas
SELECT p.id_cliente, c.nombre, p.numero_poliza, p.fecha_inicio, p.fecha_fin,
       CASE WHEN CURRENT_DATE BETWEEN p.fecha_inicio AND p.fecha_fin THEN 'ACTIVA' ELSE 'VENCIDA' END as estado
FROM polizas p
JOIN clientes c ON p.id_cliente = c.id_cliente;

-- Verificar que la función existe
SELECT proname, prosrc 
FROM pg_proc 
WHERE proname = 'calcular_cobertura_seguro';

-- Probar la función manualmente
SELECT * FROM calcular_cobertura_seguro(1, 1, 150.00, 2);

-- =============================================================================
-- ACTUALIZAR DATOS DE PRUEBA PARA SEGUROS (CON FECHAS VIGENTES)
-- =============================================================================

-- 1. Actualizar pólizas existentes con fechas vigentes
UPDATE polizas SET 
    fecha_inicio = '2025-01-01',
    fecha_fin = '2027-12-31',
    activo = TRUE
WHERE id_cliente IN (1, 2);

-- 2. Si no hay póliza para el cliente 1, insertar una
INSERT INTO polizas (id_cliente, id_aseguradora, numero_poliza, numero_carnet, fecha_inicio, fecha_fin, cobertura_porcentaje, activo)
SELECT 1, 1, 'POL-001-2025', 'CARNET-001', '2025-01-01', '2027-12-31', 80.00, TRUE
WHERE NOT EXISTS (SELECT 1 FROM polizas WHERE id_cliente = 1 AND activo = TRUE);

-- 3. Si no hay póliza para el cliente 2, insertar una
INSERT INTO polizas (id_cliente, id_aseguradora, numero_poliza, numero_carnet, fecha_inicio, fecha_fin, cobertura_porcentaje, activo)
SELECT 2, 2, 'POL-002-2025', 'CARNET-002', '2025-01-01', '2027-12-31', 75.00, TRUE
WHERE NOT EXISTS (SELECT 1 FROM polizas WHERE id_cliente = 2 AND activo = TRUE);

-- 4. Asegurar que los clientes tienen tiene_seguro = TRUE
UPDATE clientes SET tiene_seguro = TRUE WHERE id_cliente IN (1, 2);

-- 5. Verificar resultados
SELECT 
    c.id_cliente,
    c.nombre,
    c.tiene_seguro,
    p.id_poliza,
    p.numero_poliza,
    a.nombre as aseguradora,
    p.cobertura_porcentaje,
    p.fecha_inicio,
    p.fecha_fin,
    CASE WHEN CURRENT_DATE BETWEEN p.fecha_inicio AND p.fecha_fin THEN 'VIGENTE' ELSE 'VENCIDA' END as estado_poliza
FROM clientes c
LEFT JOIN polizas p ON c.id_cliente = p.id_cliente AND p.activo = TRUE
LEFT JOIN aseguradoras a ON p.id_aseguradora = a.id_aseguradora
WHERE c.tiene_seguro = TRUE;

UPDATE polizas SET 
    fecha_inicio = '2025-01-01',
    fecha_fin = '2027-12-31'
WHERE id_cliente IN (1, 2);

UPDATE polizas SET 
    fecha_inicio = '2025-01-01',
    fecha_fin = '2027-12-31',
    activo = TRUE
WHERE id_cliente IN (1, 2);

-- Verificar qué columnas existen en polizas
SELECT column_name 
FROM information_schema.columns 
WHERE table_name = 'polizas' 
ORDER BY ordinal_position;

-- Verificar estructura de abonos_credito
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'abonos_credito'
ORDER BY ordinal_position;

-- Verificar el estado actual de una venta específica (cambia el ID)
SELECT 
    id_venta,
    numero_documento,
    total,
    abonos_acumulados,
    total - COALESCE(abonos_acumulados, 0) as saldo_calculado,
    es_credito,
    estado_pago
FROM ventas 
WHERE id_venta = 1;  -- Cambia por el ID de la venta problemática

-- Ver los abonos registrados
SELECT * FROM abonos_credito WHERE id_venta = 1 ORDER BY id_abono;

-- Ver todos los triggers en la tabla detalle_venta
SELECT tgname, tgrelid::regclass as tabla, tgfoid::regproc as funcion
FROM pg_trigger 
WHERE tgrelid = 'detalle_venta'::regclass AND tgname NOT LIKE 'pg_%';

-- También verifica en ventas
SELECT tgname, tgrelid::regclass as tabla, tgfoid::regproc as funcion
FROM pg_trigger 
WHERE tgrelid = 'ventas'::regclass AND tgname NOT LIKE 'pg_%';

-- Verificar columnas de seguro en ventas
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'ventas' 
  AND column_name IN ('usa_seguro', 'monto_cubre_seguro', 'monto_paga_paciente')
ORDER BY ordinal_position;

-- Ver ventas a crédito con seguro
SELECT 
    id_venta,
    numero_documento,
    es_credito,
    usa_seguro,
    subtotal,
    itbis_total,
    total,
    monto_cubre_seguro,
    monto_paga_paciente
FROM ventas 
WHERE es_credito = true AND usa_seguro = true
ORDER BY id_venta DESC
LIMIT 10;

SELECT * FROM calcular_cobertura_seguro(1, 1, 150.00, 2);

-- Ver los datos de la venta recién creada
SELECT 
    id_venta,
    numero_documento,
    subtotal,
    itbis_total,
    total,
    usa_seguro,
    monto_cubre_seguro,
    monto_paga_paciente,
    CASE 
        WHEN usa_seguro = true AND monto_cubre_seguro = 0 THEN 'ERROR: Seguro no guardado'
        WHEN usa_seguro = true AND monto_cubre_seguro > 0 THEN 'OK: Seguro guardado'
        ELSE 'Sin seguro'
    END as estado
FROM ventas 
ORDER BY id_venta DESC 
LIMIT 5;

SELECT 
    numero_documento,
    total,
    usa_seguro,
    monto_cubre_seguro,
    CASE 
        WHEN usa_seguro = true AND monto_cubre_seguro > 0 THEN 'CON DESCUENTO'
        WHEN usa_seguro = true AND monto_cubre_seguro = 0 THEN 'SIN DESCUENTO'
        ELSE 'SIN SEGURO'
    END as estado
FROM ventas 
WHERE usa_seguro = true 
ORDER BY id_venta DESC 
LIMIT 5;

SELECT 
    id_venta,
    numero_documento,
    subtotal,
    itbis_total,
    total as total_con_descuento,
    monto_cubre_seguro,
    monto_paga_paciente,
    abonos_acumulados,
    total - COALESCE(abonos_acumulados, 0) as saldo_pendiente_correcto,
    estado_pago
FROM ventas 
WHERE usa_seguro = true 
ORDER BY id_venta DESC 
LIMIT 5;

SELECT 
    id_venta,
    numero_documento,
    subtotal,
    itbis_total,
    subtotal + itbis_total as total_original,
    total as total_con_descuento,
    monto_cubre_seguro,
    monto_paga_paciente
FROM ventas 
WHERE usa_seguro = true 
ORDER BY id_venta DESC 
LIMIT 5;

-- Corregir total y monto_paga_paciente para todas las ventas con seguro
UPDATE ventas 
SET 
    itbis_total = ROUND(subtotal * 0.18, 2),
    monto_cubre_seguro = CASE 
        WHEN usa_seguro = true AND monto_cubre_seguro = 0 THEN ROUND((subtotal + ROUND(subtotal * 0.18, 2)) * 0.8, 2)
        ELSE monto_cubre_seguro 
    END,
    total = ROUND((subtotal + ROUND(subtotal * 0.18, 2)) - monto_cubre_seguro, 2),
    monto_paga_paciente = ROUND((subtotal + ROUND(subtotal * 0.18, 2)) - monto_cubre_seguro, 2)
WHERE usa_seguro = true;

-- Corregir ventas que quedaron con saldo negativo (abonos mayores al total)
UPDATE ventas 
SET abonos_acumulados = total, estado_pago = 'PAGADO', fecha_pago_real = NOW()
WHERE es_credito = true AND total - COALESCE(abonos_acumulados, 0) < 0;

CREATE OR REPLACE FUNCTION actualizar_saldo_abono()
RETURNS TRIGGER AS $$
DECLARE
    v_id_cliente INT;
    v_total_venta NUMERIC;
    v_abonos_totales NUMERIC;
    v_saldo_anterior NUMERIC;
    v_nuevo_saldo NUMERIC;
BEGIN
    -- Obtener datos de la venta
    SELECT id_cliente, total, COALESCE(abonos_acumulados, 0) 
    INTO v_id_cliente, v_total_venta, v_abonos_totales
    FROM ventas WHERE id_venta = NEW.id_venta;
    
    -- Calcular saldo anterior y nuevo
    v_saldo_anterior := v_total_venta - v_abonos_totales;
    v_nuevo_saldo := v_saldo_anterior - NEW.monto;
    
    -- Actualizar venta (solo si el trigger no se ejecuta después de un update manual)
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
    
    -- Actualizar saldo_pendiente del cliente
    UPDATE clientes 
    SET saldo_pendiente = GREATEST(saldo_pendiente - NEW.monto, 0)
    WHERE id_cliente = v_id_cliente;
    
    -- Insertar en historial de crédito
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
FOR EACH ROW
EXECUTE FUNCTION actualizar_saldo_abono();

UPDATE ventas 
SET abonos_acumulados = total, 
    estado_pago = 'PAGADO', 
    fecha_pago_real = NOW()
WHERE total - COALESCE(abonos_acumulados, 0) < 0;

-- También corrige el saldo del cliente
UPDATE clientes c
SET saldo_pendiente = (
    SELECT COALESCE(SUM(v.total - COALESCE(v.abonos_acumulados, 0)), 0)
    FROM ventas v
    WHERE v.id_cliente = c.id_cliente 
      AND v.es_credito = true 
      AND v.estado_pago != 'PAGADO'
);


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

ALTER TABLE caja ADD COLUMN IF NOT EXISTS observaciones_cierre TEXT;

-- =============================================================================
-- TRIGGERS PARA AUDITORIA_CAMBIOS
-- =============================================================================

-- Función genérica para INSERT, UPDATE, DELETE
CREATE OR REPLACE FUNCTION funcion_auditoria()
RETURNS TRIGGER AS $$
DECLARE
    v_datos_anteriores JSONB;
    v_datos_nuevos JSONB;
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    -- Obtener usuario e IP de la sesión actual (variables de aplicación)
    v_id_usuario := current_setting('myapp.id_usuario', true)::INT;
    v_ip := current_setting('myapp.ip_address', true);
    IF v_id_usuario IS NULL THEN
        v_id_usuario := 1; -- Usuario por defecto (admin)
    END IF;

    IF TG_OP = 'INSERT' THEN
        v_datos_nuevos := to_jsonb(NEW);
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES (TG_TABLE_NAME, NEW.id_medicamento, TG_OP, v_datos_nuevos, v_id_usuario, v_ip);
        RETURN NEW;
        
    ELSIF TG_OP = 'UPDATE' THEN
        v_datos_anteriores := to_jsonb(OLD);
        v_datos_nuevos := to_jsonb(NEW);
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES (TG_TABLE_NAME, NEW.id_medicamento, TG_OP, v_datos_anteriores, v_datos_nuevos, v_id_usuario, v_ip);
        RETURN NEW;
        
    ELSIF TG_OP = 'DELETE' THEN
        v_datos_anteriores := to_jsonb(OLD);
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES (TG_TABLE_NAME, OLD.id_medicamento, TG_OP, v_datos_anteriores, v_id_usuario, v_ip);
        RETURN OLD;
    END IF;
    
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- 1. medicamentos (PK: id_medicamento)
CREATE OR REPLACE FUNCTION audit_medicamentos()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('medicamentos', NEW.id_medicamento, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('medicamentos', NEW.id_medicamento, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('medicamentos', OLD.id_medicamento, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_medicamentos ON medicamentos;
CREATE TRIGGER trg_audit_medicamentos
AFTER INSERT OR UPDATE OR DELETE ON medicamentos
FOR EACH ROW EXECUTE FUNCTION audit_medicamentos();

-- 2. lotes (PK: id_lote)
CREATE OR REPLACE FUNCTION audit_lotes()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('lotes', NEW.id_lote, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('lotes', NEW.id_lote, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('lotes', OLD.id_lote, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_lotes ON lotes;
CREATE TRIGGER trg_audit_lotes
AFTER INSERT OR UPDATE OR DELETE ON lotes
FOR EACH ROW EXECUTE FUNCTION audit_lotes();

-- 3. ventas (PK: id_venta)
CREATE OR REPLACE FUNCTION audit_ventas()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('ventas', NEW.id_venta, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('ventas', NEW.id_venta, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('ventas', OLD.id_venta, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_ventas ON ventas;
CREATE TRIGGER trg_audit_ventas
AFTER INSERT OR UPDATE OR DELETE ON ventas
FOR EACH ROW EXECUTE FUNCTION audit_ventas();

-- 4. usuarios (PK: id_usuario)
CREATE OR REPLACE FUNCTION audit_usuarios()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('usuarios', NEW.id_usuario, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('usuarios', NEW.id_usuario, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('usuarios', OLD.id_usuario, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_usuarios ON usuarios;
CREATE TRIGGER trg_audit_usuarios
AFTER INSERT OR UPDATE OR DELETE ON usuarios
FOR EACH ROW EXECUTE FUNCTION audit_usuarios();

-- 5. clientes
CREATE OR REPLACE FUNCTION audit_clientes()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('clientes', NEW.id_cliente, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('clientes', NEW.id_cliente, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('clientes', OLD.id_cliente, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_clientes ON clientes;
CREATE TRIGGER trg_audit_clientes
AFTER INSERT OR UPDATE OR DELETE ON clientes
FOR EACH ROW EXECUTE FUNCTION audit_clientes();

-- 6. productos
CREATE OR REPLACE FUNCTION audit_productos()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('productos', NEW.id_producto, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('productos', NEW.id_producto, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('productos', OLD.id_producto, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_productos ON productos;
CREATE TRIGGER trg_audit_productos
AFTER INSERT OR UPDATE OR DELETE ON productos
FOR EACH ROW EXECUTE FUNCTION audit_productos();

-- 7. compras
CREATE OR REPLACE FUNCTION audit_compras()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('compras', NEW.id_compra, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('compras', NEW.id_compra, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('compras', OLD.id_compra, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_compras ON compras;
CREATE TRIGGER trg_audit_compras
AFTER INSERT OR UPDATE OR DELETE ON compras
FOR EACH ROW EXECUTE FUNCTION audit_compras();

-- 8. proveedores
CREATE OR REPLACE FUNCTION audit_proveedores()
RETURNS TRIGGER AS $$
DECLARE
    v_id_usuario INT;
    v_ip VARCHAR(45);
BEGIN
    v_id_usuario := COALESCE(current_setting('myapp.id_usuario', true)::INT, 1);
    v_ip := current_setting('myapp.ip_address', true);
    
    IF TG_OP = 'INSERT' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_nuevos, id_usuario, ip)
        VALUES ('proveedores', NEW.id_proveedor, 'INSERT', to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, datos_nuevos, id_usuario, ip)
        VALUES ('proveedores', NEW.id_proveedor, 'UPDATE', to_jsonb(OLD), to_jsonb(NEW), v_id_usuario, v_ip);
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO auditoria_cambios (tabla_afectada, id_registro, accion, datos_anteriores, id_usuario, ip)
        VALUES ('proveedores', OLD.id_proveedor, 'DELETE', to_jsonb(OLD), v_id_usuario, v_ip);
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_proveedores ON proveedores;
CREATE TRIGGER trg_audit_proveedores
AFTER INSERT OR UPDATE OR DELETE ON proveedores
FOR EACH ROW EXECUTE FUNCTION audit_proveedores();

-- =============================================================================
-- FUNCIÓN PARA ESTABLECER VARIABLES DE SESIÓN (debe llamarse desde PHP)
-- =============================================================================
CREATE OR REPLACE FUNCTION set_audit_vars(p_id_usuario INT, p_ip VARCHAR)
RETURNS VOID AS $$
BEGIN
    PERFORM set_config('myapp.id_usuario', p_id_usuario::TEXT, false);
    PERFORM set_config('myapp.ip_address', p_ip, false);
END;
$$ LANGUAGE plpgsql;

-- =============================================================================
-- INSERCIÓN DE LOTES (evita duplicados por número_lote)
-- =============================================================================

-- Lote #1: Paracetamol 500 mg - L001 (ya existe, se actualiza stock)
INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, ubicacion)
VALUES (1, 'L001', '2026-12-31', 100, 40, 100.00, 'ACTIVO', NULL)
ON CONFLICT (numero_lote) DO UPDATE SET 
    cantidad_actual = EXCLUDED.cantidad_actual,
    estado = EXCLUDED.estado,
    fecha_vencimiento = EXCLUDED.fecha_vencimiento;

-- Lote #2: Ibuprofeno 400 mg - L002
INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, ubicacion)
VALUES (3, 'L002', '2027-01-31', 50, 38, 120.00, 'ACTIVO', NULL)
ON CONFLICT (numero_lote) DO UPDATE SET 
    cantidad_actual = EXCLUDED.cantidad_actual;

-- Lote #3: Amoxicilina 500 mg - L003
INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, ubicacion)
VALUES (4, 'L003', '2026-11-30', 30, 30, 180.00, 'ACTIVO', NULL)
ON CONFLICT (numero_lote) DO UPDATE SET 
    cantidad_actual = EXCLUDED.cantidad_actual;

-- Lote #4: Paracetamol 500 mg - LOTE-VENCIDO-99
INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, ubicacion)
VALUES (1, 'LOTE-VENCIDO-99', '2026-03-30', 50, 0, 100.00, 'VENCIDO', NULL)
ON CONFLICT (numero_lote) DO UPDATE SET 
    estado = 'VENCIDO',
    cantidad_actual = 0;

-- Lote #5: Paracetamol 250 mg - LOTE-PROX-88
INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, ubicacion)
VALUES (2, 'LOTE-PROX-88', '2026-04-14', 25, 0, 120.00, 'ACTIVO', NULL)
ON CONFLICT (numero_lote) DO UPDATE SET 
    fecha_vencimiento = EXCLUDED.fecha_vencimiento,
    cantidad_actual = 0;

-- Lote #6: Ibuprofeno 400 mg - LOTE-STOCK-77
INSERT INTO lotes (id_medicamento, numero_lote, fecha_vencimiento, cantidad_inicial, cantidad_actual, costo_unitario, estado, ubicacion)
VALUES (3, 'LOTE-STOCK-77', '2027-04-09', 200, 200, 180.00, 'ACTIVO', NULL)
ON CONFLICT (numero_lote) DO UPDATE SET 
    cantidad_actual = EXCLUDED.cantidad_actual;

-- =============================================================================
-- ACTUALIZAR INVENTARIO (stock por sucursal, asumiendo sucursal principal id=1)
-- =============================================================================

-- Para L001 (stock 40)
INSERT INTO inventario (id_lote, id_sucursal, cantidad)
SELECT id_lote, 1, 40 FROM lotes WHERE numero_lote = 'L001'
ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = EXCLUDED.cantidad;

-- L002 (stock 38)
INSERT INTO inventario (id_lote, id_sucursal, cantidad)
SELECT id_lote, 1, 38 FROM lotes WHERE numero_lote = 'L002'
ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = EXCLUDED.cantidad;

-- L003 (stock 30)
INSERT INTO inventario (id_lote, id_sucursal, cantidad)
SELECT id_lote, 1, 30 FROM lotes WHERE numero_lote = 'L003'
ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = EXCLUDED.cantidad;

-- LOTE-VENCIDO-99 (stock 0)
INSERT INTO inventario (id_lote, id_sucursal, cantidad)
SELECT id_lote, 1, 0 FROM lotes WHERE numero_lote = 'LOTE-VENCIDO-99'
ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = 0;

-- LOTE-PROX-88 (stock 0)
INSERT INTO inventario (id_lote, id_sucursal, cantidad)
SELECT id_lote, 1, 0 FROM lotes WHERE numero_lote = 'LOTE-PROX-88'
ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = 0;

-- LOTE-STOCK-77 (stock 200)
INSERT INTO inventario (id_lote, id_sucursal, cantidad)
SELECT id_lote, 1, 200 FROM lotes WHERE numero_lote = 'LOTE-STOCK-77'
ON CONFLICT (id_lote, id_sucursal) DO UPDATE SET cantidad = EXCLUDED.cantidad;

-- =============================================================================
-- REGISTRAR MOVIMIENTOS DE INVENTARIO PARA AJUSTAR STOCK
-- =============================================================================
-- Esto solo es para mantener trazabilidad si los stocks cambiaron manualmente.

INSERT INTO movimiento_inventario (id_lote, id_sucursal, tipo, cantidad, motivo, id_usuario)
SELECT l.id_lote, 1, 'AJUSTE', i.cantidad, 'Ajuste manual de stock desde administración', 1
FROM lotes l
JOIN inventario i ON l.id_lote = i.id_lote AND i.id_sucursal = 1
WHERE l.numero_lote IN ('L001', 'L002', 'L003', 'LOTE-STOCK-77')
AND NOT EXISTS (
    SELECT 1 FROM movimiento_inventario m 
    WHERE m.id_lote = l.id_lote AND m.motivo = 'Ajuste manual de stock desde administración'
);

-- ¿Existe el lote 26?
SELECT id_lote, numero_lote FROM lotes WHERE id_lote = 26;

-- Valor actual de la secuencia
SELECT currval('lotes_id_lote_seq');

-- Últimos IDs insertados correctamente
SELECT id_lote, numero_lote FROM lotes ORDER BY id_lote DESC LIMIT 5;

ALTER TABLE lotes DROP CONSTRAINT IF EXISTS chk_cantidades;
ALTER TABLE lotes ADD CONSTRAINT chk_cantidad_actual_positiva CHECK (cantidad_actual >= 0);
ALTER TABLE detalle_compra ALTER COLUMN id_lote DROP NOT NULL;

-- 2. Agregar columna id_medicamento a detalle_compra (para saber qué producto se pidió)
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS id_medicamento INT REFERENCES medicamentos(id_medicamento);


-- Agregar columna cantidad_recibida (por defecto 0)
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS cantidad_recibida INT DEFAULT 0;

-- (Opcional) Si ya hay registros, actualizar cantidad_recibida con la cantidad total (asumiendo que se recibió todo antes de este cambio)
UPDATE detalle_compra SET cantidad_recibida = cantidad WHERE id_lote IS NOT NULL;

-- Agregar columnas para almacenar el lote y vencimiento solicitados
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS numero_lote_solicitado VARCHAR(50);
ALTER TABLE detalle_compra ADD COLUMN IF NOT EXISTS fecha_vencimiento_solicitada DATE;
