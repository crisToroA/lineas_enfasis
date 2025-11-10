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
    nombre VARCHAR(100) NOT NULL UNIQUE,
    duracion VARCHAR(50) DEFAULT 'No especificado',   -- e.g. '2 semestres'
    creditos INT DEFAULT 0,
    cupos INT DEFAULT 0,
    descripcion TEXT DEFAULT ''
);

-- Asegurar que existan las líneas de énfasis oficiales (no duplicar)
INSERT IGNORE INTO lineas_enfasis (nombre) VALUES
('Ingeniería de Software'),
('Inteligencia Artificial'),
('Robótica'),
('Big Data'),
('Gestión de la Información y el Conocimiento');

-- Tabla cursos actualizada con FK a lineas_enfasis
CREATE TABLE IF NOT EXISTS cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    semestre VARCHAR(20) NOT NULL,
    profesor_id INT NOT NULL,
    linea_enfasis_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dia VARCHAR(32) NULL,
    hora_inicio TIME NULL,
    hora_fin TIME NULL,
    FOREIGN KEY (profesor_id) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (linea_enfasis_id) REFERENCES lineas_enfasis(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Tabla solicitudes
CREATE TABLE IF NOT EXISTS solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remitente_id INT NOT NULL,
    curso_id INT NULL,
    tipo VARCHAR(80) NOT NULL,
    descripcion TEXT,
    estado ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aprobador_id INT NULL,
    comentario_coordinador TEXT NULL,
    fecha_procesamiento TIMESTAMP NULL,
    linea_enfasis_id INT NULL,
    FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobador_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Cursos de ejemplo (Robótica) - requiere que existan usuarios con documento '2001'
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

-- Añadir usuarios de ejemplo (profesores) y un coordinador de prueba
INSERT IGNORE INTO usuarios (documento, password, rol, nombre) VALUES
('3001', MD5('prof3001'), 'profesor', 'Carlos Pérez'),
('3002', MD5('prof3002'), 'profesor', 'María Gómez'),
('3003', MD5('prof3003'), 'profesor', 'Javier Martínez'),
('3004', MD5('prof3004'), 'profesor', 'Laura Ramírez'),
('3005', MD5('prof3005'), 'profesor', 'Andrés Torres'),
('3006', MD5('prof3006'), 'profesor', 'Sofía Morales'),
('4001', MD5('coord4001'), 'coordinador', 'Laura Fernández');

-- Añadir líneas de énfasis adicionales útiles para la sección de Arduino/Robótica
-- (eliminadas por solicitud: 'Sistemas Embebidos' y 'Arduino y Robótica Educativa')
-- INSERT IGNORE INTO lineas_enfasis (nombre) VALUES
-- ('Sistemas Embebidos'),
-- ('Arduino y Robótica Educativa');

-- Tabla para guardar historial de preguntas del chatbot
CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    pregunta TEXT NOT NULL,
    respuesta TEXT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla para inscripciones de estudiantes en una línea de énfasis (opcional)
CREATE TABLE IF NOT EXISTS inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    linea_enfasis_id INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente','confirmada','cancelada') DEFAULT 'confirmada',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (linea_enfasis_id) REFERENCES lineas_enfasis(id) ON DELETE CASCADE
);

-- Añadir estudiantes de ejemplo
INSERT IGNORE INTO usuarios (documento, password, rol, nombre) VALUES
('1001', MD5('est1001'), 'estudiante', 'Juan Pérez'),
('2001', MD5('est2001'), 'estudiante', 'María López');

-- Asegurar que la columna 'descripcion' exista (compatible con MySQL 8+ / MariaDB)
ALTER TABLE lineas_enfasis
  ADD COLUMN IF NOT EXISTS descripcion TEXT DEFAULT '' AFTER cupos;

-- Si el servidor MySQL no soporta ADD COLUMN IF NOT EXISTS (versiones antiguas),
-- puedes usar esta alternativa comentada (descomentar y ejecutar manualmente si hace falta):
-- ALTER TABLE lineas_enfasis ADD COLUMN descripcion TEXT DEFAULT '';

-- Actualizar descripciones (ejecutar después de asegurar la columna)
UPDATE lineas_enfasis SET descripcion = 'La línea de énfasis en Big Data se centra en el análisis, procesamiento y gestión de grandes volúmenes de datos provenientes de diversas fuentes. Los estudiantes adquieren competencias en técnicas de almacenamiento distribuido, minería de datos, análisis estadístico y herramientas de visualización avanzada. Esta línea busca formar profesionales capaces de transformar los datos en información valiosa para la toma de decisiones estratégicas en sectores como la banca, la salud, el comercio electrónico, la industria y el gobierno.' WHERE nombre = 'Big Data';

UPDATE lineas_enfasis SET descripcion = 'La línea de Inteligencia Artificial (IA) aborda el diseño e implementación de sistemas capaces de aprender, razonar y tomar decisiones de manera autónoma. Incluye temas como aprendizaje automático, redes neuronales, procesamiento de lenguaje natural, visión por computadora y agentes inteligentes. Los estudiantes desarrollan soluciones innovadoras aplicables a la automatización de procesos, la analítica predictiva, los asistentes virtuales, los sistemas de recomendación y otras aplicaciones emergentes de la IA.' WHERE nombre = 'Inteligencia Artificial';

UPDATE lineas_enfasis SET descripcion = 'La línea de énfasis en Robótica combina los principios de la ingeniería de software, la electrónica y la mecánica para diseñar sistemas autónomos y colaborativos. Se estudian áreas como el control de movimiento, la percepción sensorial, la interacción humano-robot y la automatización industrial. Esta línea forma profesionales capaces de desarrollar robots para la industria, la medicina, la exploración o la asistencia social, promoviendo la integración de tecnologías inteligentes en entornos físicos.' WHERE nombre = 'Robótica';

UPDATE lineas_enfasis SET descripcion = 'En la línea de Ingeniería de Software, los estudiantes se enfocan en el ciclo completo de desarrollo de sistemas informáticos: análisis, diseño, implementación, pruebas, despliegue y mantenimiento. Se promueven metodologías ágiles, principios de arquitectura de software, pruebas automatizadas, seguridad y calidad del software. El objetivo es formar ingenieros capaces de liderar proyectos tecnológicos, garantizando soluciones robustas, escalables y alineadas con las necesidades del cliente y las buenas prácticas de la industria.' WHERE nombre = 'Ingeniería de Software';

UPDATE lineas_enfasis SET descripcion = 'La línea de Gestión de la Información y del Conocimiento se orienta a la administración estratégica de los recursos informacionales dentro de las organizaciones. Los estudiantes aprenden a diseñar sistemas de información que faciliten la captura, organización, almacenamiento y difusión del conocimiento. Se abordan temas como inteligencia de negocios, gestión documental, bases de datos, tecnologías de información organizacional y toma de decisiones basada en información. Esta línea prepara profesionales capaces de transformar los datos y el conocimiento en ventajas competitivas sostenibles.' WHERE nombre = 'Gestión de la Información y el Conocimiento';

-- Asegurar columnas duracion / creditos / cupos
ALTER TABLE lineas_enfasis
  ADD COLUMN IF NOT EXISTS duracion VARCHAR(50) DEFAULT '4 semestres' AFTER nombre,
  ADD COLUMN IF NOT EXISTS creditos INT DEFAULT 16 AFTER duracion,
  ADD COLUMN IF NOT EXISTS cupos INT DEFAULT 30 AFTER creditos;

-- Si tu versión de MySQL/MariaDB no soporta "ADD COLUMN IF NOT EXISTS", ejecuta manualmente:
-- ALTER TABLE lineas_enfasis ADD COLUMN duracion VARCHAR(50) DEFAULT '4 semestres' AFTER nombre;
-- ALTER TABLE lineas_enfasis ADD COLUMN creditos INT DEFAULT 16 AFTER duracion;
-- ALTER TABLE lineas_enfasis ADD COLUMN cupos INT DEFAULT 30 AFTER creditos;

-- Actualizar valores por línea (opcional, deja los que prefieras)
UPDATE lineas_enfasis SET duracion = '4 semestres', creditos = 20, cupos = 40 WHERE nombre = 'Ingeniería de Software';
UPDATE lineas_enfasis SET duracion = '4 semestres', creditos = 18, cupos = 30 WHERE nombre = 'Inteligencia Artificial';
UPDATE lineas_enfasis SET duracion = '4 semestres', creditos = 18, cupos = 25 WHERE nombre = 'Robótica';
UPDATE lineas_enfasis SET duracion = '4 semestres', creditos = 20, cupos = 30 WHERE nombre = 'Big Data';
UPDATE lineas_enfasis SET duracion = '4 semestres', creditos = 16, cupos = 40 WHERE nombre = 'Gestión de la Información y el Conocimiento';

-- Asegurar columnas de horario en cursos: día y hora_inicio / hora_fin (si no existen)
ALTER TABLE cursos
  ADD COLUMN IF NOT EXISTS dia VARCHAR(32) NULL AFTER semestre,
  ADD COLUMN IF NOT EXISTS hora_inicio TIME NULL AFTER dia,
  ADD COLUMN IF NOT EXISTS hora_fin TIME NULL AFTER hora_inicio;

-- Tabla calificaciones: una entrada por curso + estudiante (actualizable)
CREATE TABLE IF NOT EXISTS calificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    nota DECIMAL(5,2) DEFAULT NULL,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY ux_curso_estudiante (curso_id, estudiante_id)
);

-- Tabla asistencias / reportes de inasistencia
CREATE TABLE IF NOT EXISTS asistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha DATE NOT NULL,
    falta TINYINT(1) DEFAULT 1,
    motivo TEXT NULL,
    reportado_por INT NULL, -- id del profesor que reporta
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (reportado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla para reportes formales al coordinador (simplificada)
CREATE TABLE IF NOT EXISTS reportes_coordinador (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    tipo ENUM('inasistencias','notas','otro') NOT NULL,
    contenido TEXT NOT NULL,
    enviado_por INT NOT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    procesado TINYINT(1) DEFAULT 0,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Añadir curso Cálculo Diferencial (CLD002) asignado al profesor with documento '3001' y a la línea Ingeniería de Software
INSERT IGNORE INTO cursos (nombre, codigo, semestre, profesor_id, linea_enfasis_id, dia, hora_inicio, hora_fin)
SELECT 'Cálculo Diferencial', 'CLD002', '2024-1', u.id, le.id, 'Lunes', '10:00:00', '12:00:00'
FROM usuarios u
JOIN lineas_enfasis le ON le.nombre = 'Ingeniería de Software'
WHERE u.documento = '3001'
LIMIT 1;

-- Asegurar que el estudiante con documento '2001' esté inscrito en la misma línea (si no existe)
INSERT IGNORE INTO inscripciones (usuario_id, linea_enfasis_id, estado)
SELECT u.id, le.id, 'confirmada'
FROM usuarios u
JOIN lineas_enfasis le ON le.nombre = 'Ingeniería de Software'
WHERE u.documento = '2001'
ON DUPLICATE KEY UPDATE fecha = VALUES(fecha);

-- Insertar calificación inicial para la estudiante '2001' en el curso CLD002 (nota de ejemplo 85)
INSERT IGNORE INTO calificaciones (curso_id, estudiante_id, nota)
SELECT c.id, u.id, 85
FROM cursos c
JOIN usuarios u ON u.documento = '2001'
WHERE c.codigo = 'CLD002'
LIMIT 1;

-- Nueva tabla para actividades/tareas vinculadas a líneas o cursos
CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    fecha_entrega DATE NOT NULL,
    linea_enfasis_id INT NULL,
    curso_id INT NULL,
    prioridad ENUM('baja','media','alta') DEFAULT 'media',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (linea_enfasis_id) REFERENCES lineas_enfasis(id) ON DELETE SET NULL,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE SET NULL
);

-- Ejemplos: actividades para CLD002 (Cálculo Diferencial) si existe
INSERT IGNORE INTO actividades (titulo, descripcion, fecha_entrega, curso_id, prioridad)
SELECT 'Entrega taller 1: Límites', 'Resolver ejercicios 1-10 del taller y subir PDF.', DATE_ADD(CURDATE(), INTERVAL 7 DAY), c.id, 'alta'
FROM cursos c WHERE c.codigo = 'CLD002' LIMIT 1
UNION ALL
SELECT 'Quiz 1: Derivadas', 'Pequeña evaluación online sobre derivadas.', DATE_ADD(CURDATE(), INTERVAL 12 DAY), c.id, 'media'
FROM cursos c WHERE c.codigo = 'CLD002' LIMIT 1;
