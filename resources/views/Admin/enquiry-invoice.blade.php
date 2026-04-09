<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry {{ $enquiry->enquiry_no }}</title>
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
        <h1 class="title">Enquiry</h1>
        <div class="subtitle">Print Copy</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Order No</td>
            <td>{{ $enquiry->enquiry_no }}</td>
            <td class="label">Order Date</td>
            <td>{{ \Carbon\Carbon::parse($enquiry->enquiry_date)->format('d-m-Y') }}</td>
        </tr>
        <tr>
            <td class="label">Client</td>
            <td>{{ $enquiry->client?->cl_name ?? '-' }}</td>
            <td class="label">Product Type</td>
            <td>{{ ucfirst($enquiry->product_type) }}</td>
        </tr>
    </table>

    <table class="table">
        <thead>
        <tr>
            <th style="width: 36px;">#</th>
            <th>Item</th>
            <th style="width: 65px;">Unit</th>
            <th style="width: 110px;">Qty</th>
        </tr>
        </thead>
        <tbody>
        @forelse($enquiry->items as $index => $item)
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
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center;">No items</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if($enquiry->customItems && $enquiry->customItems->count() > 0)
    <h3 style="margin-top: 14px; font-weight: 600;">Custom Items</h3>
    <table class="table">
        <thead>
        <tr>
            <th style="width: 36px;">#</th>
            <th>Item Name</th>
            <th style="width: 110px;">Qty</th>
        </tr>
        </thead>
        <tbody>
        @foreach($enquiry->customItems as $ci_index => $ci)
            <tr>
                <td>{{ $ci_index + 1 }}</td>
                <td>{{ $ci->item_name }}</td>
                <td class="num">{{ number_format((float) $ci->qty, 4, '.', '') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    <div class="remark">
        <strong>Remark:</strong> {{ $enquiry->remark ?: '-' }}
    </div>

    <div class="footer">
        Generated at: {{ now()->format('d-m-Y H:i') }}
    </div>
</div>
</body>
</html>
