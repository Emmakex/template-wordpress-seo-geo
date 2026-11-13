#!/usr/bin/env node
'use strict';
/* Recreate only frontend rendered presentation in ephemeral Chromium.
 * The published WordPress page and its stored Gutenberg content are untouched. */
const fs=require('node:fs'),path=require('node:path');
const ORIGIN='https://emmake.com/nuevaweb/';
const SLUG='blog-transformacion-digital/';
const CSS='packages/seo-geo-theme/assets/css/presets/corporate-inner-v1.css';
const JS='packages/seo-geo-theme/assets/js/corporate-blog-digital-v1.js';
const OUT='artifacts/emmake-blog-digital-premium';
function assert(v,msg){if(!v)throw Error(msg)}
async function metrics(pg){
 return pg.evaluate(()=>{
  const root=document.querySelector('main .wp-block-post-content');if(!root)return null;
  const paths=[...root.querySelectorAll('.wp-block-latest-posts__post-title[href]')].map(a=>{
   try{const u=new URL(a.href);return u.origin===location.origin?u.pathname:null}
   catch{return null}
  }).filter(Boolean);
  const pic=root.querySelector('.seo-geo-blog-digital-archive + figure.wp-block-image');
  const image=pic?.querySelector('img');
  const imageSrc=(()=>{try{return new URL(image?.currentSrc||'').pathname}catch{return null}})();
  let imagePaleRatio=null,imageSampleError=null;
  if(image?.complete&&image?.naturalWidth){
   try {
    const canvas=document.createElement('canvas');canvas.width=canvas.height=32;
    const ctx=canvas.getContext('2d',{willReadFrequently:true});
    if(ctx){
     ctx.drawImage(image,0,0,32,32);
     const px=ctx.getImageData(0,0,32,32).data;let pale=0;
     for(let i=0;i<px.length;i+=4)if(px[i+3]<16||(px[i]>230&&px[i+1]>230&&px[i+2]>230))pale++;
     imagePaleRatio=Math.round(pale/1024*1000)/1000;
    }
   }catch(err){imageSampleError=String(err?.name||'error').slice(0,60)}
  }

  const extra=root.querySelector('.seo-geo-blog-digital-topic-grid');
  const tax=root.querySelector('.seo-geo-blog-digital-category-grid');
  const conversion=root.querySelector('.seo-geo-blog-digital-conversion-grid');
  const broken=[...root.querySelectorAll('img')].filter(i=>i.complete&&i.naturalWidth===0).length;
  return {
   height:document.documentElement.scrollHeight,viewport:innerWidth,
   overflow:document.documentElement.scrollWidth>innerWidth+4,
   enhanced:root.classList.contains('seo-geo-blog-digital-enhanced'),
   oneH1:document.querySelectorAll('h1').length===1,
   noindex:!!document.querySelector('meta[name="robots"][content*="noindex"]'),
   cards:paths.length,unique:new Set(paths).size,paths:[...new Set(paths)].sort(),
   imageCount:root.querySelectorAll('img').length,brokenImages:broken,
   featured:root.querySelector('.seo-geo-blog-digital-feature')?.querySelectorAll('li').length||0,
   archive:root.querySelector('.seo-geo-blog-digital-archive')?.querySelectorAll(':scope > li').length||0,
   topicCount:extra?.children.length||0,categoryCount:tax?.children.length||0,
   conversionCards:conversion?.querySelectorAll(':scope > section').length||0,
   paleFigureHidden:!!pic?.classList.contains('seo-geo-blog-digital-blank-legacy'),
   availableConversionCards:root.querySelectorAll('.seo-geo-blog-digital-conversion-card:not(.seo-geo-blog-digital-newsletter-unavailable)').length,

   blankImagePath:imageSrc,blankImageAlt:image?.alt?.slice(0,120)||'',imagePaleRatio,imageSampleError,

   postLists:root.querySelectorAll('.wp-block-latest-posts').length,
   archiveCols:root.querySelector('.seo-geo-blog-digital-archive')?
     getComputedStyle(root.querySelector('.seo-geo-blog-digital-archive')).gridTemplateColumns.trim().split(/\s+/).length:0,
   topicCols:extra?getComputedStyle(extra).gridTemplateColumns.trim().split(/\s+/).length:0,
   conversionCols:conversion?getComputedStyle(conversion).gridTemplateColumns.trim().split(/\s+/).length:0,
   visibleContact:!![...root.querySelectorAll('a[href]')].find(a=>a.href.includes('/nuevaweb/contacto/')),
  };
 });
}
async function main(){
 const {chromium}=require('playwright');
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const css=fs.readFileSync(CSS,'utf8'),script=fs.readFileSync(JS,'utf8');
 assert(css.includes('Migrated editorial landing: preserve the authored'),'missing blog CSS');
 assert(script.includes('seo-geo-blog-digital-enhanced'),'missing blog JS');
 fs.mkdirSync(OUT,{recursive:true});const rows=[];
 try{
  for(const [device,width,height,mobile] of [['desktop',1440,900,false],['mobile',390,844,true]]){
   const context=await browser.newContext({viewport:{width,height},isMobile:mobile,hasTouch:mobile,deviceScaleFactor:1,locale:'es-ES',serviceWorkers:'block'});
   try {
    const pg=await context.newPage();
    const response=await pg.goto(ORIGIN+SLUG,{waitUntil:'domcontentloaded',timeout:30000});
    assert(response&&response.status()===200&&pg.url()===ORIGIN+SLUG,'unexpected route');
    await pg.waitForTimeout(400);const before=await metrics(pg);
    assert(before.cards===13&&before.unique===5&&before.postLists===5,'source Gutenberg changed unexpectedly');
    await pg.addStyleTag({content:css});
    await pg.addScriptTag({content:script});
    await pg.waitForTimeout(450);
    const after=await metrics(pg);
    const check=[
      {id:'enhancer_activated',ok:after.enhanced},
      {id:'unique_posts_preserved',ok:JSON.stringify(after.paths)===JSON.stringify(before.paths)},
      {id:'duplicate_rendered_cards_eliminated',ok:after.cards===5&&after.unique===5},
      {id:'feature_and_archive',ok:after.featured===1&&after.archive===4&&after.postLists===2},
      {id:'all_authored_topics_preserved',ok:after.topicCount===6&&after.categoryCount===12},
      {id:'premium_contact_and_social_preserved',ok:after.conversionCards===3&&after.visibleContact},
      {id:'inert_newsletter_not_advertised',ok:after.availableConversionCards===2},
      {id:'oversized_autogenerated_placeholder_hidden',ok:after.paleFigureHidden},
      {id:'no_broken_images',ok:after.brokenImages===before.brokenImages&&after.brokenImages===0},
      {id:'one_h1_noindex_preserved',ok:after.oneH1&&after.noindex},
      {id:'responsive_archive_and_topic_grids',ok:after.archiveCols===(mobile?1:2)&&after.topicCols===(mobile?1:3)},
      {id:'responsive_conversion_grid',ok:after.conversionCols===(mobile?1:2)},
      {id:'no_horizontal_overflow',ok:!after.overflow},
      {id:'page_shorter',ok:after.height<before.height*.8}
    ];
    await pg.screenshot({path:path.join(OUT,device+'-blog-redesign.png'),fullPage:true,animations:'disabled',timeout:45000});
    rows.push({device,before,after,checks:check});
    await pg.close();
   }finally{await context.close()}
  }
 }finally{await browser.close()}
 const report={site:'emmake-nuevaweb',page:'blog-transformacion-digital',
  scope:'read_only_live_style_and_JS_preview',checked_at:new Date().toISOString(),
  status:rows.every(r=>r.checks.every(c=>c.ok))?'passed':'failed',
  wordpress_mutations:0,emails_sent:0,rows};
 fs.writeFileSync(path.join(OUT,'report.json'),JSON.stringify(report,null,2)+'\n');
 console.log(JSON.stringify({status:report.status,summary:rows.map(r=>({device:r.device,beforeHeight:r.before.height,
   afterHeight:r.after.height,unique:r.after.unique,rendered:r.after.cards,
   emptyImageHidden:r.after.paleFigureHidden,failures:r.checks.filter(c=>!c.ok).map(c=>c.id)}))}));
 if(report.status!=='passed')process.exitCode=1;
}
main().catch(e=>{console.error('EMMAKE Blog safe preview: '+e.message);process.exitCode=1});
