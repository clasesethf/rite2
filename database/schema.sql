-- ============================================================
-- RITE v2.0 — Esquema de Base de Datos
-- Escuela Técnica Henry Ford — Ciclo 2026
-- Base de datos limpia, sin datos heredados
-- ============================================================

PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;

-- ========================
-- 1. CICLOS LECTIVOS
-- ========================
CREATE TABLE ciclos_lectivos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    anio INTEGER NOT NULL UNIQUE,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    activo INTEGER DEFAULT 0
);

-- ========================
-- 2. CURSOS
-- ========================
CREATE TABLE cursos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,            -- "1° Año", "2° Año", etc.
    anio INTEGER NOT NULL,           -- 1..7
    ciclo_lectivo_id INTEGER NOT NULL REFERENCES ciclos_lectivos(id),
    UNIQUE(anio, ciclo_lectivo_id)
);

-- ========================
-- 3. USUARIOS (todos los roles)
-- ========================
CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    apellido TEXT NOT NULL,
    dni TEXT NOT NULL,
    email TEXT,
    telefono TEXT,
    contrasena TEXT NOT NULL,
    tipo TEXT NOT NULL CHECK(tipo IN ('admin','directivo','profesor','preceptor','estudiante')),
    roles_secundarios TEXT,          -- CSV: "profesor,preceptor"
    activo INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(dni, tipo)
);

-- ========================
-- 4. MATERIAS (catálogo)
-- ========================
CREATE TABLE materias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    codigo TEXT NOT NULL UNIQUE,
    tipo TEXT DEFAULT 'aula' CHECK(tipo IN ('aula','taller'))
);

-- ========================
-- 5. MATERIAS POR CURSO (asignaciones)
-- ========================
CREATE TABLE materias_por_curso (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    materia_id INTEGER NOT NULL REFERENCES materias(id),
    curso_id INTEGER NOT NULL REFERENCES cursos(id),
    profesor_id INTEGER REFERENCES usuarios(id),
    profesor_id_2 INTEGER REFERENCES usuarios(id),
    profesor_id_3 INTEGER REFERENCES usuarios(id),
    requiere_subgrupos INTEGER DEFAULT 0,
    UNIQUE(materia_id, curso_id)
);

-- ========================
-- 6. MATRÍCULAS
-- ========================
CREATE TABLE matriculas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    estudiante_id INTEGER NOT NULL REFERENCES usuarios(id),
    curso_id INTEGER NOT NULL REFERENCES cursos(id),
    fecha_matriculacion DATE NOT NULL,
    estado TEXT DEFAULT 'activo' CHECK(estado IN ('activo','baja','egresado')),
    tipo_matricula TEXT DEFAULT 'regular' CHECK(tipo_matricula IN ('regular','recursando','liberado')),
    UNIQUE(estudiante_id, curso_id)
);

-- ========================
-- 7. SUBGRUPOS / ROTACIONES
-- ========================
CREATE TABLE subgrupos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    materia_curso_id INTEGER NOT NULL REFERENCES materias_por_curso(id),
    nombre TEXT NOT NULL,            -- "Subgrupo A", "Subgrupo B"
    UNIQUE(materia_curso_id, nombre)
);

CREATE TABLE estudiantes_subgrupo (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subgrupo_id INTEGER NOT NULL REFERENCES subgrupos(id),
    estudiante_id INTEGER NOT NULL REFERENCES usuarios(id),
    UNIQUE(subgrupo_id, estudiante_id)
);

-- ========================
-- 8. CALIFICACIONES
-- ========================
CREATE TABLE calificaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    estudiante_id INTEGER NOT NULL REFERENCES usuarios(id),
    materia_curso_id INTEGER NOT NULL REFERENCES materias_por_curso(id),
    ciclo_lectivo_id INTEGER NOT NULL REFERENCES ciclos_lectivos(id),
    
    -- 1° Bimestre
    valoracion_1bim TEXT CHECK(valoracion_1bim IN ('TEA','TEP','TED')),
    desempeno_1bim TEXT,
    observaciones_1bim TEXT,
    
    -- Calificación 1° Cuatrimestre (1-10)
    calificacion_1c INTEGER CHECK(calificacion_1c BETWEEN 1 AND 10),
    
    -- 3° Bimestre
    valoracion_3bim TEXT CHECK(valoracion_3bim IN ('TEA','TEP','TED')),
    desempeno_3bim TEXT,
    observaciones_3bim TEXT,
    
    -- Calificación 2° Cuatrimestre (1-10)
    calificacion_2c INTEGER CHECK(calificacion_2c BETWEEN 1 AND 10),
    
    -- Intensificaciones
    intensificacion_1c INTEGER CHECK(intensificacion_1c BETWEEN 1 AND 10),
    intensificacion_diciembre INTEGER CHECK(intensificacion_diciembre BETWEEN 1 AND 10),
    intensificacion_febrero INTEGER CHECK(intensificacion_febrero BETWEEN 1 AND 10),
    
    -- Final
    calificacion_final INTEGER CHECK(calificacion_final BETWEEN 1 AND 10),
    
    -- Tipo de cursada
    tipo_cursada TEXT DEFAULT 'C' CHECK(tipo_cursada IN ('C','R')),
    
    UNIQUE(estudiante_id, materia_curso_id, ciclo_lectivo_id)
);

-- ========================
-- 9. CONTENIDOS (evaluaciones individuales)
-- ========================
CREATE TABLE contenidos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    materia_curso_id INTEGER NOT NULL REFERENCES materias_por_curso(id),
    profesor_id INTEGER REFERENCES usuarios(id),
    titulo TEXT NOT NULL,
    descripcion TEXT,
    fecha_clase DATE,
    bimestre INTEGER CHECK(bimestre IN (1,3)),
    tipo_evaluacion TEXT DEFAULT 'cualitativa' CHECK(tipo_evaluacion IN ('cualitativa','numerica')),
    orden INTEGER DEFAULT 0,
    activo INTEGER DEFAULT 1
);

CREATE TABLE contenidos_calificaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contenido_id INTEGER NOT NULL REFERENCES contenidos(id),
    estudiante_id INTEGER NOT NULL REFERENCES usuarios(id),
    -- Cualitativa
    estado TEXT CHECK(estado IN ('A','NA','EP')),  -- Acreditado, No Acreditado, En Proceso
    -- Numérica
    calificacion_numerica REAL,
    observaciones TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(contenido_id, estudiante_id)
);

-- ========================
-- 10. BLOQUEOS
-- ========================
CREATE TABLE bloqueos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ciclo_lectivo_id INTEGER NOT NULL REFERENCES ciclos_lectivos(id),
    bloqueo_general INTEGER DEFAULT 0,
    valoracion_1bim INTEGER DEFAULT 0,
    desempeno_1bim INTEGER DEFAULT 0,
    observaciones_1bim INTEGER DEFAULT 0,
    calificacion_1c INTEGER DEFAULT 0,
    valoracion_3bim INTEGER DEFAULT 0,
    desempeno_3bim INTEGER DEFAULT 0,
    observaciones_3bim INTEGER DEFAULT 0,
    calificacion_2c INTEGER DEFAULT 0,
    intensificacion_1c INTEGER DEFAULT 0,
    intensificacion_diciembre INTEGER DEFAULT 0,
    intensificacion_febrero INTEGER DEFAULT 0,
    calificacion_final INTEGER DEFAULT 0,
    UNIQUE(ciclo_lectivo_id)
);

-- ========================
-- 11. OBSERVACIONES PREDEFINIDAS
-- ========================
CREATE TABLE observaciones_predefinidas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    texto TEXT NOT NULL,
    categoria TEXT,
    activo INTEGER DEFAULT 1
);

-- ========================
-- 12. CONFIGURACIÓN DE COLUMNAS VISIBLES
-- ========================
CREATE TABLE config_columnas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    materia_curso_id INTEGER REFERENCES materias_por_curso(id),
    columna TEXT NOT NULL,           -- 'contenidos','bimestre1','cuatrimestre1', etc.
    visible INTEGER DEFAULT 1,
    UNIQUE(materia_curso_id, columna)
);

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Ciclo 2026
INSERT INTO ciclos_lectivos (anio, fecha_inicio, fecha_fin, activo)
VALUES (2026, '2026-03-02', '2026-12-18', 1);

-- Cursos
INSERT INTO cursos (nombre, anio, ciclo_lectivo_id) VALUES
('1° Año', 1, 1), ('2° Año', 2, 1), ('3° Año', 3, 1), ('4° Año', 4, 1),
('5° Año', 5, 1), ('6° Año', 6, 1), ('7° Año', 7, 1);

-- Admin
INSERT INTO usuarios (nombre, apellido, dni, contrasena, tipo)
VALUES ('Administrador', 'Sistema', 'admin', 'admin123', 'admin');

-- Bloqueos default (todo abierto)
INSERT INTO bloqueos (ciclo_lectivo_id) VALUES (1);
