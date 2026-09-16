#!/usr/bin/env python3
"""Smoke test HTTPS com CSRF ativo; somente ambiente isolado de homologação."""
import http.cookiejar
import json
from pathlib import Path
import ssl
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
STATE = ROOT / 'armazenamento/producao-teste'
BASE = 'https://127.0.0.1:8443'
context = ssl.create_default_context(cafile=str(STATE / 'tls/fullchain.pem'))
credentials = json.loads((STATE / 'acessos.json').read_text())


def client():
    return urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPSHandler(context=context), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(opener, path, method='GET', data=None, token=None, expected=200):
    headers = {'Content-Type': 'application/json'}
    if token:
        headers['X-CSRF-Token'] = token
    req = urllib.request.Request(BASE + path, data=None if data is None else json.dumps(data).encode(), headers=headers, method=method)
    try:
        response = opener.open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read()
    assert response.status == expected, f'{method} {path}: esperado {expected}, recebido {response.status}: {body[:300]!r}'
    return json.loads(body), response.headers


opener = client()
assert request(opener, '/api/health.php')[0]['status'] == 'ok'
request(opener, '/api/produtos.php', expected=401)
request(opener, '/api/login.php', 'POST', {'email': 'admin@dallogix.local', 'password': 'password'}, expected=401)
time.sleep(1)
login, headers = request(opener, '/api/login.php', 'POST', {'email': 'admin@dallogix.local', 'password': credentials['admin@dallogix.local']})
assert login['authenticated']
cookie = headers.get('Set-Cookie', '').lower()
assert all(flag in cookie for flag in ('secure', 'httponly', 'samesite=strict'))
assert headers.get('Content-Security-Policy')
token = login['csrf_token']
request(opener, '/api/produtos.php', 'POST', {'name': 'Sem CSRF'}, expected=419)
product = {'name': 'Teste HTTPS', 'code': 'HTTPS-' + str(time.time_ns()), 'category': 'Homologação', 'barcode': str(time.time_ns())}
created, _ = request(opener, '/api/produtos.php', 'POST', product, token, expected=201)
products, _ = request(opener, '/api/produtos.php')
assert any(p['code'] == product['code'] for p in products['data'])
request(opener, '/api/logout.php', 'POST', {}, token)
request(opener, '/api/produtos.php', expected=401)
time.sleep(1)
operator = client()
login, _ = request(operator, '/api/login.php', 'POST', {'email': 'operador@dallogix.local', 'password': credentials['operador@dallogix.local']})
request(operator, '/api/produtos.php', 'POST', product, login['csrf_token'], expected=403)
print('OK: TLS verificado, saúde, senha padrão recusada, login, cookies seguros, CSRF, gravação/leitura, logout e permissão do operador.')
