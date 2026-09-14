-- Credenciais de integrações externas, isoladas por empresa.
CREATE TABLE IF NOT EXISTS chaves_integracao (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  key_prefix VARCHAR(16) NOT NULL,
  secret_hash CHAR(64) NOT NULL,
  scopes JSON NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  expires_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  UNIQUE KEY uq_integration_key_prefix (key_prefix),
  KEY idx_integration_key_company (company_id, active),
  CONSTRAINT fk_integration_key_company FOREIGN KEY (company_id) REFERENCES empresas (id)
);

CREATE TABLE IF NOT EXISTS requisicoes_integracao (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  integration_key_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  method VARCHAR(10) NOT NULL,
  resource VARCHAR(60) NOT NULL,
  response_code SMALLINT UNSIGNED NOT NULL,
  response_body JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_integration_idempotency (integration_key_id, idempotency_key),
  KEY idx_integration_request_company (company_id, created_at),
  CONSTRAINT fk_integration_request_company FOREIGN KEY (company_id) REFERENCES empresas (id),
  CONSTRAINT fk_integration_request_key FOREIGN KEY (integration_key_id) REFERENCES chaves_integracao (id)
);
