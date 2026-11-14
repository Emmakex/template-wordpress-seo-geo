#!/usr/bin/env node
'use strict';
// Read-only public menu comparison after WordPress admin edits. No WP changes.
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const BASE='https://emmake.com/nuevaweb/';
const OUT='artifacts/emmake-navigation-qa';
async function capture(pg){
 return pg.evaluate(()=>{
  const header=document.querySelector('.seo-geo-site-header, .seo-geo-strategic-header');
  const menu=header?.querySelector('.seo-geo-preset-navigation--primary');
  const desktop=menu?.querySelector('.seo-geo-preset-navigation__desktop');
  const mobile=menu?.querySelector('.seo-geo-preset-navigation__mobile');
  const anchors=[...(desktop?.querySelectorAll('a[href]')||[])];
  const box=n=>{const r=n?.getBoundingClientRect();return r?{x:Math.round(r.x),y:Math.round(r.y),width:Math.round(r.width),height:Math.round(r.height)}:null};
  return {
   url:location.href, viewport:innerWidth,scrollWidth:document.documentElement.scrollWidth,
   h1:document.querySelectorAll('main h1').length,header:box(header),desktop:box(desktop),
   mobile:box(mobile),desktopDisplay:desktop?getComputedStyle(desktop).display:null,
   mobileDisplay:mobile?getComputedStyle(mobile).display:null,open:!!mobile?.open,
   items:anchors.map(a=>({title:a.textContent.trim(),href:a.href,
     current:a.getAttribute('aria-current'),box:box(a),
     depth:a.closest('li')?.className||'',submenus:a.closest('li')?.querySelectorAll('ul').length||0})),
   navTopLevel:desktop?.querySelectorAll(':scope > ul > li').length||0,
   desktopRows:new Set(anchors.filter(a=>getComputedStyle(a).display!=='none').map(a=>{const r=a.getBoundingClientRect();return Math.round(r.top+r.height/2)})).size,
   navSubmenus:desktop?.querySelectorAll('ul ul').length||0,
   mobileAnchorCount:mobile?.querySelectorAll('a[href]').length||0,
   footerMenu:[...(document.querySelectorAll('.seo-geo-preset-navigation--footer a[href]')||[])].map(x=>x.textContent.trim()),
   bodyClasses:document.body.className.slice(0,500),
   brokenLinks:anchors.filter(a=>!a.href).length
  };
 });
}
async function main(){
 fs.mkdirSync(OUT,{recursive:true});const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const rows=[];
 try{
  for(const [device,w,h,mob] of [['desktop',1440,900,false],['mobile',390,844,true]]){
   const ctx=await browser.newContext({viewport:{width:w,height:h},isMobile:mob,hasTouch:mob,locale:'es-ES',serviceWorkers:'block'});
   try{
    for(const [label,slug] of [['home',''],['services','servicios/']]){
     const pg=await ctx.newPage(),url=BASE+slug;
     const resp=await pg.goto(url,{waitUntil:'domcontentloaded',timeout:30000});
     await pg.waitForTimeout(350);
     const before=await capture(pg);
     const open=pg.locator('header .seo-geo-preset-navigation__mobile > summary').first();
     if(mob&&await open.isVisible()){
      await open.click();
      await pg.waitForTimeout(100);
     }
     const after=await capture(pg);
     rows.push({device,page:label,http:resp?.status(),before,after});
     await pg.screenshot({path:path.join(OUT,device+'-'+label+'-header.png'),fullPage:false,animations:'disabled'});
     console.log('NAV',JSON.stringify({page:label,device,http:resp?.status(),header:after.header,
      scrollWidth:after.scrollWidth,topLevel:after.navTopLevel,submenus:after.navSubmenus,desktopRows:after.desktopRows,
      desktop:after.desktopDisplay,mobile:after.mobileDisplay,open:after.open,
      items:after.items.map(x=>({title:x.title,href:x.href,box:x.box})),footer:after.footerMenu}));
     await pg.close();
    }
   } finally {await ctx.close()}
  }
 } finally {await browser.close()}
 fs.writeFileSync(path.join(OUT,'report.json'),JSON.stringify({site:'emmake-nuevaweb',scope:'public-read-only',
  wordpress_mutations:0,form_submissions:0,rows},null,2)+'\n');
 const checks=rows.map(r=>({
  page:r.page,device:r.device,
  http200:r.http===200,
  correctNine:r.after.navTopLevel===9,
  shortenedLabels:r.after.items.some(x=>x.title==='Asistentes de voz')&&r.after.items.some(x=>x.title==='Blog digital'),
  noOldLongLabels:!r.after.items.some(x=>x.title.startsWith('Descubre Los Mejores Asistentes')||x.title==='BLOG TRANSFORMACIÓN DIGITAL'),
  desktopOneRow:r.device==='mobile'||r.after.desktopRows===1,
  mobileVisible:r.device==='desktop'||r.after.mobileDisplay==='block'
 }));
 fs.writeFileSync(path.join(OUT,'acceptance.json'),JSON.stringify(checks,null,2)+'\n');
 console.log('NAV_ACCEPTANCE',JSON.stringify(checks));
 if(checks.some(r=>Object.entries(r).some(([k,v])=>!['page','device'].includes(k)&&!v)))process.exitCode=1;
}
main().catch(e=>{console.error('NAV_QA_FAILED',e.message);process.exitCode=1});
