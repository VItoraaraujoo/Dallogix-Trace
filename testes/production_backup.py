#!/usr/bin/env python3
"""Verifica backup e restauração em banco descartável no Compose de homologação."""
import hashlib
from pathlib import Path
import secrets
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts'))
from production_test import COMPOSE, ROOT, STATE, run, sql

folder = STATE / 'backups'
folder.mkdir(mode=0o700, exist_ok=True)
backup = folder / 'homologacao.sql'
with backup.open('wb') as output:
    run(COMPOSE + ['exec', '-T', 'mysql', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --routines --events --triggers --no-tablespaces -u root "$MYSQL_DATABASE"'], stdout=output)
backup.chmod(0o600)
backup.with_suffix('.sql.sha256').write_text(hashlib.sha256(backup.read_bytes()).hexdigest() + '  homologacao.sql\n')
run(['bash', str(ROOT / 'scripts/verify_backup.sh'), str(backup)])
restore_db = 'trace_restore_' + secrets.token_hex(8)
tables = sql('SHOW TABLES').splitlines()
counts = {table: sql(f'SELECT COUNT(*) FROM `{table}`') for table in tables}
sql(f'CREATE DATABASE `{restore_db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
try:
    sql(f'USE `{restore_db}`;\n' + backup.read_text())
    restored = {table: sql(f'SELECT COUNT(*) FROM `{restore_db}`.`{table}`') for table in tables}
    assert counts == restored, 'Contagem de registros diverge após restauração.'
    print(f'OK: backup restaurado em banco descartável; {len(tables)} tabelas conferidas.')
finally:
    sql(f'DROP DATABASE `{restore_db}`')
