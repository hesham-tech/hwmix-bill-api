<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>إيصال دفع | {{ $payment->id }}</title>
    <style>
        @page {
            margin: 15px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            font-size: 13px;
            color: #333;
        }

        .receipt {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            border: 2px solid #2c3e50;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #ccc;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 22px;
            margin-bottom: 5px;
        }

        .header h2 {
            color: #666;
            font-size: 16px;
            font-weight: normal;
        }

        .info-row {
            display: table;
            width: 100%;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .info-row .label {
            display: table-cell;
            width: 40%;
            font-weight: bold;
            color: #555;
        }

        .info-row .value {
            display: table-cell;
            width: 60%;
            text-align: left;
        }

        .amount-box {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 5px;
        }

        .amount-box .label {
            font-size: 14px;
            margin-bottom: 10px;
        }

        .amount-box .amount {
            font-size: 32px;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px dashed #ccc;
            text-align: center;
            font-size: 11px;
            color: #666;
        }

        .stamp {
            margin: 20px 0;
            padding: 10px;
            border: 2px solid #27ae60;
            border-radius: 50%;
            width: 100px;
            height: 100px;
            display: inline-block;
            text-align: center;
            line-height: 80px;
            font-weight: bold;
            color: #27ae60;
            transform: rotate(-15deg);
        }
    </style>
</head>

<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <h1>{{ $company->name ?? 'اسم الشركة' }}</h1>
            <h2>إيصال دفع</h2>
            <div style="text-align: center; margin-top: 15px;">
                <div class="stamp">مدفوع</div>
            </div>
        </div>

        <!-- Receipt Info -->
        <div class="info-row">
            <div class="label">رقم الإيصال:</div>
            <div class="value">#{{ $payment->id }}</div>
        </div>

        <div class="info-row">
            <div class="label">رقم الفاتورة:</div>
            <div class="value">{{ $invoice->invoice_number }}</div>
        </div>

        <div class="info-row">
            <div class="label">التاريخ:</div>
            <div class="value">
                {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : $payment->created_at->format('Y-m-d') }}
            </div>
        </div>

        <div class="info-row">
            <div class="label">العميل:</div>
            <div class="value">{{ $customer->name ?? 'غير محدد' }}</div>
        </div>

        @if($payment->paymentMethod)
            <div class="info-row">
                <div class="label">طريقة الدفع:</div>
                <div class="value">{{ $payment->paymentMethod->name }}</div>
            </div>
        @endif

        @if($payment->cashBox)
            <div class="info-row">
                <div class="label">الصندوق:</div>
                <div class="value">{{ $payment->cashBox->name }}</div>
            </div>
        @endif

        @if($payment->reference_number)
            <div class="info-row">
                <div class="label">رقم المرجع:</div>
                <div class="value">{{ $payment->reference_number }}</div>
            </div>
        @endif

        <!-- Amount -->
        <div class="amount-box">
            <div class="label">المبلغ المدفوع</div>
            <div class="amount">{{ number_format($payment->amount, 2) }} ج.م</div>
        </div>

        <!-- Invoice Status -->
        <div style="background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px;">
            <div class="info-row" style="border: none; margin: 5px 0;">
                <div class="label">صافي الفاتورة:</div>
                <div class="value">{{ number_format($invoice->net_amount, 2) }}</div>
            </div>
            <div class="info-row" style="border: none; margin: 5px 0;">
                <div class="label">المدفوع الكلي:</div>
                <div class="value" style="color: #27ae60;">{{ number_format($invoice->paid_amount, 2) }}</div>
            </div>
            <div class="info-row" style="border: none; margin: 5px 0;">
                <div class="label">المتبقي:</div>
                <div class="value" style="color: #e74c3c; font-weight: bold;">
                    {{ number_format($invoice->remaining_amount, 2) }}</div>
            </div>
        </div>

        <!-- Notes -->
        @if($payment->notes)
            <div style="margin: 15px 0; padding: 10px; background: #fffbea; border-right: 3px solid #f39c12;">
                <strong>ملاحظات:</strong><br>
                {{ $payment->notes }}
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p><strong>شكراً لتعاملكم معنا</strong></p>
            <p>تم إصدار هذا الإيصال في {{ now()->format('Y-m-d H:i:s') }}</p>
            @if($company)
                <p style="margin-top: 10px;">{{ $company->address ?? '' }}</p>
                <p>{{ $company->phone ?? '' }} | {{ $company->email ?? '' }}</p>
            @endif
        </div>
    </div>
</body>

</html>