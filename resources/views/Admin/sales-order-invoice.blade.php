<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order {{ $salesOrder->order_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
        }
        .invoice-box {
            width: 100%;
        }
        .header {
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .subtitle {
            font-size: 12px;
            color: #4b5563;
            margin-top: 2px;
        }
        .meta {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .meta td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .meta .label {
            color: #4b5563;
            width: 120px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 12px;
            table-layout: fixed;
        }
        .table th, .table td {
            border: 1px solid #d1d5db;
            padding: 5px;
            word-wrap: break-word;
        }
        .table th {
            background: #f3f4f6;
            text-align: left;
            font-weight: 600;
        }
        .num {
            text-align: right;
        }
        .dim-note {
            font-size: 10px;
            color: #6b7280;
            margin-top: 2px;
        }
        .totals {
            width: 320px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals td {
            border: 1px solid #d1d5db;
            padding: 7px;
        }
        .totals .label {
            background: #f9fafb;
            font-weight: 600;
        }
        .totals .amount {
            text-align: right;
        }
        .remark {
            margin-top: 14px;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        .footer {
            margin-top: 24px;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>
<body>
<div class="invoice-box">
    <div class="header">
        <h1 class="title">Sales Order</h1>
        <div class="subtitle">Invoice / Print Copy</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Order No</td>
            <td>{{ $salesOrder->order_no }}</td>
            <td class="label">Order Date</td>
            <td>{{ \Carbon\Carbon::parse($salesOrder->order_date)->format('d-m-Y') }}</td>
        </tr>
        <tr>
            <td class="label">Client</td>
            <td>{{ $salesOrder->client?->cl_name ?? '-' }}</td>
            <td class="label">Product Type</td>
            <td>{{ ucfirst($salesOrder->product_type) }}</td>
        </tr>
        <tr>
            <td class="label">Order GST (%)</td>
            <td>{{ number_format((float) $salesOrder->gst_percent, 2, '.', '') }}</td>
            <td class="label">Transport Charge</td>
            <td>{{ number_format((float) $salesOrder->transport_charge, 2, '.', '') }}</td>
        </tr>
    </table>

    <table class="table">
        <thead>
        <tr>
            <th style="width: 36px;">#</th>
            <th>Item</th>
            <th style="width: 65px;">Unit</th>
            <th style="width: 110px;">Qty</th>
            <th style="width: 90px;">Rate</th>
            <th style="width: 100px;">Taxable</th>
            <th style="width: 65px;">GST %</th>
            <th style="width: 90px;">GST Amt</th>
            <th style="width: 95px;">Line Total</th>
        </tr>
        </thead>
        <tbody>
        @forelse($salesOrder->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->costSheet?->qty_unit ?? '-' }}</td>
                <td class="num">
                    <div>{{ number_format((float) $item->qty, 4, '.', '') }}</div>
                    @if($item->qty_mode === 'dimension')
                        <div class="dim-note">
                            L×W×Q:
                            {{ number_format((float) $item->length, 4, '.', '') }}
                            × {{ number_format((float) $item->width, 4, '.', '') }}
                            × {{ number_format((float) $item->pieces, 4, '.', '') }}
                        </div>
                    @endif
                </td>
                <td class="num">{{ number_format((float) $item->rate, 4, '.', '') }}</td>
                <td class="num">{{ number_format((float) $item->taxable_amount, 2, '.', '') }}</td>
                <td class="num">{{ number_format((float) $item->gst_percent, 2, '.', '') }}</td>
                <td class="num">{{ number_format((float) $item->gst_amount, 2, '.', '') }}</td>
                <td class="num">{{ number_format((float) $item->line_total, 2, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center;">No items</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Items Taxable</td>
            <td class="amount">{{ number_format((float) $itemsTotal, 2, '.', '') }}</td>
        </tr>
        <tr>
            <td class="label">Items GST</td>
            <td class="amount">{{ number_format((float) $salesOrder->items_gst_total, 2, '.', '') }}</td>
        </tr>
        <tr>
            <td class="label">Transport</td>
            <td class="amount">{{ number_format((float) $salesOrder->transport_charge, 2, '.', '') }}</td>
        </tr>
        <tr>
            <td class="label">Transport GST</td>
            <td class="amount">{{ number_format((float) $transportGst, 2, '.', '') }}</td>
        </tr>
        <tr>
            <td class="label">Roundoff</td>
            <td class="amount">{{ number_format((float) $salesOrder->roundoff, 2, '.', '') }}</td>
        </tr>
        <tr>
            <td class="label">Total Amount</td>
            <td class="amount">{{ number_format((float) $salesOrder->total_amount, 2, '.', '') }}</td>
        </tr>
    </table>

    <div class="remark">
        <strong>Remark:</strong> {{ $salesOrder->remark ?: '-' }}
    </div>

    <div class="footer">
        Generated at: {{ now()->format('d-m-Y H:i') }}
    </div>
</div>
</body>
</html>
