# Sistema Gestor de Farmacias

## Descripción
Sistema web para la gestión de inventario de productos farmacéuticos y no farmacéuticos (ropa y artículos de conveniencia), control de ventas, y proceso estratégico de Delivery (entrega a domicilio) para una farmacia.

## Funcionalidades principales
- Registro y gestión de productos, inventario y lotes (con trazabilidad y control de vencimiento)
- Registro de ventas y facturación
- Gestión de clientes, proveedores y laboratorios
- Sistema de roles y permisos (Administrador, Cajero, Inventario, Supervisor, Soporte, Repartidor)
- Proceso de Delivery completo: asignación de repartidor, despacho de productos, conciliación de lo entregado, redespacho ante disputas o devoluciones
- Portal del cliente: seguimiento de sus pedidos, confirmación de recepción por cantidad de medicamento, calificación del servicio
- Auditoría de cambios (usuario, IP y detalle de cada modificación sobre las tablas principales)

## Tecnologías
- HTML, CSS, JavaScript
- PHP
- PostgreSQL
- Bootstrap

## Requisitos
- XAMPP (Apache + PHP 8.x) con la extensión `pdo_pgsql` habilitada
- PostgreSQL 14 o superior

## Cómo levantar el proyecto
1. Copiar la carpeta del proyecto dentro de `htdocs` (por ejemplo `C:\xampp\htdocs\Sistema-Gestor-de-Farmacias`).
2. Crear en PostgreSQL una base de datos llamada `Farmacia`.
3. Ejecutar el script `Farmacia.sql` completo sobre esa base de datos (contiene la creación de tablas, restricciones, triggers y los datos de prueba).
4. Revisar `backend/conexion.php` y ajustar `host`, `dbname`, `user` y `password` según la configuración local de PostgreSQL (por defecto usa `postgres` / `2003`).
5. Iniciar Apache desde el panel de XAMPP y acceder a `http://localhost/Sistema-Gestor-de-Farmacias/frontend/`.

## Usuarios de prueba (staff)
| Usuario | Contraseña | Rol |
|---|---|---|
| admin | admin123 | Administrador |
| maria | 1234 | Cajero |
| luis | 1234 | Inventario |
| ana | 1234 | Supervisor |
| carlos | 1234 | Soporte |

Para probar el Portal del Cliente o el rol Repartidor, hay que crear el acceso primero desde el panel de Administrador: en Clientes se le asigna usuario/contraseña de portal a un cliente existente, y en Repartidores se vincula un repartidor a una cuenta de usuario con rol Repartidor.

## Mi contribución
- Implementación de la lógica de registro y control de productos
- Desarrollo de funcionalidades relacionadas con la gestión de ventas
- Diseño e implementación del proceso de Delivery (despacho, conciliación, redespacho, portal del cliente)
- Implementación de lógica de seguridad y permisos del sistema
- Creación de consultas SQL para el manejo de inventario
- Integración entre la base de datos y la aplicación
- Pruebas, depuración y corrección de errores
