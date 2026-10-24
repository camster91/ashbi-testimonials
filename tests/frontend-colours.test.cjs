const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const css=fs.readFileSync(require('node:path').join(__dirname,'../assets/css/frontend.css'),'utf8');
function luminance(h,s,l){
 s/=100;l/=100;const c=(1-Math.abs(2*l-1))*s,x=c*(1-Math.abs((h/60)%2-1)),m=l-c/2;
 const rgb=h<60?[c,x,0]:h<120?[x,c,0]:h<180?[0,c,x]:h<240?[0,x,c]:h<300?[x,0,c]:[c,0,x];
 const v=rgb.map(v=>{v+=m;return v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4;});
 return .2126*v[0]+.7152*v[1]+.0722*v[2];
}
test('every generated avatar hue meets normal-text 4.5:1 contrast',()=>{
 const block=css.match(/\.abt-avatar--initials\s*\{([^}]+)\}/)[1];
 const colour=property=>{const match=block.match(new RegExp('(?:^|;)\\s*'+property+':\\s*hsl\\(var\\(--abt-hue,\\s*220\\)\\s+(\\d+)%\\s+(\\d+)%\\)'));assert.ok(match);return [Number(match[1]),Number(match[2])];};
 const bg=colour('background'),fg=colour('color');
 for(let i=0;i<=255;i++){
  const h=i*360/255,b=luminance(h,...bg),f=luminance(h,...fg),ratio=(Math.max(b,f)+.05)/(Math.min(b,f)+.05);
  assert.ok(ratio>=4.5,`generated hue ${h.toFixed(1)} has contrast ${ratio.toFixed(2)}`);
 }
});
