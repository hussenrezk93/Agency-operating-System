/* Agency OS demo — session + route guards. Must load before agencyos.js. */
(function(){
"use strict";
var SKEY="agencyos.session.v1";
var params=new URLSearchParams(location.search);
var requestedLang=params.get("lang");
var savedLang=localStorage.getItem("agencyos.lang");
var LANG=(requestedLang==="ar"||requestedLang==="en")?requestedLang:(savedLang==="ar"?"ar":"en");
localStorage.setItem("agencyos.lang",LANG);
var PAGE=location.pathname.split("/").pop()||"index.html";

var PUBLIC=["index.html","login.html","403.html","404.html","500.html","session-expired.html",
 "email-verification-success.html","email-verification-expired.html","email-verification-required.html"];
var ANY=["profile.html","change-password.html","forced-password-change.html","notifications.html","chat.html"];
var ACCESS={
 "admin-dashboard.html":["admin"], "managers.html":["admin"],
 "routing-permissions.html":["admin"], "output-access-permissions.html":["admin"],
 "system-settings.html":["admin"], "audit-log.html":["admin"],
 "manager-dashboard.html":["manager"], "tl-dashboard.html":["tl"], "employee-dashboard.html":["employee"],
 "users.html":["admin","manager"], "user-create.html":["admin","manager"], "user-edit.html":["admin","manager"],
 "departments.html":["admin","manager"], "temporary-tl.html":["manager"],
 "clients.html":["manager","tl"], "client-details.html":["manager","tl"],
 "projects.html":["manager","tl","employee"], "project-create.html":["manager","tl"],
 "project-details.html":["manager","tl","employee"],
 "tasks.html":["manager","tl","employee"], "task-create.html":["manager","tl"],
 "task-details.html":["manager","tl","employee"], "task-history.html":["manager","tl","employee"],
 "task-assign.html":["tl"], "task-execute.html":["employee","tl"],
 "tl-review.html":["tl","manager"],
 "reports.html":["manager","tl","employee"],
 "employee-performance.html":["manager","tl","employee"], "department-performance.html":["manager","tl"]
};
var HOME={admin:"admin-dashboard.html",manager:"manager-dashboard.html",tl:"tl-dashboard.html",employee:"employee-dashboard.html"};

function session(){ try{ return JSON.parse(sessionStorage.getItem(SKEY)||localStorage.getItem(SKEY)||"null"); }catch(e){ return null; } }
function setSession(s){ localStorage.setItem(SKEY,JSON.stringify(s)); }
function clearSession(){ localStorage.removeItem(SKEY); sessionStorage.removeItem(SKEY); }
function go(page,extra){
  var p=new URLSearchParams(extra||"");
  p.set("lang",LANG); var s=session(); if(s) p.set("role",s.role);
  location.href=page+"?"+p.toString();
}

/* role switching via URL param (Demo Role Switcher — Prototype Only) */
var s=session();
var urlRole=params.get("role");
if(s&&urlRole&&["admin","manager","tl","employee"].indexOf(urlRole)>-1&&urlRole!==s.role){
  s.role=urlRole; setSession(s);
}

/* guard */
(function guard(){
  if(PUBLIC.indexOf(PAGE)>-1) return;
  if(!s){ location.replace("login.html?lang="+LANG+"&next="+encodeURIComponent(PAGE+location.search)); return; }
  if(s.mustChange&&PAGE!=="forced-password-change.html"){ location.replace("forced-password-change.html?lang="+LANG+"&role="+s.role); return; }
  if(ANY.indexOf(PAGE)>-1) return;
  var allowed=ACCESS[PAGE];
  if(allowed&&allowed.indexOf(s.role)===-1){ location.replace("403.html?lang="+LANG+"&role="+s.role+"&from="+PAGE); }
})();

window.APPG={
 LANG:LANG, PAGE:PAGE, HOME:HOME, ACCESS:ACCESS, PUBLIC:PUBLIC,
 session:session, setSession:setSession, clearSession:clearSession, go:go,
 setLang:function(lang){
   if(lang!=="ar"&&lang!=="en") return;
   localStorage.setItem("agencyos.lang",lang);
   var q=new URLSearchParams(location.search); q.set("lang",lang); location.search=q.toString();
 },
 login:function(un,pw){
   /* demo credentials */
   var map={admin:"admin",manager:"manager",tl:"tl",employee:"employee",newuser:"employee"};
   if(un==="disabled") return {err:"disabled"};
   if(!map[un]||pw!=="Demo123!") return {err:"invalid"};
   var st={role:map[un],un:un,at:Date.now(),mustChange:un==="newuser"};
   setSession(st); return {ok:true,role:st.role};
 },
 logout:function(){ clearSession(); location.href="login.html?lang="+LANG; },
 expire:function(){ clearSession(); location.href="session-expired.html?lang="+LANG; },
 switchRole:function(r){ var st=session()||{un:r,at:Date.now()}; st.role=r; setSession(st); location.href=HOME[r]+"?role="+r+"&lang="+LANG; }
};
})();
