<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المزودين والحسابات - AI Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f172a; color: #f8fafc; }
        .sidebar { background-color: #1e293b; min-height: 100vh; border-left: 1px solid #334155; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .nav-link { color: #94a3b8; border-radius: 8px; padding: 10px 15px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background-color: #3b82f6; color: #fff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3">
            <h4 class="text-primary mb-4"><i class="fa-solid fa-robot me-2"></i> AI Platform</h4>
            <nav class="nav flex-column">
                <a class="nav-link" href="{{ route('ai-platform.dashboard') }}"><i class="fa-solid fa-chart-line me-2"></i> لوحة التحكم</a>
                <a class="nav-link active" href="{{ route('ai-platform.providers.index') }}"><i class="fa-solid fa-server me-2"></i> المزودين والحسابات</a>
                <a class="nav-link" href="{{ route('ai-platform.agents.index') }}"><i class="fa-solid fa-user-astronaut me-2"></i> الوكلاء (Agents)</a>
                <a class="nav-link" href="{{ route('ai-platform.prompts.index') }}"><i class="fa-solid fa-terminal me-2"></i> قوالب الـ Prompts</a>
                <a class="nav-link" href="{{ route('ai-platform.usage.index') }}"><i class="fa-solid fa-coins me-2"></i> الاستهلاك والتكاليف</a>
            </nav>
        </div>
        <div class="col-md-10 p-4">
            <h2><i class="fa-solid fa-server text-primary me-2"></i> المزودون والمفاتيح المشفرة (AES-256)</h2>
            <div class="card card-custom p-3 mt-4">
                <table class="table table-dark table-hover">
                    <thead>
                        <tr>
                            <th>المزود</th>
                            <th>الـ Driver</th>
                            <th>مفتاح API التوضيحي</th>
                            <th>الأولوية</th>
                            <th>الحالة الصحية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Google Gemini</td>
                            <td><code>Modules\AiPlatform\Drivers\GeminiDriver</code></td>
                            <td><code>...3j7k</code></td>
                            <td><span class="badge bg-info">1 (أعلى)</span></td>
                            <td><span class="badge bg-success">Healthy</span></td>
                        </tr>
                        <tr>
                            <td>OpenAI</td>
                            <td><code>Modules\AiPlatform\Drivers\OpenAiDriver</code></td>
                            <td><code>...9a2b</code></td>
                            <td><span class="badge bg-secondary">2</span></td>
                            <td><span class="badge bg-success">Healthy</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
