DROP TABLE IF EXISTS tee_time_slots CASCADE;
DROP TABLE IF EXISTS tee_times CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- 1. Usuarios / Operadores
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'operator' CHECK (role IN ('admin', 'operator')),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- 2. Salidas / Tee Times (uno por hoyo, por tabla, por fecha)
CREATE TABLE tee_times (
    id SERIAL PRIMARY KEY,
    fecha DATE NOT NULL,
    tabla_num INT NOT NULL CHECK (tabla_num IN (1, 2)),
    hoyo INT NOT NULL,
    hora_salida TIME NOT NULL,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (fecha, tabla_num, hoyo)
);
CREATE INDEX idx_tee_times_fecha_tabla ON tee_times(fecha, tabla_num);

-- 3. Jugadores por salida (1 a 1 con tee_times)
CREATE TABLE tee_time_slots (
    id SERIAL PRIMARY KEY,
    tee_time_id INT UNIQUE REFERENCES tee_times(id) ON DELETE CASCADE,
    campo VARCHAR(50) DEFAULT 'Norte',
    jugador_1 VARCHAR(100),
    jugador_2 VARCHAR(100),
    jugador_3 VARCHAR(100),
    jugador_4 VARCHAR(100),
    jugador_5 VARCHAR(100),
    detalles VARCHAR(255)
);

-- La creación del usuario admin y las salidas de hoy se hacen con api/seed.php
-- (ahí sí se puede generar el password_hash real con la función de PHP).