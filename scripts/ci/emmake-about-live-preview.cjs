#!/usr/bin/env node
'use strict';
/**
 * Read-only live preview: injects the checked-out Corporate stylesheet
 * into published WordPress About page, without deploying or changing WP.
 */
const fs=require('node:fs'),path=require('node:path');
const CSS='packages/seo-geo-theme/assets/css/presets/corporate-inner-v1.css';
const ORIGIN='https://emmake.com/nuevaweb/';
const PAGE=ORIGIN+'sobre-nosotros/';
const OUT='artifacts/emmake-about-design-preview';
function assert(v,s){if(!v)throw Error(s)}
async function measure(page){
 return page.evaluate(()=>{
  const root=document.querySelector('main .wp-block-post-content');
  if(!root)return null;
  const enhancedLogos=[...root.querySelectorAll(':scope > .seo-geo-legacy-about-brand-grid > figure.wp-block-image')];
  const logos=enhancedLogos.length?enhancedLogos:[...root.querySelectorAll(':scope > h2 ~ figure.wp-block-image')];
  const tiger=root.querySelector(':scope > .sgmb-divi-fullwidth-header + figure.wp-block-image');
  const enhancedBlurbs=[...root.querySelectorAll(':scope > .seo-geo-legacy-about-card-grid > .sgmb-divi-blurb')];
  const blurbs=enhancedBlurbs.length?enhancedBlurbs:[...root.querySelectorAll(':scope > .sgmb-divi-blurb')];
  const headings=[...root.querySelectorAll('h2,h3')];
  const imgs=[...root.querySelectorAll('img')];
  const anchors=[...root.querySelectorAll('a')];
  const form=root.querySelector('form.seo-geo-contact-form');
  const rect=n=>n&&n.getBoundingClientRect();
  return {docHeight:document.documentElement.scrollHeight,scrollWidth:document.documentElement.scrollWidth,viewport:innerWidth,
   rootDisplay:getComputedStyle(root).display,logoCount:logos.length,
   maxLogoHeight:Math.round(Math.max(0,...logos.map(x=>rect(x)?.height||0))),
   maxLogoImageHeight:Math.round(Math.max(0,...logos.flatMap(x=>[...x.querySelectorAll('img')]).map(x=>rect(x)?.height||0))),
   logoWidths:logos.slice(0,8).map(x=>Math.round(rect(x)?.width||0)),
   tigerHeight:Math.round(rect(tiger)?.height||0),blurbCount:blurbs.length,
   enhancedCardGroups:root.querySelectorAll(':scope > .seo-geo-legacy-about-card-grid').length,
   enhancedBrandGroups:root.querySelectorAll(':scope > .seo-geo-legacy-about-brand-grid').length,
   cardGridColumns:[...root.querySelectorAll(':scope > .seo-geo-legacy-about-card-grid')].map(x=>getComputedStyle(x).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length),
   brandGridColumns:[...root.querySelectorAll(':scope > .seo-geo-legacy-about-brand-grid')].map(x=>getComputedStyle(x).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length),
   blurbWidths:blurbs.slice(0,8).map(x=>Math.round(rect(x)?.width||0)),
   headings:headings.length,images:imgs.length,anchors:anchors.length,
   rootTextLength:root.textContent?.length||0,formReady:!!form,required:form?.querySelectorAll('[required]').length||0,
   h1Count:document.querySelectorAll('h1').length,
   noindex:!!document.querySelector('meta[name="robots"][content*="noindex"]'),
   brokenImgs:imgs.filter(x=>x.complete&&x.naturalWidth===0).length};
 });
}
async function main(){
 const {chromium}=require('playwright'),browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const stylesheet=fs.readFileSync(CSS,'utf8'),rows=[];
 assert(stylesheet.includes('Editorial migration rescue: About-style pages'), 'missing proposed CSS');
 fs.mkdirSync(OUT,{recursive:true});
 try{
  for(const [device,width,height,isMobile] of [['desktop',1440,900,false],['mobile',390,844,true]]){
   const ctx=await browser.newContext({viewport:{width,height},isMobile,hasTouch:isMobile,deviceScaleFactor:1,serviceWorkers:'block',locale:'es-ES'});
   try{
    const pg=await ctx.newPage(),resp=await pg.goto(PAGE,{waitUntil:'domcontentloaded',timeout:30000});
    assert(resp&&resp.status()===200&&pg.url()===PAGE,'public About unavailable');
    await pg.waitForTimeout(650);
    const before=await measure(pg);
    assert(before&&before.logoCount===8&&before.blurbCount===8,'unexpected source block structure');
    await pg.addStyleTag({content:stylesheet});
    await pg.addScriptTag({content:fs.readFileSync('packages/seo-geo-theme/assets/js/corporate-about-v1.js','utf8')});
    await pg.waitForTimeout(550);
    const after=await measure(pg);
    await pg.screenshot({path:path.join(OUT,device+'_about_proposal.png'),fullPage:true,animations:'disabled',timeout:45000});
    const checks=[
      {id:'no_content_removed',ok:after.rootTextLength===before.rootTextLength&&after.anchors===before.anchors&&after.images===before.images&&after.headings===before.headings},
      {id:'logos_preserved_and_contained',ok:after.logoCount===8&&after.maxLogoHeight<=180&&after.maxLogoImageHeight<=140},
      {id:'decorative_image_contained',ok:after.tigerHeight<=350},
      {id:'blurb_cards_preserved',ok:after.blurbCount===8},
      {id:'semantic_section_grouping',ok:after.enhancedCardGroups===2&&after.enhancedBrandGroups===1},
      {id:'actual_responsive_grids',ok:after.cardGridColumns.every(c=>c===(device==='desktop'?2:1))&&
        after.brandGridColumns.every(c=>c===(device==='desktop'?4:2))},
      {id:'readable_flow',ok:after.rootDisplay==='block'},
      {id:'no_overflow',ok:after.scrollWidth<=after.viewport+4},
      {id:'h1_and_noindex_preserved',ok:after.h1Count===1&&after.noindex},
      {id:'form_preserved',ok:after.formReady&&after.required===before.required&&after.required>=3},
      {id:'no_broken_images',ok:after.brokenImgs===0},
      {id:'height_not_excessive',ok:after.docHeight<(device==='desktop'?7900:7350)},
    ];
    rows.push({device,before,after,checks});
    await pg.close();
   }finally{await ctx.close()}
  }
 }finally{await browser.close()}
 const report={scope:'read_only_live_css_preview',page:'sobre-nosotros',checked_at:new Date().toISOString(),
  status:rows.every(r=>r.checks.every(c=>c.ok))?'passed':'failed',rows,
  wordpress_mutations:0,form_submissions:0,source_css:CSS};
 fs.writeFileSync(path.join(OUT,'report.json'),JSON.stringify(report,null,2)+'\n');
 console.log(JSON.stringify({status:report.status,summary:rows.map(r=>({device:r.device,beforeHeight:r.before.docHeight,afterHeight:r.after.docHeight,
  beforeMaxLogo:r.before.maxLogoHeight,afterMaxLogo:r.after.maxLogoHeight,
  failures:r.checks.filter(c=>!c.ok).map(c=>c.id)}))}));
 if(report.status!=='passed')process.exitCode=1;
}
main().catch(e=>{console.error('Safe About CSS preview failed: '+e.message);process.exitCode=1});
