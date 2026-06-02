-- =============================================
-- SCHEDULIN DATABASE SCHEMA
-- Jalankan file ini di phpMyAdmin atau MySQL CLI
-- =============================================

CREATE DATABASE IF NOT EXISTS schedulin_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE schedulin_db;

-- Tabel users / mahasiswa
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nim         VARCHAR(20)  NOT NULL UNIQUE,
    nama        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    prodi       VARCHAR(100) DEFAULT '',
    angkatan    YEAR        DEFAULT NULL,
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel jadwal (per-semester per-user)
CREATE TABLE IF NOT EXISTS jadwal (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    semester    VARCHAR(20)  NOT NULL DEFAULT 'Ganjil 2025/2026',
    nama_jadwal VARCHAR(100) NOT NULL DEFAULT 'KRS Saya',
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabel mata kuliah (matkul yang tersimpan dalam jadwal)
CREATE TABLE IF NOT EXISTS matkul (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    jadwal_id   INT          NOT NULL,
    user_id     INT          NOT NULL,
    nama        VARCHAR(150) NOT NULL,
    kelas       VARCHAR(10)  NOT NULL,
    sks         TINYINT      DEFAULT 3,
    dosen       VARCHAR(100) DEFAULT '',
    hari        VARCHAR(10)  DEFAULT '',
    jam_mulai   TIME         NOT NULL,
    jam_selesai TIME         NOT NULL,
    warna       VARCHAR(7)   DEFAULT '#3b82f6',
    posisi_urut INT          DEFAULT 0,
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- Index untuk performa
CREATE INDEX idx_matkul_jadwal  ON matkul(jadwal_id);
CREATE INDEX idx_matkul_user    ON matkul(user_id);
CREATE INDEX idx_jadwal_user    ON jadwal(user_id);
