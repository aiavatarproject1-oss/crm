# Inbound Message Normalization

پلتفرم‌ها ساختارهای webhook متفاوتی دارند. Adapter هر پلتفرم payload بومی را به `IncomingPlatformMessageData` تبدیل می‌کند تا لایه‌های آینده تنها با یک قرارداد canonical کار کنند. Adapter هیچ دسترسی به دیتابیس، Repository یا Domain ندارد.

## جریان

`POST /api/v1/inbound/messages → Controller → PlatformResolver → Adapter → IncomingPlatformMessageData`

```json
{"platform":"telegram","payload":{"update_id":42,"message":{"message_id":101,"from":{"id":202,"username":"amir"},"text":"Hello"}}}
```

کل payload بومی بدون تغییر در `raw_payload` حفظ می‌شود تا در آینده برای audit، رفع اشکال و بازپردازش قابل استفاده باشد. `adapter_version` نسخه mapping را ثبت می‌کند. Botهای آینده فقط envelope بالا را ارسال می‌کنند؛ افزودن پلتفرم جدید محدود به Adapter و Resolver خواهد بود. در این مرحله خروجی فقط JSON است و هیچ داده‌ای persist نمی‌شود.
