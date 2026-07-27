# HWNix AI Platform — Architecture Constitution (APC-001 v1.2)

> الوثيقة الأم · الإصدار 1.2 · تاريخ التحديث: 2026-07-26
> **الوثيقة المعتمدة النهائية — جاهزة للانتقال لتصميم قاعدة البيانات.**

---

## 📜 سجل التغييرات

| الإصدار | التاريخ | الملاحظة |
|---------|---------|---------|
| v1.0 | 2026-07-26 | الإصدار التأسيسي |
| v1.1 | 2026-07-26 | دمج 13 تحسيناً معمارياً + إضافة المبدأ المحوري P-00 |
| v1.2 | 2026-07-26 | إضافة P-16 Integration First + قسم Package Architecture |

---

## 🌐 القسم الأول: الرؤية (Vision)

### لماذا أُنشئت المنصة؟

HWNix AI Platform هي **طبقة التنسيق (Orchestration Layer)** المستقلة في نظام HWNix ERP. تُقدّم قدرات ذكاء اصطناعي لأي Module في النظام بدون أن يعرف هذا Module شيئاً عن مزودين أو نماذج أو تكاليف.

### الاستقلالية كـ Package
المنصة مكتوبة كـ Laravel Package مستقل بلا Dependencies على أي Module من HWNix.
نقلها لأي مشروع Laravel آخر: `composer require hwnix/ai-platform`.

---

## 🏛️ القسم الثاني: المبادئ المعمارية (Architectural Principles)

> المبادئ P-01 إلى P-15 لا تُكسر تحت أي ظرف.

### 🔴 المبدأ المحوري الجديد (P-00)

> **"AI Platform is an Orchestration Platform, not an LLM Wrapper."**

دور المنصة هو **تنسيق (Orchestrate)** دورة حياة الطلب بالكامل:
السياسات → التوجيه → التنفيذ → الأدوات → الذاكرة → التسجيل → التكلفة.

**ليس** مجرد تغليف (Wrap) لاستدعاءات SDK.

هذا المبدأ يمنع تحوّل المشروع بمرور الوقت إلى مجموعة استدعاءات مباشرة، ويحافظ على الاستقلالية والتوسعية.

---

### المبادئ الأساسية (P-01 إلى P-16)

| # | المبدأ | الشرح |
|---|--------|--------|
| P-01 | **Capability First** | النظام يطلب *قدرة*، لا *مزوداً بعينه* |
| P-02 | **Provider Agnostic** | لا اسم مزود مُضمَّن في Business Logic |
| P-03 | **Model Agnostic** | النماذج تُختار بالـ Router بناءً على Profiles |
| P-04 | **Package Ready** | المنصة Package مستقل بلا Dependencies على HWNix |
| P-05 | **Event Driven** | كل عملية مهمة تُولّد Event — لا Side Effects مباشرة |
| P-06 | **API First** | Public API واحدة: `/api/v1/ai/...` |
| P-07 | **Tool First** | الأدوات مستقلة — تُستخدم من Agent أو Workflow أو API |
| P-08 | **Dual Execution** | المنصة تدعم Agent-based والـ Direct Capability معاً |
| P-09 | **Database Driven** | Agents, Prompts, Policies, Models, Workflows كلها من DB |
| P-10 | **Zero Hardcoded Prompts** | لا نص Prompt في كود PHP — كلها في قاعدة البيانات |
| P-11 | **Zero Business Logic in Drivers** | Drivers تتحدث مع APIs فقط |
| P-12 | **Fail Safe** | فشل مزود → Failover تلقائي → رسالة رفض واضحة |
| P-13 | **Graceful Degradation** | المنصة تعمل جزئياً عند انهيار مزود |
| P-14 | **Zero Token Waste** | Policy Engine يحجب قبل استدعاء أي نموذج |
| P-15 | **Multi-Tenant Ready** | كل بيانات المنصة مقيدة بـ `company_id` |
| P-16 | **Integration First** | الحزمة مستقلة كاملاً — قابلة للنقل لأي مشروع Laravel بلا تعديل |

---

## 🗂️ القسم الثالث: نموذج المجال الموسَّع (Extended Domain Model)

```
── AI Capability Layer ──────────────────────────────────
AiCapability          → قدرة مسجَّلة (text.generate, image.analyze, ...)
AiCapabilityRegistry  → سجل كامل لكل الـ Capabilities وما يدعمها

── AI Provider Layer ────────────────────────────────────
AiProvider            → أي خدمة AI (LLM, OCR, TTS, STT, Translation, ...)
AiProviderCapability  → أي Capabilities يدعمها هذا Provider
AiProviderAccount     → حساب/مفتاح API مُشفَّر مع Quota + Rotation
AiModel               → نموذج محدد
AiModelProfile        → Profile كامل للنموذج (Features, Limits, Pricing)
AiModelFeatureFlag    → تفعيل/تعطيل Feature لكل Model

── AI Routing Layer ─────────────────────────────────────
AiRouterLog           → قرارات الـ Router (لماذا اختار حساباً بعينه)

── AI Execution Layer ───────────────────────────────────
AiExecutionRequest    → طلب تنفيذ (Capability + Context + Params)
AiExecutionResult     → نتيجة التنفيذ (Output + Tokens + Latency)

── AI Agent Layer ───────────────────────────────────────
AiAgent               → وكيل له Instructions + Tools + Policy + Memory
AiAgentTool           → ربط Agent بـ Tool المسموح بها
AiConversation        → محادثة (Agent + User + Messages + Context)
AiMessage             → رسالة واحدة (role, content, tokens, cost)

── AI Workflow Layer ─────────────────────────────────────
AiWorkflow            → سلسلة خطوات تنفيذ متعددة
AiWorkflowStep        → خطوة داخل Workflow (Capability + Params + Next)
AiWorkflowRun         → تشغيل Workflow (State + Progress + Results)

── AI Prompt Layer ───────────────────────────────────────
AiPrompt              → Template (Text/JSON/Multi-part/Image/Audio)
AiPromptVersion       → إصدار من Prompt (تعديل لا يحذف)
AiPromptVariable      → متغيرات الـ Prompt

── AI Tool Layer ────────────────────────────────────────
AiTool                → أداة قابلة للتنفيذ (مستقلة عن Agent)
AiToolExecution       → سجل كل تنفيذ لـ Tool

── AI Policy Layer ──────────────────────────────────────
AiPolicy              → قاعدة سماح/رفض
AiPolicyEvaluation    → نتيجة تقييم Policy (للتدقيق)

── AI Memory Layer ──────────────────────────────────────
AiMemory              → ذاكرة (short/long/pinned/shared/user/agent)

── AI Knowledge Layer ───────────────────────────────────
AiKnowledgeBase       → قاعدة معرفة (RAG)
AiKnowledgeChunk      → قطعة نص مُعالَجة
AiEmbedding           → Vector لـ Chunk

── AI Cost Layer ────────────────────────────────────────
AiUsageLog            → سجل الاستخدام (tokens, cost, latency)
AiBudget              → ميزانية (Company/Agent/User/Monthly)
AiBudgetAlert         → تنبيه عند اقتراب نفاد الميزانية
AiCostBreakdown       → تفاصيل تكلفة كل طلب

── AI Audit Layer ───────────────────────────────────────
AiAuditLog            → سجل تدقيق (Append-Only)

── AI Plugin Layer ──────────────────────────────────────
AiPlugin              → إضافة من Module خارجي
```

---

## 🏗️ القسم الرابع: المعمارية الطبقية المُحدَّثة

```
┌──────────────────────────────────────────────────────────────┐
│                      PUBLIC API LAYER                        │
│              /api/v1/ai/{capability|chat|embed|...}          │
├──────────────────────────────────────────────────────────────┤
│                     CONSUMER LAYER                           │
│          Chat · Products · Reports · Documents · ...         │
├───────────────────────┬──────────────────────────────────────┤
│    AGENT ENGINE       │       DIRECT CAPABILITY              │
│  (Agent + Context)    │  (Prompt → Execution Engine)         │
├───────────────────────┴──────────────────────────────────────┤
│                   ORCHESTRATION CORE                         │
├──────────┬────────────┬──────────┬─────────┬────────────────┤
│  POLICY  │   PROMPT   │   TOOL   │ MEMORY  │   WORKFLOW     │
│  ENGINE  │   ENGINE   │  ENGINE  │ ENGINE  │    ENGINE      │
├──────────┴────────────┴──────────┴─────────┴────────────────┤
│                   EXECUTION ENGINE                           │
│      Retry · Streaming · ToolCalls · Timeout · Async        │
├──────────────────────────────────────────────────────────────┤
│                     AI ROUTER                                │
│   Capability Resolution · Account Selection · Failover       │
├──────────────────────────────────────────────────────────────┤
│              PROVIDER DRIVER LAYER                           │
│  Gemini · OpenAI · Anthropic · ElevenLabs · OCR · STT · ... │
├──────────────────────────────────────────────────────────────┤
│           COST ENGINE · MONITORING · AUDIT                   │
└──────────────────────────────────────────────────────────────┘
```

**قاعدة العزل المطلق**: لا توجد استدعاءات بين الطبقات غير المتجاورة.
Consumer لا يتحدث مع Driver أبداً — يمر عبر Orchestration Core والـ Execution Engine.

---

## 🔄 القسم الخامس: Dual Execution Model

### التمييز الأساسي

المنصة تدعم **طريقتين للتنفيذ** — كلتاهما مدعومتان بالكامل:

#### 1️⃣ Direct Capability (بدون Agent)
```
Module → Request(capability, prompt, params)
  → Policy Engine
  → Prompt Engine (Template + Variables)
  → Execution Engine
  → Router → Driver → Response
  → Cost Engine
  → Usage Log
```

**متى تُستخدم؟**
- توليد وصف منتج
- ترجمة نص
- تلخيص مستند
- استخراج بيانات من PDF

#### 2️⃣ Agent-based (محادثة + أدوات + ذاكرة)
```
User → Message
  → Load Agent Config (DB)
  → Policy Engine
  → Memory Engine (Context Building)
  → Knowledge Engine (RAG Injection)
  → Prompt Engine (Instructions + History)
  → Execution Engine
  → Router → Driver → Response
  → Tool Engine (if Tool Calls needed) [loop]
  → Memory Engine (Save)
  → Cost Engine
  → Usage Log
```

**متى تُستخدم؟**
- Chatbot المبيعات
- مساعد الفواتير
- وكيل تحليل التقارير

---

## 📋 القسم السادس: Capability Registry

### بنية السجل

```
text.generate           → توليد نص حر
text.summarize          → تلخيص
text.translate          → ترجمة
text.classify           → تصنيف
text.extract            → استخراج بيانات منظمة
text.rewrite            → إعادة صياغة
text.qa                 → سؤال وجواب

image.generate          → توليد صورة
image.analyze           → تحليل صورة
image.ocr               → استخراج نص من صورة/PDF
image.caption           → وصف صورة

vision.extract          → استخراج بيانات من مستند مرئي

speech.tts              → تحويل نص لصوت
speech.stt              → تحويل صوت لنص

embedding.create        → إنشاء Vector Embedding
embedding.search        → بحث في Embeddings

data.rerank             → إعادة ترتيب نتائج

code.generate           → توليد كود
code.review             → مراجعة كود
code.explain            → شرح كود
```

### كيف يُعلن Provider عمّا يدعمه؟
```php
class GeminiDriver implements ProviderDriverInterface
{
    public function capabilities(): array
    {
        return [
            Capability::TEXT_GENERATE,
            Capability::IMAGE_ANALYZE,
            Capability::SPEECH_STT,
            Capability::EMBEDDING_CREATE,
            Capability::VISION_EXTRACT,
        ];
    }
}
```

الـ Router يقرأ هذا السجل ديناميكياً — لا Hardcoding.

---

## 🔌 القسم السابع: Provider Definition (الموسَّع)

### Provider = أي خدمة AI، ليس LLM فقط

```
Provider Type: LLM         → OpenAI, Anthropic, Gemini, Mistral, xAI
Provider Type: Vision      → Google Vision, AWS Rekognition
Provider Type: OCR         → Tesseract, Azure OCR
Provider Type: TTS         → ElevenLabs, OpenAI TTS
Provider Type: STT         → OpenAI Whisper, ElevenLabs, Mistral
Provider Type: Image Gen   → DALL-E, Gemini, Stability AI, xAI
Provider Type: Embedding   → OpenAI, Gemini, Cohere, Jina
Provider Type: Reranking   → Cohere, Jina
Provider Type: Translation → DeepL, Google Translate
```

Driver Contract مُستقل لكل Type — لكن الـ Router والـ Policy Engine يتعاملان معها بنفس الطريقة.

### عقد Driver الموسَّع:
```php
interface ProviderDriverInterface
{
    public function capabilities(): array;
    public function supports(Capability $capability): bool;
    public function execute(ExecutionRequest $request): ExecutionResult;
    public function stream(ExecutionRequest $request): Generator;
    public function getName(): string;
    public function getType(): ProviderType;
}
```

---

## ⚙️ القسم الثامن: Execution Engine

### طبقة التنفيذ المستقلة

الـ Agent لا يستدعي الـ Router مباشرة. يمر دائماً عبر الـ **Execution Engine**.

```
ExecutionEngine::run(ExecutionRequest $request): ExecutionResult
```

### مسؤوليات الـ Execution Engine:

| المسؤولية | التفاصيل |
|-----------|---------|
| **Retry** | عند timeout أو 429 — إعادة المحاولة مع Backoff |
| **Streaming** | نقل الـ Response تدريجياً للـ Consumer |
| **Tool Call Loop** | تنفيذ Tool Calls ودمج النتائج في السياق |
| **Timeout Management** | إنهاء الطلب بعد الحد الزمني المحدد |
| **Cancellation** | إلغاء طلب جارٍ |
| **Progress Tracking** | إبلاغ Consumer بنسبة الإنجاز (للطلبات الطويلة) |
| **Async Queueing** | رمي الطلبات الثقيلة على Queue |
| **Context Building** | دمج Memory + Knowledge + History في رسالة واحدة |

---

## 🔧 القسم التاسع: Tool Engine (المستقل)

### الـ Tool مستقل عن الـ Agent تماماً

```
AiTool يُستخدَم من:
  ├── Agent (tool_calls)
  ├── Workflow (step)
  ├── Direct API (POST /api/v1/ai/tools/{name}/execute)
  └── Automation (Scheduled / Event-triggered)
```

### عقد الـ Tool:
```php
interface AiToolInterface
{
    public function name(): string;           // 'get_invoice'
    public function description(): string;   // لماذا يُستخدم؟
    public function schema(): JsonSchema;    // Input + Output Schema
    public function execute(array $params, AiContext $context): mixed;
    public function requiredPermission(): ?string; // Gate permission
    public function isAsync(): bool;         // هل يعمل عبر Queue؟
    public function timeout(): int;          // ثواني
}
```

### تسجيل Tools من Module:
```php
// في HwnixInvoicesServiceProvider.php
AiPlatform::registerTools([
    GetInvoiceTool::class,
    CreateInvoiceTool::class,
    CancelInvoiceTool::class,
]);
```

---

## 🔁 القسم العاشر: Workflow Engine

### تعريف Workflow

سلسلة خطوات مُسلسَلة أو متوازية تستخدم Capabilities مختلفة.

```
مثال: "معالجة فاتورة PDF"

Step 1: vision.extract → استخراج بيانات الفاتورة من الصورة
Step 2: text.classify  → تصنيف نوع الفاتورة
Step 3: data.validate  → التحقق من البيانات (Tool)
Step 4: tool.save      → حفظ الفاتورة في النظام (Tool)
Step 5: text.generate  → توليد ملخص للفاتورة
```

### خصائص Workflow Engine:

| الخاصية | الشرح |
|---------|-------|
| **Sequential** | تنفيذ الخطوات بالترتيب |
| **Parallel** | تنفيذ خطوات مستقلة بالتوازي |
| **Conditional** | انتقال شرطي بين الخطوات |
| **State** | حفظ نتيجة كل خطوة واستخدامها للخطوة التالية |
| **Retry** | إعادة محاولة خطوة فاشلة |
| **Resume** | استئناف Workflow متوقف |
| **Versioning** | Workflows لها إصدارات |

---

## 📥 القسم الحادي عشر: Queue Integration (في الدستور)

### القاعدة الإلزامية:

**أي عملية تستغرق أكثر من 3 ثواني → Queue إلزامياً.**

| نوع العملية | Queue؟ |
|------------|--------|
| Chat (Streaming) | اختياري |
| Text Generation (قصير) | لا |
| OCR لـ PDF | نعم دائماً |
| Embedding لـ Document | نعم دائماً |
| Image Generation | نعم دائماً |
| Workflow متعدد الخطوات | نعم دائماً |
| Report AI Analysis | نعم دائماً |
| Batch Processing | نعم دائماً |

### الآلية:
```
Consumer → ExecutionEngine::dispatch(request) → Job → Queue →
Worker → ExecutionEngine::run(request) → Store Result →
Notify Consumer (via Broadcast / Webhook / Polling)
```

### التوافق مع Shared Hosting:
- في Shared Hosting: `QUEUE_CONNECTION=database` (no worker needed).
- في VPS: `QUEUE_CONNECTION=redis` + Supervisor.
- **لا تعديل في الكود** — فقط في `.env`.

---

## 🔐 القسم الثاني عشر: Secret Management

### API Keys لا تُخزَّن نصاً صريحاً

```
AiProviderAccount {
    api_key_encrypted: AES-256-GCM (encrypted at rest)
    api_key_hint: آخر 4 أرقام فقط للعرض
    key_version: رقم الإصدار (للـ Rotation)
    expires_at: تاريخ انتهاء الصلاحية
    last_used_at: آخر استخدام
    health_status: healthy | degraded | failed
    health_checked_at: آخر فحص
    rotation_reminder_at: تاريخ التذكير بالتجديد
}
```

### آلية الـ Health Check:
- فحص دوري (كل ساعة) يرسل طلب بسيط للـ Provider.
- إذا فشل → `health_status = degraded` → Router يتجنب هذا Account.
- إذا فشل 3 مرات متتالية → `health_status = failed` → Alert للمسؤول.

---

## 💰 القسم الثالث عشر: Cost Engine

### محرك مستقل — لا مجرد Usage Log

```
CostEngine::record(ExecutionResult $result):
  1. احسب التكلفة: input_tokens × input_price + output_tokens × output_price
  2. سجّل في AiUsageLog
  3. حدّث AiBudget المرتبط (Company + Agent + User)
  4. تحقق من AiBudgetAlert → إذا وصل الحد → أطلق Event
  5. حدّث AiCostBreakdown اليومي/الشهري
```

### مستويات الميزانية:

| المستوى | الشرح |
|---------|-------|
| **Company Budget** | حد شهري لكل شركة |
| **Agent Budget** | حد يومي/شهري لكل Agent |
| **User Budget** | حد يومي لكل مستخدم |
| **Capability Budget** | حد لكل نوع Capability |

### الوحدة المالية:
`decimal(12,6)` — دقة عالية لـ fractions of cents.

---

## ✍️ القسم الرابع عشر: Prompt Engine (متعدد الأنواع)

### أنواع Prompt:

| النوع | الشرح | مثال |
|-------|-------|------|
| `text` | نص عادي | "أنشئ وصفاً لـ {{ name }}" |
| `json` | تعليمات JSON | Schema لـ Structured Output |
| `multipart` | نص + صورة | "حلّل هذا المستند: [IMAGE]" |
| `image_prompt` | توليد صورة | "صورة احترافية لمنتج {{ name }}" |
| `audio_prompt` | تعليمات TTS | نبرة + سرعة + صوت |
| `system_prompt` | تعليمات النظام | Instructions للـ Agent |

### بنية AiPrompt:
```
AiPrompt {
    key: 'product.description.generate'
    type: 'text' | 'json' | 'multipart' | 'image_prompt' | 'audio_prompt' | 'system_prompt'
    locale: 'ar' | 'en' | ...
    version: 3
    status: 'active' | 'draft' | 'archived'
    template: ...
    variables: ['name', 'category']
    agent_id: ?
    capability: 'text.generate'
    approved_at: ?
    tested_at: ?
}
```

---

## 🧩 القسم الخامس عشر: Model Profiles & Feature Flags

### Model Profile بدلاً من مجرد اسم

```
AiModelProfile {
    provider: 'gemini'
    model_id: 'gemini-2.5-flash'
    display_name: 'Gemini 2.5 Flash'
    max_context_tokens: 1_048_576
    max_output_tokens: 65_536
    input_price_per_1k: 0.000075  (decimal 12,6)
    output_price_per_1k: 0.000300
    
    // Feature Flags
    supports_streaming: true
    supports_vision: true
    supports_tools: true
    supports_json_output: true
    supports_embedding: false
    supports_audio: true
    supports_thinking: true
    supports_mcp: false
    supports_system_prompt: true
    
    // Capability mapping
    capabilities: ['text.generate', 'image.analyze', 'speech.stt']
    
    // Router hints
    is_fast: true
    is_cheap: true
    is_quality: false
}
```

### كيف يستخدم الـ Router الـ Profiles؟
```
Router يختار Model بناءً على:
  ✓ الـ Capability المطلوبة
  ✓ Feature Flags المطلوبة (streaming؟ tools؟ vision؟)
  ✓ Cost Strategy (cheapest / fastest / best_quality)
  ✓ Context Size المتوقع
  ✓ Health Status للـ Account
```

---

## 🛡️ القسم السادس عشر: Policy Engine

```
Request arrives →
  1. PolicyEngine::evaluate($request, $agent, $user) →
  2. Load policies for this agent (DB, ordered by priority) →
  3. Evaluate each policy →
  4. DENY → return PolicyDenyResponse (NO API call, NO Token consumed) →
  5. ALLOW → proceed to Execution Engine
```

### أنواع السياسات:

| النوع | مثال |
|-------|------|
| Topic | "لا تجب على أسئلة سياسية" |
| Language | "الرد بالعربية فقط" |
| User/Role | "هذا الوكيل للمديرين فقط" |
| Time | "متاح خلال ساعات العمل فقط" |
| Budget | "لا تستهلك أكثر من 1000 Token في الطلب" |
| Content | "لا تُرجع بيانات مالية لغير المحاسبين" |
| Rate Limit | "لا أكثر من 10 طلبات في الدقيقة لكل مستخدم" |
| Capability | "هذا Agent لا يستخدم image.generate" |

---

## 🧠 القسم السابع عشر: Memory Engine

| النوع | الحياة | الاستخدام |
|-------|--------|-----------|
| `conversation` | طول المحادثة | رسائل سابقة |
| `session` | جلسة المستخدم | تفضيلات مؤقتة |
| `user` | دائمة | اسم المستخدم، أسلوبه |
| `agent` | دائمة | ما تعلمه الوكيل |
| `shared` | مشتركة بين وكلاء | معلومات الشركة |
| `pinned` | دائمة لا تُحذف | قواعد ثابتة |

---

## 📚 القسم الثامن عشر: Knowledge Engine (RAG)

```
Document →
  Parse & Chunk (by type: PDF/Word/URL/DB) →
    Embed each chunk (via Router → embedding.create) →
      Store in AiKnowledgeChunk + AiEmbedding (pgvector / Meilisearch) →
        On Query:
          Embed Query → Search Nearest Vectors →
            Filter by knowledge_base_id + company_id →
              Inject top K chunks into Agent context
```

---

## 🧩 القسم التاسع عشر: Plugin SDK

```php
// في ServiceProvider الخاص بـ Module
AiPlatform::plugin('hwnix-invoices', [
    'label'       => 'إدارة الفواتير',
    'tools'       => [GetInvoiceTool::class, CreateInvoiceTool::class],
    'agents'      => ['invoices-assistant'],
    'workflows'   => ['process-invoice-pdf'],
    'prompts'     => ['invoice.summary.generate'],
    'capabilities'=> [Capability::VISION_EXTRACT],
]);
```

---

## 📊 القسم العشرون: Dashboard

Dashboard احترافي كامل (مقارَن بـ Laravel Nova) — ليس Backend فقط.

| القسم | المحتوى |
|-------|---------|
| **Overview** | KPIs: Requests, Tokens, Cost, Errors |
| **Providers** | إضافة مزودين + اختبار الاتصال |
| **Accounts** | إدارة API Keys + Quota + Health |
| **Models** | Profiles + Feature Flags + Pricing |
| **Agents** | إنشاء + Tools + Policies + Memory |
| **Prompts** | محرر + Preview + Versioning + Approval |
| **Workflows** | Builder مرئي للـ Workflows |
| **Policies** | إنشاء + ترتيب الأولوية |
| **Cost Engine** | ميزانيات + تنبيهات + رسوم بيانية |
| **Conversations** | استعراض + بحث + تصدير |
| **Usage & Costs** | تقارير تفصيلية يومية/شهرية |
| **Router Logs** | لماذا اختار Router هذا Account؟ |
| **Health** | حالة المزودين + Latency |
| **Audit Logs** | من طلب ماذا + Policy قررت ماذا |

---

## 🌐 القسم الحادي والعشرون: Public API

```php
// Direct Capability (بدون Agent)
AI::capability('text.generate')
    ->prompt('product.description.generate')
    ->with(['name' => $product->name])
    ->run();

// Direct Capability مع Streaming
AI::capability('text.summarize')
    ->prompt('document.summary')
    ->with(['content' => $document->text])
    ->stream();

// Agent-based (محادثة)
AI::agent('invoices-assistant')
    ->conversation($conversationId)
    ->message($userMessage)
    ->run();

// Workflow
AI::workflow('process-invoice-pdf')
    ->with(['file_path' => $pdfPath])
    ->dispatch(); // → Queue
```

### Endpoints:
```
POST /api/v1/ai/capability/{name}     → Direct Capability
POST /api/v1/ai/agents/{agent}/chat  → Agent Chat
POST /api/v1/ai/workflows/{name}/run → Workflow
POST /api/v1/ai/tools/{name}/execute → Tool Direct
GET  /api/v1/ai/conversations        → قائمة المحادثات
GET  /api/v1/ai/usage                → تقرير الاستخدام
```

---

## 🗄️ القسم الثاني والعشرون: مبادئ قاعدة البيانات

- كل جدول فيه `company_id` — لا استثناء.
- Logs و Audit لا تُحذف (Append-Only).
- Prompts والـ Policies تدعم Soft Deletes + Versioning.
- `pgvector` للـ Embeddings في PostgreSQL / `json` في MySQL.
- `decimal(12,6)` للتكاليف (دقة عالية).
- API Keys مُشفَّرة بـ AES-256 — لا نص صريح في DB.
- فهارس (Indexes) على: `company_id`, `agent_id`, `capability`, `created_at`.

---

## 📦 القسم الثالث والعشرون: Package Architecture (P-16 التفصيل الكامل)

> **يجب أن تكون AI Platform موديولاً مستقلاً بالكامل، قابلاً للنقل وإعادة الاستخدام في أي مشروع Laravel دون أي تعديل في الكود المصدري للمنصة.**

---

### 📁 هيكل الحزمة (Package Structure)

```
hwnix/ai-platform/
├── config/
│   └── ai-platform.php          ← ملف الإعدادات القابل للنشر
├── database/
│   ├── migrations/              ← كل جداول المنصة
│   └── seeders/                 ← Capabilities + Default Policies
├── routes/
│   ├── api.php                  ← /api/v1/ai/...
│   └── web.php                  ← Dashboard routes
├── src/
│   ├── Contracts/               ← كل الـ Interfaces العامة
│   ├── Drivers/                 ← Provider Drivers
│   ├── Engines/
│   │   ├── AgentEngine.php
│   │   ├── ExecutionEngine.php
│   │   ├── PolicyEngine.php
│   │   ├── PromptEngine.php
│   │   ├── ToolEngine.php
│   │   ├── MemoryEngine.php
│   │   ├── WorkflowEngine.php
│   │   ├── CostEngine.php
│   │   └── KnowledgeEngine.php
│   ├── Router/
│   │   └── AiRouter.php
│   ├── Models/                  ← Eloquent Models للمنصة
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Resources/
│   │   └── Requests/
│   ├── Events/                  ← Events العامة للمنصة
│   ├── Listeners/
│   ├── Jobs/                    ← Queue Jobs
│   ├── Commands/                ← Artisan Commands
│   ├── Facades/
│   │   └── AI.php               ← AI::capability(...)->run()
│   ├── Enums/
│   │   ├── Capability.php
│   │   ├── ProviderType.php
│   │   └── MemoryType.php
│   ├── Dashboard/               ← Vue/Inertia Dashboard
│   │   ├── Pages/
│   │   └── Components/
│   ├── Plugins/                 ← Plugin Registry
│   │   └── AiPlatformPluginRegistry.php
│   └── AiPlatformServiceProvider.php
├── resources/
│   ├── views/                   ← Dashboard Blade/Inertia views
│   └── js/                     ← Dashboard Vue components
├── tests/
│   ├── Feature/                 ← Feature Tests مستقلة
│   ├── Unit/                    ← Unit Tests لكل Engine
│   └── Fixtures/               ← Fake Drivers + Mocks
└── composer.json
```

---

### 🔒 حدود مسؤوليات الحزمة

**الحزمة مسؤولة عن:**
- إدارة Providers, Accounts, Models, Capabilities
- تنفيذ جميع Engines (Agent, Policy, Prompt, Tool, Memory, Workflow, Cost)
- Routing بين Providers
- Dashboard الإدارة
- Public API
- Usage Logging و Audit
- Secret Management

**الحزمة ليست مسؤولة عن:**
- أي Business Logic خاص بـ HWNix أو أي مشروع آخر
- Models من خارج الحزمة (Invoice, Product, User, ...)
- أي Migrations خارج نطاقها
- أي قرار تجاري

---

### 🔌 نقاط التكامل (Extension Points)

المشروع الخارجي يتكامل مع المنصة **فقط** عبر هذه النقاط:

| نقطة التكامل | الآلية | مثال |
|-------------|--------|------|
| **Tools** | `AiPlatform::registerTools([...])` | `GetInvoiceTool::class` |
| **Agents** | نشر Seeder أو API | إنشاء Agent من Dashboard |
| **Prompts** | نشر Seeder أو API | إنشاء Prompt من Dashboard |
| **Policies** | نشر Seeder أو API | إنشاء Policy من Dashboard |
| **Capabilities** | `AiPlatform::registerCapability(...)` | قدرة مخصصة للمشروع |
| **Drivers** | `AiPlatform::registerDriver(...)` | مزود جديد |
| **Events** | `Event::listen(AiRequestExecuted::class, ...)` | Side Effects |
| **Plugins** | `AiPlatform::plugin('name', [...])` | تسجيل Module كامل |

---

### 🧩 آلية تسجيل Plugins

```php
// داخل HwnixInvoicesServiceProvider.php
public function boot(): void
{
    AiPlatform::plugin('hwnix-invoices', [
        'label'       => 'إدارة الفواتير',
        'version'     => '1.0.0',
        'tools'       => [
            GetInvoiceTool::class,
            CreateInvoiceTool::class,
        ],
        'workflows'   => [
            'process-invoice-pdf' => ProcessInvoicePdfWorkflow::class,
        ],
        'prompts'     => [
            'invoice.summary.generate',
        ],
    ]);
}
```

---

### 🚀 آلية نشر الملفات (Publishing)

```bash
# نشر ملف الإعدادات
php artisan vendor:publish --tag=ai-platform-config

# نشر Migrations
php artisan vendor:publish --tag=ai-platform-migrations

# نشر Dashboard Assets
php artisan vendor:publish --tag=ai-platform-assets

# نشر كل شيء
php artisan vendor:publish --provider="HwnixAi\AiPlatformServiceProvider"
```

---

### 🔄 Auto Discovery

الحزمة تدعم **Laravel Package Auto Discovery** — تثبيتها كافٍ لتشغيل كل شيء:

```json
// composer.json في الحزمة
"extra": {
    "laravel": {
        "providers": [
            "HwnixAi\AiPlatformServiceProvider"
        ],
        "aliases": {
            "AI": "HwnixAi\Facades\AI"
        }
    }
}
```

**الخطوات المطلوبة لتثبيت المنصة في مشروع جديد:**
```bash
composer require hwnix/ai-platform  # 1. تثبيت الحزمة
php artisan migrate                  # 2. تشغيل Migrations
php artisan vendor:publish ...       # 3. نشر الإعدادات (اختياري)
# 4. تسجيل Plugins في Service Providers
```

لا شيء آخر مطلوب.

---

### 📌 استراتيجية الإصدارات (Semantic Versioning)

المنصة تتبع **SemVer** بصرامة:

| نوع الإصدار | متى؟ | يكسر العقود؟ |
|------------|------|-------------|
| `PATCH` (x.x.1) | Bug fixes, Security patches | لا |
| `MINOR` (x.1.x) | Features جديدة متوافقة | لا |
| `MAJOR` (1.x.x) | تغييرات تكسر Public Contracts | نعم — مع Migration Guide |

**Backward Compatibility ضمان:**
- أي إصدار `MINOR` أو `PATCH` لا يكسر:
  - Public Contracts & Interfaces
  - Facade API: `AI::capability(...)->run()`
  - Plugin registration API
  - Public Events
  - REST API routes
- قبل أي `MAJOR` release → نشر Deprecation Notice بـ Minor version واحدة على الأقل.

---

### 🔧 سياسة التوافق مع Laravel

```
laravel/ai-platform v1.x → يدعم Laravel 11.x, 12.x
laravel/ai-platform v2.x → يدعم Laravel 12.x, 13.x
```

**القاعدة**: دعم آخر إصدارَين من Laravel في نفس الوقت.  
**الاختبار**: CI يُشغَّل على كل الإصدارات المدعومة.

---

### 🧪 اختبارات مستقلة للحزمة (Package Test Suite)

الاختبارات تعمل **خارج HWNix** بالكامل:

```bash
# تشغيل كل اختبارات الحزمة
cd packages/hwnix-ai-platform
composer test

# أو عبر Orchestra Testbench
vendor/bin/phpunit
```

**هيكل الاختبارات:**
```
tests/
├── Feature/
│   ├── DirectCapabilityTest.php
│   ├── AgentChatTest.php
│   ├── WorkflowTest.php
│   ├── PolicyEngineTest.php
│   ├── RouterTest.php
│   ├── CostEngineTest.php
│   └── PluginTest.php
├── Unit/
│   ├── Engines/
│   ├── Drivers/
│   └── Router/
└── Fixtures/
    ├── FakeGeminiDriver.php     ← لا استدعاءات API حقيقية
    ├── FakeOpenAiDriver.php
    └── FakePluginServiceProvider.php
```

**كل Driver له Fake مقابل** يُحاكي الاستجابة — لا اتصال بأي API في الاختبارات.

---

## 📅 خطة التنفيذ (Execution Plan)

| المرحلة | المحتوى | الحالة |
|---------|---------|--------|
| **المرحلة الأولى** | Architecture Constitution v1.2 | ✅ مكتملة |
| **المرحلة الثانية** | Database Design — تصميم كل جدول وعلاقة | ⏳ التالية |
| **المرحلة الثالثة** | Contracts & Interfaces — عقود كل Engine | — |
| **المرحلة الرابعة** | Package Skeleton + Auto Discovery + Test Suite | — |
| **المرحلة الخامسة** | Provider Drivers + Router + Execution Engine | — |
| **المرحلة السادسة** | Agent + Policy + Prompt + Memory + Workflow | — |
| **المرحلة السابعة** | First Consumer: توليد وصف منتج | — |
| **المرحلة الثامنة** | Dashboard | — |

---

## 🚫 المحظورات المطلقة (Iron Rules)

| # | المحظور |
|---|---------|
| IR-01 | ممنوع Prompt نصي في كود PHP |
| IR-02 | ممنوع استدعاء Driver مباشرة من Module |
| IR-03 | ممنوع استهلاك Token قبل Policy Engine |
| IR-04 | ممنوع تخزين API Key كنص صريح في DB |
| IR-05 | ممنوع ربط Tool بـ Agent مباشرة — فقط عبر Plugin SDK |
| IR-06 | ممنوع قراءة/كتابة Conversation من خارج Platform |
| IR-07 | ممنوع حذف Usage Logs أو Audit Logs |
| IR-08 | ممنوع `company_id = null` في أي جدول |
| IR-09 | ممنوع Business Logic داخل Driver |
| IR-10 | ممنوع تعديل ملفات Platform لإضافة مزود — فقط Service Provider |
| IR-11 | ممنوع Sync لعمليات تستغرق أكثر من 3 ثواني |
| IR-12 | ممنوع Agent يستدعي Router مباشرة — يمر عبر Execution Engine |
| IR-13 | ممنوع استخدام Provider Type واحد (LLM) كتعريف لكل الـ Providers |
| IR-14 | ممنوع استيراد أي Class من HWNix داخل ملفات الحزمة (`use Modules\...`) |
| IR-15 | ممنوع كسر Public Contracts إلا في Major Version مع Deprecation Notice مسبق |

---

## 💡 المبدأ الذهبي الختامي

> **"AI Platform is an Orchestration Platform, not an LLM Wrapper."**

المنصة تُنظّم (**تُنسِّق**) دورة حياة كل طلب ذكاء اصطناعي — بدءاً من استلام الطلب، مروراً بالسياسات والذاكرة والأدوات والتوجيه، وانتهاءً بالتسجيل وحساب التكلفة — وتُخفي كل هذا التعقيد خلف API بسيطة ومعبّرة.

---

*APC-001 v1.2 — معتمدة نهائياً. جاهزة للانتقال للمرحلة الثانية: تصميم قاعدة البيانات.*
