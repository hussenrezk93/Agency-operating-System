/* Agency OS demo — LocalStorage store + workflow engine (frontend-only). */
(function(){
"use strict";
var KEY="agencyos.demo.v1";
var DB=null;
function load(){ try{ var raw=localStorage.getItem(KEY); if(raw){DB=JSON.parse(raw);return;} }catch(e){}
  DB=window.SKY_SEED(); save(); }
function save(){ try{ localStorage.setItem(KEY,JSON.stringify(DB)); }catch(e){} }
function reset(){ localStorage.removeItem(KEY); DB=window.SKY_SEED(); save(); }
load();

var TODAY=DB.today||"2026-07-25";
function pad(n){return (n<10?"0":"")+n}
function now(){ return TODAY+" "+pad(new Date().getHours())+":"+pad(new Date().getMinutes()); }

/* lookups */
function user(id){ return DB.users.find(function(u){return u.id===id})||{id:0,name:"system",role:"system"}; }
function dept(id){ return DB.departments.find(function(d){return d.id===id})||{id:0,name:"—"}; }
function client(id){ return DB.clients.find(function(c){return c.id===id})||{name:"—"}; }
function project(id){ return DB.projects.find(function(p){return p.id===id})||null; }
function task(id){ return DB.tasks.find(function(t){return t.id===id})||null; }
function effectiveTL(deptId){ var t=DB.tempTLs.find(function(x){return x.dept===deptId&&x.active}); if(t) return user(t.user); var d=dept(deptId); return d.tl?user(d.tl):null; }
function deptEmployees(deptId){ return DB.users.filter(function(u){return u.dept===deptId&&u.role==="employee"}); }
function initials(name){ return name.split(" ").map(function(w){return w[0]}).join("").slice(0,2).toUpperCase(); }

/* current user by session role */
var ROLE_USER={admin:1,manager:2,tl:4,employee:9};
function me(){ var s=window.APPG?APPG.session():null; return user(s?ROLE_USER[s.role]:0); }

/* deadline status (separate from workflow status) */
function dl(step,t){
  if(!t) t={};
  if(t.life==="done"||t.life==="cancel"||step.w==="approved") return "closed";
  if(t.life==="hold") return "paused";
  if(!step.due) return "notset";
  if(step.start&&step.start>TODAY) return "notstarted";
  if(step.due<TODAY) return "overdue";
  var d1=new Date(step.due)-new Date(TODAY);
  if(d1<=86400000*1) return "duesoon";
  return "ontime";
}
function fmtDue(step){ return step.due?(step.due===TODAY?"Today 23:59":step.due.slice(5)+" 23:59"):"—"; }

/* audit + notify */
function audit(action,entity,before,after){
  DB.audit.unshift({at:now()+":00",actor:me().id,action:action,entity:entity,before:before||"—",after:after||"—",ip:"10.20.0.1"});
}
function notify(role,type,title,sub,link){
  var list=DB.notifications[role]||(DB.notifications[role]=[]);
  var id=list.reduce(function(m,n){return Math.max(m,n.id)},0)+1;
  list.unshift({id:id,type:type,title:title,sub:sub,at:now(),read:false,email:user(ROLE_USER[role]).ver?"sent":"queued",link:link||""});
}
function unread(role){ return (DB.notifications[role]||[]).filter(function(n){return !n.read}).length; }

/* id generators */
function nextTaskId(){ var m=DB.tasks.reduce(function(mx,t){return Math.max(mx,parseInt(t.id.slice(-5),10))},0); return "TSK-2026-"+String(m+1).padStart(5,"0"); }
function nextProjectId(){ var m=DB.projects.reduce(function(mx,p){return Math.max(mx,parseInt(p.id.slice(-4),10))},0); return "PRJ-2026-"+String(m+1).padStart(4,"0"); }

/* ---------- workflow operations ---------- */
function TL(t){ return t.timeline; }
function cur(t){ return t.steps[t.cur]; }
function pushT(t,type,m){ t.timeline.push({t:type,by:me().id,at:now(),m:m}); }

function assignStep(t,assigneeId,start,due){
  var s=cur(t); s.assignee=assigneeId; s.assignedBy=me().id; s.start=start; s.due=due; s.w="progress"; s.firstSeen=null;
  var self=user(assigneeId).role==="tl";
  pushT(t,"assigned",user(assigneeId).name+" · "+start.slice(5)+" → "+due.slice(5)+(self?" · Self-assigned by TL · Manager will review":""));
  audit("task.assigned",t.id+" · step "+s.seq,"waiting_assignment","in_progress · "+user(assigneeId).name);
  notify("employee","tasks","New task assigned — "+t.title,t.id+" · due "+due,"task-execute.html?id="+t.id);
  save();
}
function reassignStep(t,assigneeId,due){
  var s=cur(t); var old=s.assignee?user(s.assignee).name:"—";
  s.assignee=assigneeId; s.due=due||s.due; s.firstSeen=null; s.w="progress";
  pushT(t,"reassigned",user(assigneeId).name+" replaced "+old);
  audit("task.reassigned",t.id+" · step "+s.seq,old,user(assigneeId).name); save();
}
function firstSee(t){
  /* once-only: never overwrites an existing firstSeen; only the assigned employee triggers it;
     closed/draft tasks are never marked as newly seen */
  if(t.life==="done"||t.life==="cancel"||t.life==="draft") return;
  var s=cur(t); if(s.firstSeen||me().id!==s.assignee) return;
  s.firstSeen=now(); pushT(t,"firstseen","First opened by assignee");
  audit("task.first_view",t.id+" · step "+s.seq,"—",s.firstSeen);
  notify("tl","tasks","👁 "+me().name+" opened his task for the first time",t.id+" · first view recorded "+s.firstSeen.slice(11),"task-details.html?id="+t.id);
  save();
}
function addOutput(t,label,url){ var s=cur(t); s.outputs.push({label:label,url:url}); save(); }
function removeOutput(t,i){ var s=cur(t); if(s.w!=="review"&&s.w!=="approved"){ s.outputs.splice(i,1); save(); return true;} return false; }
function addComment(t,text){ var s=cur(t); s.comments.push({by:me().id,at:now(),text:text}); save(); }
function submitStep(t){
  var s=cur(t); if(!s.outputs.length) return false;
  s.w="review"; s.submittedAt=now();
  pushT(t,"submitted",s.outputs.length+" output link"+(s.outputs.length>1?"s":"")+" attached");
  audit("task.step_submitted",t.id+" · step "+s.seq,"in_progress","under_review · outputs: "+s.outputs.length);
  var selfA=user(s.assignee).role==="tl";
  notify(selfA?"manager":"tl","tasks","New submission — "+t.title,t.id+" · "+user(s.assignee).name,"tl-review.html?id="+t.id);
  save(); return true;
}
function review(t,decision,comment){
  var s=cur(t);
  s.reviews.push({by:me().id,decision:decision,comment:comment||"",at:now()});
  if(decision==="approved"){ s.w="approved"; pushT(t,"approved",dept(s.dept).name+" step approved"+(comment?" · "+comment:""));
    audit("task.step_approved",t.id+" · step "+s.seq,"under_review","approved");
    notify(user(s.assignee).role==="tl"?"tl":"employee","tasks","Step approved — "+t.title,t.id,"task-details.html?id="+t.id);
  } else { s.w="changes"; s.comments.push({by:me().id,at:now(),text:comment});
    pushT(t,"changes",comment);
    audit("task.changes_requested",t.id+" · step "+s.seq,"under_review","changes_requested");
    notify(user(s.assignee).role==="tl"?"tl":"employee","tasks","Changes requested on "+t.id,comment,"task-execute.html?id="+t.id);
  }
  save();
}
function sendNext(t,deptId){
  var s=cur(t);
  pushT(t,"sent",dept(s.dept).name+" → "+dept(deptId).name);
  t.steps.push({dept:deptId,seq:t.steps.length+1,w:"waiting",assignee:null,assignedBy:null,start:null,due:null,firstSeen:null,outputs:[],comments:[],reviews:[]});
  t.cur=t.steps.length-1;
  audit("task.transferred",t.id,dept(s.dept).name,dept(deptId).name);
  notify("tl","tasks","Task received — "+t.title,t.id+" · waiting your assignment","task-assign.html?id="+t.id);
  save();
}
function finish(t){
  t.life="done"; t.closed=TODAY; pushT(t,"finished","Task completed — read-only");
  audit("task.finished",t.id,"active","completed"); save();
}
function hold(t,reason){
  t.life="hold"; t.holds.push({step:cur(t).seq,reason:reason,by:me().id,start:TODAY,end:null,days:0});
  pushT(t,"hold",reason); audit("task.on_hold",t.id,"active","on_hold · reason logged");
  notify("employee","tasks","Task on hold — "+t.title,reason,"task-details.html?id="+t.id); save();
}
function resume(t){
  t.life="active"; var h=t.holds[t.holds.length-1]; var days=2;
  if(h&&!h.end){ h.end=TODAY; h.days=h.days||days; }
  var s=cur(t); if(s.due){ var d=new Date(s.due); d.setDate(d.getDate()+(h?h.days:0)); s.due=d.toISOString().slice(0,10); }
  pushT(t,"resume","Deadline extended by "+(h?h.days:0)+" day(s)");
  audit("task.resumed",t.id,"on_hold","active · deadline extended"); save();
}
function redirect(t,deptId,reason){
  var s=cur(t); s.w="cancel";
  pushT(t,"redirected",dept(s.dept).name+" → "+dept(deptId).name+" · "+reason);
  t.steps.push({dept:deptId,seq:t.steps.length+1,w:"waiting",assignee:null,assignedBy:null,start:null,due:null,firstSeen:null,outputs:[],comments:[],reviews:[]});
  t.cur=t.steps.length-1;
  audit("task.redirected",t.id,"dept: "+dept(s.dept).name,"dept: "+dept(deptId).name+" · reason logged"); save();
}
function skip(t,deptId,reason){
  var s=cur(t); s.w="approved";
  pushT(t,"skipped","Step skipped ("+dept(s.dept).name+") · "+reason);
  audit("task.step_skipped",t.id+" · step "+s.seq,"—",reason);
  sendNext(t,deptId);
}
function cancelTask(t,reason){
  t.life="cancel"; t.closed=TODAY; t.reason=reason; cur(t).w="cancel";
  pushT(t,"cancelled",reason); audit("task.cancelled",t.id,"active","cancelled · reason logged");
  notify("employee","tasks","Task cancelled — "+t.title,reason,"task-details.html?id="+t.id); save();
}
function changeDeadline(t,due){
  var s=cur(t); var old=s.due; s.due=due;
  pushT(t,"deadline","Due date "+(old?old.slice(5):"—")+" → "+due.slice(5));
  audit("task.deadline_changed",t.id+" · step "+s.seq,old||"—",due);
  notify("employee","deadlines","Deadline updated — "+t.title,t.id+" · new due "+due,"task-execute.html?id="+t.id); save();
}

/* ---------- WhatsApp ---------- */
function waValid(u){ return /^https:\/\/chat\.whatsapp\.com\/[A-Za-z0-9]+/.test(u); }
function waSet(p,url,label){
  var v=(p.wa?p.wa.version:0)+1;
  p.wa={url:url,label:label,version:v,updatedAt:now(),updatedBy:me().id};
  p.waHistory.unshift({v:v,action:v===1?"created":"updated",url:url,by:me().id,at:now(),note:v===1?"sent to all current members":"re-sent to all current members"});
  /* regenerate deliveries for members of participating depts */
  p.deliveries=[];
  p.depts.forEach(function(dId){
    deptEmployees(dId).concat(effectiveTL(dId)?[effectiveTL(dId)]:[]).forEach(function(u){
      if(u.status!=="active"&&u.status!=="on_leave") return;
      p.deliveries.push({user:u.id,dept:dId,v:v,channel:"in_app",status:"sent",at:now()});
      p.deliveries.push({user:u.id,dept:dId,v:v,channel:"email",status:u.ver?"sent":"queued",at:u.ver?now():""});
    });
  });
  audit("whatsapp_link."+(v===1?"added":"updated"),"project "+p.id,"version: "+(v-1),"version: "+v+" · resend: "+(p.deliveries.length/2)+" members");
  /* invitation notification to every demo member whose department participates */
  ["employee","tl"].forEach(function(r){
    var u=user(ROLE_USER[r]);
    if(u.dept&&p.depts.indexOf(u.dept)>-1)
      notify(r,"projects","💬 WhatsApp invitation (v"+v+") — "+p.name,"Tap to open the project and join the group","project-details.html?id="+p.id);
  });
  save();
}

/* Automatic Seen: called when the employee opens My Tasks — marks every currently
   assigned, not-yet-seen step as seen (first-seen timestamp written once, audited once). */
function firstSeeAll(){
  var m=me(); if(m.role!=="employee") return 0;
  var n=0;
  DB.tasks.forEach(function(t){
    if(t.life!=="active"&&t.life!=="hold") return;
    var s=cur(t);
    if(s.assignee===m.id&&!s.firstSeen&&(s.w==="progress"||s.w==="changes")){ firstSee(t); n++; }
  });
  return n;
}

window.STORE={
 DB:DB, TODAY:TODAY, save:save, reset:reset, now:now, firstSeeAll:firstSeeAll,
 user:user, dept:dept, client:client, project:project, task:task,
 effectiveTL:effectiveTL, deptEmployees:deptEmployees, initials:initials, me:me, ROLE_USER:ROLE_USER,
 dl:dl, fmtDue:fmtDue, audit:audit, notify:notify, unread:unread,
 nextTaskId:nextTaskId, nextProjectId:nextProjectId, cur:cur,
 assignStep:assignStep, reassignStep:reassignStep, firstSee:firstSee,
 addOutput:addOutput, removeOutput:removeOutput, addComment:addComment,
 submitStep:submitStep, review:review, sendNext:sendNext, finish:finish,
 hold:hold, resume:resume, redirect:redirect, skip:skip, cancelTask:cancelTask, changeDeadline:changeDeadline,
 waValid:waValid, waSet:waSet
};
})();
