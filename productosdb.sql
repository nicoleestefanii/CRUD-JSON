-- Base de datos y tabla del laboratorio CRUD Fetch + PHP OOP + MySQL
CREATE DATABASE IF NOT EXISTS productosdb CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE productosdb;

CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL,
  producto VARCHAR(100) NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  cantidad INT NOT NULL
);

-- Datos de ejemplo (opcional)
INSERT INTO productos (codigo, producto, precio, cantidad) VALUES
('P001', 'Teclado mecánico', 45.50, 10),
('P002', 'Mouse inalámbrico', 18.75, 25),
('P003', 'Monitor 24 pulgadas', 139.99, 0);
