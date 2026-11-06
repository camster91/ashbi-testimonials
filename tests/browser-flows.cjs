const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;
const fixture = JSON.parse(fs.readFileSync('browser-fixture.log','utf8').match(/ABT_BROWSER_FIXTURE=(\{[^\r\n]*\})/)[1]);
(async () => {
 const browser = await chromium.launch({channel:'chromium',headless:false});
 const context = await browser.newContext({viewport:{width:1440,height:1000}});
 let page = await context.newPage();
 const errors=[];
 let expectedDiscard=false;
 const watch=target=>{
 target.on('pageerror',e=>errors.push(e.message));
 target.on('dialog',async dialog=>{
   if(expectedDiscard && dialog.type()==='confirm' && dialog.message()==='Discard your unsaved changes?'){
    expectedDiscard=false;
    await dialog.dismiss();
    return;
   }
   console.error('Unexpected browser dialog:',dialog.message());errors.push(dialog.message());await dialog.dismiss();
  });
 target.on('response',async response=>{if(response.url().includes('admin-post.php')){const headers=await response.allHeaders();console.log('Form transport',response.status(),headers.location||response.url());}});
 };
 watch(page);
 fs.mkdirSync('browser-results',{recursive:true});
 try {
  const noScript=await browser.newContext({javaScriptEnabled:false});
  const fallback=await noScript.newPage();
  await fallback.goto(fixture.url);
  assert.ok(await fallback.locator('.abt--grid .abt-card__full').first().isVisible(),'the full review is available without JavaScript');
  assert.equal(await fallback.locator('.abt-card__more:visible,.abt__more:visible,.abt-slider__nav:visible').count(),0,'script-dependent controls are hidden without JavaScript');
  await noScript.close();
  for(const width of [375,768,1440]) {
   await page.setViewportSize({width,height:1000});
   await page.goto(fixture.url,{waitUntil:'networkidle'});
   assert.equal(await page.locator('.abt-form').count(),2);
   const ids=await page.locator('.abt [id]').evaluateAll(nodes=>nodes.map(n=>n.id));
   assert.equal(new Set(ids).size,ids.length,'plugin IDs must remain unique when a form is repeated');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'no horizontal page overflow');
   const accessibility=await new AxeBuilder({page}).include('.abt').withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
   assert.deepEqual(accessibility.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>n.target)})),[]);
   await page.screenshot({path:`browser-results/reviews-${width}.png`,fullPage:true});
  }
  const grid=page.locator('.abt--grid').first();
  const read=grid.locator('.abt-card__more').first();
  await read.focus();
  await page.keyboard.press('Enter');
  assert.equal(await read.getAttribute('aria-expanded'),'true');
  assert.ok(await grid.locator('.abt-card__full').first().isVisible(),'keyboard opens the full quote');
  await page.keyboard.press('Enter');
  assert.equal(await read.getAttribute('aria-expanded'),'false');
  const count=await grid.locator('.abt-card').count();
  await grid.getByRole('button',{name:'Load more'}).click();
  await page.waitForFunction(n=>document.querySelector('.abt--grid').querySelectorAll('.abt-card').length>n,count);
  assert.ok(await grid.locator('.abt-card').nth(count).getByRole('button',{name:'Read more',exact:true}).isVisible(),'new quotes are enhanced after pagination');
  const quoteIds=await grid.locator('.abt-card__full').evaluateAll(nodes=>nodes.map(n=>n.id));
  assert.equal(new Set(quoteIds).size,quoteIds.length,'pagination keeps unique disclosure IDs');
  const slider=page.locator('.abt-slider__track').first();
  await slider.focus(); await page.keyboard.press('ArrowRight');
  await page.waitForFunction(()=>Math.abs(document.querySelector('.abt-slider__track').scrollLeft)>0);
  const form=page.locator('.abt-form').first();
  const selectedStar=form.locator('[name=rating]:checked');
  await selectedStar.focus();
  await page.keyboard.press('ArrowLeft');
  assert.equal(await selectedStar.inputValue(),'4','left arrow moves one star lower in LTR');
  const focusStyle=await form.locator('[name=rating]:focus-visible + label').evaluate(label=>getComputedStyle(label).outlineStyle);
  assert.equal(focusStyle,'solid','keyboard star focus has a visible outline');
  await form.locator('[name=author]').fill('Browser test reviewer');
  await form.locator('[name=email]').fill('browser@example.test');
  await form.locator('[name=content]').fill('Retain this text after a network failure.');
  await form.locator('[name=consent]').check();
  await form.getByLabel('Project (public)').fill('Browser project');
  await form.getByLabel('Private note (not published)').fill('Private browser answer');
  await page.route('**/admin-post.php',route=>route.abort());
  await form.getByRole('button',{name:'Submit review'}).click();
  await page.getByRole('alert').first().waitFor();
  assert.equal(await form.locator('[name=content]').inputValue(),'Retain this text after a network failure.');
  await page.unroute('**/admin-post.php');
  await form.getByRole('button',{name:'Submit review'}).click();
  await page.getByRole('status').filter({hasText:'received'}).first().waitFor();
  assert.equal(await form.locator('[name=content]').inputValue(),'');
  await page.goto(fixture.login);
  await page.locator('#user_login').fill(fixture.username);
  await page.locator('#user_pass').fill('abt-ci-only-password');
  await page.locator('#wp-submit').click();
  await page.waitForURL('**/wp-admin/**');
  // Keep anonymous network-abort/viewport instrumentation out of authenticated manager flows.
  await page.close(); page=await context.newPage(); watch(page);
  const dashboardUrl = new URL(fixture.admin);
  dashboardUrl.searchParams.delete('collection');
  await page.goto(dashboardUrl.href,{waitUntil:'networkidle'});
  const workspace=page.getByRole('navigation',{name:'Testimonials workspace'});
  await workspace.getByRole('link',{name:'Shortcode reference'}).click();
  assert.equal(await page.locator('#abt-shortcode-reference').getAttribute('open'),'');
  await page.getByRole('searchbox',{name:'Filter collections'}).fill('no matching collection name 123');
  await page.getByRole('status').filter({hasText:'No collections match'}).waitFor();
  await page.getByRole('searchbox',{name:'Filter collections'}).fill('');
  await page.getByRole('status').filter({hasText:'No collections match'}).waitFor({state:'hidden'});
  for (const width of [375,768,1440]) {
   await page.setViewportSize({width,height:1000});
   assert.equal(await page.locator('.abt-wrap').evaluate(node=>node.scrollWidth<=node.clientWidth+1),true,'dashboard fits the viewport');
  }
  await page.goto(fixture.admin,{waitUntil:'networkidle'});
  assert.ok((await page.locator('body').innerText()).includes('Demo reviewer'));
  const place=page.getByRole('button',{name:'Place on site',exact:true});
  const placement=page.getByRole('dialog',{name:'Place on site',exact:true});
  for (const width of [375,768,1440]) {
   await page.setViewportSize({width,height:1000});
   assert.equal(await page.locator('.abt-wrap').evaluate(node=>node.scrollWidth<=node.clientWidth+1),true,'collection fits the viewport');
   await place.click();
   assert.equal(await placement.locator('code').count(),3);
   assert.match(await placement.locator('code').last().innerText(),/^\[ashbi_testimonial_form collection=/);
   assert.equal(await placement.evaluate(node=>node.scrollWidth<=node.clientWidth+1),true,'placement dialog fits the viewport');
   await page.screenshot({path:`browser-results/placement-${width}.png`});
   await page.keyboard.press('Escape');
   assert.equal(await place.evaluate(node=>document.activeElement===node),true,'closing placement returns focus to its opener');
  }
  await place.click();
  const createFormPage=placement.getByRole('link',{name:'Create a review form page',exact:true});
  const formPageUrl=new URL(await createFormPage.getAttribute('href'),page.url());
  const formPage=await context.newPage();
  try {
   await formPage.goto(formPageUrl.href);
   await formPage.waitForFunction(collectionId=>{
    const editor=window.wp?.data?.select('core/editor');
    const blocks=window.wp?.data?.select('core/block-editor')?.getBlocks() || [];
    return editor?.getCurrentPostType()==='page' && ['auto-draft','draft'].includes(editor.getEditedPostAttribute('status')) &&
     blocks.some(block=>block.name==='ashbi/review-form' && block.attributes.collection===collectionId);
   },formPageUrl.searchParams.get('abt_review_form_collection'));
  } finally {
   await formPage.close();
  }
  await placement.getByRole('button',{name:'Customize display'}).click();
  const builder=page.getByRole('dialog',{name:'Customize display'});
  await builder.locator('[data-abt-builder-preview][aria-busy=false]').waitFor();
  assert.match(await builder.locator('[data-abt-builder-code]').innerText(),/^\[ashbi_testimonials collection=/);
  await builder.getByText('More display options',{exact:true}).click();
  await builder.getByLabel('Theme',{exact:true}).selectOption('dark');
  assert.match(await builder.locator('[data-abt-builder-code]').innerText(),/theme="dark"/);
  await page.keyboard.press('Escape');
  assert.equal(await placement.getByRole('button',{name:'Customize display'}).evaluate(node=>document.activeElement===node),true,'closing the builder returns to placement');
  await page.keyboard.press('Escape');
  await page.getByRole('button',{name:'Add testimonial',exact:true}).first().click();
  const editor=page.locator('#abt-editor');
  await editor.getByLabel('Name',{exact:true}).fill('Custom browser manager');
  await editor.locator('[name=content]').fill('Created in the native admin editor.');
  await editor.getByLabel('Project (public)').fill('Manager project');
  await editor.getByLabel('Private note (not published)').fill('Manager private note');
  await editor.getByLabel('Status',{exact:true}).selectOption('draft');
  assert.ok(await editor.getByRole('button',{name:'Save draft',exact:true}).isVisible());
  await editor.getByLabel('Status',{exact:true}).selectOption('publish');
  expectedDiscard=true;
  await page.keyboard.press('Escape');
  assert.equal(expectedDiscard,false,'Escape asks before discarding changed fields');
  assert.equal(await editor.getByLabel('Name',{exact:true}).inputValue(),'Custom browser manager');
  await editor.getByRole('button',{name:'Publish testimonial',exact:true}).click();
  await editor.waitFor({state:'hidden'});
  await page.getByText('Custom browser manager',{exact:true}).first().waitFor();
  const manager=page.locator('.abt-row').filter({hasText:'Custom browser manager'});
  await manager.getByRole('button',{name:'Edit',exact:true}).click();
  assert.equal(await editor.getByLabel('Project (public)').inputValue(),'Manager project');
  assert.equal(await editor.getByLabel('Private note (not published)').inputValue(),'Manager private note');
  await editor.locator('[name=content]').fill('Edited manager review in the browser.');
  await editor.screenshot({path:'browser-results/editor-custom-fields.png'});
  await editor.getByRole('button',{name:'Update published testimonial',exact:true}).click();
  await editor.waitFor({state:'hidden'});
  await manager.getByText('Edited manager review in the browser.',{exact:true}).waitFor();
  const pending=page.locator('.abt-row').filter({hasText:'Browser test reviewer'});
  await pending.getByRole('button',{name:'Approve',exact:true}).click();
  await pending.getByText('Published',{exact:true}).waitFor();
  await page.getByText('Import / export',{exact:true}).click();
  const downloaded=page.waitForEvent('download');
  await page.getByRole('link',{name:'Export CSV',exact:true}).click();
  const csv=fs.readFileSync(await (await downloaded).path(),'utf8');
  assert.ok(csv.includes('custom_fields') && csv.includes('Manager private note'),'authorised CSV exports include portable private answers');
  await page.getByRole('button',{name:'Import CSV',exact:true}).click();
  const importer=page.locator('#abt-import-dialog');
  await importer.locator('#abt-i-file').setInputFiles({name:'fictional-review.csv',mimeType:'text/csv',buffer:Buffer.from('author,content,rating\nCSV browser reviewer,Imported fictional feedback,4\n')});
  const loseImportResponse = async route => {
   if (!/name="action"\r?\n\r?\nabt_import\r?\n/.test(route.request().postData() || '')) return route.continue();
   const response = await route.fetch();
   assert.equal(response.ok(),true,'WordPress processed the batch before losing the response');
   await route.abort();
  };
  await page.route('**/admin-ajax.php',loseImportResponse);
  await importer.getByRole('button',{name:'Import',exact:true}).click();
  await importer.getByRole('status').filter({hasText:'Import paused at row 2.'}).waitFor();
  await page.unroute('**/admin-ajax.php',loseImportResponse);
  await importer.getByRole('button',{name:'Import',exact:true}).click();
  await importer.getByRole('status').filter({hasText:'Imported 1 testimonial.'}).waitFor();
  await importer.locator('.abt-dialog__foot').getByRole('button',{name:'Close',exact:true}).click();
  await page.getByText('CSV browser reviewer',{exact:true}).first().waitFor();
  assert.equal(await page.getByText('CSV browser reviewer',{exact:true}).count(),1,'retrying after a committed batch must not duplicate reviews');
  await page.getByRole('button',{name:'Collection settings',exact:true}).click();
  const collection=page.locator('#abt-collection-dialog');
  assert.equal(await collection.locator('[data-field-label]').count(),2);
  await collection.getByRole('button',{name:'Add field',exact:true}).click();
  await collection.locator('[data-field-label]').last().fill('Extra question');
  console.log('Saving collection question configuration');
  await Promise.all([page.waitForEvent('framenavigated',{predicate:frame=>frame===page.mainFrame()}),collection.getByRole('button',{name:'Save collection',exact:true}).click()]);
  await page.waitForLoadState('networkidle');
  await page.bringToFront();
  await collection.waitFor({state:'hidden'});
  await page.getByRole('button',{name:'Collection settings',exact:true}).click();
  await collection.locator('[data-field-label]').last().waitFor();
  assert.equal(await collection.locator('[data-field-label]').last().inputValue(),'Extra question');
  await collection.getByRole('button',{name:'Cancel',exact:true}).click();
  await page.screenshot({path:'browser-results/admin-1440.png',fullPage:true});
  await page.goto(fixture.url,{waitUntil:'networkidle'});
  const body=await page.locator('.abt').allTextContents();
  assert.ok(body.join(' ').includes('Manager project') && !body.join(' ').includes('Manager private note'));
  await page.locator('.abt--grid').first().screenshot({path:'browser-results/showcase-grid.png'});
  const rtl=await context.newPage();
  await rtl.addInitScript(()=>document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('.abt').forEach(root=>root.dir='rtl')));
  for(const width of [375,1440]) {
   await rtl.setViewportSize({width,height:1000}); await rtl.goto(fixture.url,{waitUntil:'networkidle'});
   assert.equal(await rtl.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'RTL direction smoke has no overflow');
   await rtl.locator('.abt--grid').first().screenshot({path:`browser-results/showcase-rtl-${width}.png`});
  }
  await rtl.close();
  assert.deepEqual(errors,[],'no browser runtime errors');
  console.log('OK: responsive/axe checks, repeated forms, real keyboard scrolling, recovery, create/edit/approve, CSV, collection fields and RTL direction smoke');
 } catch (error) { console.error('Original browser failure:',error); console.log('Form notices:',await page.locator('.abt-form__notice').allTextContents()); try { await page.screenshot({path:'browser-results/failure.png',fullPage:true,timeout:5000,animations:'disabled'}); } catch (capture) { console.log('Failure capture:',capture.message); } throw error; }
 finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
