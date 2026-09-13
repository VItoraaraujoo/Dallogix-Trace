SET NAMES utf8mb4;

INSERT INTO
  empresas (name)
SELECT
  'Empresa Demonstração'
WHERE
  NOT EXISTS (
    SELECT
      1
    FROM
      empresas
    WHERE
      name = 'Empresa Demonstração'
  );

INSERT INTO
  equipamentos (company_id, equipment_code, name)
SELECT
  c.id,
  'EST-001',
  'Esteira principal'
FROM
  empresas c
WHERE
  c.name = 'Empresa Demonstração'
  AND NOT EXISTS (
    SELECT
      1
    FROM
      equipamentos e
    WHERE
      e.equipment_code = 'EST-001'
  );

INSERT INTO
  usuarios (company_id, name, email, password_hash, role)
SELECT
  c.id,
  'Administrador local',
  'admin@dallogix.local',
  '$2y$10$ebOT1MqNyajFths8pCaJu.qE7MOSNMWkXYLan9LVzGSXFHIdgxK8C',
  'ADMIN_EMPRESA'
FROM
  empresas c
WHERE
  c.name = 'Empresa Demonstração'
  AND NOT EXISTS (
    SELECT
      1
    FROM
      usuarios
    WHERE
      email = 'admin@dallogix.local'
  );

INSERT INTO
  usuarios (company_id, name, email, password_hash, role)
SELECT
  c.id,
  'Supervisor local',
  'supervisor@dallogix.local',
  '$2y$12$rNW5syuIUQXWmnkzFzzCUOq7APYSVr.0iN9JIkPvCvoT0mfa/Bwm.',
  'SUPERVISOR'
FROM
  empresas c
WHERE
  c.name = 'Empresa Demonstração'
  AND NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'supervisor@dallogix.local'
  );

INSERT INTO
  usuarios (company_id, name, email, password_hash, role)
SELECT
  c.id,
  'Operador local',
  'operador@dallogix.local',
  '$2y$12$rNW5syuIUQXWmnkzFzzCUOq7APYSVr.0iN9JIkPvCvoT0mfa/Bwm.',
  'USUARIO'
FROM
  empresas c
WHERE
  c.name = 'Empresa Demonstração'
  AND NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'operador@dallogix.local'
  );

INSERT INTO
  produtos (company_id, code, name)
SELECT
  c.id,
  'PROD3',
  'Ração cães adulto • 10,1 KG'
FROM
  empresas c
WHERE
  c.name = 'Empresa Demonstração'
  AND NOT EXISTS (
    SELECT
      1
    FROM
      produtos p
    WHERE
      p.code = 'PROD3'
  );

INSERT INTO
  codigos_produtos (company_id, product_id, barcode)
SELECT
  p.company_id,
  p.id,
  '7898250782592'
FROM
  produtos p
WHERE
  p.code = 'PROD3'
  AND NOT EXISTS (
    SELECT
      1
    FROM
      codigos_produtos pc
    WHERE
      pc.company_id = p.company_id
      AND pc.barcode = '7898250782592'
  );

INSERT INTO
  usuarios (company_id, name, email, password_hash, role)
SELECT
  NULL,
  'Administrador Dallogix',
  'master@dallogix.local',
  '$2y$10$ebOT1MqNyajFths8pCaJu.qE7MOSNMWkXYLan9LVzGSXFHIdgxK8C',
  'ADMIN_DALLOGIX'
WHERE
  NOT EXISTS (
    SELECT
      1
    FROM
      usuarios
    WHERE
      email = 'master@dallogix.local'
  );
