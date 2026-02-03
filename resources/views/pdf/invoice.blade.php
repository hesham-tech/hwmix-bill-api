<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
        }

        .container {
            width: 100%;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .company-info {
            margin-bottom: 20px;
            font-size: 11px;
            color: #666;
        }

        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .invoice-info .left,
        .invoice-info .right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .info-box h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .info-box p {
            margin: 5px 0;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table thead {
            background: #2c3e50;
            color: white;
        }

        table thead th {
            padding: 12px 8px;
            text-align: right;
            font-size: 12px;
            font-weight: bold;
        }

        table tbody td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
            text-align: right;
        }

        table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .totals {
            margin-top: 30px;
            float: left;
            width: 300px;
        }

        .totals table {
            margin: 0;
        }

        .totals td {
            padding: 8px;
            border: none;
        }

        .totals .label {
            font-weight: bold;
            text-align: left;
        }

        .totals .value {
            text-align: left;
            font-weight: bold;
        }

        .totals .grand-total {
            background: #2c3e50;
            color: white;
            font-size: 14px;
        }

        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
            clear: both;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-paid {
            background: #27ae60;
            color: white;
        }

        .status-unpaid {
            background: #e74c3c;
            color: white;
        }

        .status-partial {
            background: #f39c12;
            color: white;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $company->name ?? 'اسم الشركة' }}</h1>
            <div class="company-info">
                @if($company)
                    <p>{{ $company->address ?? '' }}</p>
                    <p>هاتف: {{ $company->phone ?? '' }} | بريد: {{ $company->email ?? '' }}</p>
                @endif
            </div>
        </div>

        <!-- Invoice Info -->
        <div class="invoice-info">
            <div class="right">
                <div class="info-box">
                    <h3>معلومات الفاتورة</h3>
                    <p><strong>رقم الفاتورة:</strong> {{ $invoice->invoice_number }}</p>
                    <p><strong>التاريخ:</strong> {{ $invoice->created_at->format('Y-m-d') }}</p>
                    <p><strong>تاريخ الاستحقاق:</strong>
                        {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') : 'غير محدد' }}
                    </p>
                    <p><strong>الحالة:</strong>
                        @if($invoice->payment_status == 'paid')
                            <span class="status-badge status-paid">مدفوعة</span>
                        @elseif($invoice->payment_status == 'unpaid')
                            <span class="status-badge status-unpaid">غير مدفوعة</span>
                        @else
                            <span class="status-badge status-partial">مدفوعة جزئياً</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="left">
                <div class="info-box">
                    <h3>معلومات العميل</h3>
                    @if($customer)
                        <p><strong>الاسم:</strong> {{ $customer->name }}</p>
                        <p><strong>البريد:</strong> {{ $customer->email ?? '' }}</p>
                        <p><strong>الهاتف:</strong> {{ $customer->phone ?? '' }}</p>
                    @else
                        <p>بدون عميل</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="35%">المنتج</th>
                    <th width="10%">الكمية</th>
                    <th width="15%">السعر</th>
                    <th width="10%">الخصم</th>
                    <th width="10%">الضريبة</th>
                    <th width="15%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            {{ $item->name }}
                            @if($item->variant)
                                <br><small style="color: #666;">{{ $item->variant->sku ?? '' }}</small>
                            @endif
                        </td>
                        <td>{{ number_format($item->quantity, 0) }}</td>
                        <td>{{ number_format($item->unit_price, 2) }}</td>
                        <td>{{ number_format($item->discount ?? 0, 2) }}</td>
                        <td>{{ number_format($item->tax_amount ?? 0, 2) }}</td>
                        <td><strong>{{ number_format($item->total, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <table>
                <tr>
                    <td class="label">المجموع الإجمالي:</td>
                    <td class="value">{{ number_format($invoice->gross_amount, 2) }}</td>
                </tr>
                @if($invoice->total_discount > 0)
                    <tr>
                        <td class="label">الخصم:</td>
                        <td class="value">({{ number_format($invoice->total_discount, 2) }})</td>
                    </tr>
                @endif
                @if($invoice->total_tax > 0)
                    <tr>
                        <td class="label">الضريبة:</td>
                        <td class="value">{{ number_format($invoice->total_tax, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td class="label">الصافي:</td>
                    <td class="value">{{ number_format($invoice->net_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">المدفوع:</td>
                    <td class="value" style="color: #27ae60;">{{ number_format($invoice->paid_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">المتبقي:</td>
                    <td class="value" style="color: #e74c3c;">{{ number_format($invoice->remaining_amount, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- Notes -->
        @if($invoice->notes)
            <div
                style="clear: both; margin-top: 20px; padding: 15px; background: #f8f9fa; border-right: 4px solid #2c3e50;">
                <h3 style="margin-bottom: 10px; color: #2c3e50;">ملاحظات:</h3>
                <p>{{ $invoice->notes }}</p>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>شكراً لتعاملكم معنا</p>
            <p>تم إنشاء هذه الفاتورة إلكترونياً في {{ now()->format('Y-m-d H:i') }}</p>
        </div>
    </div>
</body>

</html>