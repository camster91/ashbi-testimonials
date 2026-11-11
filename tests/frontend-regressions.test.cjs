const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/frontend.js'),'utf8');
function formHarness(fail=false,responseQuery='ashbte_status=thanks'){
 let sent,reset=false;
 const attributes={action:'https://example.test/wp-admin/admin-post.php'};
 const note={setAttribute(k,v){this[k]=v;},focus(){}};
 const button={textContent:'Submit review',disabled:false};
 const field={value:'ftp://example.test',setAttribute(k,v){this[k]=v;},removeAttribute(k){delete this[k];},addEventListener(_type,listener){this.onInput=listener;},focus(){this.focused=true;}};
 const form={action:{name:'action',value:'ashbte_submit'},elements:{namedItem:name=>name==='ashbte_custom[website]'?field:null},getAttribute:key=>attributes[key]??null,setAttribute:(k,v)=>attributes[k]=v,removeAttribute:key=>delete attributes[key],querySelector:selector=>selector.startsWith('[type')?button:null,closest:()=>({id:'review-form',querySelector:()=>note}),reset(){reset=true;}};
 const context={cfg:{form:{thanks:'Review received',error:'Keep your review and try again',custom_url:'Use https:// or http://'}},t:(_key,fallback)=>fallback,FormData:class {set(){} append(){}},freshNonce:async()=>'test-nonce',AbortController,URL,setTimeout,clearTimeout,document:{},async fetch(url){sent=url;if(fail)throw Error('network');return {ok:true,url:'https://example.test/reviews/?'+responseQuery};}};
 vm.createContext(context);
 vm.runInContext(source.slice(source.indexOf('async function submitReview('),source.indexOf("\n\tdocument.addEventListener( 'submit'")),context);
 return {run:()=>context.submitReview(form),get sent(){return sent;},get reset(){return reset;},note,button,field};
}
test('form transport uses its URL attribute even when WordPress action input shadows form.action',async()=>{
 const h=formHarness();await h.run();assert.equal(h.sent,'https://example.test/wp-admin/admin-post.php');assert.equal(h.reset,true);assert.equal(h.note.role,'status');
});
test('network failure retains review values and restores an accessible retry action',async()=>{
 const h=formHarness(true);await h.run();assert.equal(h.reset,false);assert.equal(h.note.role,'alert');assert.equal(h.button.disabled,false);assert.match(h.note.textContent,/Keep your review/);
});
test('custom URL errors focus and describe the field without discarding its answer',async()=>{
 const h=formHarness(false,'ashbte_status=custom_url&ashbte_field=website');await h.run();
 assert.equal(h.reset,false);assert.equal(h.field.value,'ftp://example.test');
 assert.equal(h.field.focused,true);assert.equal(h.field['aria-invalid'],'true');
 assert.equal(h.field['aria-errormessage'],h.note.id);assert.match(h.note.textContent,/https/);
 h.field.onInput();assert.equal(h.field['aria-invalid'],undefined);
});
test('a stalled nonce refresh is aborted and falls back so form and Load more recover',async()=>{
 let calls=0;
 const context={cfg:{ajaxUrl:'/wp-admin/admin-ajax.php',nonce:'page-nonce'},FormData:class {append(){}},AbortController,clearTimeout(){},
  setTimeout:fn=>{queueMicrotask(fn);return 0;},
  fetch:(_url,opts)=>{calls++;return new Promise((_resolve,reject)=>opts.signal.addEventListener('abort',()=>reject(Error('aborted'))));}};
 vm.createContext(context);
 vm.runInContext(source.slice(source.indexOf('\tlet noncePromise'),source.indexOf('\tconst t = ')),context);
 assert.equal(await context.freshNonce(),'page-nonce');
 assert.equal(await context.freshNonce(),'page-nonce');
 assert.equal(calls,2,'a failed refresh is retried on the next interaction');
});
