CREATE TABLE cursos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    semestre VARCHAR(20) NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE solicitudes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    estudiante_id INT NOT NULL,
    curso_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES cursos(id)
);

CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('estudiante', 'profesor', 'coordinador') NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Borrar usuarios existentes si hay
TRUNCATE TABLE usuarios;

-- Insertar usuarios de prueba con contraseñas simples
INSERT INTO usuarios (documento, password, rol, nombre) VALUES
('1234567', '$2a$12$K8HGT9a/WjnzFYXZk.YhKeSXI6w3Diu5YVFKxk1P9KV6TYyqxRIre', 'coordinador', 'Coordinador Demo'), -- password: admin123
('2345678', '$2a$12$K8HGT9a/WjnzFYXZk.YhKeSXI6w3Diu5YVFKxk1P9KV6TYyqxRIre', 'profesor', 'Profesor Demo'),      -- password: admin123
('3456789', '$2a$12$K8HGT9a/WjnzFYXZk.YhKeSXI6w3Diu5YVFKxk1P9KV6TYyqxRIre', 'estudiante', 'Estudiante Demo');  -- password: admin123
