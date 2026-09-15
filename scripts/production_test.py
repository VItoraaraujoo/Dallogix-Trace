#!/usr/bin/env python3
"""Prepara homologação local em modo production, sem usar o banco atual."""
import json
import os
from pathlib import Path
import secrets
import subprocess

ROOT = Path(__file__).resolve().parents[1]
STATE = ROOT / 'armazenamento/producao-teste'
ENV = ROOT / '.env.production-test'
COMPOSE = ['docker', 'compose', '--env-file', str(ENV), '-f', str(ROOT / 'docker-compose.production-test.yml')]


def run(args, **kwargs):
    return subprocess.run(args, cwd=ROOT, check=True, **kwargs)


def sql(statement):
    return run(COMPOSE + ['exec', '-T', 'mysql', 'sh', '-c',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -N -u root "$MYSQL_DATABASE"'],
        input=statement, text=True, capture_output=True).stdout.strip()


def main():
    STATE.mkdir(mode=0o700, parents=True, exist_ok=True)
    tls = STATE / 'tls'
    tls.mkdir(mode=0o700, exist_ok=True)
    storage = STATE / 'storage'
    storage.mkdir(exist_ok=True)
    storage.chmod(0o777)  # O usuário PHP do contêiner escreve neste bind isolado.
    if not ENV.exists():
        run(['bash', 'scripts/setup_production_env.sh', str(ENV), 'https://localhost:8443'])
    else:
        env_text = ENV.read_text()
        missing = []
        for key in ('TRACE_DEVICE_TOKEN', 'CAMERA_DEVICE_TOKEN'):
            if not any(line.startswith(f'{key}=') for line in env_text.splitlines()):
                missing.append(f'{key}={secrets.token_hex(32)}')
        if missing:
            ENV.write_text(env_text.rstrip() + '\n' + '\n'.join(missing) + '\n')
            ENV.chmod(0o600)
    check_env = os.environ.copy()
    check_env['TRACE_ENV_CHECK_SKIP_DATABASE'] = '1'
    run(['bash', 'scripts/check_production_env.sh', str(ENV)], env=check_env)
    if not (tls / 'fullchain.pem').exists():
        conf = tls / 'openssl.cnf'
        conf.write_text('[req]\ndistinguished_name=dn\nx509_extensions=ext\nprompt=no\n[dn]\nCN=localhost\n[ext]\nsubjectAltName=DNS:localhost,IP:127.0.0.1\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n')
        run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '30',
             '-keyout', str(tls / 'privkey.pem'), '-out', str(tls / 'fullchain.pem'), '-config', str(conf)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        (tls / 'privkey.pem').chmod(0o600)
    (STATE / 'nginx.conf').write_text((ROOT / 'nginx/https.conf.example').read_text().replace('__APP_URL__', 'https://localhost:8443'))
    run(COMPOSE + ['up', '-d', '--build', '--wait', 'mysql', 'php'])
    sql('CREATE TABLE IF NOT EXISTS trace_deployment_migrations (name VARCHAR(190) PRIMARY KEY);')
    applied = set(sql('SELECT name FROM trace_deployment_migrations').splitlines())
    for migration in sorted((ROOT / 'banco-de-dados/migrations').glob('*.sql')):
        if migration.name not in applied:
            sql(migration.read_text())
            sql(f"INSERT INTO trace_deployment_migrations VALUES ('{migration.name}')")
            print('Migration aplicada:', migration.name, flush=True)
    if 'production-test-accounts' not in applied:
        sql((ROOT / 'banco-de-dados/seeds/001_local_seed.sql').read_text())
        credentials_path = STATE / 'acessos.json'
        if credentials_path.exists():
            credentials = json.loads(credentials_path.read_text())
        else:
            credentials = {f'{name}@dallogix.local': secrets.token_urlsafe(24) for name in ('admin', 'supervisor', 'operador', 'master')}
            fd = os.open(credentials_path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
            with os.fdopen(fd, 'w') as f:
                json.dump(credentials, f, indent=2)
        code = '''$pdo=new PDO("mysql:host=mysql;dbname=".getenv("DB_NAME"),getenv("DB_USER"),getenv("DB_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("UPDATE usuarios SET password_hash=?, must_change_password=0 WHERE email=?");
        foreach(json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR) as $email=>$password){$stmt->execute([password_hash($password,PASSWORD_DEFAULT),$email]);}
        $pdo->exec("INSERT INTO trace_deployment_migrations VALUES ('production-test-accounts')");$pdo->commit();'''
        run(COMPOSE + ['exec', '-T', 'php', 'php', '-r', code], input=json.dumps(credentials), text=True)
    run(COMPOSE + ['run', '--rm', '--no-deps', 'nginx', 'nginx', '-t'])
    run(COMPOSE + ['up', '-d', '--wait', 'nginx'])
    run(COMPOSE + ['exec', '-T', 'nginx', 'nginx', '-s', 'reload'])
    print('Pronto: https://localhost:8443 | Acessos: armazenamento/producao-teste/acessos.json')
    print('Certificado local para homologação, válido por 30 dias; domínio público exige certificado confiável.')


if __name__ == '__main__':
    main()
