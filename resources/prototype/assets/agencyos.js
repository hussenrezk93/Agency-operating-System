/* Agency OS demo — UI shell + shared components. Load order: i18n → guards → mock-data → demo-store → agencyos. */
(function(){
"use strict";
var LANG=APPG.LANG;
var D=window.SKY_I18N;
function t(k){ return (D[LANG]&&D[LANG][k])||D.en[k]||k; }
document.documentElement.lang=LANG;
document.documentElement.dir=LANG==="ar"?"rtl":"ltr";

var params=new URLSearchParams(location.search);
function withP(href){
  if(!href||href.charAt(0)==="#"||/^https?:/.test(href)) return href;
  var parts=href.split("?"); var p=new URLSearchParams(parts[1]||"");
  if(!p.get("lang")) p.set("lang",LANG);
  var s=APPG.session(); if(s&&!p.get("role")) p.set("role",s.role);
  return parts[0]+"?"+p.toString();
}

/* icons */
function ic(name,cls){
  var P={home:'<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h5v-6h4v6h5V9.5"/>',
  task:'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
  check:'<path d="m4 12.5 5 5L20 6.5"/>',review:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
  folder:'<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
  users:'<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14.6A4.8 4.8 0 0 1 21 19"/>',
  building:'<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 8h1.5M13.5 8H15M9 12h1.5M13.5 12H15M9 16h1.5M13.5 16H15"/>',
  chart:'<path d="M4 20V6M4 20h16"/><rect x="7.5" y="11" width="3" height="6" rx=".8"/><rect x="12.5" y="8" width="3" height="9" rx=".8"/><rect x="17.5" y="13" width="3" height="4" rx=".8"/>',
  chat:'<path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12Z"/>',
  bell:'<path d="M18 9a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7"/><path d="M10 20a2.2 2.2 0 0 0 4 0"/>',
  shield:'<path d="M12 3 5 6v5c0 4.6 3 8 7 10 4-2 7-5.4 7-10V6Z"/><path d="m9.3 12 2 2 3.6-4"/>',
  gear:'<circle cx="12" cy="12" r="3.2"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2-1.2L14.2 3h-4l-.4 2.5a7 7 0 0 0-2 1.2l-2.3-1-2 3.4 2 1.5a7 7 0 0 0 0 2.4l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2 1.2l.4 2.5h4l.4-2.5a7 7 0 0 0 2-1.2l2.3 1 2-3.4-2-1.5A7 7 0 0 0 19 12Z"/>',
  search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
  route:'<circle cx="6" cy="18" r="2.4"/><circle cx="18" cy="6" r="2.4"/><path d="M8.4 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.6"/>',
  star:'<path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6L12 16.7 6.6 19.6l1.1-6L3.2 9.4l6.1-.8Z"/>',
  clock:'<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/>',
  user:'<circle cx="12" cy="8" r="3.6"/><path d="M5 20a7 7 0 0 1 14 0"/>'};
  return '<svg class="ic '+(cls||"")+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(P[name]||P.task)+"</svg>";
}

/* nav per role (spec §6) */
var NAV={
 admin:[{s:"nav.section.main"},{k:"nav.dashboard",i:"home",h:"admin-dashboard.html",p:"dashboard"},
  {s:"nav.section.system"},
  {k:"nav.managers",i:"users",h:"users.html?mode=managers",p:"users"},
  {k:"nav.routing",i:"route",h:"routing-permissions.html",p:"routing"},
  {k:"nav.output",i:"review",h:"output-access-permissions.html",p:"output"},
  {k:"nav.settings",i:"gear",h:"system-settings.html",p:"settings"},
  {k:"nav.audit",i:"shield",h:"audit-log.html",p:"audit"},
  {k:"nav.profile",i:"user",h:"profile.html",p:"profile"}],
 manager:[{s:"nav.section.main"},{k:"nav.dashboard",i:"home",h:"manager-dashboard.html",p:"dashboard"},
  {k:"nav.notifications",i:"bell",h:"notifications.html",p:"notifications",n:1},
  {s:"nav.section.work"},
  {k:"nav.tasks",i:"task",h:"tasks.html",p:"tasks"},
  {k:"nav.projects",i:"folder",h:"projects.html",p:"projects"},
  {k:"nav.clients",i:"star",h:"clients.html",p:"clients"},
  {k:"nav.reports",i:"chart",h:"reports.html",p:"reports"},
  {s:"nav.section.org"},
  {k:"nav.users",i:"users",h:"users.html",p:"users"},
  {k:"nav.departments",i:"building",h:"departments.html",p:"departments"},
  {k:"nav.temptl",i:"clock",h:"temporary-tl.html",p:"temptl"},
  {k:"nav.chat",i:"chat",h:"chat.html",p:"chat"},
  {k:"nav.profile",i:"user",h:"profile.html",p:"profile"}],
 tl:[{s:"nav.section.main"},{k:"nav.dashboard",i:"home",h:"tl-dashboard.html",p:"dashboard"},
  {k:"nav.notifications",i:"bell",h:"notifications.html",p:"notifications",n:1},
  {s:"nav.section.work"},
  {k:"nav.tasks",i:"task",h:"tasks.html",p:"tasks"},
  {k:"nav.review",i:"review",h:"tl-review.html",p:"review"},
  {k:"nav.mytasks",i:"check",h:"tasks.html?view=my",p:"mytasks"},
  {k:"nav.projects",i:"folder",h:"projects.html",p:"projects"},
  {k:"nav.reports",i:"chart",h:"reports.html",p:"reports"},
  {k:"nav.chat",i:"chat",h:"chat.html",p:"chat"},
  {k:"nav.profile",i:"user",h:"profile.html",p:"profile"}],
 employee:[{s:"nav.section.main"},{k:"nav.dashboard",i:"home",h:"employee-dashboard.html",p:"dashboard"},
  {k:"nav.notifications",i:"bell",h:"notifications.html",p:"notifications",n:1},
  {s:"nav.section.work"},
  {k:"nav.mytasks",i:"task",h:"tasks.html",p:"mytasks"},
  {k:"nav.projects",i:"folder",h:"projects.html",p:"projects"},
  {k:"nav.performance",i:"chart",h:"employee-performance.html",p:"performance"},
  {k:"nav.chat",i:"chat",h:"chat.html",p:"chat"},
  {k:"nav.profile",i:"user",h:"profile.html",p:"profile"}]};

var SCREENS=[["login.html","Login"],["admin-dashboard.html","Admin dashboard"],["manager-dashboard.html","Manager dashboard"],
 ["tl-dashboard.html","TL dashboard"],["employee-dashboard.html","Employee dashboard"],["users.html","Users"],
 ["departments.html","Departments"],["temporary-tl.html","Temporary TLs"],["clients.html","Clients"],["projects.html","Projects"],
 ["project-create.html","New project"],["project-details.html?id=PRJ-2026-0007","Project details"],["tasks.html","Tasks"],
 ["task-create.html","Create task"],["task-details.html?id=TSK-2026-00341","Task details"],["task-assign.html?id=TSK-2026-00340","Assign task"],
 ["task-execute.html?id=TSK-2026-00322","Employee execution"],["tl-review.html?id=TSK-2026-00331","TL review"],
 ["notifications.html","Notifications"],["chat.html","Chat"],["reports.html","Reports"],
 ["routing-permissions.html","Routing permissions"],["output-access-permissions.html","Output access"],
 ["system-settings.html","System settings"],["audit-log.html","Audit Log"],["profile.html","Profile"]];

/* ---------- shell ---------- */
function renderShell(){
  var body=document.body;
  var s=APPG.session();
  var role=(s&&s.role)||body.dataset.role;
  if(!role||body.dataset.shell==="none") return;
  body.dataset.role=role;
  var u=window.STORE?STORE.me():{name:"—"};
  var page=body.dataset.page||"";
  var chip={admin:"role-admin",manager:"role-manager",tl:"role-tl",employee:"role-employee"}[role];
  var un=window.STORE?STORE.unread(role):0;

  var navHtml="";
  NAV[role].forEach(function(n){
    if(n.s){ navHtml+='<div class="nav-label">'+t(n.s)+"</div>"; return; }
    var active=(n.p===page)?" active":"";
    var cnt=n.n&&un?'<span class="count">'+un+"</span>":"";
    navHtml+='<a href="'+withP(n.h)+'" class="'+active.trim()+'">'+ic(n.i)+"<span>"+t(n.k)+"</span>"+cnt+"</a>";
  });

  var shell=document.createElement("div");
  shell.className="shell";
  shell.innerHTML=
   '<aside class="sidebar" id="sidebar">'+
   '<div class="brand"><div class="brand-mark">S</div><div><div class="brand-name">Agency<b>OS</b></div>'+
   '<div class="small muted">'+t("app.tag")+"</div></div></div>"+
   '<span class="role-chip '+chip+'">'+t("role."+role)+"</span>"+
   '<nav class="nav">'+navHtml+"</nav>"+
   '<div class="sidebar-foot">v1.1 · '+t("footer.tz")+' · <span class="mono">'+t("common.demo")+"</span></div></aside>"+
   '<div class="main"><header class="topbar">'+
   '<button class="burger" id="burger" data-live aria-label="Menu">☰</button>'+
   '<div class="searchbox">'+ic("search")+'<input id="globalSearch" type="search" placeholder="'+t("top.search")+'"></div>'+
   '<div class="topbar-actions">'+
   '<button class="language-switch" id="langBtn" data-live data-active="'+LANG+'" role="switch" aria-checked="'+(LANG==="ar"?"true":"false")+'" aria-label="'+(LANG==="ar"?"تغيير اللغة إلى الإنجليزية":"Switch language to Arabic")+'"><span class="lang-choice lang-en">EN</span><span class="lang-choice lang-ar">العربية</span></button>'+
   '<button class="icon-btn" id="bellBtn" data-live aria-label="'+t("top.notifications")+'">'+ic("bell")+(un?'<span class="dot"></span>':"")+"</button>"+
   '<div class="userbox" id="userBtn" data-live><div class="avatar">'+(window.STORE?STORE.initials(u.name):"·")+'</div>'+
   '<div><div class="uname">'+u.name+'</div><div class="urole">'+t("role."+role)+"</div></div></div>"+
   "</div></header></div>";
  body.insertBefore(shell,body.firstChild);
  var mainCol=shell.querySelector(".main");
  var main=document.querySelector("main.page"); if(main) mainCol.appendChild(main);

  var items=(window.STORE?(STORE.DB.notifications[role]||[]):[]).slice(0,3).map(function(n){
    return '<a class="drop-item" style="display:flex;color:inherit" href="'+withP(n.link||"notifications.html")+'">'+
    '<span class="avatar sm'+(n.read?" grey":"")+'">'+(n.type==="deadlines"?"⏰":n.type==="chat"?"💬":"🔔")+"</span>"+
    "<div><b>"+n.title+'</b><div class="muted small">'+n.sub+"</div></div></a>";
  }).join("")||'<div class="drop-item muted">'+t("common.empty")+"</div>";
  mainCol.insertAdjacentHTML("beforeend",
   '<div class="drop hide" id="bellDrop" data-live><div class="drop-head"><span>'+t("top.notifications")+
   '</span><a href="#" id="markAllTop" class="small">'+t("common.markread")+"</a></div>"+items+
   '<div class="drop-foot"><a href="'+withP("notifications.html")+'">'+t("top.viewall")+"</a></div></div>"+
   '<div class="drop udrop hide" id="userDrop" data-live>'+
   '<div class="udrop-head"><span class="avatar">'+(window.STORE?STORE.initials(u.name):"·")+'</span>'+
   "<div><b>"+u.name+'</b><span class="r">'+t("role."+role)+"</span></div></div>"+
   '<a class="udrop-item" href="'+withP("profile.html")+'"><span class="ib">'+ic("user")+"</span><span>"+t("nav.profile")+
   "<small>"+(window.STORE&&STORE.me().ver?"✓ "+t("profile.verified"):"✉ "+t("profile.unverified"))+"</small></span></a>"+
   '<a class="udrop-item" href="'+withP("change-password.html")+'"><span class="ib">'+ic("shield")+"</span><span>"+t("pw.change")+"</span></a>"+
   '<a class="udrop-item out" href="#" id="logoutBtn"><span class="ib">'+ic("clock")+"</span><span>"+t("proto.logout")+"</span></a></div>");

  document.getElementById("burger").addEventListener("click",function(){
    body.classList.toggle("nav-open");
    if(body.classList.contains("nav-open")&&!document.querySelector(".scrim")){
      var sc=document.createElement("div"); sc.className="scrim";
      sc.addEventListener("click",function(){ body.classList.remove("nav-open"); sc.remove(); });
      body.appendChild(sc);
    } else { var x=document.querySelector(".scrim"); if(x) x.remove(); }
  });
  var bd=document.getElementById("bellDrop"), ud=document.getElementById("userDrop");
  document.getElementById("bellBtn").addEventListener("click",function(e){e.stopPropagation();ud.classList.add("hide");bd.classList.toggle("hide");});
  document.getElementById("userBtn").addEventListener("click",function(e){e.stopPropagation();bd.classList.add("hide");ud.classList.toggle("hide");});
  document.addEventListener("click",function(){bd.classList.add("hide");ud.classList.add("hide");});
  document.getElementById("logoutBtn").addEventListener("click",function(e){e.preventDefault();APPG.logout();});
  document.getElementById("markAllTop").addEventListener("click",function(e){e.preventDefault();
    (STORE.DB.notifications[role]||[]).forEach(function(n){n.read=true;}); STORE.save(); toast(t("toast.read"),"success");
    bd.querySelectorAll(".avatar").forEach(function(a){a.classList.add("grey");});
    var dot=document.querySelector("#bellBtn .dot"); if(dot) dot.remove();
  });
  var gs=document.getElementById("globalSearch");
  if(gs) gs.addEventListener("keydown",function(e){ if(e.key==="Enter"&&gs.value.trim()) APPG.go("tasks.html","q="+encodeURIComponent(gs.value.trim())); });
  document.getElementById("langBtn").addEventListener("click",function(){ APPG.setLang(LANG==="ar"?"en":"ar"); });
}

/* ---------- toast / modal / confirm ---------- */
function toast(msg,type){
  var w=document.getElementById("toasts");
  if(!w){ w=document.createElement("div"); w.id="toasts"; w.className="toasts"; document.body.appendChild(w); }
  var el=document.createElement("div"); el.className="toast toast-"+(type||"info"); el.textContent=msg;
  w.appendChild(el);
  setTimeout(function(){ el.classList.add("out"); setTimeout(function(){el.remove();},250); },2600);
}
function modal(o){
  var back=document.createElement("div"); back.className="mback"; back.setAttribute("data-live","");
  back.innerHTML='<div class="mbox '+(o.size||"")+'" role="dialog" aria-modal="true"><div class="mhead"><div><h2>'+o.title+
   "</h2>"+(o.sub?'<div class="msub">'+o.sub+"</div>":"")+'</div><button class="mclose" aria-label="'+t("common.close")+'">✕</button></div><div class="mbody">'+o.body+
   '</div><div class="mfoot"></div></div>';
  var foot=back.querySelector(".mfoot");
  (o.actions||[{label:t("common.close"),cls:"btn-outline"}]).forEach(function(a){
    var b=document.createElement("button"); b.className="btn "+(a.cls||"btn-outline"); b.innerHTML=a.label;
    b.addEventListener("click",function(){ if(a.onclick){ if(a.onclick(back)===false) return; } close(); });
    foot.appendChild(b);
  });
  function close(){ back.remove(); document.removeEventListener("keydown",escK); }
  function escK(e){ if(e.key==="Escape") close(); }
  back.querySelector(".mclose").addEventListener("click",close);
  back.addEventListener("click",function(e){ if(e.target===back) close(); });
  document.addEventListener("keydown",escK);
  document.body.appendChild(back);
  var f=back.querySelector(".mbody input,.mbody select,.mbody textarea")||back.querySelector(".mfoot button"); if(f) f.focus();
  return {close:close,el:back};
}
function confirmBox(text,onOk,danger){
  modal({title:t("common.confirm"),body:'<p style="margin:0">'+text+"</p>",
   actions:[{label:t("common.cancel"),cls:"btn-outline"},
    {label:t("common.confirm"),cls:danger?"btn-danger-outline":"btn-primary",onclick:function(){onOk();}}]});
}

/* ---------- helpers ---------- */
var WCLS={waiting:"b-waiting",progress:"b-progress",review:"b-review",changes:"b-changes",approved:"b-approved",
 hold:"b-hold",done:"b-done",cancel:"b-cancel",draft:"b-neutral"};
function badge(w){ return '<span class="badge '+(WCLS[w]||"b-neutral")+'"><span class="bdot"></span>'+t("st."+w)+"</span>"; }
var DCLS={notstarted:"b-waiting",ontime:"b-neutral",duesoon:"b-changes",overdue:"b-overdue",paused:"b-hold",closed:"b-approved",notset:"b-waiting"};
function dlBadge(k,extra){ return '<span class="badge '+(DCLS[k]||"b-neutral")+'"><span class="bdot"></span>'+t("dl."+k)+(extra?" · "+extra:"")+"</span>"; }
function prio(p){ return '<span class="badge p-'+p+'">'+t("p."+p)+"</span>"; }
function avatar(u,sm){ return '<span class="avatar'+(sm?" sm":"")+'">'+STORE.initials(u.name)+"</span>"; }
function userInline(id){ var u=STORE.user(id); return '<span class="inline">'+avatar(u,1)+u.name+"</span>"; }
function escH(s){ return String(s==null?"":s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c];}); }
function exportTable(tbl,name){
  var rows=[].map.call(tbl.querySelectorAll("tr"),function(tr){
    return [].map.call(tr.querySelectorAll("th,td"),function(td){ return '"'+td.textContent.trim().replace(/"/g,'""')+'"'; }).join(",");
  }).join("\n");
  var a=document.createElement("a");
  a.href="data:text/csv;charset=utf-8,"+encodeURIComponent(rows);
  a.download=(name||"agencyos-export")+".csv"; a.click(); toast(t("toast.saved"),"success");
}

/* ---------- demo switcher ---------- */
function renderProto(){
  var roles=["admin","manager","tl","employee"].map(function(r){
    return '<a href="#" data-switch="'+r+'">'+t("role."+r)+"</a>";
  }).join("");
  var links=SCREENS.map(function(s){ return '<a href="'+withP(s[0])+'">'+s[1]+"</a>"; }).join("");
  var d=document.createElement("details"); d.className="proto"; d.setAttribute("data-live","");
  d.innerHTML="<summary>◧ "+t("proto.title")+"</summary><div class='proto-body' style='max-height:340px;overflow:auto'>"+
   "<div class='plabel'>"+t("proto.roles")+"</div>"+roles+
   "<div class='plabel'>·</div>"+
   "<a href='#' id='protoReset'>↺ "+t("proto.reset")+"</a>"+
   "<a href='#' id='protoExpire'>⏱ "+t("proto.expire")+"</a>"+
   "<a href='#' id='protoLogout'>⎋ "+t("proto.logout")+"</a>"+
   "<div class='plabel'>"+t("proto.screens")+"</div>"+links+"</div>";
  document.body.appendChild(d);
  d.addEventListener("click",function(e){
    var sw=e.target.closest("[data-switch]");
    if(sw){ e.preventDefault(); APPG.switchRole(sw.dataset.switch); return; }
    if(e.target.id==="protoReset"){ e.preventDefault(); STORE.reset(); toast(t("toast.reset"),"success"); setTimeout(function(){location.reload();},400); }
    if(e.target.id==="protoExpire"){ e.preventDefault(); APPG.expire(); }
    if(e.target.id==="protoLogout"){ e.preventDefault(); APPG.logout(); }
  });
}

/* ---------- global behaviors ---------- */
function globals(){
  document.querySelectorAll("a[href]").forEach(function(a){
    var h=a.getAttribute("href");
    if(h&&h.indexOf(".html")>-1&&!/^https?:/.test(h)) a.setAttribute("href",withP(h));
  });
  document.querySelectorAll("[data-i18n]").forEach(function(el){ el.textContent=t(el.dataset.i18n); });
  document.querySelectorAll("[data-i18n-ph]").forEach(function(el){ el.placeholder=t(el.dataset.i18nPh); });
  document.addEventListener("click",function(e){
    var a=e.target.closest('a[href="#"]');
    if(a&&!a.closest("[data-live]")){ e.preventDefault(); toast(t("toast.sim"),"info"); return; }
    var b=e.target.closest("button");
    if(b&&!b.closest("[data-live]")&&!b.closest("form")){
      var seg=b.closest(".seg");
      if(seg){ seg.querySelectorAll(".on").forEach(function(x){x.classList.remove("on");}); b.classList.add("on"); }
      toast(t("toast.sim"),"info");
    }
  });
}

document.addEventListener("DOMContentLoaded",function(){
  renderShell(); globals(); renderProto();
  if(typeof window.PAGE_INIT==="function") window.PAGE_INIT();
});

window.APP={t:t,lang:LANG,withP:withP,ic:ic,toast:toast,modal:modal,confirm:confirmBox,
 badge:badge,dlBadge:dlBadge,prio:prio,avatar:avatar,userInline:userInline,esc:escH,exportTable:exportTable,params:params};
})();
