const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/admin.js'), 'utf8');

test('opening an imported review preserves paragraphs and safe HTML', () => {
  const fields = new Proxy({}, { get: (target, key) => target[key] ||= { value: '', focus() {} } });
  const ctx = { eform: { elements: fields, reset() {} }, eerror: {}, etitle: { dataset: {} }, collectionId: 1,
    collectionsBox: {}, editor: {}, customBox: { replaceChildren() {} }, customSnapshots: {}, renderCustom() {}, $: () => ({}), $$: () => [], today: () => '2026-01-01',
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

test('multiple collections keep at most ten stored answers and preserve historical answers', () => {
  const box = { innerHTML: '', insertAdjacentHTML(_, html) { this.innerHTML += html; } };
  const defs = prefix => Object.fromEntries(Array.from({length:6},(_,i)=>[prefix+i,{key:prefix+i,label:prefix+i,type:'text',public:true,options:[]} ]));
  const ctx = { cfg:{collections:[{id:1,fields:defs('a')},{id:2,fields:defs('b')}]}, editor:{}, collectionsBox:{},
    $: selector => selector === '[data-abt-custom-values]' ? box : { addEventListener() {} },
    $$: selector => selector.includes('collections') ? [{value:'1'},{value:'2'}] : [], esc: x=>String(x), __: x=>x, sprintf:(x,n)=>x.replace('%s',n) };
  vm.createContext(ctx);
  const start=source.indexOf('let customSnapshots =');
  const end=source.indexOf('\n\tcustomBox.addEventListener',start);
  vm.runInContext(source.slice(start,end),ctx);
  vm.runInContext('renderCustom()',ctx);
  assert.equal(vm.runInContext('Object.keys(customSnapshots).length',ctx),10);
  assert.ok(box.innerHTML.includes('data-custom-remove') && box.innerHTML.includes('Additional question'));
  vm.runInContext("customSnapshots={old:{label:'Historical',type:'text',public:false,value:'Keep this'}};renderCustom()",ctx);
  assert.equal(vm.runInContext('Object.keys(customSnapshots).length',ctx),10);
  assert.equal(vm.runInContext('customSnapshots.old.value',ctx),'Keep this');
});
