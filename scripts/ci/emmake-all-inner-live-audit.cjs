#!/usr/bin/env node
'use strict';
// Fixed-origin public read-only design audit: no WordPress writes and no form submissions.
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const ORIGIN='https://emmake.com/nuevaweb/';
const OUT='artifacts/emmake-all-inner-audit';
const PAGES=[
 ['contacto','contacto/'],['nuestras-marcas','nuestras-marcas/'],['blog','blog/'],
 ['sobre-nosotros','sobre-nosotros/'],['politica-de-privacidad','politica-de-privacidad/'],
 ['blog-transformacion-digital','blog-transformacion-digital/'],
 ['trabaja-con-nosotros','trabaja-con-nosotros/'],
 ['asistentes-virtuales-de-voz','asistentes-virtuales-de-voz/'],['servicios','servicios/']
];
const DEVICES=[['desktop',1440,900,false],['mobile',390,844,true]];
async function measure(pg) {
 return pg.evaluate(()=>{
  const main=document.querySelector('main'),root=document.querySelector('main .wp-block-post-content');
  const html=document.documentElement,links=[...document.querySelectorAll('main a[href]')];
  const text=(root?.innerText||'').replace(/\s+/g,' ').trim();
  const images=[...(main?.querySelectorAll('img')||[])];
  const headings=[...(main?.querySelectorAll('h1,h2,h3,h4')||[])].map(n=>({
   tag:n.tagName.toLowerCase(),text:(n.textContent||'').replace(/\s+/g,' ').trim().slice(0,180)
  }));
  const forms=[...document.querySelectorAll('main form')].map(f=>({
   className:f.className,method:f.method,required:f.querySelectorAll('[required]').length,
   labels:f.querySelectorAll('label').length,
   submitButtons:f.querySelectorAll('button[type="submit"],input[type="submit"]').length
  }));
  const overflowNodes=[...document.querySelectorAll('main *')].filter(n=>{
   const r=n.getBoundingClientRect(),css=getComputedStyle(n);
   return r.width>0&&css.position!=='fixed'&&css.visibility!=='hidden'&&(r.right>innerWidth+5||r.left< -5);
  }).slice(0,12).map(n=>({tag:n.tagName.toLowerCase(),
   className:String(n.className||'').slice(0,85),right:Math.round(n.getBoundingClientRect().right)}));
  const blocks=[...document.querySelectorAll('main .wp-block-post-content > *')].slice(0,20).map(n=>({
   tag:n.tagName.toLowerCase(),className:String(n.className||'').slice(0,110),
   text:(n.textContent||'').replace(/\s+/g,' ').trim().slice(0,110)
  }));
  return {
   final_url:location.href,documentTitle:document.title.slice(0,200),
   robots:document.querySelector('meta[name="robots"]')?.content||null,
   canonical:document.querySelector('link[rel="canonical"]')?.href||null,
   mainCount:document.querySelectorAll('main').length,
   h1Count:document.querySelectorAll('main h1').length,headings,
   contentTextLength:text.length,contentOpening:text.slice(0,440),blocks,
   scrollWidth:html.scrollWidth,viewportWidth:innerWidth,documentHeight:html.scrollHeight,
   horizontallyOverflowing:html.scrollWidth>innerWidth+4,overflowNodes,
   imageCount:images.length,brokenImageCount:images.filter(n=>n.complete&&n.naturalWidth===0).length,
   imageAltMissing:images.filter(n=>!n.hasAttribute('alt')).length,
   linkCount:links.length,
   links:links.slice(0,55).map(n=>({
    text:(n.textContent||n.getAttribute('aria-label')||'').replace(/\s+/g,' ').trim().slice(0,85),
    path:(()=>{try{const u=new URL(n.href,location.href);return u.origin===location.origin?u.pathname:u.hostname}catch{return 'invalid'}})()
   })),
   forms,
   footerPresent:!!document.querySelector('footer'),headerPresent:!!document.querySelector('header'),
   latestPostLists:document.querySelectorAll('main .wp-block-latest-posts').length,
   brandBlurbs:document.querySelectorAll('main .sgmb-divi-blurb').length,
   corporateCSS:!![...document.styleSheets].find(s=>(s.href||'').includes('corporate-inner-v1.css')),
  };
 });
}
async function main(){
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const rows=[];fs.mkdirSync(OUT,{recursive:true});
 try{
  for(const [device,width,height,mobile] of DEVICES){
   const ctx=await browser.newContext({viewport:{width,height},isMobile:mobile,hasTouch:mobile,
    deviceScaleFactor:1,locale:'es-ES',serviceWorkers:'block'});
   try{
    for(const [key,slug] of PAGES){
     const pg=await ctx.newPage();const url=ORIGIN+slug;
     let row={page:key,device,requested_url:url};
     try{
      const resp=await pg.goto(url,{waitUntil:'domcontentloaded',timeout:35000});
      await pg.waitForTimeout(350);
      row={...row,httpStatus:resp?.status()||0,...await measure(pg)};
      const checks=[
       {id:'expected_http_200',ok:row.httpStatus===200&&row.final_url===url},
       {id:'main_header_footer',ok:row.mainCount===1&&row.footerPresent&&row.headerPresent},
       {id:'one_h1',ok:row.h1Count===1},
       {id:'no_horizontal_overflow',ok:!row.horizontallyOverflowing},
       {id:'staging_noindex',ok:(row.robots||'').includes('noindex')},
       {id:'no_broken_images',ok:row.brokenImageCount===0},
       {id:'meaningful_content',ok:row.contentTextLength>=(key==='servicios'?300:120)},
       {id:'content_links',ok:row.linkCount>0},
       ...(key==='contacto'?[{id:'contact_form',ok:row.forms.some(f=>f.className.includes('seo-geo-contact-form')&&f.submitButtons>0&&f.required>=2)}]:[])
      ];
      row.checks=checks;row.failed=checks.filter(c=>!c.ok).map(c=>c.id);
      await pg.screenshot({path:path.join(OUT,device+'-'+key+'.png'),fullPage:true,
       animations:'disabled',timeout:45000});
      console.log(JSON.stringify({page:key,device,httpStatus:row.httpStatus,chars:row.contentTextLength,
       h1:row.h1Count,forms:row.forms.length,width:row.scrollWidth,broken:row.brokenImageCount,failed:row.failed}));
     }catch(e){
      row.error=String(e?.message||e).slice(0,300);
      row.failed=['navigation_or_measure_failed'];
      console.error('PAGE_AUDIT_FAILURE',key,device,row.error);
     }finally{rows.push(row);await pg.close()}
    }
   }finally{await ctx.close()}
  }
 }finally{await browser.close()}
 const report={site:'emmake-nuevaweb',scope:'public-read-only',
  checked_at:new Date().toISOString(),wordpress_mutations:0,form_submissions:0,pages:rows,
  passed:rows.filter(r=>r.failed?.length===0).length,failed:rows.filter(r=>r.failed?.length).length};
 fs.writeFileSync(path.join(OUT,'report.json'),JSON.stringify(report,null,2)+'\n');
 console.log('AUDIT_SUMMARY',JSON.stringify({total:rows.length,passed:report.passed,failed:report.failed,
  issues:rows.filter(r=>r.failed?.length).map(r=>({page:r.page,device:r.device,failed:r.failed}))}));
 if(rows.some(r=>r.error))process.exitCode=1;
}
main().catch(e=>{console.error('Read-only page audit failed: '+e.message);process.exitCode=1});
