/* Agency OS Arabic runtime localization.
   Keeps LocalStorage canonical and localizes every visible mock-data value only in Arabic mode. */
(function(){
"use strict";
var LANG=(window.APPG&&APPG.LANG)||((new URLSearchParams(location.search)).get("lang")==="ar"?"ar":"en");
if(LANG!=="ar") return;

var EXACT={
  "Agency OS — Sign in":"Agency OS — تسجيل الدخول","Agency OS — UI/UX Prototype":"Agency OS — النموذج التجريبي للواجهة",
  "Agency OS — Tasks":"Agency OS — المهام","Agency OS — Task Details":"Agency OS — تفاصيل المهمة","Agency OS — Task History":"Agency OS — سجل المهمة",
  "Agency OS — Assign Task":"Agency OS — تعيين المهمة","Agency OS — Execute Task":"Agency OS — تنفيذ المهمة","Agency OS — Review Queue":"Agency OS — قائمة المراجعة",
  "Agency OS — Projects":"Agency OS — المشروعات","Agency OS — Project":"Agency OS — المشروع","Agency OS — New Project":"Agency OS — مشروع جديد",
  "Agency OS — Clients":"Agency OS — العملاء","Agency OS — Client":"Agency OS — العميل","Agency OS — Departments":"Agency OS — الأقسام",
  "Agency OS — Users":"Agency OS — المستخدمون","Agency OS — Temporary TLs":"Agency OS — قادة الفريق المؤقتون","Agency OS — Notifications":"Agency OS — الإشعارات",
  "Agency OS — Chat":"Agency OS — المحادثات","Agency OS — Reports":"Agency OS — التقارير","Agency OS — My Performance":"Agency OS — أدائي",
  "Agency OS — Audit Log":"Agency OS — سجل التدقيق","Agency OS — System Settings":"Agency OS — إعدادات النظام","Agency OS — Profile":"Agency OS — الملف الشخصي",
  "Agency OS — Routing Permissions":"Agency OS — صلاحيات التوجيه","Agency OS — Output Access":"Agency OS — صلاحيات المخرجات",
  "Agency OS — Password":"Agency OS — كلمة المرور","Agency OS — Verified":"Agency OS — تم التحقق","Agency OS — Verify email":"Agency OS — التحقق من البريد",
  "Agency OS — Link expired":"Agency OS — انتهت صلاحية الرابط","Agency OS — Session expired":"Agency OS — انتهت الجلسة",
  "Good morning, Omar 👋":"صباح الخير يا عمر 👋","Hi Mohamed 👋":"أهلًا يا محمد 👋",
  "Saturday 25 Jul 2026 · Africa/Cairo · Operational overview across all departments":"السبت 25 يوليو 2026 · أفريقيا/القاهرة · نظرة تشغيلية على جميع الأقسام",
  "Marketing · Team Leader: Sara Mostafa · Saturday 25 Jul 2026":"التسويق · قائدة الفريق: سارة مصطفى · السبت 25 يوليو 2026",
  "Sara Mostafa · 6 team members · Saturday 25 Jul 2026":"سارة مصطفى · 6 أعضاء بالفريق · السبت 25 يوليو 2026",
  "Employee's own view · July 2026":"عرض الموظف الشخصي · يوليو 2026",
  "Interactive frontend demo for management.":"نموذج واجهة تفاعلي للعرض على الإدارة.",
  "No backend yet — everything runs in the browser with realistic demo data saved in LocalStorage.":"لا يوجد Backend بعد — كل شيء يعمل داخل المتصفح ببيانات تجريبية واقعية محفوظة في LocalStorage.",
  "Workflow & Task Management — Interactive Frontend Demo (BRD v1.1 · ERD v1.2)":"إدارة سير العمل والمهام — نموذج واجهة تفاعلي (BRD v1.1 · ERD v1.2)",
  "Username + password, no self-registration, AR/EN toggle.":"اسم مستخدم وكلمة مرور، بدون تسجيل ذاتي، مع تبديل عربي/إنجليزي.",
  "Full operational control: KPIs, needs-attention queue, departments, temp TLs.":"تحكم تشغيلي كامل: مؤشرات الأداء، العناصر التي تحتاج متابعة، الأقسام وقادة الفريق المؤقتون.",
  "Waiting-assignment queue, review queue, My Tasks, first-seen tracking.":"قائمة انتظار التعيين، قائمة المراجعة، مهامي وتتبع أول مشاهدة.",
  "Assigned work, current-month score, due-soon reminders.":"العمل المعيّن، تقييم الشهر الحالي وتنبيهات قرب الموعد.",
  "Configuration only: routing rules, output access, managers, audit — no task content.":"إعدادات فقط: قواعد التوجيه، صلاحيات المخرجات، المديرون والتدقيق — بدون محتوى المهام.",
  "The signature route rail: department hand-offs, timeline, outputs, review.":"المسار الرئيسي: انتقالات الأقسام، الخط الزمني، المخرجات والمراجعة.",
  "Needs attention":"يحتاج متابعة","Urgent & high priority":"عاجل وأولوية مرتفعة","Department on-time rate — July":"نسبة الالتزام بالمواعيد حسب القسم — يوليو",
  "Temporary Team Leaders":"قادة الفريق المؤقتون","Active projects":"المشروعات النشطة","Active tasks":"المهام النشطة","Overdue steps":"المراحل المتأخرة",
  "Waiting assignment":"بانتظار التعيين","Submissions awaiting review":"تسليمات بانتظار المراجعة","Awaiting my review":"بانتظار مراجعتي",
  "Recent notifications":"أحدث الإشعارات","Next deadlines":"المواعيد القادمة","My tasks":"مهامي","My score — July":"تقييمي — يوليو",
  "My Performance":"أدائي","Reports — July 2026":"التقارير — يوليو 2026","Company on-time rate":"نسبة التزام الشركة بالمواعيد",
  "Department tasks by employee":"مهام القسم حسب الموظف","Department report":"تقرير القسم","Team Leader report":"تقرير قائد الفريق","Employee report":"تقرير الموظف",
  "Overview":"نظرة عامة","Basic information":"المعلومات الأساسية","Reference links":"روابط مرجعية","Participating departments":"الأقسام المشاركة",
  "Project (optional)":"المشروع (اختياري)","First department":"القسم الأول","Department route":"مسار الأقسام","Workflow timeline":"الخط الزمني لسير العمل",
  "Output links":"روابط المخرجات","Output Access Permissions":"صلاحيات الوصول للمخرجات","Routing Permissions":"صلاحيات التوجيه",
  "Department routing — who can send to whom":"توجيه الأقسام — من يمكنه الإرسال إلى من","Which department may view another department's outputs.":"أي قسم يمكنه مشاهدة مخرجات قسم آخر.",
  "Click any cell to toggle. Every change is audited.":"اضغط على أي خلية للتبديل. يتم تسجيل كل تغيير في سجل التدقيق.",
  "Latest audit events":"أحدث أحداث التدقيق","System administration":"إدارة النظام","Workflow defaults":"إعدادات سير العمل الافتراضية",
  "Email channel health":"حالة قناة البريد","Email bounces (7d)":"رسائل البريد المرتدة (7 أيام)","Chat digest interval (hours)":"فاصل ملخص المحادثات (بالساعات)",
  "Daily deadline time":"وقت الموعد النهائي اليومي","Sender domain":"نطاق المرسل","Timezone":"المنطقة الزمنية",
  "Simple client registry — no client portal in v1.":"سجل عملاء بسيط — لا توجد بوابة عملاء في الإصدار الأول.",
  "Every project belongs to exactly one client.":"كل مشروع مرتبط بعميل واحد فقط.","One client per project · departments must be participants before receiving project tasks.":"عميل واحد لكل مشروع · يجب أن تكون الأقسام مشاركة قبل استلام مهام المشروع.",
  "Members of these departments receive project notifications and the WhatsApp invitation.":"أعضاء هذه الأقسام يستلمون إشعارات المشروع ودعوة واتساب.",
  "The client is saved to the registry the moment the project is created.":"يتم حفظ العميل في السجل فور إنشاء المشروع.",
  "Google Drive, docs, briefs — Agency OS stores links only, never files.":"Google Drive والمستندات والملخصات — يحفظ Agency OS الروابط فقط ولا يحفظ الملفات.",
  "Self-assigned steps are reviewed by the Manager — you cannot approve your own work.":"المراحل التي يعيّنها قائد الفريق لنفسه يراجعها المدير — لا يمكنك اعتماد عملك بنفسك.",
  "One active delegation per department · main TL becomes view-only · auto-restore at end date.":"تكليف مؤقت نشط واحد لكل قسم · يصبح قائد الفريق الأساسي للعرض فقط · استعادة تلقائية عند تاريخ الانتهاء.",
  "Primary TL is mandatory · routing follows the Admin matrix.":"قائد الفريق الأساسي إلزامي · التوجيه يتبع مصفوفة الأدمن.",
  "Name and description are stored in English (BRD language rule).":"تظهر الأسماء والأوصاف التجريبية بالعربية عند اختيار اللغة العربية وبالإنجليزية عند اختيار الإنجليزية.",
  "No other delegations scheduled.":"لا توجد تكليفات أخرى مجدولة.","No self-registration.":"لا يوجد تسجيل ذاتي.",
  "Internal system.":"نظام داخلي.","Demo accounts":"حسابات تجريبية","Sign in to start:":"سجّل الدخول للبدء:",
  "Demo Role Switcher":"مبدّل الأدوار التجريبي","Demo Data":"بيانات تجريبية","Open Audit Log →":"فتح سجل التدقيق ←",
  "Open review queue →":"فتح قائمة المراجعة ←","Full monthly report →":"التقرير الشهري الكامل ←","Full history →":"السجل الكامل ←",
  "All tasks →":"كل المهام ←","All →":"الكل ←","Open →":"فتح ←",
  "Manager accounts":"حسابات المديرين","New delegation":"تكليف جديد","New department":"قسم جديد","New user":"مستخدم جديد",
  "New client":"عميل جديد","Existing client":"عميل موجود","Create one right here":"أنشئ عميلًا من هنا","Pick from the registry":"اختر من السجل",
  "New project":"مشروع جديد","New task":"مهمة جديدة","+ New project":"+ مشروع جديد","+ New task":"+ مهمة جديدة",
  "Invite deliveries":"سجل إرسال الدعوات","WhatsApp group link":"رابط مجموعة واتساب","Members are removed from the group manually.":"تتم إزالة الأعضاء من المجموعة يدويًا.",
  "Text & links only · no editing · delete removes for everyone · Admin never sees chat content.":"نصوص وروابط فقط · لا يوجد تعديل · الحذف يزيل الرسالة للجميع · الأدمن لا يرى محتوى المحادثات.",
  "Chat content is not accessible to the Admin role — only chat":"محتوى المحادثات غير متاح لدور الأدمن — فقط أحداث المحادثة",
  "the Audit Log records events only. It never grants access to task details, output content, or chat messages.":"يسجل سجل التدقيق الأحداث فقط ولا يمنح حق الوصول لتفاصيل المهام أو محتوى المخرجات أو رسائل المحادثات.",
  "Append-only · Admin access only · chat message":"إضافة فقط · وصول الأدمن فقط · محتوى رسالة المحادثة",
  "is never stored here.":"لا يتم تخزينه هنا مطلقًا.",
  "Overdue right now":"متأخر حاليًا","Steps due in July":"مراحل مستحقة في يوليو","Due steps":"المراحل المستحقة","Tasks completed":"المهام المكتملة",
  "Personal score":"التقييم الشخصي","Team deadline-overrun rate":"نسبة تجاوز مواعيد الفريق","Personal steps reviewed by Manager":"مراحل شخصية راجعها المدير",
  "Manager reviews these":"يراجع المدير هذه المراحل","no self-assigned steps due":"لا توجد مراحل ذاتية مستحقة","best month this year":"أفضل شهر هذا العام",
  "How your score works":"كيف يتم حساب تقييمك","Snapshot rule:":"قاعدة تثبيت النتائج:","Score":"التقييم","On-time compliance · July 2026 — Manager sees all departments; a TL sees only their own.":"الالتزام بالمواعيد · يوليو 2026 — يرى المدير جميع الأقسام ويرى قائد الفريق قسمه فقط.",
  "Highlighted row = the individual report shown alongside. Employees open only their own report — never a colleague's.":"الصف المميز هو التقرير الفردي المعروض. الموظف يفتح تقريره فقط ولا يرى تقرير زميله.",
  "Configuration and governance only — Admin has no access to task content or chat messages.":"الإعدادات والحوكمة فقط — لا يملك الأدمن صلاحية الوصول إلى محتوى المهام أو رسائل المحادثات.",
  "Admin boundary (BRD §5, §19):":"حدود صلاحيات الأدمن (BRD §5، §19):",
  "SPF / DKIM / DMARC":"SPF / DKIM / DMARC","Email delivery":"تسليم البريد","Queue (24h)":"قائمة الانتظار (24 ساعة)",
  "Idle soon":"خمول قريب","Unverified users":"مستخدمون غير موثّقين","Email bounces (7d)":"رسائل مرتدة (7 أيام)",

  "Khaled Samir":"خالد سمير","Omar El-Sayed":"عمر السيد","Nour Adel":"نور عادل","Sara Mostafa":"سارة مصطفى",
  "Ahmed Hassan":"أحمد حسن","Laila Farouk":"ليلى فاروق","Youssef Nabil":"يوسف نبيل","Mona Khalil":"منى خليل",
  "Hany Tarek":"هاني طارق","Mohamed Ali":"محمد علي","Dina Saeed":"دينا سعيد","Omar Farid":"عمر فريد",
  "Salma Nagy":"سلمى ناجي","Rania Fouad":"رانيا فؤاد","Aya Ibrahim":"آية إبراهيم","Karim Fathy":"كريم فتحي","Tarek Hussein":"طارق حسين",
  "Nile Foods":"نايل فودز","Cairo Motors":"كايرو موتورز","Lotus Cosmetics":"لوتس لمستحضرات التجميل","Delta Properties":"دلتا العقارية","Alexandria Medical Center":"مركز الإسكندرية الطبي",
  "Marketing":"التسويق","Content":"المحتوى","Photography":"التصوير","Design":"التصميم","Editing":"المونتاج","Moderation":"إدارة الصفحات","Print Production":"الإنتاج الطباعي",
  "Ramadan Campaign 2026":"حملة رمضان 2026","Falcon X Launch":"إطلاق فالكون X","Lotus Summer Collection":"مجموعة لوتس الصيفية","Summer Menu Refresh":"تحديث قائمة الصيف","Clinic Rebrand":"إعادة هوية العيادة",
  "Ramadan hero video — 30s cut":"فيديو رمضان الرئيسي — نسخة 30 ثانية","Ramadan social calendar — week 2":"تقويم رمضان للسوشيال — الأسبوع الثاني",
  "Newsletter draft — Lotus loyalty":"مسودة نشرة لوتس للولاء","Influencer brief — Lotus summer collection":"ملخص المؤثرين — مجموعة لوتس الصيفية",
  "Landing page copy — Falcon X":"محتوى صفحة الهبوط — فالكون X","Media plan — Falcon X launch":"الخطة الإعلامية — إطلاق فالكون X",
  "Competitor pricing snapshot — Q3":"ملخص أسعار المنافسين — الربع الثالث","In-store photography — dessert line":"تصوير داخل المتجر — خط الحلويات",
  "Storefront signage mockups":"نماذج لافتات واجهة المتجر","UGC hashtag tracker — July":"متابعة هاشتاج محتوى المستخدمين — يوليو",
  "Archive tagging — Q2 campaigns":"تصنيف أرشيف حملات الربع الثاني","Clinic rebrand — logo refinements":"إعادة هوية العيادة — تحسينات الشعار",
  "Pack shots — juice range":"صور منتجات — مجموعة العصائر","Billboard adaptation — Delta towers":"تكييف تصميم اللوحات — أبراج دلتا",
  "Product photoshoot — dessert line":"جلسة تصوير المنتجات — خط الحلويات","Landing banners brief":"ملخص بانرات صفحة الهبوط","Q3 media budget draft":"مسودة ميزانية الإعلام للربع الثالث",
  "Agency shortlist — outdoor ads":"القائمة المختصرة للوكالات — إعلانات خارجية","Store visit recap":"ملخص زيارة المتجر","Audience segments — Falcon X":"شرائح الجمهور — فالكون X",
  "Ramadan social calendar — week 1":"تقويم رمضان للسوشيال — الأسبوع الأول","Influencer brief — Lotus summer":"ملخص المؤثرين — صيف لوتس",
  "New submission — Ramadan social calendar (week 2)":"تسليم جديد — تقويم رمضان للسوشيال (الأسبوع الثاني)",
  "Due today 23:59 — Ramadan hero video (Editing step)":"مستحق اليوم 23:59 — فيديو رمضان الرئيسي (مرحلة المونتاج)",
  "Task received from Content — Competitor pricing snapshot Q3":"تم استلام مهمة من المحتوى — ملخص أسعار المنافسين للربع الثالث",
  "WhatsApp invite updated — Ramadan Campaign 2026 (v2)":"تم تحديث دعوة واتساب — حملة رمضان 2026 (الإصدار 2)",
  "Temporary TL activated — Karim Fathy leads Photography":"تم تفعيل قائد فريق مؤقت — كريم فتحي يقود قسم التصوير",
  "Changes requested on TSK-2026-00322":"تم طلب تعديلات على TSK-2026-00322","Deadline in 24h — TSK-2026-00331":"الموعد النهائي خلال 24 ساعة — TSK-2026-00331",
  "WhatsApp invite v2 — Ramadan Campaign 2026":"دعوة واتساب الإصدار 2 — حملة رمضان 2026","Self-assigned TL step awaits your review":"مرحلة نفذها قائد الفريق بنفسه بانتظار مراجعتك",
  "Reassignment needed — Photography":"مطلوب إعادة تعيين — التصوير","Overdue 2 days — Clinic rebrand logo refinements":"متأخر يومين — تحسينات شعار إعادة هوية العيادة",
  "Email delivery failed — Tarek Hussein":"فشل إرسال البريد — طارق حسين","Email hard bounce flagged — user #16":"تم تسجيل ارتداد دائم للبريد — المستخدم #16",
  "Routing rule updated — Editing → Moderation allowed":"تم تحديث قاعدة التوجيه — السماح من المونتاج إلى إدارة الصفحات",
  "👁 Mohamed Ali opened his task for the first time":"👁 فتح محمد علي مهمته للمرة الأولى",
  "Chat digest — 6 unread messages in 2 conversations":"ملخص المحادثات — 6 رسائل غير مقروءة في محادثتين",
  "Manager + TLs":"المدير + قادة الفرق","All Team Leaders":"جميع قادة الفرق","Marketing — team":"التسويق — الفريق",
  "Ramadan Campaign — Core Team":"حملة رمضان — الفريق الأساسي","Lotus SS26":"لوتس صيف 2026","Menu Refresh":"تحديث القائمة",
  "Brand guidelines 2026":"دليل الهوية 2026","Client brief — signed scope":"ملخص العميل — النطاق الموقّع","Falcon X press kit":"الحقيبة الصحفية لفالكون X",
  "SS26 brand guide":"دليل هوية صيف 2026","Approved storyboard":"لوحة القصة المعتمدة","Campaign key messages":"الرسائل الرئيسية للحملة",
  "Script + storyboard v2":"النص ولوحة القصة — الإصدار 2","Photography — selects folder":"التصوير — مجلد الاختيارات","Hero 30s — v3 final cut":"فيديو 30 ثانية — النسخة النهائية 3",
  "Hero 30s — clean feed":"فيديو 30 ثانية — نسخة نظيفة","Week 1 calendar (approved)":"تقويم الأسبوع الأول (معتمد)","Week 2 calendar — master sheet":"تقويم الأسبوع الثاني — الملف الرئيسي",
  "Visual references board":"لوحة المراجع البصرية","SS26 tone-of-voice guide":"دليل نبرة صوت صيف 2026","Product copy blocks — approved":"نصوص المنتجات — معتمدة",
  "Newsletter draft v2 (Google Doc)":"مسودة النشرة الإصدار 2 (Google Doc)","Media plan v1":"الخطة الإعلامية الإصدار 1","Photography — selects folder":"التصوير — مجلد الاختيارات",
  "Logo route B refinements":"تحسينات مسار الشعار B","Naming rationale":"مبررات التسمية","Pack shots — selects":"صور المنتجات — الاختيارات","Retouched finals":"النسخ النهائية المعالجة",
  "Tracker sheet":"ملف المتابعة","Signage copy":"نصوص اللافتات","SS26 tone-of-voice guide":"دليل نبرة صوت صيف 2026",
  "Client confirming dimensions.":"العميل يؤكد المقاسات.","Client reviewing script VO.":"العميل يراجع التعليق الصوتي للنص.","Client dropped the OOH placement.":"العميل ألغى حجز الإعلان الخارجي.","Client paused engagement":"العميل أوقف التعاقد مؤقتًا",
  "Shorten the intro to 2 lines, fix the CTA links, match SS26 tone.":"اختصر المقدمة إلى سطرين، وأصلح روابط الدعوة لاتخاذ إجراء، وطابق نبرة صيف 2026.",
  "Mark spacing is off in small sizes — retest at 24px.":"المسافات في الشعار غير مناسبة عند الأحجام الصغيرة — أعد الاختبار على 24px.",
  "Reshoot table scene — glare on the juice bottles.":"أعد تصوير مشهد الطاولة — يوجد انعكاس على زجاجات العصير.","Great coverage, all angles usable.":"تغطية ممتازة، كل الزوايا قابلة للاستخدام.",
  "On it — swapping the CTA links now.":"جاري التنفيذ — أستبدل روابط الدعوة لاتخاذ إجراء الآن.","Check the review notes on the newsletter — mainly the CTA links.":"راجع ملاحظات النشرة — خصوصًا روابط الدعوة لاتخاذ إجراء.",
  "Ping me after you fix the CTA links":"أبلغني بعد إصلاح روابط الدعوة لاتخاذ إجراء","Got it — will assign this morning.":"تم — سأقوم بالتعيين هذا الصباح.",
  "Sending you the pricing task today":"سأرسل لك مهمة الأسعار اليوم","July reviews Thursday 2pm":"مراجعات يوليو يوم الخميس الساعة 2 مساءً","Works for Content.":"مناسب لقسم المحتوى.",
  "hero cut submitted 🎬":"تم تسليم نسخة الفيديو الرئيسية 🎬","Nice — Design is clear for handoff if it lands today.":"ممتاز — قسم التصميم جاهز للاستلام إذا وصلت اليوم.",
  "Calendar template updated ✔ new version here:":"تم تحديث قالب التقويم ✔ النسخة الجديدة هنا:",
  "Week 2 calendar is in — submitted for review. Friday slot still waits on the pack-shot.":"تم تسليم تقويم الأسبوع الثاني للمراجعة. محتوى الجمعة ما زال بانتظار صورة المنتج.",
  "Reminder: urgent items first today — the hero video and week-2 calendar are both due 23:59.":"تذكير: ابدأ بالعناصر العاجلة اليوم — فيديو الحملة وتقويم الأسبوع الثاني مستحقان الساعة 23:59.",
  "Friday post pending the pack-shot from Photography — placeholder marked in yellow.":"منشور الجمعة بانتظار صورة المنتج من قسم التصوير — تم تمييز المكان المؤقت باللون الأصفر.",
  "Write the launch landing page copy: hero, 3 feature blocks, spec highlights, test-drive CTA.":"اكتب محتوى صفحة هبوط الإطلاق: القسم الرئيسي، 3 أقسام للمزايا، أبرز المواصفات ودعوة لتجربة القيادة.",
  "Draft the paid media plan: channels, budget split, flighting for the first 6 weeks.":"أعد مسودة خطة الإعلام المدفوع: القنوات، توزيع الميزانية والجدول لأول 6 أسابيع.",
  "One-pager comparing Q3 price positioning for the top 5 competitors, with sources.":"صفحة واحدة تقارن تموضع الأسعار في الربع الثالث لأهم 5 منافسين مع المصادر.",
  "Shoot the dessert line in-store: 12 hero shots + detail coverage.":"صوّر خط الحلويات داخل المتجر: 12 صورة رئيسية مع تغطية التفاصيل.",
  "Mock up the storefront signage set in 3 sizes.":"جهّز نماذج لافتات واجهة المتجر بثلاثة مقاسات.",
  "Track branded hashtag usage for July and flag the top 10 UGC posts for reposting.":"تابع استخدام هاشتاج العلامة خلال يوليو وحدد أفضل 10 منشورات من محتوى المستخدمين لإعادة النشر.",
  "Tag and index the Q2 campaign folders on Drive per the naming convention.":"صنّف وافهرس مجلدات حملات الربع الثاني على Drive حسب قواعد التسمية.",
  "Refine the shortlisted logo route per the review notes.":"حسّن مسار الشعار المختار حسب ملاحظات المراجعة.",
  "Pack shots for the juice range, white background + lifestyle.":"صور منتجات لمجموعة العصائر بخلفية بيضاء وصور استخدام واقعية.",
  "Adapt key visual to 3 billboard sizes.":"كيّف التصميم الرئيسي لثلاثة مقاسات لوحات إعلانية.",
  "Build the week-2 Ramadan calendar: all 7 days incl. suhoor slots, copy hooks per post, and visual references.":"أنشئ تقويم الأسبوع الثاني من رمضان: الأيام السبعة بما فيها مواعيد السحور، وأفكار النص لكل منشور والمراجع البصرية.",
  "Cut the 30-second hero spot from the approved footage: 2 revisions max, subtitle-safe area, end-frame per brand guide.":"نفّذ نسخة 30 ثانية من اللقطات المعتمدة: بحد أقصى تعديلان، ومساحة آمنة للترجمة، وإطار ختامي حسب دليل الهوية.",
  "Draft the influencer collaboration brief: 3 macro + 8 micro creators, deliverables per tier, usage rights (6 months, EG + KSA), content do's & don'ts.":"أعد ملخص تعاون المؤثرين: 3 مؤثرين كبار و8 صغار، ومخرجات لكل فئة، وحقوق استخدام لمدة 6 أشهر في مصر والسعودية، وضوابط المحتوى.",
  "Write the July loyalty newsletter: hero block for the Summer Collection, 3 product cards, loyalty-points reminder, one CTA. Under 250 words.":"اكتب نشرة الولاء لشهر يوليو: قسم رئيسي للمجموعة الصيفية، 3 بطاقات منتجات، تذكير بنقاط الولاء ودعوة واحدة لاتخاذ إجراء، في أقل من 250 كلمة.",
  "Draft":"مسودة","Submitted":"تم التسليم","Returned · 1":"مرتجع · 1","Active · 3":"نشط · 3","Completed · 9":"مكتمل · 9",
  "Active":"نشط","Inactive":"غير نشط","Disabled":"معطّل","On leave":"في إجازة","On hold":"متوقف مؤقتًا","Paused":"متوقف","Completed":"مكتمل","Cancelled":"ملغي",
  "Under review":"قيد المراجعة","Changes requested":"تعديلات مطلوبة","In progress":"قيد التنفيذ","Waiting assignment":"بانتظار التعيين","Overdue":"متأخر","Near deadline":"اقترب الموعد","On time":"في الموعد","Not set":"غير محدد","Unseen":"لم تتم المشاهدة",
  "Urgent":"عاجل","High":"مرتفع","Medium":"متوسط","Low":"منخفض","All":"الكل","All departments":"كل الأقسام","All tasks":"كل المهام",
  "Status":"الحالة","Priority":"الأولوية","Department":"القسم","Assignee":"المكلّف","Project":"المشروع","Client":"العميل","Actions":"الإجراءات","Action":"الإجراء","Actor":"المنفذ","Entity":"الكيان","Time":"الوقت","Date":"التاريخ",
  "Title":"العنوان","Brief":"الملخص","Description":"الوصف","Notes":"ملاحظات","Name":"الاسم","Username":"اسم المستخدم","Password":"كلمة المرور","Personal email":"البريد الشخصي","Client email":"بريد العميل","Client phone":"هاتف العميل","Client name":"اسم العميل",
  "Start":"البداية","Due":"الموعد","Dates":"التواريخ","Current":"الحالي","Required":"مطلوب","optional":"اختياري","Add":"إضافة","Add link":"إضافة رابط","Label":"التسمية","URL":"الرابط","Details":"التفاصيل","Outputs":"المخرجات","Comments":"التعليقات",
  "Create":"إنشاء","Save":"حفظ","Cancel":"إلغاء","Confirm":"تأكيد","Back":"رجوع","Open":"فتح","Manage":"إدارة","Assign":"تعيين","Reassign":"إعادة تعيين","Review":"مراجعة","Send":"إرسال","Show":"إظهار","Export CSV":"تصدير CSV","Mark all read":"تحديد الكل كمقروء",
  "Admin":"أدمن","Manager":"مدير","Team Leader":"قائد فريق","Employee":"موظف","Users":"المستخدمون","Departments":"الأقسام","Clients":"العملاء","Projects":"المشروعات","Tasks":"المهام","Notifications":"الإشعارات","Chat":"المحادثات","Audit Log":"سجل التدقيق","System Settings":"إعدادات النظام","Review Queue":"قائمة المراجعة",
  "Today 23:59":"اليوم 23:59","Tomorrow 23:59":"غدًا 23:59","Yesterday":"أمس","Today":"اليوم","2 h ago":"منذ ساعتين","3 h ago":"منذ 3 ساعات","24 min later":"بعد 24 دقيقة",
  "July":"يوليو","June":"يونيو","May":"مايو","Jul":"يوليو","Jun":"يونيو","Aug":"أغسطس","Saturday":"السبت","Thursday":"الخميس","Monday":"الاثنين","Friday":"الجمعة",
  "(bottom corner) to jump roles and screens, and":"(الركن السفلي) للتنقل بين الأدوار والشاشات، و",
  "), password":")، كلمة المرور",". Use the black":". استخدم الزر الأسود","1 link":"رابط واحد","2 links":"رابطان","3 links":"3 روابط",
  "1 step · 2 days excluded":"مرحلة واحدة · تم استبعاد يومين","1 urgent":"مهمة عاجلة واحدة","2 clients onboarding":"جاري ضم عميلين","2 tasks":"مهمتان",
  "1 · Login page":"1 · صفحة تسجيل الدخول","2 · Manager view":"2 · عرض المدير","3 · Team Leader view":"3 · عرض قائد الفريق","4 · Employee view":"4 · عرض الموظف","5 · Admin view":"5 · عرض الأدمن","6 · Task workflow":"6 · سير عمل المهمة",
  "4 items":"4 عناصر","4.2% of open steps":"4.2% من المراحل المفتوحة","3 final-only":"3 نهائي فقط","both active":"كلاهما نشط",
  "21 of 23 due steps completed on time":"اكتملت 21 من 23 مرحلة مستحقة في الموعد","4 of 5 · scored as employee + temp TL":"4 من 5 · محسوبة كموظف وقائد فريق مؤقت",
  "5 of 15 late · covers 20 Jul →":"5 من 15 متأخرة · يغطي من 20 يوليو ←","23:59 Africa/Cairo":"23:59 أفريقيا/القاهرة",
  "Auto number:":"الرقم التلقائي:","Change password":"تغيير كلمة المرور","Client name is required":"اسم العميل مطلوب","Project name is required":"اسم المشروع مطلوب",
  "Compliance":"نسبة الالتزام","Deadline in 24 h — TSK-2026-00331":"الموعد النهائي خلال 24 ساعة — TSK-2026-00331","Deadline — all":"الموعد النهائي — الكل",
  "Edit rules":"تعديل القواعد","Enter a valid email or leave empty":"أدخل بريدًا إلكترونيًا صحيحًا أو اتركه فارغًا","Enter a valid phone":"أدخل رقم هاتف صحيحًا",
  "From ↓ / To →":"من ↓ / إلى ←","Hero-video revision cycle":"دورة تعديلات الفيديو الرئيسي","Invalid link":"رابط غير صالح","Invite link":"رابط الدعوة","Late":"متأخر",
  "Manager dashboard — Agency OS":"لوحة تحكم المدير — Agency OS","Team Leader dashboard — Agency OS":"لوحة تحكم قائد الفريق — Agency OS","My dashboard — Agency OS":"لوحتي — Agency OS","Reports — Agency OS":"التقارير — Agency OS",
  "Marketing · Sara Mostafa assigned this step to herself — Manager review required":"التسويق · عيّنت سارة مصطفى هذه المرحلة لنفسها — مراجعة المدير مطلوبة",
  "Media plan — Falcon X":"الخطة الإعلامية — فالكون X","Min 8 chars.":"8 أحرف على الأقل.","Mohamed Ali — my report":"محمد علي — تقريري","My profile":"ملفي الشخصي","Nudge":"تنبيه",
  "Output-access rules":"قواعد صلاحيات المخرجات","Priority — all":"الأولوية — الكل","Select at least one department":"اختر قسمًا واحدًا على الأقل","Select client":"اختر العميل",
  "Sign in":"تسجيل الدخول","Sign in to Agency OS":"تسجيل الدخول إلى Agency OS","Task":"المهمة","You":"أنت","Your review":"مراجعتك",
  'Rania: "Calendar template updated ✔"':"رانيا: «تم تحديث قالب التقويم ✔»","Reem Shaker":"ريم شاكر","Routing rules":"قواعد التوجيه",
  "Ramadan social calendar — week 2 (TSK-2026-00331) is under review. If changes are requested, resubmit before the deadline to stay on time.":"تقويم رمضان للسوشيال — الأسبوع الثاني (TSK-2026-00331) قيد المراجعة. عند طلب تعديلات، أعد التسليم قبل الموعد للحفاظ على الالتزام.",
  "On-time steps ÷ steps due this month × 100. Requested changes don't lower the score if the final version is approved before the deadline. On-hold time is paused.":"المراحل المكتملة في الموعد ÷ المراحل المستحقة هذا الشهر × 100. طلب التعديلات لا يخفض التقييم إذا تم اعتماد النسخة النهائية قبل الموعد. مدة التوقف لا تُحتسب.",
  "Per BRD §17: a TL's personal task performance and their team's deadline-overrun rate are reported separately — one never dilutes the other.":"وفق BRD §17: يتم عرض أداء قائد الفريق الشخصي ونسبة تجاوز فريقه للمواعيد بصورة منفصلة — لا يؤثر أحدهما في الآخر.",
  "Photography impacted by mid-month TL leave and one disabled account (2 steps reassigned). A step belongs to the month its deadline falls in.":"تأثر قسم التصوير بإجازة قائد الفريق منتصف الشهر وتعطيل حساب واحد (تمت إعادة تعيين مرحلتين). تُنسب المرحلة إلى الشهر الذي يقع فيه موعدها النهائي.",
  "Manager sees all three and the comparison · a TL sees their department + their own · an employee sees only their own":"يرى المدير التقارير الثلاثة والمقارنة · يرى قائد الفريق قسمه وتقريره الشخصي · يرى الموظف تقريره فقط",
  'Three report types, one formula: on-time steps ÷ steps due in the month × 100. On-hold time excluded · cancelled steps excluded · "changes requested" still counts on time if final approval lands before the deadline · no due steps →':"ثلاثة أنواع من التقارير بمعادلة واحدة: المراحل المكتملة في الموعد ÷ المراحل المستحقة خلال الشهر × 100. يتم استبعاد مدة التوقف والمراحل الملغاة، وتُحسب التعديلات في الموعد إذا تم الاعتماد النهائي قبل الموعد، وعند عدم وجود مراحل مستحقة ←",
  "Score = on-time due steps ÷ total due steps × 100 · a step counts on-time when the":"التقييم = المراحل المستحقة المكتملة في الموعد ÷ إجمالي المراحل المستحقة × 100 · تُحسب المرحلة في الموعد عندما",
  "If provided, every member of the participating departments receives the invitation (in-app + email) at creation — versioned, with a full delivery ledger.":"عند إضافة الرابط، يستلم كل عضو في الأقسام المشاركة الدعوة داخل النظام وعبر البريد عند الإنشاء — مع إصدارات وسجل تسليم كامل.",
  "Yesterday 23:59 · in-app + email":"أمس 23:59 · داخل النظام + البريد","appear in the Audit Log.":"تظهر في سجل التدقيق.","arrived to Marketing":"وصلت إلى قسم التسويق",
  "content":"المحتوى","due 15 Jul · 2 change rounds · final approved 15 Jul 21:40":"الموعد 15 يوليو · جولتا تعديل · الاعتماد النهائي 15 يوليو 21:40",
  "due 18 Jul · approved 17 Jul":"الموعد 18 يوليو · تم الاعتماد 17 يوليو","due steps · on time · late · compliance %":"المراحل المستحقة · في الموعد · متأخرة · نسبة الالتزام",
  "events":"الأحداث","monthly figures freeze on the 1st of the following month; the current month recalculates live. Manager additionally gets the cross-comparison of departments, TLs and employees on this page.":"تُثبّت أرقام الشهر في اليوم الأول من الشهر التالي، ويُعاد حساب الشهر الحالي مباشرة. يحصل المدير أيضًا على مقارنة بين الأقسام وقادة الفرق والموظفين في هذه الصفحة.",
  "on white, tokens centralized in":"على خلفية بيضاء، ومتغيرات الألوان موحدة داخل","personal score & team overrun — always separate":"التقييم الشخصي وتجاوز الفريق — منفصلان دائمًا",
  "project PRJ-2026-0007 · v1→v2":"المشروع PRJ-2026-0007 · الإصدار 1←2","system":"النظام","temp TL":"قائد فريق مؤقت",
  "to flip language and direction. Full walkthrough in README.md.":"لتغيير اللغة والاتجاه. الشرح الكامل داخل README.md.","user #23 (Tarek Hussein)":"المستخدم #23 (طارق حسين)","user #31 · hard bounce":"المستخدم #31 · ارتداد دائم",
  "username":"اسم المستخدم","· assigned automatically":"· تم التعيين تلقائيًا","· the first department's TL will assign it.":"· سيقوم قائد الفريق في القسم الأول بتعيينها.",
  "⏰ Deadline is always":"⏰ الموعد النهائي دائمًا","▲ 3 pts vs June":"▲ 3 نقاط مقارنة بيونيو","🏷 Project number":"🏷 رقم المشروع",
  "👁 First-view time is recorded automatically the moment the assignee opens the task, and is shown to the department TL (and Manager) only. Employees never see when you read their work.":"👁 يتم تسجيل وقت أول مشاهدة تلقائيًا عند فتح المكلّف للمهمة، ويظهر لقائد الفريق والمدير فقط. لا يرى الموظفون وقت قراءتك لأعمالهم.",
  "👁 first-view tracking":"👁 تتبع أول مشاهدة","🙋 Employee report — all employees (Manager view)":"🙋 تقرير الموظفين — جميع الموظفين (عرض المدير)",
  "Admin — Agency OS":"الأدمن — Agency OS","Managers":"المديرون","(self-assigned steps)":"(مراحل بتعيين ذاتي)","Due today 23:59:":"مستحق اليوم 23:59:","Due today 23:59":"مستحق اليوم 23:59",
  "email.bounced":"ارتداد البريد","password.reset":"إعادة تعيين كلمة المرور","temp_tl.assigned":"تعيين قائد فريق مؤقت","whatsapp_link.updated":"تحديث رابط واتساب",
  "5 retries exhausted":"تم استنفاد 5 محاولات إعادة","Automotive · Falcon X launch":"سيارات · إطلاق فالكون X","Beauty · social-first brand":"تجميل · علامة تعتمد على السوشيال أولًا",
  "FMCG · seasonal campaigns":"سلع استهلاكية · حملات موسمية","Healthcare · rebrand cancelled":"رعاية صحية · تم إلغاء إعادة الهوية","Real estate":"عقارات",
  "Full-funnel Ramadan campaign: TV-cut hero video, social calendar, in-store photography and packaging adaptations.":"حملة رمضان متكاملة: فيديو رئيسي للتلفزيون، تقويم سوشيال، تصوير داخل المتجر وتعديلات العبوات.",
  "Identity refresh for the clinic network.":"تحديث هوية شبكة العيادات.","Launch package for the Falcon X model: landing copy, media plan, showroom visuals.":"حزمة إطلاق طراز فالكون X: محتوى صفحة الهبوط، الخطة الإعلامية ومرئيات صالة العرض.",
  "Menu photography and POS refresh for the summer menu.":"تصوير القائمة وتحديث مواد نقاط البيع لقائمة الصيف.","SS26 social-first push: influencer program, lifestyle shots, packaging refresh.":"حملة صيف 2026 تركز على السوشيال: برنامج مؤثرين، صور استخدام واقعية وتحديث العبوات.",
  "Copy draft v1":"مسودة المحتوى — الإصدار 1","Mark spacing is off in small sizes.":"مسافات الشعار غير مناسبة في الأحجام الصغيرة.",
  "Omar Farid replaced Tarek Hussein (account disabled)":"حلّ عمر فريد محل طارق حسين (تم تعطيل الحساب)","On it — CTA links were from the old campaign, swapping now.":"جاري التنفيذ — روابط الدعوة كانت من الحملة القديمة وأستبدلها الآن.",
  "Returned to TL — Tarek Hussein disabled":"أُعيدت إلى قائد الفريق — تم تعطيل حساب طارق حسين",'Sara Mostafa · "Shorten intro, fix CTA links"':"سارة مصطفى · «اختصر المقدمة وأصلح روابط الدعوة»",
  "Self-assigned by TL · Manager will review":"عيّن قائد الفريق المهمة لنفسه · سيراجعها المدير","Task completed — read-only":"تم إكمال المهمة — للقراءة فقط",
  "allowed: false":"مسموح: لا","allowed: true":"مسموح: نعم","attempts: 4":"المحاولات: 4","attempts: 5 · hard_bounce · email flagged":"المحاولات: 5 · ارتداد دائم · تم وضع علامة على البريد",
  "conversation #2 · msg #2":"المحادثة #2 · الرسالة #2","deleted_for: all · content not stored":"تم الحذف للجميع · لم يتم تخزين المحتوى","dept Photography":"قسم التصوير",
  "dept: Design":"القسم: التصميم","dept: Editing · reason logged":"القسم: المونتاج · تم تسجيل السبب","frozen at completion":"تم تثبيته عند الإكمال",
  "notification #99182 · user #16":"الإشعار #99182 · المستخدم #16","project PRJ-2026-0007":"المشروع PRJ-2026-0007","re-sent to all 28 current members":"أُعيد الإرسال إلى جميع الأعضاء الحاليين وعددهم 28",
  "sent to 21 members at creation":"تم الإرسال إلى 21 عضوًا عند الإنشاء","sent to 24 members at creation":"تم الإرسال إلى 24 عضوًا عند الإنشاء",
  "status: active":"الحالة: نشط","status: disabled · 2 steps returned to TL":"الحالة: معطّل · أُعيدت مرحلتان إلى قائد الفريق","temp password issued · must_change: true":"تم إصدار كلمة مرور مؤقتة · تغيير كلمة المرور إلزامي",
  "temp: Karim Fathy · 20 Jul–05 Aug":"المؤقت: كريم فتحي · 20 يوليو–05 أغسطس","under_review · outputs: 2":"قيد المراجعة · المخرجات: 2",
  "version: 1":"الإصدار: 1","version: 2 · resend: 28 members":"الإصدار: 2 · إعادة الإرسال: 28 عضوًا","user #16 · Tarek Hussein":"المستخدم #16 · طارق حسين",
  "Editing → Moderation":"المونتاج ← إدارة الصفحات","TSK-2026-00331 · step 1":"TSK-2026-00331 · المرحلة 1",
  "Agency":"أجنسي","OS":"أو إس"
};

var PHRASES={
  "Manager review required":"مراجعة المدير مطلوبة","changes requested twice":"تم طلب تعديلات مرتين","returned to TL":"أُعيدت إلى قائد الفريق","employee":"الموظف","was disabled":"تم تعطيل حسابه",
  "assigned this step to herself":"عيّنت هذه المرحلة لنفسها","held 3 days":"متوقفة منذ 3 أيام","client confirming dimensions":"العميل يؤكد المقاسات","deadline paused":"الموعد متوقف",
  "across all departments":"على جميع الأقسام","across 3 departments":"عبر 3 أقسام","across the team":"على مستوى الفريق","this week":"هذا الأسبوع","this month":"هذا الشهر",
  "due today":"مستحق اليوم","due":"الموعد","submitted":"تم التسليم","assigned":"تم التعيين","first view recorded":"تم تسجيل أول مشاهدة","waiting your assignment":"بانتظار تعيينك",
  "deadline reminder":"تذكير بالموعد","main TL view-only":"قائد الفريق الأساسي للعرض فقط","workflow unaffected":"سير العمل لم يتأثر","New link resent to all":"أُعيد إرسال الرابط الجديد إلى جميع",
  "project members":"أعضاء المشروع","Join the project group":"انضم إلى مجموعة المشروع","Hard bounce after":"ارتداد دائم بعد","retries":"محاولات إعادة",
  "Task completed — read-only":"تم إكمال المهمة — للقراءة فقط","First department:":"القسم الأول:","step approved":"تم اعتماد المرحلة","First opened by assignee":"أول فتح بواسطة المكلّف",
  "First opened":"تم الفتح لأول مرة","output links attached":"روابط مخرجات مرفقة","output link attached":"رابط مخرج مرفق","Deadline extended by":"تم تمديد الموعد بمقدار",
  "days":"أيام","day":"يوم","members":"أعضاء","departments":"أقسام","open tasks":"مهام مفتوحة","late":"متأخر","on time":"في الموعد","due steps":"مراحل مستحقة",
  "no WA link":"لا يوجد رابط واتساب","WA link":"رابط واتساب","Active projects":"المشروعات النشطة","Open steps":"مراحل مفتوحة","active tasks":"مهام نشطة",
  "First seen":"أول مشاهدة","not opened yet":"لم تُفتح بعد","seen":"شوهدت","starts":"تبدأ","Monday":"الاثنين","Yesterday":"أمس","Today":"اليوم","Tomorrow":"غدًا",
  "from Manager":"من المدير","from Content":"من قسم المحتوى","standalone task":"مهمة مستقلة","standalone":"مستقلة","self-assigned":"تعيين ذاتي",
  "also sent to your email":"تم إرسالها أيضًا إلى بريدك","email only":"البريد فقط","next at":"التالي الساعة","digest":"ملخص","unread messages":"رسائل غير مقروءة","conversations":"محادثات",
  "By":"بواسطة","Until":"حتى","Covering":"بديل عن","leave":"إجازة","joined":"انضم","configured":"مُعدّ","verified":"موثّق","hard bounce":"ارتداد دائم","flagged":"تم وضع علامة",
  "sent":"تم الإرسال","queued":"في قائمة الانتظار","failed":"فشل","retried":"أُعيدت المحاولة","bounced":"مرتدة","all current members":"جميع الأعضاء الحاليين","at creation":"عند الإنشاء",
  "New":"جديد","History":"السجل","Current":"الحالي","email channel disabled until verified":"قناة البريد متوقفة حتى التحقق","no due steps":"لا توجد مراحل مستحقة",
  "on the due date":"في تاريخ الاستحقاق","final approval":"الاعتماد النهائي","lands before its deadline":"يتم قبل الموعد النهائي","changes requested":"التعديلات المطلوبة",
  "score":"التقييم","Score":"التقييم","month":"الشهر","Department":"القسم","Team Leader":"قائد الفريق","Employee":"الموظف","Manager":"المدير","Admin":"الأدمن",
  "Photography":"التصوير","Marketing":"التسويق","Content":"المحتوى","Design":"التصميم","Editing":"المونتاج","Moderation":"إدارة الصفحات"
};
var phraseKeys=Object.keys(PHRASES).sort(function(a,b){return b.length-a.length;});

function translate(value){
  if(value==null) return value;
  var original=String(value);
  if(!/[A-Za-z]/.test(original)) return original;
  if(/^https?:\/\//i.test(original)||/^[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}$/.test(original)) return original;
  var lead=(original.match(/^\s*/)||[""])[0], trail=(original.match(/\s*$/)||[""])[0];
  var core=original.trim();
  if(EXACT[core]) return lead+EXACT[core]+trail;
  var out=core;
  function replaceTerm(text,key,value){
    if(/^[A-Za-z]+$/.test(key)){
      var escaped=key.replace(/[.*+?^${}()|[\]\\]/g,"\\$&");
      return text.replace(new RegExp("\\b"+escaped+"\\b","g"),value);
    }
    return text.indexOf(key)>-1?text.split(key).join(value):text;
  }
  Object.keys(EXACT).sort(function(a,b){return b.length-a.length;}).forEach(function(k){ out=replaceTerm(out,k,EXACT[k]); });
  phraseKeys.forEach(function(k){ out=replaceTerm(out,k,PHRASES[k]); });
  out=out.replace(/\b(\d+) of (\d+)\b/g,"$1 من $2").replace(/\b(\d+) h ago\b/g,"منذ $1 ساعات").replace(/\b(\d+) days?\b/g,"$1 يوم");
  return lead+out+trail;
}
function skipNode(node){
  var p=node.parentElement;
  return !p||p.closest("script,style,code,pre,textarea")||p.hasAttribute("data-no-localize");
}
function localizeNode(root){
  if(!root) return;
  if(root.nodeType===3){ if(!skipNode(root)){var n=translate(root.nodeValue);if(n!==root.nodeValue)root.nodeValue=n;} return; }
  if(root.nodeType!==1&&root.nodeType!==9&&root.nodeType!==11) return;
  var walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);
  var nodes=[]; while(walker.nextNode()) nodes.push(walker.currentNode);
  nodes.forEach(function(n){if(!skipNode(n)){var v=translate(n.nodeValue);if(v!==n.nodeValue)n.nodeValue=v;}});
  var els=[]; if(root.nodeType===1) els.push(root); if(root.querySelectorAll) els=els.concat([].slice.call(root.querySelectorAll("[placeholder],[title],[aria-label],input[value]")));
  els.forEach(function(el){
    ["placeholder","title","aria-label"].forEach(function(a){var v=el.getAttribute&&el.getAttribute(a);if(v){var n=translate(v);if(n!==v)el.setAttribute(a,n);}});
    if(el.tagName==="INPUT"&&/^(text|search|button|submit)$/i.test(el.type||"text")&&el.value&&!el.value.includes("@")){var n=translate(el.value);if(n!==el.value)el.value=n;}
  });
}
function boot(){
  document.documentElement.lang="ar";document.documentElement.dir="rtl";
  document.title=translate(document.title);
  localizeNode(document.body);
  var busy=false;
  new MutationObserver(function(ms){if(busy)return;busy=true;ms.forEach(function(m){m.addedNodes.forEach(localizeNode);if(m.type==="characterData")localizeNode(m.target);});busy=false;}).observe(document.body,{subtree:true,childList:true,characterData:true});
}
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",boot); else boot();
window.SKY_AR={translate:translate,localize:localizeNode};
})();
