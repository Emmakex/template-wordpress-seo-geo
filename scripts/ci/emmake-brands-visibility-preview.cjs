#!/usr/bin/env node
'use strict';
/* Public, fixed-origin read-only visual proof for contained migrated Brand images.
   Script injects only checked-out CSS/JS in an ephemeral browser; zero WP writes. */
const fs=require('node:fs'),path=require('node:path');
const ORIGIN='https://emmake.com/nuevaweb/';
const SLUG='nuestras-marcas/';
const OUT='artifacts/emmake-brands-visibility';
const CSS='packages/seo-geo-theme/assets/css/presets/corporate-inner-v1.css';
const JS='packages/seo-geo-theme/assets/js/corporate-about-v1.js';
const assert=(v,m)=>{if(!v)throw Error(m)};
const measure=pg=>pg.evaluate(()=>{
 const root=document.querySelector('main .wp-block-post-content');if(!root)return null;
 const cards=[...root.querySelectorAll('.sgmb-divi-blurb')];
 const imageSamples=cards.map((card,index)=>{
   const figure=card.querySelector('.wp-block-image');
   const img=figure?.querySelector('img');
   const r=img?.getBoundingClientRect();
   const row={index,figure:!!figure,image:!!img,
     naturalWidth:img?.naturalWidth||0,naturalHeight:img?.naturalHeight||0,
     renderedHeight:Math.round(r?.height||0),sampled:false,transparentRatio:null,
     brightVisibleRatio:null,opaqueBrightRatio:null,error:null};
   if(!img?.complete||!img?.naturalWidth)return row;
   try{
     const cv=document.createElement('canvas');cv.width=cv.height=36;
     const ctx=cv.getContext('2d',{willReadFrequently:true});
     ctx.drawImage(img,0,0,36,36);
     const px=ctx.getImageData(0,0,36,36).data;
     let trans=0,visible=0,bright=0;
     for(let i=0;i<px.length;i+=4){
       if(px[i+3]<40){trans++;continue}
       visible++;
       if(px[i]>225&&px[i+1]>225&&px[i+2]>225)bright++;
     }
     row.sampled=true;row.transparentRatio=Math.round(trans/1296*1000)/1000;
     row.brightVisibleRatio=visible?Math.round(bright/visible*1000)/1000:null;
     row.opaqueBrightRatio=Math.round(bright/1296*1000)/1000;
   }catch(e){row.error=String(e?.name||'unreadable').slice(0,50)}
   return row;
 });
 return {
   imageSamples,
   brandCards:cards.length,
   images:root.querySelectorAll('img').length,
   anchors:root.querySelectorAll('a[href]').length,
   textLength:root.textContent?.length||0,
   darkened:cards.filter(c=>c.classList.contains('seo-geo-legacy-blurb-light-art')).length,
   blankMedia:cards.filter(c=>c.classList.contains('seo-geo-legacy-blurb-blank-art')).length,
   brokenImages:[...root.querySelectorAll('img')].filter(img=>img.complete&&img.naturalWidth===0).length,
   overflow:document.documentElement.scrollWidth>innerWidth+4,
   noindex:!!document.querySelector('meta[name="robots"][content*="noindex"]'),
   oneH1:document.querySelectorAll('h1').length===1,
   maxCardHeight:Math.round(Math.max(0,...cards.map(c=>c.getBoundingClientRect().height)))
 };
});
async function main(){
 const {chromium}=require('playwright');
 const css=fs.readFileSync(CSS,'utf8'),js=fs.readFileSync(JS,'utf8');
 assert(css.includes('Corporate grouped blurb media fallback'),'missing grouped blurb CSS');
 assert(js.includes('seo-geo-legacy-about-enhanced'),'missing original About enhancer JS');
 fs.mkdirSync(OUT,{recursive:true});
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const rows=[];
 try{
  for(const [device,width,height,isMobile] of [['desktop',1440,900,false],['mobile',390,844,true]]){
   const ctx=await browser.newContext({viewport:{width,height},isMobile,hasTouch:isMobile,deviceScaleFactor:1,locale:'es-ES',serviceWorkers:'block'});
   try{
    const pg=await ctx.newPage();
    const res=await pg.goto(ORIGIN+SLUG,{waitUntil:'domcontentloaded',timeout:30000});
    assert(res&&res.status()===200&&pg.url()===ORIGIN+SLUG,'unexpected public route');
    await pg.waitForTimeout(750);
    const before=await measure(pg);
    assert(before&&before.brandCards>=10&&before.brandCards<=20,'unexpected migrated blurb structure');
    await pg.addStyleTag({content:css});
    await pg.addScriptTag({content:js});
    await pg.waitForTimeout(700);
    const after=await measure(pg);
    const checks=[
      {id:'all_original_migrated_cards_preserved',ok:after.brandCards===before.brandCards},
      {id:'no_content_links_or_media_removed',ok:after.textLength===before.textLength&&after.images===before.images&&after.anchors===before.anchors},
      {id:'nested_migrated_brand_images_contained',ok:Math.max(...after.imageSamples.map(x=>x.renderedHeight))<=185&&Math.max(...before.imageSamples.map(x=>x.renderedHeight))>550},
      {id:'oversized_brand_cards_compacted',ok:after.maxCardHeight<before.maxCardHeight&&after.maxCardHeight<600},
      {id:'no_broken_media',ok:after.brokenImages===0},
      {id:'no_horizontal_overflow',ok:!after.overflow},
      {id:'staging_indexability_unchanged',ok:after.noindex&&after.oneH1}
    ];
    await pg.screenshot({path:path.join(OUT,device+'-brands-contrast.png'),fullPage:true,animations:'disabled',timeout:45000});
    rows.push({device,before,after,checks});
    await pg.close();
   }finally{await ctx.close()}
  }
 }finally{await browser.close()}
 const report={site:'emmake-nuevaweb',page:'nuestras-marcas',scope:'public_read_only_CSS_JS_preview',
    observed_at:new Date().toISOString(),wordpress_mutations:0,form_submissions:0,
    status:rows.every(r=>r.checks.every(c=>c.ok))?'passed':'failed',rows};
 fs.writeFileSync(path.join(OUT,'report.json'),JSON.stringify(report,null,2)+'\n');
 console.log(JSON.stringify({status:report.status,summary:rows.map(r=>({
    device:r.device,brandCards:r.after.brandCards,maxImageBefore:Math.max(...r.before.imageSamples.map(x=>x.renderedHeight)),
    maxImageAfter:Math.max(...r.after.imageSamples.map(x=>x.renderedHeight)),
    maxCardBefore:r.before.maxCardHeight,maxCardAfter:r.after.maxCardHeight,failures:r.checks.filter(c=>!c.ok).map(c=>c.id)
 }))}));
 if(report.status!=='passed')process.exitCode=1;
}
main().catch(e=>{console.error('Read-only brand visual proof failed: '+e.message);process.exitCode=1});
