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
            background: #27ae60;
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>تأكيد استلام دفعة</h2>
        </div>
        <div class="content">
            <p>عزيزي {{ $payment->invoice->user->name }}،</p>
            <p>تم استلام مبلغ <strong>{{ number_format($payment->amount, 2) }}</strong> بنجاح بخصوص الفاتورة رقم
                <strong>{{ $payment->invoice->invoice_number }}</strong>.</p>
            <p><strong>تفاصيل الدفعة:</strong></p>
            <ul>
                <li>تاريخ الدفع:
                    {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : $payment->created_at->format('Y-m-d') }}
                </li>
                <li>المبلغ المتبقي على الفاتورة: {{ number_format($payment->invoice->remaining_amount, 2) }}</li>
            </ul>
            <p>شكراً لالتزامك بالسداد.</p>
        </div>
        <div class="footer">
            <p>تم أرسال هذا البريد تلقائياً من نظام {{ config('app.name') }}</p>
        </div>
    </div>
</body>

</html>