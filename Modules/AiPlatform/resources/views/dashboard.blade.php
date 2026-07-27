<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HWNix AI Platform Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #0f172a; color: #f8fafc; }
        .sidebar { background-color: #1e293b; min-height: 100vh; border-left: 1px solid #334155; }
        .card-custom { background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .stat-icon { font-size: 2rem; color: #38bdf8; }
        .badge-active { background-color: #10b981; color: #fff; }
        .nav-link { color: #94a3b8; border-radius: 8px; padding: 10px 15px; margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { background-color: #3b82f6; color: #fff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-3">
            <h4 class="text-primary mb-4"><i class="fa-solid fa-robot me-2"></i> AI Platform</h4>
            <nav class="nav flex-column">
                <a class="nav-link active" href="{{ route('ai-platform.dashboard') }}"><i class="fa-solid fa-chart-line me-2"></i> لوحة التحكم</a>
                <a class="nav-link" href="{{ route('ai-platform.providers.index') }}"><i class="fa-solid fa-server me-2"></i> المزودين والحسابات</a>
                <a class="nav-link" href="{{ route('ai-platform.agents.index') }}"><i class="fa-solid fa-user-astronaut me-2"></i> الوكلاء (Agents)</a>
                <a class="nav-link" href="{{ route('ai-platform.prompts.index') }}"><i class="fa-solid fa-terminal me-2"></i> قوالب الـ Prompts</a>
                <a class="nav-link" href="{{ route('ai-platform.usage.index') }}"><i class="fa-solid fa-coins me-2"></i> الاستهلاك والتكاليف</a>
            </nav>
        </div>

        <!-- Content -->
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fa-solid fa-gauge text-info me-2"></i> ملخص المنصة المعمارية</h2>
                <span class="badge bg-success fs-6"><i class="fa-solid fa-shield-halved me-1"></i> P-16 Integration First Active</span>
            </div>

            <!-- Metric Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card card-custom p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">المزودون الفعّالون</h6>
                                <h3 class="mb-0">2</h3>
                            </div>
                            <i class="fa-solid fa-microchip stat-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-custom p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">إجمالي الـ Capabilities</h6>
                                <h3 class="mb-0">20</h3>
                            </div>
                            <i class="fa-solid fa-bolt stat-icon text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-custom p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">الاستدعاءات الناجحة</h6>
                                <h3 class="mb-0">100%</h3>
                            </div>
                            <i class="fa-solid fa-circle-check stat-icon text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-custom p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">إجمالي الميزانية المتبقية</h6>
                                <h3 class="mb-0">$500.00</h3>
                            </div>
                            <i class="fa-solid fa-wallet stat-icon text-info"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overview Tables -->
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="card card-custom p-3">
                        <h5><i class="fa-solid fa-network-wire me-2"></i> حالة الـ Router والمزودين</h5>
                        <table class="table table-dark table-hover mt-3">
                            <thead>
                                <tr>
                                    <th>المزود</th>
                                    <th>النوع</th>
                                    <th>النماذج المدعومة</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Google Gemini</td>
                                    <td><span class="badge bg-primary">LLM / Vision</span></td>
                                    <td>gemini-2.5-flash, gemini-2.5-pro</td>
                                    <td><span class="badge badge-active">Healthy</span></td>
                                </tr>
                                <tr>
                                    <td>OpenAI</td>
                                    <td><span class="badge bg-primary">LLM / ImageGen</span></td>
                                    <td>gpt-4o, gpt-4o-mini</td>
                                    <td><span class="badge badge-active">Healthy</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-custom p-3">
                        <h5><i class="fa-solid fa-shield-cat me-2"></i> محركات المنصة النشطة</h5>
                        <ul class="list-group list-group-flush bg-transparent">
                            <li class="list-group-item bg-transparent text-light"><i class="fa-solid fa-check text-success me-2"></i> Execution Engine</li>
                            <li class="list-group-item bg-transparent text-light"><i class="fa-solid fa-check text-success me-2"></i> AI Router Engine</li>
                            <li class="list-group-item bg-transparent text-light"><i class="fa-solid fa-check text-success me-2"></i> Policy Engine</li>
                            <li class="list-group-item bg-transparent text-light"><i class="fa-solid fa-check text-success me-2"></i> Prompt Engine</li>
                            <li class="list-group-item bg-transparent text-light"><i class="fa-solid fa-check text-success me-2"></i> Memory & RAG Engine</li>
                            <li class="list-group-item bg-transparent text-light"><i class="fa-solid fa-check text-success me-2"></i> Cost & Budget Engine</li>
                            <li class="list-group-item bg-transparent text-light"><i class="fa-solid fa-check text-success me-2"></i> Agent & Workflow Engine</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
