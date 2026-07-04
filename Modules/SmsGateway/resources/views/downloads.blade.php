<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنزيل تطبيق HWNix SMS Agent</title>
    <!-- خط Inter و Tajawal من Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 24, 39, 0.7);
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --border-color: rgba(255, 255, 255, 0.08);
            --gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Tajawal', 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.15) 0%, transparent 40%);
        }

        .container {
            width: 100%;
            max-width: 750px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 40px 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 1.1rem;
            margin-bottom: 35px;
        }

        .apk-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 30px;
        }

        .apk-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            text-align: right;
        }

        .apk-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.1);
        }

        .apk-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .apk-version {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 99px;
            background: var(--gradient);
            color: white;
            font-weight: bold;
        }

        .apk-meta {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: flex;
            gap: 15px;
        }

        .download-btn {
            background: var(--gradient);
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .download-btn:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .no-data {
            padding: 30px;
            color: var(--text-muted);
            font-style: italic;
        }

        .footer {
            margin-top: 20px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        @media (max-width: 600px) {
            .apk-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .download-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>HWNix SMS Gateway</h1>
        <p>اختر إصدار تطبيق الأندرويد للتحميل والتثبيت</p>
    </div>

    <div class="apk-list">
        @forelse($apkFiles as $index => $apk)
            <div class="apk-card">
                <div class="apk-info">
                    <div class="apk-version">
                        إصدار v{{ $apk['version'] }}
                        @if($index === 0)
                            <span class="badge">الأحدث</span>
                        @endif
                    </div>
                    <div class="apk-meta">
                        <span>الحجم: {{ $apk['size'] }}</span>
                        <span>تاريخ الرفع: {{ $apk['date'] }}</span>
                    </div>
                </div>
                <a href="{{ $apk['url'] }}" class="download-btn">تنزيل APK</a>
            </div>
        @empty
            <div class="no-data">
                لا توجد إصدارات متوفرة للتحميل حالياً. يرجى مراجعة إدارة النظام.
            </div>
        @endforelse
    </div>

    <div class="footer">
        HWNix © 2025 • جميع الحقوق محفوظة
    </div>
</div>

</body>
</html>
