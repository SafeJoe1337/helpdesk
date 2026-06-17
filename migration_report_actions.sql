-- Migration: create table to store resident/admin action notes per report
-- Run this in the `helpdesk` database.

CREATE TABLE IF NOT EXISTS report_actions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  user_id INT NOT NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_report_actions_report
    FOREIGN KEY (report_id) REFERENCES reports (id)
    ON DELETE CASCADE,

  CONSTRAINT fk_report_actions_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE,

  INDEX idx_report_actions_report_id (report_id),
  INDEX idx_report_actions_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

