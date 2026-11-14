#!/usr/bin/env node
'use strict';
/** Read-only published proof of governed WordPress Blog Query Loop and Careers page. */
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
const ROOT='https://emmake.com/nuevaweb/';
const OUTPUT='artifacts/emmake-blog-careers-live-acceptance';
const devices=[['desktop',1440,900,false],['mobile',390,844,true]];
function assert(ok,tag){if(!ok)throw Error(tag)}
async function snapshot(pg){
 return pg.evaluate(()=>{
  const main=document.querySelector('main');
  const root=main?.querySelector('.wp-block-post-content');
  const cards=[...document.querySelectorAll('main .seo-geo-native-blog-query .seo-geo-inner-service-card')];
  const titles=[...document.querySelectorAll('main .seo-geo-native-blog-query .wp-block-post-title a[href]')];
  const pagination=document.querySelector('main .wp-block-query-pagination');
  const next=pagination?.querySelector('a.wp-block-query-pagination-next, a[href*="query-7-page"]');
  const grid=document.querySelector('main .seo-geo-native-blog-query .seo-geo-inner-service-grid');
  const urls=titles.map(a=>{try{return new URL(a.href).pathname}catch{return ''}});
  return {
   finalUrl:location.href,
   robots:document.querySelector('meta[name="robots"]')?.content||'',
   h1:main?.querySelectorAll('h1').length||0,
   textLength:root?.innerText?.length||0,
   cards:cards.length,links:urls,unique:new Set(urls).size,
   gridColumns:grid?getComputedStyle(grid).gridTemplateColumns.trim().split(/\s+/).filter(Boolean).length:0,
   next:next?.href||null,hasPagination:!!pagination,
   overflow:document.documentElement.scrollWidth>innerWidth+4,
   privacy:!!main?.querySelector('a[href*="politica-de-privacidad"]'),
   careerCards:main?.querySelectorAll('.seo-geo-inner-service-card').length||0,
   hrEmail:!!main?.querySelector('a[href="mailto:rrhh@emmake.com"]'),
   oldJob:!!(root?.innerText||'').match(/Game Master Streaming/i),
   brokenImages:[...(main?.querySelectorAll('img')||[])].filter(x=>x.complete&&x.naturalWidth===0).length
  };
 });
}
async function main(){
 fs.mkdirSync(OUTPUT,{recursive:true});
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const rows=[];
 try{
  for(const [device,width,height,mobile] of devices){
   const ctx=await browser.newContext({viewport:{width,height},isMobile:mobile,hasTouch:mobile,
    deviceScaleFactor:1,locale:'es-ES',serviceWorkers:'block'});
   try{
    const pg=await ctx.newPage();
    const a=await pg.goto(ROOT+'trabaja-con-nosotros/',{waitUntil:'domcontentloaded',timeout:30000});
    await pg.waitForTimeout(350);
    const career=await snapshot(pg);
    const careerChecks={
     status:a?.status()===200,uniqueH1:career.h1===1,noindex:career.robots.includes('noindex'),
     substantial:career.textLength>700,threeCards:career.careerCards===3,
     hrAddress:career.hrEmail,staleRoleRemoved:!career.oldJob,privacyLink:career.privacy,
     noOverflow:!career.overflow,imagesOkay:career.brokenImages===0
    };
    rows.push({device,page:'careers',data:career,checks:careerChecks});
    await pg.screenshot({path:path.join(OUTPUT,device+'-careers.png'),fullPage:true,animations:'disabled'});
    const b=await pg.goto(ROOT+'blog/',{waitUntil:'domcontentloaded',timeout:30000});
    await pg.waitForTimeout(400);
    const blog=await snapshot(pg);
    const blogChecks={
     status:b?.status()===200,uniqueH1:blog.h1===1,noindex:blog.robots.includes('noindex'),
     twelveCards:blog.cards===12,uniquePosts:blog.unique===12,
     gridResponsive:blog.gridColumns===(mobile?1:3),
     paginationPresent:blog.hasPagination&&!!blog.next,
     noOverflow:!blog.overflow,imagesOkay:blog.brokenImages===0
    };
    rows.push({device,page:'blog-page-1',data:blog,checks:blogChecks});
    await pg.screenshot({path:path.join(OUTPUT,device+'-blog-page-1.png'),fullPage:true,animations:'disabled'});
    if(blog.next){
     const nextURL=new URL(blog.next);
     assert(nextURL.origin===new URL(ROOT).origin && nextURL.pathname===new URL(ROOT+'blog/').pathname,
       'unsafe_pagination_next_url');
     const c=await pg.goto(blog.next,{waitUntil:'domcontentloaded',timeout:30000});
     await pg.waitForTimeout(350);
     const page2=await snapshot(pg);
     const check={
      status:c?.status()===200,uniqueH1:page2.h1===1,
      cards:page2.cards===12,uniquePosts:page2.unique===12,
      differentPosts:page2.links.every(link=>!blog.links.includes(link)),
      noindex:page2.robots.includes('noindex'),noOverflow:!page2.overflow
     };
     rows.push({device,page:'blog-page-2',data:page2,checks:check});
     await pg.screenshot({path:path.join(OUTPUT,device+'-blog-page-2.png'),fullPage:true,animations:'disabled'});
    }
    await pg.close();
   }finally{await ctx.close()}
  }
 }finally{await browser.close()}
 const failed=rows.flatMap(r=>Object.entries(r.checks).filter(([,passed])=>!passed).map(([id])=>r.device+':'+r.page+':'+id));
 const report={site:'emmake-nuevaweb',scope:'read-only-public-browser-acceptance',
  wordpress_mutations:0,form_submissions:0,rows,failed,passed:failed.length===0};
 fs.writeFileSync(path.join(OUTPUT,'report.json'),JSON.stringify(report,null,2)+'\n');
 console.log('EMMAKE_ACCEPTANCE',JSON.stringify({passed:report.passed,failed,
  pages:rows.map(x=>({device:x.device,page:x.page,cards:x.data.cards,
   gridColumns:x.data.gridColumns,linkCount:x.data.links.length,next:x.data.next,
   noOverflow:!x.data.overflow}))}));
 if(!report.passed)process.exitCode=1;
}
main().catch(e=>{console.error('Emmake native blog/Careers audit failed:',e.message);process.exitCode=1});
