# تست MVP با Postman

## فایل‌های قابل Import

این دو فایل را در Postman وارد کنید:

- `AI-Influencer-MVP.postman_collection.json`
- `AI-Influencer-MVP.postman_environment.json`

بعد از Import، environment با نام `AI Influencer MVP - Local` را فعال کنید.

## پیش‌نیازها

MongoDB، Redis و Ollama باید فعال باشند. مدل‌های پیش‌فرض:

```powershell
ollama pull llama3.2
ollama pull nomic-embed-text
ollama pull qwen2.5:7b
```

مقادیر `.env` باید با Postman Environment هماهنگ باشند:

```dotenv
APP_URL=http://127.0.0.1:8000
MONGODB_DATABASE=ai_influencer
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.2
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
OLLAMA_QUALITY_MODEL=qwen2.5:7b
```

fixtureهای Persona و Rule را به‌شکل idempotent وارد کنید:

```powershell
mongosh "mongodb://127.0.0.1:27017" docs/postman/setup-mongodb.js
```

اگر نام دیتابیس یا شناسه‌های Tenant/Influencer را تغییر دادید، فایل setup و Postman Environment را نیز یکسان تغییر دهید.

سپس Laravel را اجرا کنید:

```powershell
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
```

## ترتیب اجرا

1. پوشه `00 - Ollama Prerequisites` را اجرا کنید.
2. درخواست `New Message - Full Pipeline` را اجرا کنید.
3. بلافاصله `Same Message - Idempotency` را اجرا کنید.
4. adapterهای پلتفرم را جداگانه اجرا کنید.
5. سناریوهای `ADMIN_REVIEW` و `BLOCK` را اجرا کنید.
6. در پایان validationها را اجرا کنید.

اجرای کل Collection چند بار LLM و Quality Model را فراخوانی می‌کند و ممکن است روی سیستم محلی زمان‌بر باشد.

## چرا پاسخ API متن AI را نشان نمی‌دهد؟

قرارداد فعلی endpoint مربوط به ingestion است و فقط این داده را برمی‌گرداند:

```json
{
  "success": true,
  "data": {
    "message_id": "...",
    "conversation_id": "...",
    "user_id": "...",
    "created": true,
    "duplicate": false
  }
}
```

با وجود این، pipeline به‌صورت synchronous تا Prompt، LLM و Quality Checker اجرا شده است. اگر Quality تأیید کند، پیام outgoing در `messages` ذخیره می‌شود. اگر Quality رد کند، پیام AI ذخیره نمی‌شود و رکوردی در `admin_tasks` ساخته می‌شود.

## بررسی نتیجه در MongoDB

پس از اجرای درخواست، در `mongosh`:

```javascript
use ai_influencer

db.users.find({tenant_id: 'tenant-demo', influencer_id: 'influencer-sofia'})

db.conversations.find({tenant_id: 'tenant-demo', influencer_id: 'influencer-sofia'})
  .sort({last_activity_at: -1})

db.messages.find({tenant_id: 'tenant-demo', influencer_id: 'influencer-sofia'})
  .sort({created_at: -1})

db.messages.find({
  tenant_id: 'tenant-demo',
  influencer_id: 'influencer-sofia',
  conversation_id: '<last_conversation_id from Postman>',
  sender_type: 'ai',
  direction: 'outgoing'
})

db.admin_tasks.find({tenant_id: 'tenant-demo', influencer_id: 'influencer-sofia'})
  .sort({created_at: -1})
```

مقادیر `last_message_id`، `last_conversation_id` و `last_user_id` بعد از درخواست موفق به‌صورت خودکار در Postman Environment ذخیره می‌شوند.

## رفتار مورد انتظار سناریوها

| سناریو | HTTP | نتیجه |
|---|---:|---|
| پیام جدید | `201` | پیام incoming ساخته می‌شود؛ سپس AI pipeline اجرا می‌شود |
| پیام تکراری | `200` | `duplicate=true` و AI دوباره اجرا نمی‌شود |
| `ADMIN_REVIEW` Rule | `201` | LLM اجرا نمی‌شود و فقط پیام incoming باقی می‌ماند |
| `BLOCK` Rule | `201` | LLM و Quality Checker اجرا نمی‌شوند |
| Quality تأییدشده | `201` | پیام assistant با `sender_type=ai` ذخیره می‌شود |
| Quality ردشده | `201` | `admin_tasks` ساخته می‌شود و پیام assistant ذخیره نمی‌شود |
| پلتفرم ناشناخته | `422` | خطای validation برای `platform` |
| مالکیت ناقص | `422` | خطای validation برای Tenant و Influencer |

## محدودیت RAG در تست دستی

Retrieval در هر پیام مجاز اجرا می‌شود، اما تا زمانی که `knowledge_chunks` و `knowledge_vectors` از قبل داده نداشته باشند، `knowledge_context` خالی خواهد بود. طبق feature freeze، API آپلود یا ingestion خودکار Knowledge در پروژه وجود ندارد. این Collection داده ساختگی Vector تولید نمی‌کند تا ابعاد embedding یا مدل اشتباه وارد دیتابیس نشود.
