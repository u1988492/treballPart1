-- Script unificado para crear ambas bases de datos
-- Ejecutar desde setup/ con: sqlite3 ../private/users.db ".read create_databases.sql"
-- Luego: sqlite3 ../private/games.db ".read create_databases.sql"

-- ==================== USERS DATABASE ====================
-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY,
    user_name varchar(63),
    user_password varchar(255),
    user_email varchar(255),
    recovery_token varchar(255),
    recovery_expires INTEGER,
    totp_secret varchar(32),
    failed_attempts INTEGER DEFAULT 0,
    last_attempt INTEGER,
    email_verified INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS user_name_UNIQUE ON users (user_name ASC);
CREATE UNIQUE INDEX IF NOT EXISTS user_email_UNIQUE ON users (user_email ASC);

-- ==================== GAMES DATABASE ====================
-- Tabla principal del estado del juego
CREATE TABLE IF NOT EXISTS game_state (
    game_id TEXT PRIMARY KEY,
    player1_id TEXT,
    player2_id TEXT,
    player1_name TEXT,
    player2_name TEXT,
    player1_color TEXT,
    player2_color TEXT,
    player1_snake TEXT,
    player2_snake TEXT,
    player1_direction TEXT,
    player2_direction TEXT,
    player1_next_direction TEXT,
    player2_next_direction TEXT,
    fruits TEXT,
    game_status TEXT,
    winner TEXT,
    last_update INTEGER,
    created_at INTEGER
);

-- Tabla para latencia de jugadores
CREATE TABLE IF NOT EXISTS player_latency (
    player_id TEXT,
    game_id TEXT,
    ping_sent INTEGER,
    ping_received INTEGER,
    latency_ms INTEGER,
    PRIMARY KEY (player_id, game_id, ping_sent)
);