const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/admin.js'), 'utf8');

test('opening an imported review preserves paragraphs and safe HTML', () => {
  const fields = new Proxy({}, { get: (target, key) => target[key] ||= { value: '', focus() {} } });
  const ctx = { eform: { elements: fields, reset() {} }, eerror: {}, etitle: { dataset: {} }, collectionId: 1,
    collectionsBox: {}, editor: {}, $: () => ({}), $$: () => [], today: () => '2026-01-01',
    setRating() {}, setAvatar() {}, setProduct() {}, openDialog() {} };
  vm.createContext(ctx);
  vm.runInContext(source.slice(source.indexOf('function openEditor('), source.indexOf('\n\tasync function saveEditor')), ctx);
  const content = '<p>First paragraph.</p><p><strong>Second</strong> paragraph.</p>';
  ctx.openEditor({ id: 1, content, collections: [] });
  assert.equal(fields.content.value, content);
});

function listHarness() {
  const pending = [];
  const ctx = { state: { product: '', status: 'any', search: 'first', rating: 0, orderby: 'date', order: 'desc', page: 1, selected: new Set() },
    collectionId: 1, rowsEl: { innerHTML: '', setAttribute() {}, classList: { add() {}, remove() {} } },
    request(action, body) { return new Promise((resolve, reject) => pending.push({ body, resolve, reject })); },
    renderRows() {}, renderCounts() {}, renderStats() {}, renderPager() {}, updateBulk() {}, esc: value => value };
  vm.createContext(ctx);
  // Include the sequence state belonging to load(), if present.
  const start = source.lastIndexOf('\n\tlet ', source.indexOf('async function load()'));
  vm.runInContext(source.slice(start > source.indexOf('const statusLabels') ? start : source.indexOf('async function load()'), source.indexOf('\n\tfunction renderRows')), ctx);
  return { ctx, pending };
}

for (const fails of [false, true]) {
  test(`stale ${fails ? 'errors' : 'results'} cannot replace the latest filter results`, async () => {
    const { ctx, pending } = listHarness();
    const old = ctx.load();
    ctx.state.search = 'second';
    const latest = ctx.load();
    pending[1].resolve({ items: [{ id: 2 }] });
    await latest;
    if (fails) pending[0].reject(new Error('Old request failed')); else pending[0].resolve({ items: [{ id: 1 }] });
    await old;
    assert.equal(ctx.state.items[0].id, 2);
    assert.equal(ctx.rowsEl.innerHTML, '');
  });
}
