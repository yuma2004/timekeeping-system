# 参考SQL（MySQL 5.7）

```sql
SET NAMES utf8mb4;

CREATE TABLE employees (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(64) NOT NULL UNIQUE,
  login_id VARCHAR(128) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(128) NOT NULL,
  role ENUM('employee','approver','admin') NOT NULL DEFAULT 'employee',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(64) NOT NULL UNIQUE,
  user_id BIGINT NOT NULL,
  kind ENUM('in','out','break_start','break_end') NOT NULL,
  occurred_at DATETIME NOT NULL, -- サーバー時刻（UTC推奨）
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  raw_payload JSON NULL,
  prev_hmac CHAR(64) NULL,
  hmac_link CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_user_time (user_id, occurred_at),
  CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE daily_summaries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  work_date DATE NOT NULL,
  clock_in_at DATETIME NULL,
  clock_out_at DATETIME NULL,
  break_minutes INT NOT NULL DEFAULT 0,
  total_work_minutes INT NULL,
  snapshot_hash CHAR(64) NULL,
  UNIQUE KEY uk_user_date (user_id, work_date),
  CONSTRAINT fk_daily_user FOREIGN KEY (user_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE correction_requests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  work_date DATE NOT NULL,
  before_json JSON NOT NULL,
  after_json JSON NOT NULL,
  reason_text TEXT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  approver_id BIGINT NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_user_date (user_id, work_date, status),
  CONSTRAINT fk_corr_user FOREIGN KEY (user_id) REFERENCES employees(id),
  CONSTRAINT fk_corr_approver FOREIGN KEY (approver_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT NOT NULL,
  action VARCHAR(64) NOT NULL,
  target VARCHAR(128) NOT NULL,
  detail_json JSON NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_created (created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES employees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
> 事実：WINGのMySQLは**5.7**で、**外部接続不可**。この仕様を前提に設計している。citeturn1view0
