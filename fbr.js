// /* Tech SG Studio (SMC-Pvt.) Ltd. – FBR Digital Invoicing layer (front-end).
//    Modes: simulation (default, nothing sent) | sandbox | production (real HTTP calls). */
// (function(){
// "use strict";
// var KEY="techsg_fbr_cfg_v1",$=function(s,p){return(p||document).querySelector(s)};
// var D={name:"Tech SG Studio (SMC-Pvt.) Ltd.",ntn:"",strn:"",province:"Punjab",address:"Faisalabad, Punjab, Pakistan",mode:"simulation",
//  validateUrl:"https://gw.fbr.gov.pk/di_data/v1/di/validateinvoicedata",postUrl:"https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata",token:"",taxRate:16,hsCode:"9806.0000",uom:"Numbers, pieces, units"};
// function cfg(){try{return Object.assign({},D,JSON.parse(localStorage.getItem(KEY)||"{}"))}catch(e){return Object.assign({},D)}}
// function put(c){try{localStorage.setItem(KEY,JSON.stringify(c))}catch(e){}}
// function el(h){var d=document.createElement("div");d.innerHTML=h;return d.firstElementChild}
// function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]})}

// /* ---- company block on invoice ---- */
// function brandInvoice(){
//  var b=$(".invoice-company>div:last-child");if(!b)return;var c=cfg();
//  b.innerHTML="<strong>Tech SG Studio</strong><span>(SMC-Pvt.) Ltd.</span><div class='fbr-co'>"+esc(c.address)+"<br>NTN: "+esc(c.ntn||"— set in Settings")+" | STRN: "+esc(c.strn||"— set in Settings")+"</div>";
// }

// /* ---- settings card ---- */
// function settingsCard(){
//  var host=$("main")||$(".page")||document.body,c=cfg();if($("#fbrCfg"))return;
//  var f=function(id,l,v,t){return"<div><label for='"+id+"'>"+l+"</label><input id='"+id+"' type='"+(t||"text")+"' value='"+esc(v)+"'></div>"};
//  var n=el("<section class='fbr-card' id='fbrCfg'><h3>FBR Digital Invoicing</h3><p class='m'>Company tax identity and FBR (PRAL) connection. Stored in this browser only.</p><div class='fbr-grid'>"+
//  f("fNtn","Seller NTN / CNIC",c.ntn)+f("fStrn","STRN",c.strn)+f("fProv","Seller province",c.province)+f("fAddr","Seller address",c.address)+
//  f("fRate","Sales tax rate %",c.taxRate,"number")+f("fHs","Default HS code",c.hsCode)+
//  "<div><label for='fMode'>Mode</label><select id='fMode'><option value='simulation'>Simulation (nothing sent)</option><option value='sandbox'>Sandbox</option><option value='production'>Production</option></select></div>"+
//  f("fVal","Validate URL",c.validateUrl)+f("fPost","Post URL",c.postUrl)+f("fTok","Bearer token (from PRAL)",c.token,"password")+
//  "</div><div class='fbr-warn'>Browsers usually block direct calls to the FBR gateway (CORS), and a token stored in the browser is visible to anyone using this device. For real submissions, point the URLs to your own server-side proxy that holds the token. FBR does not allow cancelling a posted invoice, so test in Sandbox first.</div><div class='fbr-row'><button class='fbr-btn' id='fSave'>Save FBR settings</button><span id='fMsg' class='fbr-badge ok' style='display:none'>Saved</span></div></section>");
//  host.appendChild(n);$("#fMode").value=c.mode;
//  $("#fSave").onclick=function(){var v=function(i){return $("#"+i).value.trim()};
//   put({name:D.name,ntn:v("fNtn"),strn:v("fStrn"),province:v("fProv"),address:v("fAddr"),taxRate:+v("fRate")||0,hsCode:v("fHs"),mode:v("fMode"),validateUrl:v("fVal"),postUrl:v("fPost"),token:$("#fTok").value,uom:D.uom});
//   var m=$("#fMsg");m.style.display="inline-block";setTimeout(function(){m.style.display="none"},2000)};
// }

// /* ---- invoice view: payload, validate, submit, QR ---- */
// function num(t){return parseFloat(String(t).replace(/[^0-9.\-]/g,""))||0}
// function payload(c){
//  if(window.SG_CUR){var I=SG_CUR.inv,C=SG_CUR.client||{};return{invoiceType:"Sale Invoice",invoiceDate:I.issue,sellerNTNCNIC:c.ntn,sellerBusinessName:c.name,sellerProvince:c.province,sellerAddress:c.address,buyerNTNCNIC:C.ntn||"",buyerBusinessName:C.name||"Walk-in Customer",buyerProvince:c.province,buyerAddress:C.address||"",buyerRegistrationType:C.ntn?"Registered":"Unregistered",invoiceRefNo:I.id,items:I.items.map(function(x){var ex=x.q*x.p;return{hsCode:c.hsCode,productDescription:x.d,rate:c.taxRate+"%",uoM:c.uom,quantity:x.q,totalValues:+(ex*(1+c.taxRate/100)).toFixed(2),valueSalesExcludingST:ex,fixedNotifiedValueOrRetailPrice:0,salesTaxApplicable:+(ex*c.taxRate/100).toFixed(2),salesTaxWithheldAtSource:0,extraTax:"",furtherTax:0,sroScheduleNo:"",fedPayable:0,discount:0,saleType:"Services",sroItemSerialNo:""}})}}
//  var doc=$(".invoice-document"),no=($(".invoice-document-title strong")||{}).textContent||"INV";
//  var buyer=($(".invoice-info-block strong")||{}).textContent||"Walk-in Customer",rows=[];
//  doc.querySelectorAll("table tbody tr").forEach(function(r){var t=r.cells;if(t.length>=3){var q=num(t[t.length>=5?2:1].textContent)||1,a=num(t[t.length-1].textContent),ex=a;
//   rows.push({hsCode:c.hsCode,productDescription:(t[0].textContent+" "+(t.length>=5?t[1].textContent:"")).replace(/\s+/g," ").trim(),rate:c.taxRate+"%",uoM:c.uom,quantity:q,totalValues:+(ex*(1+c.taxRate/100)).toFixed(2),valueSalesExcludingST:ex,fixedNotifiedValueOrRetailPrice:0,salesTaxApplicable:+(ex*c.taxRate/100).toFixed(2),salesTaxWithheldAtSource:0,extraTax:"",furtherTax:0,sroScheduleNo:"",fedPayable:0,discount:0,saleType:"Services",sroItemSerialNo:""})}});
//  return{invoiceType:"Sale Invoice",invoiceDate:new Date().toISOString().slice(0,10),sellerNTNCNIC:c.ntn,sellerBusinessName:c.name,sellerProvince:c.province,sellerAddress:c.address,buyerNTNCNIC:"",buyerBusinessName:buyer.trim(),buyerProvince:c.province,buyerAddress:"",buyerRegistrationType:"Unregistered",invoiceRefNo:no.trim(),items:rows};
// }
// function call(url,c,body){
//  return fetch(url,{method:"POST",headers:{"Content-Type":"application/json","Authorization":"Bearer "+c.token},body:JSON.stringify(body)}).then(function(r){return r.json()});
// }
// function invoicePanel(){
//  var doc=$(".invoice-document")||$(".inv-fit");if(!doc||$("#fbrBox"))return;
//  var box=el("<section class='fbr-card' id='fbrBox'><h3>FBR Digital Invoice <span class='fbr-badge' id='fSt'>Not submitted</span></h3><p class='m' id='fModeTxt'></p><div class='fbr-row'><button class='fbr-btn g' id='fV'>Validate</button><button class='fbr-btn' id='fS'>Submit to FBR</button></div><div class='fbr-out' id='fOut'></div><div class='fbr-log' id='fLog'></div></section>");
//  doc.parentNode.insertBefore(box,doc.nextSibling);
//  var no=window.SG_CUR?SG_CUR.inv.id:(($(".invoice-document-title strong")||{}).textContent||"INV").trim(),sk="techsg_fbr_inv_"+no;
//  function mode(){var c=cfg();$("#fModeTxt").textContent={simulation:"Simulation mode – nothing is sent to FBR; the IRN below is a placeholder.",sandbox:"Sandbox mode – test submission.",production:"Production mode – real submission, cannot be cancelled."}[c.mode]}
//  function show(r){var o=$("#fOut");o.innerHTML="";if(!r)return;var s=$("#fSt");s.textContent=r.sim?"Simulated":"Submitted";s.className="fbr-badge ok";
//   o.innerHTML="<div><small>FBR Invoice No. (IRN)</small><br><code>"+esc(r.irn)+"</code></div><div id='fQr'></div>";
//   $("#fQr").innerHTML='<div style="width:96px;height:96px">'+(window.SG?SG.qr(r.irn):"")+"</div>"}
//  function log(t){$("#fLog").textContent=t}
//  function run(post){var c=cfg(),p=payload(c);
//   if(!p.items.length){log("No line items found on this invoice.");return}
//   if(!c.ntn){log("Set your NTN in Settings → FBR Digital Invoicing first.");return}
//   if(c.mode==="simulation"){
//    if(!post){log("Validation passed locally ("+p.items.length+" item(s)). Nothing was sent.");return}
//    var r={irn:c.ntn+"-"+p.invoiceDate.replace(/-/g,"")+"-SIM"+Math.floor(1000+Math.random()*9000),sim:true};
//    try{localStorage.setItem(sk,JSON.stringify(r))}catch(e){}show(r);log("Simulated submission saved. Switch mode in Settings for real calls.");return}
//   log("Contacting FBR…");
//   call(post?c.postUrl:c.validateUrl,c,p).then(function(j){
//    var ok=j&&j.validationResponse&&j.validationResponse.statusCode==="00";
//    if(!post){log(ok?"Validation passed.":"Validation failed: "+JSON.stringify(j.validationResponse||j));return}
//    if(j.invoiceNumber){var r={irn:j.invoiceNumber};try{localStorage.setItem(sk,JSON.stringify(r))}catch(e){}show(r);log("Submitted to FBR.")}
//    else{$("#fSt").textContent="Rejected";$("#fSt").className="fbr-badge er";log("FBR response: "+JSON.stringify(j.validationResponse||j))}
//   }).catch(function(e){log("Request failed ("+e.message+"). Likely CORS or network – use a server-side proxy URL in Settings.")})}
//  $("#fV").onclick=function(){run(false)};$("#fS").onclick=function(){run(true)};mode();
//  try{show(JSON.parse(localStorage.getItem(sk)||"null"))}catch(e){}
// }

// function init(){brandInvoice();invoicePanel();if(/settings/.test(location.pathname))settingsCard()}
// window.SGFBR={init:init};if(!window.SG_NOAUTO){if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init()}
// })();


/* The FBR layer is switched off. This small file stays so every page that loads fbr.js still works. */
window.SGFBR = { init: function () {} };