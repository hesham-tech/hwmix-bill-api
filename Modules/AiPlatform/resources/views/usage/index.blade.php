<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الاستهلاك والتكاليف - AI Platform</title>
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
                <a class="nav-link" href="{{ route('ai-platform.providers.index') }}"><i class="fa-solid fa-server me-2"></i> المزودين والحسابات</a>
                <a class="nav-link" href="{{ route('ai-platform.agents.index') }}"><i class="fa-solid fa-user-astronaut me-2"></i> الوكلاء (Agents)</a>
                <a class="nav-link" href="{{ route('ai-platform.prompts.index') }}"><i class="fa-solid fa-terminal me-2"></i> قوالب الـ Prompts</a>
                <a class="nav-link active" href="{{ route('ai-platform.usage.index') }}"><i class="fa-solid fa-coins me-2"></i> الاستهلاك والتكاليف</a>
            </nav>
        </div>
        <div class="col-md-10 p-4">
            <h2><i class="fa-solid fa-coins text-warning me-2"></i> تقرير الاستهلاك وحساب التكاليف (Cost Engine)</h2>
            <div class="card card-custom p-3 mt-4">
                <p class="text-muted">يتم تسجيل الاستهلاك والتكاليف بدقة عالية <code>decimal(12,6)</code> بشكل غير قابل للتعديل (Append-Only).</p>
                <div class="alert alert-secondary bg-dark border-secondary text-light">
                    <i class="fa-solid fa-clock-rotate-left text-warning me-2"></i> يتم تطبيق سياسة الأرشفة التلقائية بعد 90 يوماً لحفظ أداء النظام.
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
