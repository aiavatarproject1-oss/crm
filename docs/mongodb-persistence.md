# MongoDB Persistence Adapter

## جایگاه MongoDB

MongoDB یک جزئیات فنی و متعلق به لایه Infrastructure است. Domain Entityها و قراردادهای Application هیچ وابستگی‌ای به MongoDB یا Laravel ندارند. مسیر تبدیل داده به شکل زیر است:

`Domain Entity ⇄ Mapper ⇄ MongoDB Document ⇄ Repository`

## Repository و Document

قرارداد Repository در Application تعریف شده و پیاده‌سازی MongoDB آن در Infrastructure قرار دارد. `UserDocument`، `ConversationDocument` و `MessageDocument` فقط نمایش ذخیره‌سازی collectionها هستند و نباید به‌عنوان Entity دامنه استفاده شوند.

Mapperها تنها primitiveها، Identifierها و تاریخ‌ها را تبدیل می‌کنند و هیچ قاعده تجاری ندارند. هنگام بازسازی Conversation و Message، رویداد اولیه‌ای که factory دامنه می‌سازد تخلیه می‌شود تا hydration باعث انتشار رویداد جدید نشود.

## استراتژی ایندکس

- users: یکتایی هویت پلتفرمی در محدوده `tenant_id + influencer_id`.
- conversations: بازیابی فعالیت‌ها با `tenant_id + influencer_id + user_id + last_activity_at`.
- messages: یکتایی پیام خارجی با `tenant_id + influencer_id + platform + external_message_id` و خواندن timeline با `conversation_id + created_at` در همان محدوده tenant/influencer.

نام ایندکس‌ها ثابت است و migration با API idempotent درایور MongoDB اجرا می‌شود. ایندکس قدیمی User پیش از ساخت نسخه tenant-aware با `dropIndexIfExists` حذف می‌شود و rollback نیز فقط نام‌های متعلق به همین migration را حذف می‌کند.

## Idempotency پیام

`MongoMessageRepository` از همان کلید یکتای چهارقسمتی در `updateOrCreate` استفاده می‌کند. بنابراین دریافت مجدد یک پیام خارجی، سند منطقی جدیدی ایجاد نمی‌کند؛ unique index نیز محافظت نهایی دیتابیس در برابر race condition است.
