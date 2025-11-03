CREATE DATABASE IF NOT EXISTS portal_udemedellin;
USE portal_udemedellin;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('estudiante','profesor','coordinador') NOT NULL,
    nombre VARCHAR(100)
);

-- Tabla cursos: incluye referencia al profesor responsable (profesor_id)
CREATE TABLE IF NOT EXISTS cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    semestre VARCHAR(20) NOT NULL,
    profesor_id INT NOT NULL,
    linea_enfasis_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (linea_enfasis_id) REFERENCES lineas_enfasis(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Tabla de líneas de énfasis
CREATE TABLE IF NOT EXISTS lineas_enfasis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

-- Ejemplos de líneas de énfasis
INSERT IGNORE INTO lineas_enfasis (nombre) VALUES
('Desarrollo de Software'),
('Inteligencia Artificial'),
('Gestión de Proyectos'),
('Innovación y Emprendimiento'),
('Marketing Digital'),
('Derecho Internacional');

-- Tabla solicitudes (si se necesita)
CREATE TABLE IF NOT EXISTS solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    curso_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Ejemplo de usuarios (si no existen)
INSERT IGNORE INTO usuarios (documento, password, rol, nombre) VALUES
('1001', MD5('1234'), 'estudiante', 'Juan Pérez'),
('2001', MD5('abcd'), 'profesor', 'María Gómez'),
('3001', MD5('admin'), 'coordinador', 'Carlos Ruiz');
