/* Tech SG Studio (SMC-Pvt.) Ltd. – core: data store, auth/roles, auto IDs, share links, QR, invoice design, shell, dev guide.
   Front-end only: data lives in this browser (localStorage). See the Dev Guide panel for backend mapping. */
(function(){
"use strict";
var W=window,D=document,NS="techsg_v2_",SG={stop:false};W.SG=SG;
function esc(s){return String(s==null?"":s).replace(/[&<>"']/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]})}
var DB={get:function(k,d){try{var v=localStorage.getItem(NS+k);return v?JSON.parse(v):d}catch(e){return d}},set:function(k,v){try{localStorage.setItem(NS+k,JSON.stringify(v))}catch(e){}return v}};
/* sha256 (compact, sync) */
function sha256(a){function R(v,n){return(v>>>n)|(v<<(32-n))}var mp=Math.pow,mw=mp(2,32),i,j,r="",w=[],bl=a.length*8,h=sha256.h=sha256.h||[],k=sha256.k=sha256.k||[],pc=k.length,ic={};
 for(var c=2;pc<64;c++)if(!ic[c]){for(i=0;i<313;i+=c)ic[i]=c;h[pc]=(mp(c,.5)*mw)|0;k[pc++]=(mp(c,1/3)*mw)|0}
 a+="\x80";while(a.length%64-56)a+="\x00";for(i=0;i<a.length;i++){j=a.charCodeAt(i);w[i>>2]|=j<<((3-i)%4)*8}
 w[w.length]=(bl/mw)|0;w[w.length]=bl;
 for(j=0;j<w.length;){var x=w.slice(j,j+=16),o=h;h=h.slice(0,8);
  for(i=0;i<64;i++){var w15=x[i-15],w2=x[i-2],A=h[0],E=h[4];
   var t1=h[7]+(R(E,6)^R(E,11)^R(E,25))+((E&h[5])^((~E)&h[6]))+k[i]+(x[i]=(i<16)?x[i]:(x[i-16]+(R(w15,7)^R(w15,18)^(w15>>>3))+x[i-7]+(R(w2,17)^R(w2,19)^(w2>>>10)))|0);
   var t2=(R(A,2)^R(A,13)^R(A,22))+((A&h[1])^(A&h[2])^(h[1]&h[2]));h=[(t1+t2)|0].concat(h);h[4]=(h[4]+t1)|0}
  for(i=0;i<8;i++)h[i]=(h[i]+o[i])|0}
 for(i=0;i<8;i++)for(j=3;j+1;j--){var b=(h[i]>>(j*8))&255;r+=(b<16?0:"")+b.toString(16)}return r}
function hash(p){return sha256(unescape(encodeURIComponent("techsg|"+p)))}
function iso(o){var d=new Date();d.setDate(d.getDate()+(o||0));return d.toISOString().slice(0,10)}
function pad(n,l){n=String(n);while(n.length<l)n="0"+n;return n}
SG.esc=esc;SG.DB=DB;SG.iso=iso;SG.hash=hash;
SG.num=function(n){return(+n||0).toLocaleString("en-PK",{maximumFractionDigits:2})};
SG.fmt=function(n){return"Rs. "+SG.num(n)};
/* company profile */
var CO={name:"Tech SG Studio",legal:"Tech SG Studio (SMC-Pvt.) Ltd.",tagline:"IT & Software Development Company",slogan:"Ideas | Code | Solutions",phone:"+92 306 9524047",email:"info@techsgstudio.com",web:"www.techsgstudio.com",address:"Faisalabad, Punjab, Pakistan",ntn:"",strn:"",bank:"",acTitle:"Tech SG Studio (SMC-Pvt.) Ltd.",acNo:"",iban:"",methods:"Bank Transfer / Easypaisa / JazzCash",terms:14};
SG.company=function(){return Object.assign({},CO,DB.get("company",{}))};SG.saveCompany=function(c){DB.set("company",c)};
/* data */
SG.clients=function(){return DB.get("clients",[])};SG.saveClients=function(a){DB.set("clients",a)};
SG.invoices=function(){return DB.get("invoices",[])};SG.saveInvoices=function(a){DB.set("invoices",a)};
SG.users=function(){return DB.get("users",[])};SG.saveUsers=function(a){DB.set("users",a)};
SG.client=function(id){return SG.clients().filter(function(c){return c.id===id})[0]};
SG.invoice=function(id){return SG.invoices().filter(function(c){return c.id===id})[0]};
SG.nextClientId=function(){var m=0;SG.clients().forEach(function(c){var n=+String(c.id).replace(/\D/g,"");if(n>m)m=n});return"CL-"+pad(m+1,4)};
SG.nextInvoiceId=function(){var y=new Date().getFullYear(),m=0,p="TSG-INV-"+y+"-";SG.invoices().forEach(function(c){if(String(c.id).indexOf(p)===0){var n=+String(c.id).slice(p.length);if(n>m)m=n}});return p+pad(m+1,4)};
SG.nextUserId=function(){var m=0;SG.users().forEach(function(c){var n=+String(c.id).replace(/\D/g,"");if(n>m)m=n});return"U-"+pad(m+1,4)};
SG.totals=function(i){var s=0;(i.items||[]).forEach(function(x){s+=(+x.q||0)*(+x.p||0)});var d=Math.min(+i.discount||0,s),tx=(s-d)*(+i.taxRate||0)/100;return{sub:s,disc:d,tax:tx,total:s-d+tx}};
SG.status=function(i){return i.status==="Pending"&&i.due<iso(0)?"Overdue":i.status};
SG.badge=function(s){var c={Paid:"green",Pending:"orange",Overdue:"red",Draft:"gray",Cancelled:"red"}[s]||"gray";return'<span class="badge '+c+'">'+esc(s)+"</span>"};
function seed(){
 if(!DB.get("users"))DB.set("users",[{id:"U-0001",name:"Uzair Ahmad",username:"admin",email:"info@techsgstudio.com",role:"admin",active:true,hash:hash("Admin@123"),created:iso(0)}]);
 if(!DB.get("clients")){var y=new Date().getFullYear(),I=function(n){return"TSG-INV-"+y+"-"+pad(n,4)};
  DB.set("clients",[{id:"CL-0001",name:"ABC Enterprises",contact:"Ali Raza",email:"client@abcenterprise.com",phone:"+92 300 1234567",address:"Office No. 12, Business Center, Lahore, Pakistan",ntn:""},{id:"CL-0002",name:"Noor Academy",contact:"Noor Fatima",email:"admin@noor-academy.example",phone:"+92 301 7654321",address:"Susan Road, Faisalabad, Pakistan",ntn:""},{id:"CL-0003",name:"Sara Traders",contact:"Sara Khan",email:"sara@traders.example",phone:"+92 302 1112223",address:"Clifton, Karachi, Pakistan",ntn:""}]);
  DB.set("invoices",[
  {id:I(1),clientId:"CL-0001",issue:iso(-5),terms:14,due:iso(9),taxRate:0,discount:0,status:"Pending",notes:"",createdBy:"admin",items:[{d:"Website Development (Custom)",s:"Responsive website with modern design and CMS integration",q:1,p:50000},{d:"Mobile App Development (Android & iOS)",s:"Cross-platform mobile application (Flutter)",q:1,p:80000},{d:"UI/UX Design",s:"Modern and user-friendly interface design",q:1,p:15000},{d:"Testing & Deployment",s:"Quality assurance and production deployment",q:1,p:10000},{d:"Maintenance (3 Months)",s:"Bug fixes, updates and technical support",q:1,p:7500}]},
  {id:I(2),clientId:"CL-0002",issue:iso(-30),terms:14,due:iso(-16),taxRate:0,discount:0,status:"Paid",paidAt:iso(-18),notes:"",createdBy:"admin",items:[{d:"LMS Setup",s:"Courses platform configuration",q:1,p:60000},{d:"Staff Training",s:"2-day onboarding",q:1,p:10000}]},
  {id:I(3),clientId:"CL-0003",issue:iso(-40),terms:14,due:iso(-26),taxRate:0,discount:2000,status:"Pending",notes:"",createdBy:"admin",items:[{d:"SEO Package (Monthly)",s:"On-page SEO and reporting",q:1,p:25000}]},
  {id:I(4),clientId:"CL-0001",issue:iso(-2),terms:7,due:iso(5),taxRate:0,discount:0,status:"Draft",notes:"",createdBy:"admin",items:[{d:"Domain & Hosting (1 Year)",s:"Domain registration and shared hosting",q:1,p:8000}]}])}
}
seed();
/* auth */
SG.session=function(){var s=DB.get("session");return s&&s.exp>Date.now()?s:null};
SG.login=function(u,p){var us=SG.users().filter(function(x){return x.username.toLowerCase()===String(u).trim().toLowerCase()&&x.active!==false})[0];if(!us||us.hash!==hash(p))return null;DB.set("session",{uid:us.id,name:us.name,role:us.role,username:us.username,exp:Date.now()+12*36e5});return us};
SG.logout=function(){try{localStorage.removeItem(NS+"session")}catch(e){}location.href="login.html"};
SG.isAdmin=function(){var s=SG.session();return!!s&&s.role==="admin"};
var PAGE=(location.pathname.split("/").pop()||"index.html").replace(/\.html$/,"")||"index";SG.page=PAGE;
var PUB=["login","forgot-password","reset-password","invoice-share","pay-proof"],ADM=["users","settings","audit-log"],S=SG.session();
if(PUB.indexOf(PAGE)<0){if(!S){SG.stop=true;location.replace("login.html?next="+encodeURIComponent(PAGE+".html"+location.search))}else if(S.role!=="admin"&&ADM.indexOf(PAGE)>-1){SG.stop=true;location.replace("index.html")}}
SG.ready=function(f){if(SG.stop)return;if(D.readyState==="loading")D.addEventListener("DOMContentLoaded",f);else f()};
SG.toast=function(m){var t=D.createElement("div");t.className="sg-toast";t.textContent=m;D.body.appendChild(t);setTimeout(function(){t.remove()},2600)};
/* share link (data travels inside the link – no server needed) */
function b64u(u){var s="";for(var i=0;i<u.length;i++)s+=String.fromCharCode(u[i]);return btoa(s).replace(/\+/g,"-").replace(/\//g,"_").replace(/=+$/,"")}
function unb(s){s=s.replace(/-/g,"+").replace(/_/g,"/");while(s.length%4)s+="=";var b=atob(s),u=new Uint8Array(b.length);for(var i=0;i<b.length;i++)u[i]=b.charCodeAt(i);return u}
async function pipe(data,S){var s=new S("deflate-raw"),w=s.writable.getWriter();w.write(data);w.close();return new Uint8Array(await new Response(s.readable).arrayBuffer())}
SG.pack=async function(o){var u=new TextEncoder().encode(JSON.stringify(o));if(W.CompressionStream)return"z"+b64u(await pipe(u,W.CompressionStream));return"p"+b64u(u)};
SG.unpack=async function(h){var u=unb(h.slice(1));if(h[0]==="z")u=await pipe(u,W.DecompressionStream);return JSON.parse(new TextDecoder().decode(u))};
SG.shareData=function(inv){var c=SG.client(inv.clientId)||{},co=SG.company();return{i:inv,c:{id:c.id,name:c.name,email:c.email,phone:c.phone,address:c.address,ntn:c.ntn},o:co}};
SG.shareLink=async function(inv){return location.href.replace(/[?#].*$/,"").replace(/[^\/]*$/,"")+"invoice-share.html#"+await SG.pack(SG.shareData(inv))};
SG.qr=function(t){try{var q=W.qrcode(0,"L");q.addData(t);q.make();return q.createSvgTag({cellSize:3,margin:0,scalable:true})}catch(e){return""}};
SG.waPhone=function(p){var d=String(p||"").replace(/\D/g,"");if(d.charAt(0)==="0")d="92"+d.slice(1);return d};
SG.emailLink=function(inv,cl,link){var co=SG.company(),t=SG.totals(inv);return"mailto:"+encodeURIComponent(cl.email||"")+"?subject="+encodeURIComponent("Invoice "+inv.id+" from "+co.name)+"&body="+encodeURIComponent("Dear "+(cl.contact||cl.name||"Customer")+",\n\nPlease find your invoice "+inv.id+" for "+SG.fmt(t.total)+" (due "+inv.due+").\nView / download it online:\n"+link+"\n\nRegards,\n"+co.name+"\n"+co.phone+" | "+co.email)};
SG.waLink=function(inv,cl,link){var t=SG.totals(inv);return"https://wa.me/"+SG.waPhone(cl.phone)+"?text="+encodeURIComponent("Assalam o Alaikum "+(cl.contact||cl.name||"")+", your invoice "+inv.id+" for "+SG.fmt(t.total)+" is ready. View it here: "+link)};
/* invoice design */
var IC={web:'<path d="M8 7l-5 5 5 5M16 7l5 5-5 5M14 4l-4 16"/>',app:'<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',cloud:'<path d="M7 18a4 4 0 010-8 5 5 0 019.6-1A4.5 4.5 0 0117 18z"/>',gear:'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',user:'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>',build:'<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',mail:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',phone:'<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',pin:'<path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',globe:'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',card:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',shield:'<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',hand:'<path d="M3 12l4-4 4 2 4-2 6 5-5 5-3-2-3 2-7-5z"/>',head:'<path d="M4 14v-2a8 8 0 0116 0v2"/><rect x="3" y="14" width="4" height="6" rx="1"/><rect x="17" y="14" width="4" height="6" rx="1"/>',doc:'<path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7M9 16h7"/>'};
function ic(n){return'<svg viewBox="0 0 24 24" aria-hidden="true">'+IC[n]+"</svg>"}SG.ic=ic;
SG.invoiceHTML=function(inv,cl,co,link){cl=cl||{};var t=SG.totals(inv),st=SG.status(inv),qr=link?SG.qr(link):"";
 var rows=(inv.items||[]).map(function(x,i){return"<tr><td>"+(i+1)+"</td><td><b>"+esc(x.d)+"</b><small>"+esc(x.s||"")+"</small></td><td>"+esc(x.q)+"</td><td>"+SG.num(x.p)+"</td><td>"+SG.num(x.q*x.p)+"</td></tr>"}).join("");
 var r=function(a,b){return"<div><span>"+a+"</span><b>"+(esc(b)||"—")+"</b></div>"},m=function(a,b){return"<div class='inv-ct'>"+ic(a)+"<span>"+esc(b)+"</span></div>"};
 var terms=["Payment is due within "+(inv.terms||co.terms)+" days from the invoice date.","Late payments may result in additional charges.","All work remains the property of "+co.name+" until full payment is received.","For any queries, please contact us at "+co.email+"."].map(function(x){return"<li>"+esc(x)+"</li>"}).join("");
 return'<article class="inv" data-inv="'+esc(inv.id)+'">'+(st==="Paid"?'<div class="inv-stamp">PAID</div>':"")+
 '<header class="inv-top"><div class="inv-brand"><img src="assets/logo.png" alt="'+esc(co.name)+'"><div><h2>'+esc(co.name)+"</h2><em>"+esc(co.slogan)+"</em><p>"+esc(co.tagline.toUpperCase())+'</p></div></div><div class="inv-hero"><p>Building Digital Solutions<br>for a Better Tomorrow</p><ul><li>'+ic("web")+"Web<br>Development</li><li>"+ic("app")+"Mobile App<br>Development</li><li>"+ic("cloud")+"Cloud<br>Solutions</li><li>"+ic("gear")+"Custom Software<br>Development</li></ul></div></header>"+
 '<section class="inv-meta"><div class="inv-title"><h1>INVOICE</h1><p>Professional IT &amp; Software Development Services</p></div><div class="inv-facts">'+r("Invoice No:",inv.id)+r("Issue Date:",inv.issue)+r("Due Date:",inv.due)+r("Payment Terms:","Net "+(inv.terms||co.terms))+"</div></section>"+
 '<section class="inv-parties"><div class="inv-card invoice-info-block"><h3>'+ic("user")+"Bill To</h3><strong>"+esc(cl.name||"—")+"</strong>"+(cl.id?"<small>Client ID: "+esc(cl.id)+"</small>":"")+(cl.email?m("mail",cl.email):"")+(cl.phone?m("phone",cl.phone):"")+(cl.address?m("pin",cl.address):"")+(cl.ntn?m("doc","NTN/CNIC: "+cl.ntn):"")+"</div>"+
 '<div class="inv-card"><h3>'+ic("build")+"From</h3><div class='inv-from'><div><strong>"+esc(co.name)+"</strong><small>"+esc(co.tagline)+"</small>"+m("mail",co.email)+m("phone",co.phone)+m("pin",co.address)+m("globe",co.web)+(co.ntn?m("doc","NTN: "+co.ntn+(co.strn?"  |  STRN: "+co.strn:"")):"")+"</div><q>Your Vision<br>Our Code</q></div></div></section>"+
 '<table class="inv-table"><thead><tr><th>#</th><th>Service / Description</th><th>Qty</th><th>Unit Price (PKR)</th><th>Amount (PKR)</th></tr></thead><tbody>'+rows+"</tbody></table>"+
 '<section class="inv-lower"><div><div class="inv-card inv-pay"><h3>'+ic("card")+"Payment Details</h3>"+r("Bank Name",co.bank)+r("Account Title",co.acTitle)+r("Account Number",co.acNo)+r("IBAN",co.iban)+r("Payment Method",co.methods)+"</div>"+
 '<div class="inv-badges"><span>'+ic("shield")+"Secure<br>Payments</span><span>"+ic("hand")+"Professional<br>Service</span><span>"+ic("head")+"24/7<br>Support</span></div></div>"+
 '<div><div class="inv-tot"><div><span>Subtotal</span><b>'+SG.num(t.sub)+"</b></div>"+(t.disc?"<div><span>Discount</span><b>-"+SG.num(t.disc)+"</b></div>":"")+"<div><span>Tax ("+(+inv.taxRate||0)+"%)</span><b>"+SG.num(t.tax)+'</b></div><div class="inv-grand"><span>Total Amount (PKR)</span><b>'+SG.num(t.total)+"</b></div></div>"+
 '<div class="inv-tc"><h3>'+ic("doc")+"Terms &amp; Conditions</h3><ol>"+terms+"</ol>"+(inv.notes?"<p class='inv-note'><b>Note:</b> "+esc(inv.notes)+"</p>":"")+"</div>"+
 (qr?'<div class="inv-qr"><div class="inv-qr-img">'+qr+"</div><p><b>Scan to view this invoice online</b><br>Download, print or submit payment proof</p></div>":"")+"</div></section>"+
 '<footer class="inv-foot"><div class="inv-thanks"><i>Thank You!</i><p>We appreciate your business<br>and look forward to working with you again.</p></div><div class="inv-sign"><i>Tech SG</i><b>Team '+esc(co.name)+"</b><small>"+esc(co.tagline)+'</small></div><div class="inv-contact">'+m("phone",co.phone)+m("mail",co.email)+m("globe",co.web)+m("pin",co.address)+"</div></footer></article>"};
SG.fit=function(){D.querySelectorAll(".inv-fit").forEach(function(w){var i=w.firstElementChild;if(!i)return;i.style.transform="";w.style.height="";var s=Math.min(1,w.clientWidth/i.offsetWidth);if(s<1){i.style.transformOrigin="top left";i.style.transform="scale("+s+")";w.style.height=i.offsetHeight*s+"px"}})};
W.addEventListener("resize",SG.fit);W.addEventListener("beforeprint",function(){D.querySelectorAll(".inv-fit").forEach(function(w){w.firstElementChild.style.transform="";w.style.height=""})});W.addEventListener("afterprint",SG.fit);
/* modal */
SG.modal=function(title,body,onSave,label){var m=D.createElement("div");m.className="sgm";m.innerHTML='<div class="sgm-box"><div class="sgm-h"><h3>'+title+'</h3><button type="button" class="sgm-x" aria-label="Close">&times;</button></div><form class="sgm-b">'+body+'<div class="sgm-f"><button type="button" class="btn sgm-c">Cancel</button><button class="btn primary">'+(label||"Save")+"</button></div></form></div>";D.body.appendChild(m);var f=m.querySelector("form"),cl=function(){m.remove()};
 m.querySelector(".sgm-x").onclick=m.querySelector(".sgm-c").onclick=cl;m.onclick=function(e){if(e.target===m)cl()};f.onsubmit=function(e){e.preventDefault();if(onSave(f,cl)!==false)cl()};var i=f.querySelector("input:not([readonly])");if(i)i.focus();return{close:cl,form:f}};
SG.clientModal=function(c,done){var e=!!c;c=c||{};var v=function(k){return esc(c[k]||"")};
 SG.modal(e?"Edit client":"New client",'<div class="form-grid"><div class="form-group"><label>Client ID (auto)</label><input class="input" readonly value="'+esc(e?c.id:SG.nextClientId())+'"></div><div class="form-group"><label>Client / company name *</label><input class="input" name="name" required value="'+v("name")+'"></div><div class="form-group"><label>Contact person</label><input class="input" name="contact" value="'+v("contact")+'"></div><div class="form-group"><label>Email</label><input class="input" type="email" name="email" value="'+v("email")+'"></div><div class="form-group"><label>Phone / WhatsApp</label><input class="input" name="phone" placeholder="+92 3XX XXXXXXX" value="'+v("phone")+'"></div><div class="form-group"><label>NTN / CNIC (for FBR)</label><input class="input" name="ntn" value="'+v("ntn")+'"></div><div class="form-group full"><label>Address</label><input class="input" name="address" value="'+v("address")+'"></div></div>',function(f){var a=SG.clients(),o;if(e){o=a.filter(function(x){return x.id===c.id})[0]}else{o={id:SG.nextClientId()};a.push(o)}
  ["name","contact","email","phone","ntn","address"].forEach(function(k){o[k]=f.elements[k].value.trim()});SG.saveClients(a);SG.toast(e?"Client updated":"Client "+o.id+" created");if(done)done(o)},e?"Save changes":"Create client")};
/* shell for rebuilt pages */
var NAV=[["MAIN",[["index.html","Dashboard","fa-grid-2"],["invoices.html","Invoices","fa-file-invoice"],["quotations.html","Quotations","fa-file-signature"],["clients.html","Clients","fa-users"],["items.html","Items","fa-boxes-stacked"]]],["MANAGEMENT",[["payments.html","Payments","fa-credit-card"],["recurring.html","Recurring","fa-arrows-rotate"],["projects.html","Projects","fa-diagram-project"],["reports.html","Reports","fa-chart-column"]]],["SYSTEM",[["users.html","Staff & Users","fa-user-group"],["audit-log.html","Audit log","fa-clock-rotate-left"],["settings.html","Settings","fa-gear"]]]];
SG.shell=function(o){var s=SG.session(),root=D.getElementById("root");
 var nav=NAV.map(function(g){return'<div class="nav-section-title">'+g[0]+"</div>"+g[1].map(function(l){return'<a href="'+l[0]+'"'+(l[0]===PAGE+".html"?' class="active"':"")+'><i class="fa-solid '+l[2]+'"></i><span>'+l[1]+"</span></a>"}).join("")}).join("");
 root.innerHTML='<aside class="sidebar"><div class="brand"><div class="brand-mark"></div><div class="brand-text"><strong>Tech SG Studio</strong><span>(SMC-Pvt.) Ltd.</span></div></div><nav class="nav">'+nav+'</nav><div class="sidebar-foot"><div class="avatar">'+esc((s.name||"U").slice(0,2).toUpperCase())+'</div><div class="user-mini"><strong>'+esc(s.name)+"</strong><span>"+(s.role==="admin"?"Administrator":"Staff")+"</span></div></div></aside>"+
 '<main class="main"><header class="topbar"><button class="menu" type="button" aria-label="Menu"><i class="fa-solid fa-bars"></i></button><div class="topbar-title">'+esc(o.title)+'</div></header><section class="page"><div class="page-head"><div><div class="eyebrow">'+esc(o.eyebrow||"")+"</div><h1>"+esc(o.title)+"</h1><p>"+esc(o.sub||"")+'</p></div><div class="actions">'+(o.actions||"")+'</div></div><div id="view"></div></section><footer class="page-footer"><div>&copy; '+new Date().getFullYear()+" Tech SG Studio (SMC-Pvt.) Ltd.</div></footer></main>";
 var sb=root.querySelector(".sidebar");root.querySelector(".menu").onclick=function(){sb.classList.toggle("open");D.body.classList.toggle("sidebar-open")};root.querySelector(".main").addEventListener("click",function(){sb.classList.remove("open");D.body.classList.remove("sidebar-open")});return D.getElementById("view")};
/* developer guide panel */
var G={index:["Dashboard","Reads clients + invoices from the store and aggregates in the browser.","Backend: GET /api/dashboard returning totals, 6-month series and recent invoices.","Overdue = status Pending and due date before today (computed, never stored)."],invoices:["Invoices list","Data key: techsg_v2_invoices. Statuses: Draft, Pending, Paid, Cancelled (Overdue is computed).","Backend: GET/PATCH /api/invoices, DELETE only for admin.","Share link = compressed invoice JSON inside the URL hash. With a server, replace with /i/{publicToken}."],"invoice-create":["Create / edit invoice","Invoice ID auto: TSG-INV-YYYY-0001 (max existing + 1). Generate it server-side in production to avoid races.","Totals: subtotal - discount, then tax % on the remainder. Keep this formula identical in the API.","New client from this page also gets an auto Client ID (CL-0001...)."],"invoice-view":["Invoice view","Rendered by SG.invoiceHTML() – A4 layout, prints cleanly (Print / Save as PDF).","QR code encodes the client share link. Email button uses mailto:; for automatic sending add a server endpoint (PHP mail/SMTP) that sends the same link.","FBR panel below uses fbr.js (Simulation by default)."],clients:["Clients","Data key: techsg_v2_clients. Client ID is auto (CL-0001...) and never editable.","Backend: unique index on client_id; block delete when invoices exist.","NTN/CNIC is used in the FBR payload buyerNTNCNIC."],users:["Staff & roles","Roles: admin (everything) and staff (invoices, clients, quotations, payments).","Passwords are SHA-256 hashed in the browser – demo-grade only. Real auth must be server-side (sessions/JWT, bcrypt/argon2).","Admin-only pages are guarded in core.js; repeat the checks in the API."],settings:["Settings","Company profile card (bottom) feeds every invoice: bank details, NTN, STRN, contacts.","FBR card holds PRAL token/URLs; keep the token on a server proxy in production."],default:["Developer notes","Shared code: core.js (data, auth, IDs, invoice design), fbr.js (FBR), advanced.js (Ctrl+K palette, backup).","Theme: techsg-theme.css, layout-fix.css, app.css. Data keys start with techsg_v2_.","Use Ctrl+K > Export backup before clearing browser data."]};
function guide(){if(PUB.indexOf(PAGE)>-1||!SG.session())return;var g=G[PAGE]||G["default"];
 var a=D.createElement("button");a.className="sgg-tab";a.type="button";a.innerHTML="&lt;/&gt; Dev Guide";var b=D.createElement("aside");b.className="sgg";b.innerHTML='<header><b>Developer guidelines</b><button type="button" aria-label="Close">&times;</button></header><div class="sgg-b"><h4>'+esc(g[0])+"</h4><ul>"+g.slice(1).map(function(x){return"<li>"+esc(x)+"</li>"}).join("")+"</ul><h4>Project files</h4><ul><li>core.js · fbr.js · advanced.js</li><li>techsg-theme.css · layout-fix.css · app.css</li><li>Press Ctrl+K for quick actions</li></ul></div>";
 a.onclick=function(){b.classList.toggle("on")};b.querySelector("button").onclick=function(){b.classList.remove("on")};D.body.appendChild(a);D.body.appendChild(b)}
function companyCard(){var host=D.querySelector("main")||D.body,c=SG.company();if(D.getElementById("coCard"))return;
 var F=[["name","Brand name"],["slogan","Slogan"],["tagline","Tagline"],["phone","Phone"],["email","Email"],["web","Website"],["address","Address"],["ntn","NTN"],["strn","STRN"],["bank","Bank name"],["acTitle","Account title"],["acNo","Account number"],["iban","IBAN"],["methods","Payment methods"],["terms","Default payment terms (days)"]];
 var n=D.createElement("section");n.className="fbr-card";n.id="coCard";n.innerHTML='<h3>Company profile &amp; bank details</h3><p class="m">Shown on every invoice and in the client share link.</p><div class="fbr-grid">'+F.map(function(f){return'<div><label>'+f[1]+'</label><input data-k="'+f[0]+'" value="'+esc(c[f[0]])+'"></div>'}).join("")+'</div><div class="fbr-row"><button class="fbr-btn" type="button">Save company profile</button></div>';
 host.appendChild(n);n.querySelector("button").onclick=function(){var o=SG.company();n.querySelectorAll("input").forEach(function(i){o[i.dataset.k]=i.dataset.k==="terms"?(+i.value||14):i.value.trim()});SG.saveCompany(o);
  try{var k="techsg_fbr_cfg_v1",f=JSON.parse(localStorage.getItem(k)||"{}");f.ntn=o.ntn;f.strn=o.strn;f.address=o.address;localStorage.setItem(k,JSON.stringify(f))}catch(e){}SG.toast("Company profile saved")}}
/* enhancements for every page (old + new) */
function enhance(){var s=SG.session();if(!s)return;
 D.querySelectorAll(".brand-mark,.quotation-company-logo").forEach(function(e){e.innerHTML='<img src="assets/logo.png" alt="Tech SG Studio">';e.classList.add("has-logo")});
 D.querySelectorAll(".user-mini strong").forEach(function(e){e.textContent=s.name;var p=e.parentNode.querySelector("span,small");if(p)p.textContent=s.role==="admin"?"Administrator":"Staff"});
 D.querySelectorAll(".user-chip span").forEach(function(e){e.textContent=s.name});D.querySelectorAll(".user-chip .avatar,.sidebar .avatar").forEach(function(e){e.textContent=(s.name||"U").slice(0,2).toUpperCase()});
 if(s.role!=="admin")D.querySelectorAll('a[href="users.html"],a[href="settings.html"],a[href="audit-log.html"],[data-role="admin"]').forEach(function(e){e.style.display="none"});
 D.querySelectorAll('.nav a[href="users.html"] span,.sidebar-nav a[href="users.html"]').forEach(function(e){if(e.tagName==="SPAN")e.textContent="Staff & Users"});
 var tb=D.querySelector(".topbar");if(tb&&!D.getElementById("sgOut")){var o=D.createElement("button");o.id="sgOut";o.type="button";o.className="btn sm sg-out";o.textContent="Logout";o.onclick=SG.logout;tb.appendChild(o)}
 if(!D.querySelector('link[rel="icon"]')){var l=D.createElement("link");l.rel="icon";l.href="assets/favicon.png";D.head.appendChild(l)}
 if(PAGE==="settings"&&s.role==="admin")companyCard();guide();SG.fit()}
if(!SG.stop){if(D.readyState==="complete")setTimeout(enhance,0);else W.addEventListener("load",enhance)}
})();
/* ===== Server connection (added by Maryam Ayub): talks to the PHP API in the api folder ===== */
(function () {
  "use strict";
  var SG = window.SG;
  var SESSION_KEY = "techsg_v2_session";   // the browser copy of the session (name, role, expiry)
  var PUBLIC_PAGES = ["login", "forgot-password", "reset-password", "invoice-share", "pay-proof"];

  SG.csrf = "";                            // security token, filled in from the server

  // Call the PHP API. Examples: SG.api("session.php")   SG.api("login.php", "POST", {username: "x"})
  // Gives back the JSON answer (also for errors). The HTTP status is in answer._status.
  SG.api = function (path, method, body) {
    method = method || "GET";
    var send = function () {
      var headers = {"Content-Type": "application/json"};
      if (SG.csrf) { headers["X-CSRF-Token"] = SG.csrf; }
      return fetch("api/" + path, {
        method: method, headers: headers, credentials: "same-origin",
        body: body ? JSON.stringify(body) : undefined
      }).then(function (r) {
        return r.json().catch(function () { return {ok: false, error: "Server error"}; })
                .then(function (j) { j._status = r.status; return j; });
      });
    };
    // Requests that change data need the security token first
    if (method !== "GET" && !SG.csrf) {
      return SG.api("session.php").then(function (j) { SG.csrf = j.csrf_token || ""; return send(); });
    }
    return send();
  };

  // Sign in through the server. Gives back the user, or throws an Error whose message can be shown.
  SG.apiLogin = function (username, password) {
    return SG.api("session.php").then(function (s) {
      SG.csrf = s.csrf_token || "";
      return SG.api("login.php", "POST", {username: username, password: password});
    }).then(function (j) {
      if (!j.ok) { throw new Error(j.error || "Could not sign in."); }
      SG.csrf = j.csrf_token || "";
      // Keep a browser copy of the session so the pages know the name and role (12 hours)
      localStorage.setItem(SESSION_KEY, JSON.stringify({
        uid: j.user.uid, name: j.user.name, role: j.user.role, username: j.user.username,
        exp: Date.now() + 12 * 36e5
      }));
      return j.user;
    }).catch(function (e) {
      if (e instanceof TypeError) { throw new Error("Cannot reach the server."); }
      throw e;
    });
  };

  // Sign out on the server first, then clear the browser copy and go to the login page
  SG.logout = function () {
    var done = function () {
      try { localStorage.removeItem(SESSION_KEY); } catch (e) {}
      location.href = "login.html";
    };
    SG.api("logout.php", "POST").then(done, done);
  };

  // On every page except the public ones, ask the server if the user is really signed in
  var page = (location.pathname.split("/").pop() || "index.html").replace(/\.html$/, "") || "index";
  if (PUBLIC_PAGES.indexOf(page) < 0) {
    SG.api("session.php").then(function (j) {
      if (j.ok) { SG.csrf = j.csrf_token || ""; return; }
      try { localStorage.removeItem(SESSION_KEY); } catch (e) {}
      location.replace("login.html?next=" + encodeURIComponent(page + ".html" + location.search));
    }).catch(function () { /* server not reachable: do nothing */ });
  }
})();
/* ===== Clients now live in MySQL (added by Maryam Ayub) ===== */
(function () {
  "use strict";
  var SG = window.SG, NS = "techsg_v2_";
  var PUBLIC_PAGES = ["login", "forgot-password", "reset-password", "invoice-share", "pay-proof"];
  var FIELDS = ["name", "contact", "email", "phone", "address", "ntn"];
  var page = (location.pathname.split("/").pop() || "index.html").replace(/\.html$/, "") || "index";

  var cache = [];                  // the clients, as the server last sent them
  var nextNo = 1;                  // the number of the next new client
  var chain = Promise.resolve();   // server writes run one after another

  function pad4(n) { return ("0000" + n).slice(-4); }
  function copy(x) { return JSON.parse(JSON.stringify(x)); }
  function numOf(id) { return parseInt(String(id).replace(/\D/g, ""), 10) || 0; }

  // 1) Load the clients from the server BEFORE the page draws.
  //    This request waits for the answer on purpose (the old pages read their data instantly).
  function loadClients() {
    var x = new XMLHttpRequest();
    x.open("GET", "api/clients.php", false);   // false = wait here until the answer arrives
    var j;
    try { x.send(); j = JSON.parse(x.responseText); } catch (e) { return; }
    if (j && j.ok) { cache = j.clients || []; nextNo = numOf(j.next) || 1; }
  }
  if (!SG.stop && PUBLIC_PAGES.indexOf(page) < 0) { loadClients(); }

  // 2) The pages keep calling the same functions as before
  SG.clients = function () { return copy(cache); };           // a fresh copy each time, like before
  SG.nextClientId = function () { return "CL-" + pad4(nextNo); };

  function fields(c) { var o = {}; FIELDS.forEach(function (k) { o[k] = c[k] || ""; }); return o; }
  function check(j) { if (!j.ok) { throw new Error(j.error || "Server error"); } return j; }

  // Send one change to the server
  function run(op) {
    var id = encodeURIComponent(op.client.id);
    if (op.type === "delete") { return SG.api("clients.php?id=" + id, "DELETE").then(check); }
    if (op.type === "update") { return SG.api("clients.php?id=" + id, "PUT", fields(op.client)).then(check); }
    return SG.api("clients.php", "POST", fields(op.client)).then(check).then(function (j) {
      var made = j.client.id;
      nextNo = Math.max(nextNo, numOf(made) + 1);
      if (made !== op.client.id) {                 // the server chose another number: use the server's
        cache.forEach(function (c) { if (c.id === op.client.id) { c.id = made; } });
        SG.toast("Client saved as " + made);
        setTimeout(function () { location.reload(); }, 1200);
      }
    });
  }

  // 3) The pages hand over the WHOLE list when they save. We find what changed and tell the server.
  SG.saveClients = function (list) {
    var oldById = {}, newIds = {}, ops = [];
    cache.forEach(function (c) { oldById[c.id] = c; });
    list.forEach(function (c) {
      newIds[c.id] = true;
      var o = oldById[c.id];
      if (!o) {
        ops.push({type: "create", client: copy(c)});
        nextNo = Math.max(nextNo, numOf(c.id) + 1);
      } else if (FIELDS.some(function (k) { return (o[k] || "") !== (c[k] || ""); })) {
        ops.push({type: "update", client: copy(c)});
      }
    });
    cache.forEach(function (c) { if (!newIds[c.id]) { ops.push({type: "delete", client: c}); } });

    cache = copy(list);                            // the page shows the change at once
    ops.forEach(function (op) { chain = chain.then(function () { return run(op); }); });
    chain = chain.catch(function (e) {             // something failed: say why, then reload the real data
      SG.toast(e.message || "Could not save");
      setTimeout(function () { location.reload(); }, 1500);
    });
  };

  // 4) One-time cleanup: the old demo invoices point at demo clients that no longer exist
  try {
    if (!localStorage.getItem(NS + "demo_cleared")) {
      localStorage.setItem(NS + "invoices", "[]");
      localStorage.setItem(NS + "demo_cleared", "1");
    }
  } catch (e) {}
})();
