# تصميم الباك اند بالتفصيل — ShopHub API

هذا الملف يشرح **كيف مصمم الباك اند تحديدًا**: الطبقات، دورة حياة الطلب، كل route وcontroller وmodel، قاعدة البيانات، المصادقة والتفويض، ومنطق الدفع الوهمي. للشرح العام عن كل المشروع (فرونت اند + باك اند + ليش Laravel/Sanctum) شوف [`PROJECT_EXPLANATION.md`](PROJECT_EXPLANATION.md).

---
## 1. البنية العامة (Layered Architecture)

الباك اند مبني على نمط **MVC** القياسي بـ Laravel، بس بما إنه REST API بحت (مافي Views/Blade)، الطبقات الفعلية هي:

```
طلب HTTP من الفرونت اند
        │
        ▼
  routes/api.php          ← يحدد أي Controller method يعالج كل مسار
        │
        ▼
   Middleware              ← auth:sanctum (مصادقة) / admin (تفويض)
        │
        ▼
   Controller               ← يتحقق من صحة البيانات (validate) وينفذ منطق العمل
        │
        ▼
   Model (Eloquent)         ← يتكلم مع قاعدة البيانات، يعرّف العلاقات
        │
        ▼
   MySQL Database
        │
        ▼
   JSON Response            ← response()->json(...) يترجع للفرونت اند
```

مافيش **Services layer** ولا **Repositories** منفصلة — المنطق كله داخل الـ Controllers مباشرة (Fat Controllers). هذا قرار متعمّد: المشروع بحجم متوسط (Controller واحد لكل مورد، منطق مش معقد كتير)، فإضافة طبقات تجريدية زيادة كانت رح تكون **over-engineering** بدون فايدة حقيقية. الاستثناء الوحيد: منطق التحقق من الكرت (Luhn algorithm، فحص الانتهاء) انسحب لكلاس مساعد [`app/Support/CardValidator.php`](backend/app/Support/CardValidator.php) لأنه **مستخدم فعليًا بمكانين** (`OrderController` و`AccountController`) — يعني هون التجريد كان له مبرر حقيقي (DRY)، مش افتراضي.

---

## 2. هيكل المجلدات

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/       ← منطق كل مورد (Auth, Product, Category, Cart, Order, Stats, Account)
│   │   └── Middleware/        ← EnsureUserIsAdmin (تفويض الأدمن)
│   ├── Models/                ← User, Product, Category, CartItem, Order, OrderItem
│   └── Support/                ← CardValidator (منطق مشترك، مش Model ولا Controller)
├── bootstrap/
│   └── app.php                 ← تسجيل الـ middleware alias، إعدادات الـ routing
├── config/
│   ├── sanctum.php              ← إعدادات مصادقة الـ API tokens
│   └── cors.php                  ← سياسة الـ CORS
├── database/
│   ├── migrations/               ← تسلسل زمني لكل تغيير على قاعدة البيانات
│   └── seeders/                   ← بيانات تجريبية (DatabaseSeeder)
├── routes/
│   └── api.php                    ← كل الـ endpoints
└── tests/
    └── Feature/                    ← اختبارات لكل مورد (AuthTest, ProductTest, ...)
```

---

## 3. طبقة الـ Routing — [`routes/api.php`](backend/routes/api.php)

كل الـ routes مقسومة لمجموعات حسب مين يقدر يوصلها. هاي هي البنية الحالية:

| المجموعة | الحماية | ليش |
|---|---|---|
| `/register`, `/login` | بدون حماية (عامة) | لازم يكون أي حدا يقدر يسجل/يدخل |
| `/logout` | `auth:sanctum` | لازم تكون مسجل دخول عشان تحذف التوكن حقك |
| `/account`, `/account/card` | `auth:sanctum` | معلومات حساب شخصية، لازم مصادقة |
| `GET /products`, `GET /products/{id}` | بدون حماية (تصفح عام) | أي زائر يقدر يشوف المنتجات |
| `GET /my-products`, `POST/PUT/DELETE /products` | `auth:sanctum` (+ فحص ملكية جوا الـ controller) | أي مستخدم مسجل يقدر يضيف منتج يبيعه، بس التعديل/الحذف مقصور على صاحب المنتج أو الأدمن |
| `GET /categories` | بدون حماية | تصفح عام |
| `POST/PUT/DELETE /categories` | `auth:sanctum` + `admin` | إدارة التصنيفات للأدمن فقط |
| `/cart/*` | `auth:sanctum` | السلة شخصية لكل مستخدم |
| `POST /orders`, `GET /orders`, `GET /orders/{id}` | `auth:sanctum` | لازم تكون مسجل دخول عشان تشتري أو تشوف طلباتك |
| `PUT /orders/{id}/status`, `GET /stats` | `auth:sanctum` + `admin` | عمليات إدارية بحتة |

### مثال من الكود الفعلي:
```php
// Products (public browse; any logged-in user can list their own products; owner or admin can edit/delete)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-products', [ProductController::class, 'mine']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});
```

**ملاحظة مهمة عن Route Model Binding**: لما تكتب `Route::put('/products/{product}', ...)` وبالـ controller method بتحط `Product $product`، Laravel **تلقائيًا** بيدور على المنتج بالـ ID من الـ URL ويحقنه جاهز (أو يرجع 404 لو مش موجود) — هذا اسمه **Implicit Route Model Binding**، وموجود بكل الـ controllers (`Product $product`, `Order $order`, `CartItem $cartItem`, `Category $category`).

---

## 4. طبقة الـ Middleware

في نوعين من الحماية مستخدمين مع بعض:

### أ. `auth:sanctum` — المصادقة (Authentication: "مين إنت؟")
Middleware جاهز من Laravel Sanctum. بيتحقق إنه في `Authorization: Bearer <token>` صحيح بالـ request، ولو صحيح بيحط المستخدم بـ `$request->user()`. غير هيك بيرجع `401 Unauthenticated`.

### ب. `admin` — التفويض (Authorization: "مسموحلك تعمل هاد؟")
Middleware مخصص كتبناه إحنا: [`app/Http/Middleware/EnsureUserIsAdmin.php`](backend/app/Http/Middleware/EnsureUserIsAdmin.php)

```php
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
```

سجّلناه كـ **alias** بملف [`bootstrap/app.php`](backend/bootstrap/app.php) عشان نقدر نستخدمه بالاسم `admin` بالـ routes:
```php
$middleware->alias([
    'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
]);
```

### ج. فحص الملكية (Ownership) — مو middleware، منطق داخل الـ Controller
لما الموضوع مش "أدمن ولا لأ" بس "هاد المورد تبعك ولا لأ" (متل تعديل منتج، أو سلة/طلب مستخدم تاني)، الفحص بيصير **جوا الـ controller method نفسه**، مش middleware منفصل، لأنه بيحتاج يقارن `user_id` الموجود بالـ **جسم المورد نفسه** (اللي جاي من route model binding) مو بس من التوكن. مثال من [`ProductController::update()`](backend/app/Http/Controllers/ProductController.php):

```php
public function update(Request $request, Product $product)
{
    if (! $request->user()->isAdmin() && $product->user_id !== $request->user()->id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }
    // ...
}
```

نفس النمط مستخدم بـ `CartController` (المستخدم بيشوف/يعدل عناصر سلته بس) و`OrderController` (الزبون بيشوف طلباته بس، الأدمن بيشوف الكل).

---

## 5. طبقة الـ Controllers بالتفصيل

### `AuthController` — التسجيل والدخول
| Method | المسار | الوظيفة |
|---|---|---|
| `register()` | `POST /register` | يتحقق من البيانات، يعمل `User::create()` (الباسورد بينشفر تلقائيًا عن طريق الـ cast `'password' => 'hashed'` بموديل `User`)، وبعدين ينشئ Sanctum token وبيرجعه مع بيانات المستخدم |
| `login()` | `POST /login` | يدور على المستخدم بالإيميل، يتحقق من الباسورد بـ `Hash::check()`، ينشئ token جديد |
| `logout()` | `POST /logout` | يحذف التوكن الحالي بس (`$request->user()->currentAccessToken()->delete()`) — مو كل التوكنات، عشان لو المستخدم مسجل دخول من أكتر من جهاز ما يطلعوا كلهم |

### `ProductController` — المنتجات (تصفح عام + بيع بالمشاركة)
| Method | المسار | الوظيفة |
|---|---|---|
| `index()` | `GET /products` | يرجع المنتجات النشطة (`is_active = true`) بس، مع فلترة اختيارية بـ `?category=` و`?search=` |
| `mine()` | `GET /my-products` | يرجع **كل** منتجات المستخدم الحالي (نشطة وغير نشطة)، لصفحة "My Listings" |
| `show()` | `GET /products/{id}` | تفاصيل منتج واحد + معلومات البائع (`seller:id,name`) |
| `store()` | `POST /products` | أي مستخدم مسجل دخول يقدر ينشئ منتج؛ `user_id` بينحط تلقائيًا من التوكن (`$request->user()->id`) — **مش من الفورم** — عشان حدا ما يقدر يزوّر إنه صاحب منتج غيره |
| `update()` / `destroy()` | `PUT`/`DELETE /products/{id}` | فحص ملكية (صاحب المنتج أو أدمن) قبل أي تعديل |

### `CategoryController` — التصنيفات (إدارة أدمن فقط)
بسيط: CRUD كامل، بس فيه قيد منطقي بـ `destroy()`:
```php
if ($category->products()->exists()) {
    return response()->json(['message' => 'Cannot delete a category that still has products.'], 422);
}
```
هذا يمنع حذف تصنيف لسا فيه منتجات مرتبطة فيه (يحمي من بيانات يتيمة orphaned data).

### `CartController` — سلة المشتريات
كل عملية (`update`, `destroy`) بتتحقق إنه `$cartItem->user_id === $request->user()->id` قبل ما تنفذ — بترجع `404` (مو `403`) لو مش تبعه، عشان ما يعرف أصلاً إنه في عنصر سلة بهيدا الـ ID (مبدأ أمني: لا تكشف وجود مورد المستخدم مالوش صلاحية عليه).

`store()` فيها منطق بسيط للـ **upsert**: لو المنتج موجود أصلاً بالسلة بيزيد الكمية (`increment`)، غير هيك بينشئ صف جديد.

### `OrderController` — الطلبات + الدفع الوهمي
أكتر controller فيه منطق، لأنه بيربط كذا شي مع بعض:

1. **التحقق من بيانات الكرت** (`card_name`, `card_number`, `card_expiry`, `card_cvv`) عن طريق `CardValidator` (شرح بالقسم 7).
2. **محاكاة قرار الدفع**: رقم `4000000000000002` (رقم اختبار معروف من Stripe) بيترفض عمدًا، أي رقم صحيح تاني بينوافق عليه.
3. **DB Transaction**: إنشاء الطلب + عناصره + إنقاص المخزون + تفريغ السلة، كل هذا **جوا `DB::transaction()`** — يعني لو صار خطأ بأي خطوة بينعمل rollback تلقائي لكل شي.

```php
$order = DB::transaction(function () use ($request, $validated, $cartItems, $cardNumber) {
    $order = Order::create([...]);

    foreach ($cartItems as $item) {
        OrderItem::create([...]);
        $item->product->decrement('stock', $item->quantity);
    }

    $request->user()->cartItems()->delete();

    return $order;
});
```

`index()` بيفرّق بين الزبون العادي (بيشوف طلباته بس) والأدمن (بيشوف الكل) بنفس الطريقة:
```php
if (! $request->user()->isAdmin()) {
    $query->where('user_id', $request->user()->id);
}
```

### `StatsController` — إحصائيات لوحة تحكم الأدمن
Controller واحد بس بميثود وحدة (`index()`)، بيستخدم استعلامات SQL مجمّعة (aggregate) مباشرة عن طريق Eloquent:
- `selectRaw('DATE(created_at) as day, SUM(total_amount) as revenue')` → الإيرادات آخر 14 يوم مجمّعة حسب اليوم.
- `groupBy('status')` → عدد الطلبات حسب الحالة.
- أكثر 5 منتجات مبيعًا حسب الكمية المباعة.

هاي الاستعلامات بتصير مرة وحدة وقت تحميل صفحة الداشبورد، مش بكل request عادي — عشان هيك منطقيًا موجودة بـ controller منفصل مش جوا `ProductController` أو `OrderController`.

### `AccountController` — بيانات الحساب والكرت المحفوظ
| Method | المسار | الوظيفة |
|---|---|---|
| `show()` | `GET /account` | يرجع بيانات المستخدم الحالي كاملة (بما فيها حالة الكرت المحفوظ) |
| `updateCard()` | `PUT /account/card` | يتحقق من الكرت (نفس منطق `OrderController`)، ويخزن **آخر 4 أرقام بس** + النوع (Visa/Mastercard/...) + تاريخ الانتهاء + اسم صاحب الكرت — **أبدًا مش الرقم الكامل ولا الـ CVV** |
| `destroyCard()` | `DELETE /account/card` | يصفّر حقول الكرت الأربعة |

---

## 6. طبقة الـ Models والعلاقات (ERD)

```
User 1───* CartItem *───1 Product *───1 Category
 │                                        │
 │ (seller)                               │
 └────────────────────────────────────────┘
 │
 └─1───* Order 1───* OrderItem *───1 Product
```

| Model | العلاقات | ملاحظات |
|---|---|---|
| `User` | `cartItems()`, `orders()` — HasMany | فيه `isAdmin()` و`hasSavedCard()` كدوال مساعدة (مش أعمدة، منطق محسوب) |
| `Product` | `category()` BelongsTo، `seller()` BelongsTo (لـ `User` عن طريق `user_id`)، `cartItems()`/`orderItems()` HasMany | `user_id` **nullable** — يعني في منتجات "رسمية" (مالها بائع، `null`) ومنتجات مستخدمين |
| `Category` | `products()` HasMany | |
| `CartItem` | `user()`, `product()` BelongsTo | جدول وسيط بين User وProduct |
| `Order` | `user()` BelongsTo، `items()` HasMany (OrderItem) | فيه `status` (حالة الشحن) و`payment_status` (حالة الدفع) — **حقلين منفصلين لأنهم مفهومين مختلفين تمامًا** |
| `OrderItem` | `order()`, `product()` BelongsTo | بيحتفظ بالسعر وقت الشراء (`price`) مش سعر المنتج الحالي — عشان لو تغيّر سعر المنتج بعدين، الطلبات القديمة تضل تعرض السعر الأصلي |

كل Model بيستخدم PHP Attribute `#[Fillable([...])]` (ميزة من Laravel 13 الجديدة) بدل `protected $fillable = [...]` التقليدية — نفس الوظيفة (حماية من Mass Assignment)، بس بصيغة attributes.

---

## 7. `CardValidator` — منطق مشترك (Support Class)

بما إنه فحص صحة الكرت (خوارزمية Luhn + فحص تاريخ الانتهاء) لازم يصير بمكانين (`OrderController` وقت الشراء، و`AccountController` وقت حفظ كرت بالحساب)، انسحب لكلاس واحد بدل ما يتكرر:

```php
namespace App\Support;

class CardValidator
{
    public static function digitsOnly(string $number): string { ... }
    public static function passesLuhnCheck(string $number): bool { ... }  // خوارزمية Luhn الحقيقية
    public static function isExpired(string $expiry): bool { ... }
    public static function detectBrand(string $number): string { ... }    // Visa/Mastercard/Amex
}
```

مو Service Provider ولا Interface ولا Dependency Injection معقدة — بس كلاس بميثودز `static` بسيطة، لأنه ما في state (حالة) لازم تتخزن، كل ميثود مستقلة بذاتها (pure function). هذا أبسط حل ممكن يحل مشكلة التكرار بدون تعقيد زيادة.

**مهم:** رقم الكرت الكامل **ما بينتخزن بقاعدة البيانات أبدًا** — بس بيتفحص بالذاكرة (`digitsOnly`, `passesLuhnCheck`) وبعدين بيتاخد منه آخر 4 أرقام بس (`substr($cardNumber, -4)`) قبل ما يترمى الباقي. هذا تطبيق عملي لمبدأ أمني حقيقي (PCI-DSS): لا تخزّن بيانات حساسة مالك داعي إلها.

---

## 8. قاعدة البيانات — تسلسل الـ Migrations

الـ migrations مرتبة زمنيًا (بالاسم فيه timestamp)، وهذا التسلسل نفسه بيوثق تطور المشروع:

| التاريخ | Migration | الوصف |
|---|---|---|
| (Laravel الافتراضي) | `create_users_table` | جدول المستخدمين الأساسي |
| `2026-07-29` | `add_role_to_users_table` | إضافة `role` (customer/admin) |
| `2026-07-29` | `create_categories_table` | |
| `2026-07-29` | `create_products_table` | |
| `2026-07-29` | `create_cart_items_table` | |
| `2026-07-29` | `create_orders_table` | |
| `2026-07-29` | `create_order_items_table` | |
| `2026-07-29` | `create_personal_access_tokens_table` | جدول Sanctum لتخزين التوكنات |
| `2026-07-30` | `add_image_url_to_products_table` | |
| `2026-08-01` | `add_payment_fields_to_orders_table` | `payment_status`, `payment_method`, `card_last_four` — لميزة الدفع الوهمي |
| `2026-08-01` | `add_user_id_to_products_table` | `user_id` نلّبل — لميزة "بيع منتجك" |
| `2026-08-01` | `add_card_fields_to_users_table` | كرت محفوظ بالحساب |

**لماذا migrations منفصلة بدل ما نعدل الجدول الأصلي مباشرة؟** لأنه Laravel بيتتبع كل migration اتنفذت أو لأ (بجدول `migrations`)، فلو رجعت تشغل المشروع من الصفر (`php artisan migrate`)، كل التغييرات بتنطبق **بالترتيب الصحيح** تلقائيًا. تعديل migration قديمة اتنفذت أصلاً معناته لازم تعمل `migrate:fresh` (تصفير كامل) — تعديلات جديدة كملفات منفصلة أسلم بكثير، خصوصًا لو في بيانات حقيقية بقاعدة البيانات.

---

## 9. معالجة الأخطاء (Error Handling)

بملف [`bootstrap/app.php`](backend/bootstrap/app.php):
```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*'),
    );
})
```
هذا بيضمن إنه أي خطأ (حتى لو استثناء (Exception) غير متوقع) بيترجع كـ **JSON** مش صفحة HTML — لأنه الفرونت اند (`api.js`) عم يتوقع دايمًا JSON لما يعمل `fetch()`.

الـ validation errors بترجع تلقائيًا بصيغة موحدة من Laravel (`422` + `{"errors": {...}}`)، والفرونت اند بيقرأها بدالة `apiRequest()`:
```js
const message = (data && (data.message || Object.values(data.errors || {})[0]?.[0])) || 'Request failed';
```

---

## 10. طبقة الاختبارات (Testing)

كل الاختبارات بـ [`tests/Feature/`](backend/tests/Feature/) هي **Feature Tests** (مش Unit Tests) — يعني بتضرب الـ API endpoints الحقيقية (`postJson`, `getJson`, ...) وتتحقق من الرد، مش تختبر دوال معزولة. هذا قرار مقصود: بمشروع API صغير، اختبار الـ endpoint كامل (Route → Middleware → Controller → DB) أفيد بكثير من اختبار كل ميثود لحالها، لأنه بيتأكد إنه **كل الطبقات شغالة سوا صح**.

كل test class فيه `use RefreshDatabase;` — يعني قاعدة البيانات بتترجع فاضية قبل كل اختبار (migrations تتنفذ من جديد بقاعدة بيانات اختبار منفصلة)، فمافي تداخل بين الاختبارات.

| ملف الاختبار | شو بيغطي |
|---|---|
| `AuthTest` | تسجيل، دخول، خروج، تكرار الإيميل |
| `ProductTest` | تصفح، بحث، ملكية (إنشاء/تعديل/حذف)، صلاحيات الأدمن مقابل الزبون |
| `CategoryTest` | CRUD + منع حذف تصنيف فيه منتجات |
| `CartTest` | إضافة/تعديل/حذف، حماية من التلاعب بسلة مستخدم تاني |
| `OrderTest` | الشراء الطبيعي، سلة فاضية، كرت غير صالح/منتهي/مرفوض، صلاحيات عرض الطلبات |
| `AccountTest` | عرض الحساب، حفظ/حذف الكرت، رفض الضيوف |

---

## 11. ملخص القرارات المعمارية الأساسية

| القرار | البديل المرفوض | ليش هذا القرار |
|---|---|---|
| Fat Controllers (بدون Service layer) | Repository/Service pattern | المشروع صغير-متوسط، تجريد زيادة كان رح يعقّد بدون فايدة حقيقية |
| فحص الملكية جوا الـ Controller | Policy classes (`Gate::authorize`) | بمشروع بهاد الحجم، `if` واحد أوضح وأسرع فهمًا من تسجيل Policy كاملة لكل Model |
| `CardValidator` كـ static helper class | تكرار الكود بمكانين، أو Service Provider معقد | التكرار الفعلي (مكانين) بيبرر الاستخراج، بس المشكلة بسيطة فمش محتاجة أكتر من static methods |
| Sanctum بدل Passport | Laravel Passport (OAuth2) | تطبيق واحد فقط (الفرونت اند حقنا) بيكلم الـ API، مش تطبيقات طرف ثالث |
| DB Transactions بالطلبات | تنفيذ الخطوات لحالها | ضمان الاتساق: لو صار خطأ بمنتصف العملية، ما تبقى بيانات ناقصة (طلب بدون عناصر، أو مخزون نقص بدون طلب) |
| آخر 4 أرقام بس من الكرت تتخزن | تخزين رقم الكرت الكامل | مبدأ أمني (PCI-DSS): لا تخزّن بيانات حساسة مالك داعي إلها، حتى بمشروع تعليمي |
| Feature Tests بدل Unit Tests | اختبار كل دالة لحالها | بمشروع API، التأكد إنه الطبقات شغالة سوا (routing + middleware + validation + DB) أهم من عزل كل دالة |
