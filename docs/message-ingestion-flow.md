# Message Ingestion Flow

## چرخه پیام

`Native payload → Platform Adapter → IncomingPlatformMessageData → ReceiveIncomingMessageCommand → Handler → Domain Aggregates → Repository Adapters → MessageCreated`

Endpoint `POST /api/v1/inbound/messages` پس از normalization، مالکیت `tenant_id` و `influencer_id` را به DTO immutable اضافه می‌کند. Controller فقط Command را ساخته و `MessageIngestionResult` را بازمی‌گرداند؛ orchestration در Handler قرار دارد.

## User resolution

User همیشه با `tenant_id + influencer_id + platform + external_user_id` جست‌وجو می‌شود و در صورت نبودن ساخته می‌شود. بنابراین شناسه یکسان میان پلتفرم‌ها، tenantها یا influencerهای متفاوت مشترک نیست.

## Conversation resolution

Conversation فعال متعلق به `tenant_id + influencer_id + user_id` استفاده می‌شود. در نبود آن Aggregate جدید ساخته می‌شود. Conversationهای بسته برای تاریخچه آینده قابل نگهداری هستند.

## Idempotency

پیش از ساخت، Message با `tenant_id + influencer_id + platform + external_message_id` بررسی می‌شود. پیام تکراری با `created=false` و `duplicate=true` برمی‌گردد. همین کلید در MongoDB unique index و `updateOrCreate` استفاده می‌شود.

## نقطه اتصال AI آینده

پس از persistence موفق، eventهای Message آزاد و از طریق قرارداد مستقل از framework منتشر می‌شوند. Adapter فعلی آن‌ها را synchronous به dispatcher لاراول می‌دهد. AI، Rule، RAG و Memory در آینده باید مصرف‌کننده `MessageCreated` باشند؛ در این مرحله هیچ مصرف‌کننده یا Queue اضافه نشده است.
