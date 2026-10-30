/* Tech SG Studio – advanced layer: command palette, backup, amount in words, branding extras */
(function(){
"use strict";
var $=function(s,p){return(p||document).querySelector(s)},$$=function(s,p){return[].slice.call((p||document).querySelectorAll(s))};
function toast(m){var t=document.createElement("div");t.className="sg-toast";t.textContent=m;document.body.appendChild(t);setTimeout(function(){t.remove()},2400)}

/* amount in words (Pakistani system) */
var O=["","One","Two","Three","Four","Five","Six","Seven","Eight","Nine","Ten","Eleven","Twelve","Thirteen","Fourteen","Fifteen","Sixteen","Seventeen","Eighteen","Nineteen"],T=["","","Twenty","Thirty","Forty","Fifty","Sixty","Seventy","Eighty","Ninety"];
function two(n){return n<20?O[n]:T[Math.floor(n/10)]+(n%10?" "+O[n%10]:"")}
function three(n){return(n>99?O[Math.floor(n/100)]+" Hundred"+(n%100?" ":""):"")+(n%100?two(n%100):"")}
function words(n){n=Math.floor(n);if(!n)return"Zero";var p=[],c=Math.floor(n/1e7);n%=1e7;var l=Math.floor(n/1e5);n%=1e5;var t=Math.floor(n/1e3);n%=1e3;
 if(c)p.push(three(c)+" Crore");if(l)p.push(two(l)+" Lakh");if(t)p.push(two(t)+" Thousand");if(n)p.push(three(n));return p.join(" ")}

/* top contact strip like techsgstudio.com */
function strip(){var s=document.createElement("div");s.className="sg-strip";
 s.innerHTML="<span>Tech SG Studio (SMC-Pvt.) Ltd. – IT &amp; Software Development Company</span><span><a href='tel:+923069524047'>+92 306 9524047</a><a href='mailto:info@techsgstudio.com'>info@techsgstudio.com</a><a href='https://techsgstudio.com' target='_blank' rel='noopener'>techsgstudio.com</a></span>";
 document.body.insertBefore(s,document.body.firstChild)}

/* footer */
function foot(){var f=$(".page-footer");if(!f)return;var d=document.createElement("div");d.className="sg-foot";
 d.innerHTML="<span>SECP-registered SMC-Private Limited · Faisalabad, Punjab, Pakistan</span><span>Press <b>Ctrl</b>+<b>K</b> for quick actions</span>";f.appendChild(d)}

/* invoice view extras */
function invoiceExtras(){var g=$(".invoice-grand-total strong"),doc=$(".invoice-document");if(!g||!doc||$(".sg-words"))return;
 var mm=g.textContent.match(/[\d,]+(?:\.\d+)?/),v=mm?parseFloat(mm[0].replace(/,/g,"")):0,ps=Math.round((v-Math.floor(v))*100);
 var w=document.createElement("div");w.className="sg-words";
 w.innerHTML="<b>Amount in words:</b> Rupees "+words(v)+(ps?" and "+two(ps)+" Paisa":"")+" Only";
 var sm=$(".invoice-summary");if(sm&&sm.parentNode)sm.parentNode.insertBefore(w,sm.nextSibling);else doc.appendChild(w);
 var s=document.createElement("div");s.innerHTML="<div class='sg-sign'><div>Customer signature</div><div>Authorized signatory – Tech SG Studio (SMC-Pvt.) Ltd.</div></div><div class='sg-terms'><b>Terms:</b> Payment is due by the due date shown. Pay by bank transfer, Easypaisa or JazzCash and submit your payment proof through the link provided by our accounts team. Payments are verified by admin before the invoice is marked Paid. This is a computer-generated invoice.</div>";
 doc.appendChild(s)}

/* backup / restore of all techsg_* data */
function backup(){var o={};for(var i=0;i<localStorage.length;i++){var k=localStorage.key(i);if(/^techsg_|^sg-/.test(k))o[k]=localStorage.getItem(k)}
 var a=document.createElement("a");a.href=URL.createObjectURL(new Blob([JSON.stringify(o,null,1)],{type:"application/json"}));a.download="techsg-invoicing-backup-"+new Date().toISOString().slice(0,10)+".json";a.click();toast("Backup downloaded")}
function restore(){var i=document.createElement("input");i.type="file";i.accept=".json";i.onchange=function(){var r=new FileReader();r.onload=function(){try{var o=JSON.parse(r.result);Object.keys(o).forEach(function(k){if(/^techsg_|^sg-/.test(k))localStorage.setItem(k,o[k])});toast("Backup restored – reloading");setTimeout(function(){location.reload()},900)}catch(e){toast("Invalid backup file")}};r.readAsText(i.files[0])};i.click()}

/* command palette */
function palette(){
 var items=$$(".sidebar .nav a").map(function(a){return{t:a.textContent.replace(/\s+/g," ").trim(),h:"Go to page",f:function(){location.href=a.getAttribute("href")}}});
 items.unshift({t:"New invoice",h:"Action",f:function(){location.href="invoice-create.html"}},{t:"New quotation",h:"Action",f:function(){location.href="quotation-create.html"}},
  {t:"Export backup (JSON)",h:"Data",f:backup},{t:"Import backup",h:"Data",f:restore},{t:"Toggle dark mode",h:"Theme",f:function(){var d=document.body.classList.toggle("dark-mode");try{localStorage.setItem("sg-theme",d?"dark":"light")}catch(e){}}},{t:"Print this page",h:"Action",f:function(){window.print()}},{t:"Open techsgstudio.com",h:"Link",f:function(){window.open("https://techsgstudio.com","_blank")}});
 var m=document.createElement("div");m.id="sgK";m.innerHTML="<div class='box'><input placeholder='Type a page or action…' aria-label='Quick actions'><ul></ul></div>";document.body.appendChild(m);
 var inp=$("input",m),ul=$("ul",m),sel=0,list=[];
 function draw(){var q=inp.value.toLowerCase();list=items.filter(function(i){return i.t.toLowerCase().indexOf(q)>-1});sel=Math.min(sel,Math.max(list.length-1,0));
  ul.innerHTML=list.map(function(i,n){return"<li class='"+(n===sel?"sel":"")+"' data-n='"+n+"'><span>"+i.t+"</span><small>"+i.h+"</small></li>"}).join("")||"<li>No results</li>"}
 function open(){m.classList.add("on");inp.value="";sel=0;draw();inp.focus()}function close(){m.classList.remove("on")}
 ul.onclick=function(e){var li=e.target.closest("li[data-n]");if(li){close();list[+li.dataset.n].f()}};
 m.onclick=function(e){if(e.target===m)close()};inp.oninput=function(){sel=0;draw()};
 inp.onkeydown=function(e){if(e.key==="ArrowDown"){sel=Math.min(sel+1,list.length-1);draw();e.preventDefault()}else if(e.key==="ArrowUp"){sel=Math.max(sel-1,0);draw();e.preventDefault()}else if(e.key==="Enter"&&list[sel]){close();list[sel].f()}else if(e.key==="Escape")close()};
 document.addEventListener("keydown",function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==="k"){e.preventDefault();m.classList.contains("on")?close():open()}
  else if(e.key==="Escape")close()})}

/* reveal + count-up */
function motion(){var els=$$(".card,.stat-card,.metric-card");els.forEach(function(e){e.classList.add("sg-reveal")});
 if(!("IntersectionObserver"in window)){els.forEach(function(e){e.classList.add("in")});return}
 var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add("in");io.unobserve(x.target)}})},{threshold:.08});els.forEach(function(e){io.observe(e)})}

function bars(){$$('.bar[data-height]').forEach(function(b,i){b.style.height='0%';setTimeout(function(){b.style.transition='height .8s ease';b.style.height=(+b.dataset.height*0.78)+'%'},120+i*90)})}
function currencies(){$$('select').forEach(function(sel){var seen={};[].slice.call(sel.options).forEach(function(o){if(seen[o.value])o.remove();else seen[o.value]=1})});
 var c=$('#currency')||$('#companyCurrency');if(c&&!c.dataset.sg){c.dataset.sg=1;try{var saved=localStorage.getItem('techsg_currency_pref');c.value=saved||'PKR'}catch(e){c.value='PKR'}c.addEventListener('change',function(){try{localStorage.setItem('techsg_currency_pref',c.value)}catch(e){}})}}
function init(){currencies();bars();strip();foot();invoiceExtras();palette();motion()}
if(document.readyState==="complete")init();else window.addEventListener("load",init);
})();
