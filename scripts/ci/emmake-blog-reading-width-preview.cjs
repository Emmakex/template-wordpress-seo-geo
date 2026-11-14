#!/usr/bin/env node
'use strict';
// Preview a *checked-out* new CSS file against existing WordPress HTML.
// Fixed public origin, zero WordPress changes, zero form submissions.
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const ORIGIN='https://emmake.com/nuevaweb/';
const CSS='packages/seo-geo-theme/assets/css/presets/corporate-inner-v1.css';
const OUT='artifacts/emmake-blog-width-preview';
const view=pg=>pg.evaluate(()=>{
 const root=document.querySelector('main .wp-block-post-content');
 const list=root?.querySelector(':scope > ul.wp-block-latest-posts');
 const cards=[...(list?.querySelectorAll(':scope > li')||[])];
 const rect=cards[0]?.getBoundingClientRect();
 const rootText=root?.textContent||'';
 return {
  rootTextLength:rootText.length,
  totalLinks:root?.querySelectorAll('a[href]').length||0,
  cards:cards.length,
  firstCardWidth:Math.round(rect?.width||0),
  listWidth:Math.round(list?.getBoundingClientRect().width||0),
  gridColumns:list?getComputedStyle(list).gridTemplateColumns.trim().split(/\s+/).length:0,
  overflow:document.documentElement.scrollWidth>innerWidth+4,
  pageHeight:document.documentElement.scrollHeight,
  noindex:!!document.querySelector('meta[name="robots"][content*="noindex"]'),
  h1:document.querySelectorAll('main h1').length,
  forms:root?.querySelectorAll('form').length||0,
  images:root?.querySelectorAll('img').length||0,
  broken:[...(root?.querySelectorAll('img')||[])].filter(x=>x.complete&&x.naturalWidth===0).length,
  headings:[...(root?.querySelectorAll('h2')||[])].map(x=>x.textContent?.trim()).filter(Boolean),
 };
});
async function main(){
 const stylesheet=fs.readFileSync(CSS,'utf8');
 if(!stylesheet.includes('Corporate editorial index reading-width rescue'))throw Error('missing checked-out blog width');
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const results=[];fs.mkdirSync(OUT,{recursive:true});
 try{
  for(const [device,width,height,mobile] of [['desktop',1440,900,false],['tablet',768,900,false],['mobile',390,844,true]]){
   const ctx=await browser.newContext({viewport:{width,height},isMobile:mobile,hasTouch:mobile,
    locale:'es-ES',serviceWorkers:'block',deviceScaleFactor:1});
   try{
    for(const slug of ['blog/','servicios/']){
     const pg=await ctx.newPage();
     try {
      const response=await pg.goto(ORIGIN+slug,{waitUntil:'domcontentloaded',timeout:35000});
      if(!response||response.status()!==200)throw Error('Live WordPress published route missing');
      await pg.waitForTimeout(350);
      const before=await view(pg);
      await pg.addStyleTag({content:stylesheet});
      await pg.waitForTimeout(200);
      const after=await view(pg);
      const isBlog=slug==='blog/';
      const cols=device==='desktop'?3:device==='tablet'?2:1;
      const checks=[
       {id:'source_semantics_untouched',ok:Math.abs(before.rootTextLength-after.rootTextLength)<=40&&
        before.totalLinks===after.totalLinks&&before.cards===after.cards&&before.images<=after.images&&before.forms===after.forms&&
        JSON.stringify(before.headings)===JSON.stringify(after.headings)},
       {id:'h1_and_noindex_preserved',ok:after.h1===1&&after.noindex},
       {id:'no_horizontal_overflow_or_broken_media',ok:!after.overflow&&after.broken===0},
       {id:'long_blog_grid_only',ok:!isBlog||(after.cards>=9&&after.gridColumns===cols)},
       {id:'blog_card_legibility',ok:!isBlog||(after.firstCardWidth>=(device==='desktop'?300:device==='tablet'?270:250))},
       {id:'non_blog_service_unchanged',ok:isBlog||after.firstCardWidth===before.firstCardWidth},
       {id:'wider_than_baseline_or_tablet_already_readable',ok:!isBlog||(device==='tablet' ? after.firstCardWidth>=before.firstCardWidth : after.firstCardWidth>before.firstCardWidth+25)},
      ];
      await pg.screenshot({path:path.join(OUT,device+'-'+slug.replace('/','')+'.png'),
       fullPage:true,animations:'disabled',timeout:45000});
      results.push({slug,device,before,after,checks});
      console.log(JSON.stringify({slug,device,beforeCardWidth:before.firstCardWidth,
       afterCardWidth:after.firstCardWidth,columns:after.gridColumns,failures:checks.filter(c=>!c.ok).map(c=>c.id)}));
     }finally{await pg.close()}
    }
   }finally{await ctx.close()}
  }
 }finally{await browser.close()}
 const report={scope:'read-only live injected CSS',wordpress_mutations:0,form_submissions:0,rows:results,
  status:results.every(r=>r.checks.every(c=>c.ok))?'passed':'failed'};
 fs.writeFileSync(path.join(OUT,'report.json'),JSON.stringify(report,null,2)+'\n');
 console.log('BLOG_WIDTH_PREVIEW='+report.status);
 if(report.status!=='passed')process.exitCode=1;
}
main().catch(e=>{console.error('BLOG_WIDTH_PREVIEW=failed '+e.message);process.exitCode=1});
