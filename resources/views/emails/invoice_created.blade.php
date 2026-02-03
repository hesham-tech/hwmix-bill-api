<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            width: 80%;
            margin: 20px auto;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
        }

        .header {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }

        .content {
            padding: 20px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #27ae60;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>فاتورة جديدة من {{ $invoice->company->name }}</h2>
        </div>
        <div class="content">
            <p>عزيزي {{ $invoice->user->name }}،</p>
            <p>تم إصدار فاتورة جديدة لك برقم <strong>{{ $invoice->invoice_number }}</strong>.</p>
            <p><strong>تفاصيل الفاتورة:</strong></p>
            <ul>
                <li>التاريخ: {{ $invoice->created_at->format('Y-m-d') }}</li>
                <li>المبلغ الإجمالي: {{ number_format($invoice->net_amount, 2) }}
                    {{ $invoice->company->currency ?? 'ج.م' }}</li>
            </ul>
            <p>نقدر تعاملك معنا.</p>
            <a href="{{ config('app.url') }}/invoices/{{ $invoice->id }}" class="btn">عرض الفاتورة</a>
        </div>
        <div class="footer">
            <p>تم أرسال هذا البريد تلقائياً من نظام {{ config('app.name') }}</p>
        </div>
    </div>
</body>

</html>