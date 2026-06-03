CREATE DATABASE IF NOT EXISTS math_warriors
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE math_warriors;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255),
    email VARCHAR(255),
    google_id VARCHAR(255),
    avatar VARCHAR(255),
    tier VARCHAR(20) DEFAULT 'Pemula',
    points INT DEFAULT 0,
    last_active DATETIME,
    fcm_token VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- GAME SESSIONS TABLE
-- Menyimpan riwayat permainan setiap user
-- =============================================
CREATE TABLE IF NOT EXISTS game_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    score INT DEFAULT 0,
    correct_count INT DEFAULT 0,
    wrong_count INT DEFAULT 0,
    total_questions INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FIX: Jika tabel sudah ada tapi kolom kurang,
-- jalankan query di bawah ini satu per satu:
-- =============================================
-- ALTER TABLE users ADD COLUMN password VARCHAR(255) AFTER username;
-- ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER password;
-- ALTER TABLE users ADD COLUMN google_id VARCHAR(255) AFTER email;
-- ALTER TABLE users ADD COLUMN avatar VARCHAR(255) AFTER google_id;
-- ALTER TABLE users ADD COLUMN tier VARCHAR(20) DEFAULT 'Pemula' AFTER avatar;
-- ALTER TABLE users ADD COLUMN points INT DEFAULT 0 AFTER tier;
-- ALTER TABLE users ADD COLUMN last_active DATETIME AFTER points;
-- ALTER TABLE users ADD COLUMN fcm_token VARCHAR(500) DEFAULT NULL AFTER last_active;
