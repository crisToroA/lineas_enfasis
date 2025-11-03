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

-- Tabla de líneas de énfasis (debe existir antes de cursos)
CREATE TABLE IF NOT EXISTS lineas_enfasis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

-- Reemplazar las líneas de énfasis por las oficiales de Ingeniería de Sistemas
DELETE FROM lineas_enfasis
WHERE nombre NOT IN (
  'Ingeniería de Software',
  'Gestión de la Información y el Conocimiento',
  'Inteligencia Artificial',
  'Robótica',
  'Big Data'
);

INSERT IGNORE INTO lineas_enfasis (nombre) VALUES
('Ingeniería de Software'),
('Gestión de la Información y el Conocimiento'),
('Inteligencia Artificial'),
('Robótica'),
('Big Data');

-- Tabla cursos actualizada con FK a lineas_enfasis
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

-- Tabla solicitudes redefinida
CREATE TABLE IF NOT EXISTS solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remitente_id INT NOT NULL,
    curso_id INT NULL,
    tipo VARCHAR(80) NOT NULL,
    descripcion TEXT,
    estado ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE SET NULL
);

-- Agregar campos para registrar quién procesa la solicitud y el comentario del coordinador
ALTER TABLE solicitudes
  ADD COLUMN IF NOT EXISTS aprobador_id INT NULL AFTER estado,
  ADD COLUMN IF NOT EXISTS comentario_coordinador TEXT NULL AFTER descripcion,
  ADD COLUMN IF NOT EXISTS fecha_procesamiento TIMESTAMP NULL AFTER fecha;

-- Crear FK opcional hacia usuarios para aprobador (si la tabla usuarios existe)
ALTER TABLE solicitudes
  ADD CONSTRAINT IF NOT EXISTS fk_solicitudes_aprobador
  FOREIGN KEY (aprobador_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Cursos de ejemplo (Robótica)
DELETE FROM cursos WHERE codigo IN ('ARD101','ROB201');
INSERT IGNORE INTO cursos (nombre, codigo, semestre, profesor_id, linea_enfasis_id)
SELECT 'Introducción a Arduino', 'ARD101', '2024-1', u.id, le.id
FROM usuarios u
JOIN lineas_enfasis le ON le.nombre = 'Robótica'
WHERE u.documento = '2001'
UNION ALL
SELECT 'Robótica Básica', 'ROB201', '2024-2', u.id, le.id
FROM usuarios u
JOIN lineas_enfasis le ON le.nombre = 'Robótica'
WHERE u.documento = '2001';

-- Solicitudes de ejemplo
DELETE FROM solicitudes
WHERE remitente_id IN (
    SELECT id FROM usuarios WHERE documento IN ('1001','2001')
);

INSERT INTO solicitudes (remitente_id, curso_id, tipo, descripcion, estado)
SELECT u.id, c.id, 'Cambio de carrera',
       'Solicito cambio de carrera a Ingeniería de Sistemas por afinidad con la robótica.',
       'pendiente'
FROM usuarios u
JOIN cursos c ON c.codigo = 'ARD101'
WHERE u.documento = '1001'
UNION ALL
SELECT u.id, c.id, 'Homologación',
       'Deseo homologar la materia de Robótica Básica por experiencia previa.',
       'pendiente'
FROM usuarios u
JOIN cursos c ON c.codigo = 'ROB201'
WHERE u.documento = '1001'
UNION ALL
SELECT u.id, c.id, 'Aplazamiento',
       'Solicito aplazamiento del curso por motivos personales.',
       'pendiente'
FROM usuarios u
JOIN cursos c ON c.codigo = 'ARD101'
WHERE u.documento = '1001'
UNION ALL
SELECT u.id, NULL, 'Suplemento de créditos',
       'Requiero autorización para inscribir un crédito adicional en el semestre.',
       'pendiente'
FROM usuarios u
WHERE u.documento = '2001';
