# Influencer Persona

## Persona و Memory

Persona هویت و تنظیمات پایدار Influencer است: زبان، tone، style، description و system rules. Memory اطلاعات بلندمدت مربوط به یک User در تعامل با Influencer است. Persona هرگز از Memory کاربر استخراج یا با آن ذخیره نمی‌شود.

## Scope

هر Persona دقیقاً به `tenant_id + influencer_id` تعلق دارد. Repository و unique index MongoDB از همین scope استفاده می‌کنند؛ بنابراین Influencerهای یک tenant و tenantهای مختلف تنظیمات مشترک ندارند.

## Context flow

`BuildPersonaContextHandler` Entity را به `PersonaContext` immutable تبدیل می‌کند. `BuildConversationContextHandler` این DTO را کنار recent messages و user memories قرار می‌دهد و AI pipeline آن را در `LlmRequest.influencer_context` ارسال می‌کند. اگر Persona تعریف نشده باشد، context خنثی با زبان `en`، tone خنثی و system rules خالی ساخته می‌شود.

## Prompt engine آینده

در آینده Prompt Template Engine می‌تواند PersonaContext، User Memory و Context مکالمه را به system prompt نهایی تبدیل کند. این مرحله فقط داده ساختاریافته را فراهم می‌کند و هیچ prompt generation، AI generation، RAG یا Admin UI ندارد.
