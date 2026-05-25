CREATE DATABASE IF NOT EXISTS ai_knowledge_base
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ai_knowledge_base;

CREATE TABLE chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    role ENUM('user', 'model') NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (session_id)
);