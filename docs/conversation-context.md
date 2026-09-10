# Conversation Context & Memory

## Short-term history

`BuildConversationContextHandler` آخرین Messageهای همان `tenant_id + influencer_id + conversation_id` را با limit محدود دریافت و به قالب role/content قابل استفاده برای LLM تبدیل می‌کند. Query در MongoDB جدیدترین رکوردها را محدود کرده و سپس برای prompt به ترتیب زمانی برمی‌گرداند.

## Long-term memory

Memory یک Aggregate مستقل و متعلق به tenant، influencer و user است. نوع‌های فعلی آن `PROFILE`، `PREFERENCE`، `FACT` و `RELATIONSHIP` هستند. Repository مهم‌ترین Memoryها را با ترتیب `importance_score` نزولی و limit مشخص بازمی‌گرداند. ساخت یا استخراج خودکار Memory بخشی از این مرحله نیست.

## LLM context

`ConversationContext` immutable است و سه بخش دارد: `recent_messages`، `user_memories` و `influencer_persona`. AI pipeline این context را پیش از Gateway می‌سازد و در `LlmRequest` قرار می‌دهد. Message به‌عنوان Aggregate مستقل باقی مانده و UserId صریحاً در command pipeline حمل می‌شود.

## Performance و isolation

تمام lookupها tenant/influencer scoped هستند. ایندکس مرکب Memory روی `tenant_id + influencer_id + user_id + importance_score` و ایندکس موجود Message روی conversation و created_at، queryهای محدودشده را پشتیبانی می‌کنند. limitها از رشد بی‌حد prompt و مصرف حافظه جلوگیری می‌کنند.

## RAG آینده

RAG می‌تواند در آینده یک provider دیگر برای Context Builder باشد و اسناد مرتبط را به context اضافه کند. هیچ embedding، Vector DB، retrieval یا summarization در Step 9 وجود ندارد.
