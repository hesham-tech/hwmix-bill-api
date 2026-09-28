# وثيقة متطلبات برمجية: خدمة الذكاء الاصطناعي المصغرة لاستخراج البيانات
# AI Microservice Prompt - GLiNER Structured Data Extraction

هذا الملف يحتوي على المواصفات الهندسية لإنشاء خدمة ذكاء اصطناعي مصغرة (Microservice) تعمل محلياً على سيرفر الـ VPS، متخصصة في استخراج البيانات المهيكلة (JSON) من النصوص العربية العشوائية باستخدام نموذج GLiNER، دون الاعتماد على أي API خارجي أو استهلاك موارد ضخمة.

---

**Project Goal:**
Build a lightweight, localized AI Microservice for Arabic Structured Data Extraction (Text-to-JSON) without relying on any external APIs or LLMs (e.g., no OpenAI, no massive 8GB models). The solution must run on a standard VPS alongside our existing Laravel application.

**Architecture & Tech Stack:**
1. **AI Model:** `GLiNER` (Generalist and Lightweight Information Extraction).
2. **Target Model Variant:** `NAMAA-Space/gliner_arabic-v2.1` (Highly optimized for Arabic NLP and zero-shot NER).
3. **Backend Framework:** Python with `FastAPI` (for lightning-fast, asynchronous local API responses).
4. **Integration:** Laravel will communicate with this Python microservice internally via `cURL / HTTP Client`.

**Core Requirements for the Python Developer:**
1. **Microservice Setup:** Create a FastAPI application running on `127.0.0.1:8000` (Internal access only, blocked from the outside world).
2. **Model Loading:** The script must load the `GLiNER` model into RAM on startup (Singleton pattern) so that subsequent requests are processed in milliseconds without reloading the 300MB model.
3. **API Endpoint:** 
   - **Method:** `POST /api/extract`
   - **Request Payload (JSON):** 
     ```json
     {
       "text": "استلمت من السيد أحمد محمود مبلغ وقدره 1500 جنيه مصري دفعة مقدمة للفاتورة رقم 9982 بتاريخ 27 سبتمبر.",
       "template_labels": ["اسم العميل", "المبلغ", "رقم الفاتورة", "تاريخ الفاتورة"]
     }
     ```
4. **Processing Logic:** Pass the `text` and `template_labels` to the GLiNER model to extract the entities.
5. **Response Format:** Map the extracted entities back to the requested labels and return clean JSON:
     ```json
     {
       "success": true,
       "data": {
         "اسم العميل": "أحمد محمود",
         "المبلغ": "1500",
         "رقم الفاتورة": "9982",
         "تاريخ الفاتورة": "27 سبتمبر"
       }
     }
     ```
6. **Deployment:** Provide a simple `systemd` service file or `Supervisor` configuration to keep the FastAPI script running continuously in the background on Ubuntu.

**Requirements for the Laravel Developer:**
1. Create an `AiExtractionService` class.
2. Implement a method `extractData(string $text, array $labels)` that sends a synchronous HTTP request to `http://127.0.0.1:8000/api/extract`.
3. Handle timeouts gracefully (e.g., fallback if the Python service is restarting).
4. Do NOT expose the Python port to the internet.
