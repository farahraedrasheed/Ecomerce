# شرح المشروع — منصة تجارة إلكترونية (E-commerce Platform)

هذا الملف يشرح المشروع بالكامل: شو بنعمل، كيف بنى، ولِيش اخترنا كل تقنية استخدمناها. الهدف إنه يكون مرجع سريع لما حدا (أو الدكتورة) يسأل "ليش استخدمتوا كذا؟".

للتفاصيل الأعمق عن تصميم الباك اند تحديدًا (طبقات، دورة حياة الطلب، كل controller/model، الاختبارات) شوف [`BACKEND_DESIGN.md`](BACKEND_DESIGN.md).

---

## 1. فكرة المشروع

منصة تجارة إلكترونية (E-commerce) بسيطة فيها:
- تصفح منتجات وتصنيفات (Categories)
- تسجيل حساب / تسجيل دخول
- سلة مشتريات (Cart)
- إتمام الطلب (Checkout) وإنشاء طلبات (Orders)
- لوحة تحكم للأدمن (Admin Dashboard) لإدارة المنتجات، التصنيفات، الطلبات، ومتابعة إحصائيات المبيعات

المشروع مقسوم لجزئين منفصلين تمامًا:

```
backend/   → Laravel REST API (كل منطق العمل وقاعدة البيانات)
frontend/  → صفحات HTML/CSS/JS عادية بتستهلك الـ API
```

هاي البنية اسمها **decoupled architecture** (فرونت اند منفصل عن الباك اند وبيتواصلوا عن طريق API فقط)، مش تطبيق Laravel تقليدي بيرندر صفحات HTML من السيرفر (Blade).

---

## 2. لماذا Laravel للباك اند؟

- فريمورك PHP جاهز بيوفر MVC structure (Models / Controllers / Routes) بدون ما نبني كل شي من الصفر.
- عنده ORM اسمه **Eloquent** بيسهّل التعامل مع قاعدة البيانات (العلاقات بين الجداول، الـ migrations، الـ seeders).
- مدمج فيه نظام validation، وnظام routing لعمل REST API بسرعة.
- مناسب جدًا لمشروع capstone لأنه معياري (standard) بالصناعة، وموثّق كويس.

---

## 3. لماذا Laravel Sanctum للمصادقة (Authentication)؟

هاي كانت نقطة أساسية بالمشروع. الفرونت اند عبارة عن صفحات HTML/JS بسيطة بتستدعي الـ API عن طريق `fetch()` — مش تطبيق SPA كامل بفريمورك (React/Vue)، ومش تطبيق Laravel بيرندر Blade من السيرفر.

**Sanctum** هو الحل الرسمي من Laravel المصمم بالضبط لهيك حالة:
- بيعطي **API token** (Bearer token) لما المستخدم يسجل دخول، والفرونت اند بيخزنه بـ `localStorage` وبيرسله مع كل طلب بـ header:
  `Authorization: Bearer <token>`
- أخف بكثير من **Laravel Passport** (اللي هو OAuth2 كامل) — Passport مصمم لما يكون في تطبيقات طرف ثالث (third-party) بتحتاج grant types، client IDs، refresh tokens... إحنا عنا تطبيق واحد فقط (الفرونت اند حقنا) بيكلم API حقنا، فـ OAuth كامل يكون تعقيد زيادة مالوش داعي.
- أبسط من الاعتماد على **session cookies** التقليدية، لأنه ما بيحتاج نفس الـ domain أو التعامل مع CSRF بشكل معقد — مناسب أكتر لما يكون الفرونت والباك مفصولين.
- بيتكامل مباشرة مع نظام الـ guards والـ middleware بـ Laravel: كفاية نحط `auth:sanctum` على أي route عشان نحميه ونقدر نجيب المستخدم الحالي بـ `$request->user()`.

بالكود: [`backend/routes/api.php`](backend/routes/api.php) بيستخدم `auth:sanctum` middleware على كل الـ routes المحمية (`/cart`, `/orders`, عمليات الأدمن...)، و[`AuthController`](backend/app/Http/Controllers/AuthController.php) بينشئ التوكن عن طريق `$user->createToken('api-token')->plainTextToken`.

---

## 4. لماذا MySQL لقاعدة البيانات؟

- قاعدة بيانات علائقية (relational) — مناسبة تمامًا لبيانات فيها علاقات واضحة: منتج بيتبع تصنيف، طلب بيتبع مستخدم وفيه عناصر (order items)، سلة مشتريات مرتبطة بمستخدم ومنتج... إلخ.
- مدعومة بشكل أساسي من Laravel (ما بتحتاج إعدادات إضافية).
- شغالة محليًا عن طريق XAMPP، وهي الخيار المعياري لمعظم مشاريع Laravel التعليمية.

---

## 5. هيكلية الباك اند (backend/)

### الجداول (Migrations) — [`backend/database/migrations/`](backend/database/migrations/)
| الجدول | الوظيفة |
|---|---|
| `users` | المستخدمين، فيه عمود `role` (customer / admin) |
| `categories` | تصنيفات المنتجات |
| `products` | المنتجات (سعر، مخزون stock، صورة، تصنيف) |
| `cart_items` | عناصر سلة كل مستخدم |
| `orders` | الطلبات (حالة الطلب، المجموع، عنوان الشحن) |
| `order_items` | تفاصيل كل طلب (منتج، كمية، سعر وقت الشراء) |
| `personal_access_tokens` | توكنات Sanctum |

### الموديلز (Models) — [`backend/app/Models/`](backend/app/Models/)
كل موديل بيمثل جدول، وفيه العلاقات (relationships) بينهم عن طريق Eloquent:
- `User` → عنده `cartItems()` و `orders()`، ودالة `isAdmin()` بترجع `true` إذا `role === 'admin'`.
- `Product` → بيتبع `Category`، وعنده `cartItems()` و `orderItems()`.
- `Order` → بيتبع `User`، وعنده `items` (OrderItem).

### الكنترولرز (Controllers) — [`backend/app/Http/Controllers/`](backend/app/Http/Controllers/)
| Controller | المسؤولية |
|---|---|
| `AuthController` | تسجيل حساب، دخول، خروج — بينشئ Sanctum token |
| `ProductController` | عرض المنتجات (مع فلترة بالتصنيف والبحث بالاسم)، وعمليات CRUD للأدمن فقط |
| `CategoryController` | عرض وإدارة التصنيفات |
| `CartController` | إضافة/تعديل/حذف عناصر السلة — كل عملية بتتحقق إنه العنصر يخص المستخدم الحالي |
| `OrderController` | إنشاء طلب من محتويات السلة (جوا **DB transaction** عشان لو صار خطأ يترجع كل شي)، إنقاص المخزون تلقائيًا، وعرض طلبات المستخدم (أو كل الطلبات إذا أدمن) |
| `StatsController` | إحصائيات للوحة تحكم الأدمن: إجمالي المبيعات، عدد الطلبات، الإيرادات آخر 14 يوم، أكثر 5 منتجات مبيعًا |

### الحماية (Middleware)
- `auth:sanctum` → لازم تكون مسجل دخول.
- `EnsureUserIsAdmin` (اسمه بالـ routes `admin`) → لازم يكون `role === admin`، وإلا بيرجع 403 Forbidden. مستخدم على routes إدارة المنتجات/التصنيفات/تحديث حالة الطلب/الإحصائيات.

### لماذا DB Transaction بإنشاء الطلب؟
بـ [`OrderController::store()`](backend/app/Http/Controllers/OrderController.php) — إنشاء الطلب، إنشاء order items، وإنقاص المخزون كلهم لازم يصيروا **سوا أو ولا واحد**. لو صار خطأ بالنص (مثلاً نفاذ مخزون)، الـ `DB::transaction()` بترجع كل التغييرات (rollback) عشان ما تفضل بيانات غير متسقة.

---

## 6. الفرونت اند (frontend/)

صفحات HTML/CSS/JavaScript عادية **بدون أي framework وبدون build step** (مافي React/Vue/Webpack).

| الصفحة | الوظيفة |
|---|---|
| `index.html` | عرض المنتجات + فلترة/بحث |
| `product.html` | تفاصيل منتج واحد |
| `cart.html` | السلة |
| `checkout.html` | إتمام الطلب |
| `login.html` / `register.html` | تسجيل الدخول/حساب جديد |
| `orders.html` | طلبات المستخدم |
| `admin/dashboard.html` | إحصائيات الأدمن (بيستهلك `/api/stats`) |
| `admin/products.html`, `admin/categories.html`, `admin/orders.html` | إدارة المنتجات/التصنيفات/الطلبات |

كل التواصل مع الـ API عن طريق [`frontend/js/api.js`](frontend/js/api.js) — فيه دالة مشتركة `apiRequest()` بتضيف الـ Bearer token تلقائيًا لما يكون المستخدم مسجل دخول.

**لماذا بدون framework؟**
- المشروع بسيط بما يكفي إنه ما بيحتاج تعقيد إضافي زي React/Vue.
- بيوضح فهم أساسيات الـ Fetch API والتعامل المباشر مع DOM، وهو مطلوب أساسي بمنهج الدورة.
- سهل التشغيل بدون أي build tools — كفاية سيرفر ثابت (static server).

**CORS**: مفعّل بشكل مفتوح (`allowed_origins: ['*']`) بملف [`backend/config/cors.php`](backend/config/cors.php) لأن المصادقة بتعتمد على Bearer token مش cookies، فمافي داعي لتقييد الـ origin.

---

## 7. الاختبارات (Tests) — [`backend/tests/Feature/`](backend/tests/Feature/)

في اختبارات Feature لكل جزء أساسي:
- `AuthTest` → تسجيل حساب، دخول، خروج
- `ProductTest` → عرض، بحث، فلترة، وعمليات الأدمن
- `CategoryTest` → CRUD التصنيفات
- `CartTest` → إضافة/تعديل/حذف عناصر السلة
- `OrderTest` → إنشاء طلب، صلاحيات (مستخدم بيشوف طلباته بس، الأدمن بيشوف الكل)

هاي الاختبارات بتثبت إنه الـ business logic (زي التحقق من الصلاحيات، والتحقق من المخزون) شغالة صح، وهو جزء مطلوب من checklist المشروع (خطوة "Test backend workflows and API responses").

---

## 8. ملخص سريع — جدول "ليش استخدمنا هيك؟"

| التقنية/القرار | ليش |
|---|---|
| Laravel | فريمورك PHP معياري، MVC جاهز، Eloquent ORM، سريع لبناء REST API |
| Laravel Sanctum | مصادقة بسيطة بـ tokens مناسبة لفرونت اند منفصل (decoupled)، أخف من Passport (OAuth2 كامل) |
| MySQL | قاعدة بيانات علائقية تناسب العلاقات الواضحة بين المنتجات/الطلبات/المستخدمين |
| HTML/CSS/JS بدون framework | بساطة، ما بيحتاج build step، يوضح فهم أساسيات الـ Fetch API |
| REST API منفصل عن الفرونت اند | فصل الاهتمامات (separation of concerns) — كل جزء ممكن يتطور لحاله |
| DB Transactions بالطلبات | ضمان اتساق البيانات (data consistency) لو صار خطأ بمنتصف العملية |
| Middleware للأدمن (`EnsureUserIsAdmin`) | فصل صلاحيات المستخدم العادي عن الأدمن بمكان واحد قابل لإعادة الاستخدام |
| Feature Tests | التأكد إنه المنطق (صلاحيات، مخزون، حسابات) شغال صح قبل التسليم |
