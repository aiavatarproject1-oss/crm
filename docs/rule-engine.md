# Rule Engine

## مسئولیت

Rule Engine یک guard قطعی پیش از AI است. متن Message را با Ruleهای فعال همان `tenant_id + influencer_id` ارزیابی می‌کند و فقط نتیجه می‌دهد؛ هیچ workflow، AI، پلتفرم خارجی، MongoDB یا Queue را فراخوانی نمی‌کند.

## مدل تصمیم

- `ALLOW_AI`: هیچ مانعی برای مرحله AI وجود ندارد.
- `ADMIN_REVIEW`: پیام باید در آینده برای بررسی انسانی هدایت شود.
- `BLOCK`: پردازش بعدی مجاز نیست.
- `IGNORE`: پیام بدون پاسخ کنار گذاشته می‌شود.

`RuleEvaluationResult` علاوه بر تصمیم، دلیل match و metadata قاعده برنده شامل id، name، type، priority، version و metadata را برمی‌گرداند. در نبود match، مشخصات Rule برابر null است.

## Strategy و اولویت

Handler منطق keyword یا regex ندارد. `RuleMatcherRegistry` براساس `RuleType` یکی از Strategyهای `KeywordRuleMatcher` یا `RegexRuleMatcher` را انتخاب می‌کند. Ruleهای معتبر به ترتیب priority نزولی ارزیابی می‌شوند و اولین match برنده است. افزودن classifierهای semantic یا LLM در آینده با پیاده‌سازی Strategy جدید ممکن است؛ چنین classifierی در این مرحله وجود ندارد.

## Tenant isolation و نسخه‌بندی

Repository contract فقط Ruleهای scope جاری را درخواست می‌کند و Handler نیز scope و enabled بودن را دوباره کنترل می‌کند. فیلد `version` امکان نگهداری تاریخچه، draft/publish و rollback آینده را فراهم می‌کند.

## رویداد و پنل مدیریت آینده

هنگام match، `RuleTriggered` با rule id، message id، decision و reason منتشر می‌شود. پنل مدیریت آینده می‌تواند Ruleها، نسخه‌ها، priority و metadata را مدیریت کند؛ CRUD و persistence بخشی از Step 7 نیستند.
