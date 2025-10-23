CREATE DATABASE portal_udemedellin;
USE portal_udemedellin;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('estudiante','profesor','coordinador') NOT NULL,
    nombre VARCHAR(100)
);

-- Ejemplo de usuarios
INSERT INTO usuarios (documento, password, rol, nombre)
VALUES
('1001', MD5('1234'), 'estudiante', 'Juan Pérez'),
('2001', MD5('abcd'), 'profesor', 'María Gómez'),
('3001', MD5('admin'), 'coordinador', 'Carlos Ruiz');
