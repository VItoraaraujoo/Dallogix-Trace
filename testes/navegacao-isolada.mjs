import assert from 'node:assert/strict';
import fs from 'node:fs';

// Executa a função da aplicação com dependências simuladas, sem servidor.
const source = fs.readFileSync(new URL('../interface/js/aplicacao.js', import.meta.url), 'utf8');
const body = source.slice(source.indexOf('async function loadPageData(page)'), source.indexOf('async function loadDalaView(id)'));
const calls = [];
let loadingReady = false;
const store = new Proxy({}, {
  get: (_, name) => async () => {
    calls.push(name);
    if (name === 'loadPendingReadings') assert.equal(loadingReady, true);
    if (name === 'loadActiveLoading') {
      await new Promise(resolve => setTimeout(resolve, 5));
      loadingReady = true;
    }
  },
});
let selectedId = null;
const load = new Function('store', 'authenticatedUser', 'queryId', 'loadDalaView', `${body}; return loadPageData;`)(
  store, { role: 'ADMIN_EMPRESA' }, () => selectedId, async () => calls.push('loadDalaView'),
);
await load('manifests');
assert.deepEqual(calls, ['loadManifests']);
calls.length = 0;
await assert.rejects(load('dala'), /Registro não informado/);
assert.deepEqual(calls, []);
selectedId = '5';
await load('dala');
assert.deepEqual(calls, ['loadDalaView']);
calls.length = 0;
await load('work');
assert.equal(calls.includes('loadUsers'), false);
assert.equal(calls.at(-1), 'loadPendingReadings');
console.log('OK: navegação isolada, ID obrigatório e sequência de leituras.');
