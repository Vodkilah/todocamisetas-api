-- =====================================================================
-- TodoCamisetas - Esquema de Base de Datos
-- API RESTful en PHP puro
-- =====================================================================

CREATE DATABASE IF NOT EXISTS todocamisetas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE todocamisetas;

-- ---------------------------------------------------------------------
-- Tabla: clientes
-- Almacena los clientes B2B (tiendas minoristas) de TodoCamisetas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_comercial VARCHAR(100) NOT NULL,
    rut VARCHAR(20) NOT NULL UNIQUE,
    direccion VARCHAR(150) NOT NULL,
    categoria ENUM('Regular', 'Preferencial') NOT NULL DEFAULT 'Regular',
    contacto_nombre VARCHAR(100) NOT NULL,
    contacto_email VARCHAR(120) NOT NULL,
    porcentaje_oferta DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: camisetas
-- Almacena el stock de camisetas (productos) de TodoCamisetas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS camisetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    club VARCHAR(100) NOT NULL,
    pais VARCHAR(60) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    color VARCHAR(60) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    precio_oferta DECIMAL(10,2) NULL DEFAULT NULL,
    detalles TEXT NULL,
    codigo_producto VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: tallas
-- Catalogo de tallas disponibles (S, M, L, XL, etc.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tallas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(10) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: camiseta_tallas
-- Relacion muchos a muchos entre camisetas y tallas, con stock
-- ON DELETE CASCADE: si se elimina una camiseta o una talla,
-- se eliminan automaticamente las relaciones asociadas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS camiseta_tallas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camiseta_id INT NOT NULL,
    talla_id INT NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_camiseta_talla (camiseta_id, talla_id),
    CONSTRAINT fk_ct_camiseta FOREIGN KEY (camiseta_id)
        REFERENCES camisetas(id) ON DELETE CASCADE,
    CONSTRAINT fk_ct_talla FOREIGN KEY (talla_id)
        REFERENCES tallas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tabla: pedidos
-- Relacion muchos a muchos entre clientes y camisetas (historial de
-- pedidos). Permite "Listar camisetas por cliente" y sirve para
-- validar que no se elimine un cliente o camiseta con pedidos
-- asociados (integridad referencial con ON DELETE RESTRICT).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    camiseta_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedido_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pedido_camiseta FOREIGN KEY (camiseta_id)
        REFERENCES camisetas(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================================
-- Datos de prueba (seed)
-- =====================================================================

INSERT INTO clientes (nombre_comercial, rut, direccion, categoria, contacto_nombre, contacto_email, porcentaje_oferta)
VALUES
('90minutos', '76.123.456-7', 'Providencia, Santiago', 'Preferencial', 'Carla Munoz', 'compras@90minutos.cl', 15.00),
('tdeportes', '77.987.654-3', 'Concepcion, Biobio', 'Regular', 'Pedro Soto', 'pedro@tdeportes.cl', 0.00);

INSERT INTO tallas (nombre) VALUES ('S'), ('M'), ('L'), ('XL');

INSERT INTO camisetas (titulo, club, pais, tipo, color, precio, precio_oferta, detalles, codigo_producto)
VALUES
('Camiseta Local 2025 - Seleccion Chilena', 'Seleccion Chilena', 'Chile', 'Local', 'Rojo y Azul', 45000, 38000, 'Edicion aniversario 2025', 'SCL2025L'),
('Camiseta Visita 2025 - Seleccion Chilena', 'Seleccion Chilena', 'Chile', 'Visita', 'Blanco y Azul', 42000, NULL, 'Tela dry-fit', 'SCL2025V'),
('Camiseta Local 2025 - Real Madrid', 'Real Madrid', 'Espana', 'Local', 'Blanco', 55000, 49990, 'Version aficionado', 'RMA2025L');

INSERT INTO camiseta_tallas (camiseta_id, talla_id, stock) VALUES
(1, 1, 10), (1, 2, 15), (1, 3, 12), (1, 4, 5),
(2, 2, 8), (2, 3, 8),
(3, 1, 4), (3, 2, 6), (3, 3, 6), (3, 4, 2);

INSERT INTO pedidos (cliente_id, camiseta_id, cantidad) VALUES
(1, 1, 20),
(1, 3, 10),
(2, 2, 15);
