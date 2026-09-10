# AI Pipeline

## LLM abstraction

Application فقط `LlmGatewayInterface` را می‌شناسد. `LlmRequest` شامل conversation id، پیام‌های context، system prompt و metadata است و `LlmResponse` محتوای خروجی، مدل، token usage، latency و metadata را برمی‌گرداند. بنابراین تعویض Ollama با API تولیدی بدون تغییر use case ممکن است.

## چرخه درخواست

`Incoming Message → Rule Engine → ALLOW_AI → GenerateResponseHandler → LLM Gateway → assistant Message`

`ADMIN_REVIEW`، `BLOCK` و `IGNORE` پیش از فراخوانی Gateway متوقف می‌شوند. در حالت مجاز، پاسخ به‌عنوان Message مستقل با `sender_type=ai`، `direction=outgoing` و `content_type=text` ذخیره و event آن منتشر می‌شود. خطای Gateway به `ApplicationException` تبدیل می‌شود و Message ناقص ذخیره نمی‌گردد.

## Ollama محلی

`OllamaLlmGateway` از endpoint سازگار با OpenAI در `/v1/chat/completions` استفاده می‌کند:

```env
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.2
```

جزئیات HTTP فقط در Infrastructure قرار دارند. برای production کافی است Adapter دیگری برای `LlmGatewayInterface` ثبت شود.

## محدوده فعلی

Context پیش‌فرض فقط پیام جاری است، اما DTO آرایه کامل messages را می‌پذیرد. تاریخچه، RAG، Memory، Quality و Queue در این مرحله پیاده‌سازی نشده‌اند. تمام Ruleها و Messageها همچنان در محدوده tenant و influencer پردازش می‌شوند.
