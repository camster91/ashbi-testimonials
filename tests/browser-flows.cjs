const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;
const fixture = JSON.parse(fs.readFileSync('browser-fixture.log','utf8').match(/ABT_BROWSER_FIXTURE=(\{[^\r\n]*\})/)[1]);
(async () => {
 const browser = await chromium.launch({headless:true});
 const context = await browser.newContext();
 const page = await context.newPage();
 const errors=[]; page.on('pageerror',e=>errors.push(e.message));
 fs.mkdirSync('browser-results',{recursive:true});
 try {
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
  const count=await grid.locator('.abt-card').count();
  await grid.getByRole('button',{name:'Load more'}).click();
  await page.waitForFunction(n=>document.querySelector('.abt--grid').querySelectorAll('.abt-card').length>n,count);
  const slider=page.locator('.abt-slider__track').first();
  await slider.focus(); await page.keyboard.press('ArrowRight');
  const form=page.locator('.abt-form').first();
  await form.locator('[name=author]').fill('Browser test reviewer');
  await form.locator('[name=email]').fill('browser@example.test');
  await form.locator('[name=content]').fill('Retain this text after a network failure.');
  await form.locator('[name=consent]').check();
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
  await page.goto(fixture.admin,{waitUntil:'networkidle'});
  assert.ok((await page.locator('body').innerText()).includes('Demo reviewer'));
  await page.screenshot({path:'browser-results/admin-1440.png',fullPage:true});
  assert.deepEqual(errors,[],'no browser runtime errors');
  console.log('OK: three viewport/accessibility checks, repeated forms, load more, keyboard, recovery and admin rendering');
 } catch (error) { await page.screenshot({path:'browser-results/failure.png',fullPage:true}); throw error; }
 finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
