const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/frontend.js'),'utf8');
function formHarness(fail=false){
 let sent,reset=false;
 const attributes={action:'https://example.test/wp-admin/admin-post.php'};
 const note={setAttribute(k,v){this[k]=v;},focus(){}};
 const button={textContent:'Submit review',disabled:false};
 const form={action:{name:'action',value:'abt_submit'},getAttribute:key=>attributes[key]??null,setAttribute:(k,v)=>attributes[k]=v,removeAttribute:key=>delete attributes[key],querySelector:()=>button,closest:()=>({querySelector:()=>note}),reset(){reset=true;}};
 const context={cfg:{form:{thanks:'Review received',error:'Keep your review and try again'}},t:(_key,fallback)=>fallback,FormData:class {},AbortController,URL,setTimeout,clearTimeout,document:{},async fetch(url){sent=url;if(fail)throw Error('network');return {ok:true,url:'https://example.test/reviews/?abt_status=thanks'};}};
 vm.createContext(context);
 vm.runInContext(source.slice(source.indexOf('async function submitReview('),source.indexOf("\n\tdocument.addEventListener( 'submit'")),context);
 return {run:()=>context.submitReview(form),get sent(){return sent;},get reset(){return reset;},note,button};
}
test('form transport uses its URL attribute even when WordPress action input shadows form.action',async()=>{
 const h=formHarness();await h.run();assert.equal(h.sent,'https://example.test/wp-admin/admin-post.php');assert.equal(h.reset,true);assert.equal(h.note.role,'status');
});
test('network failure retains review values and restores an accessible retry action',async()=>{
 const h=formHarness(true);await h.run();assert.equal(h.reset,false);assert.equal(h.note.role,'alert');assert.equal(h.button.disabled,false);assert.match(h.note.textContent,/Keep your review/);
});
