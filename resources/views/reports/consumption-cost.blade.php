<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Consumption Cost Analysis Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .summary {
            margin-bottom: 20px;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
        }

        .summary-row {
            margin-bottom: 5px;
        }

        .summary-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Consumption Cost Analysis Report</h1>
    </div>

    <div class="summary">
        <div class="summary-row">
            <span class="summary-label">Period:</span>
            {{ $summary['start_date'] }} to {{ $summary['end_date'] }}
        </div>
        <div class="summary-row">
            <span class="summary-label">Location:</span>
            {{ $summary['location'] }}
        </div>
        <div class="summary-row">
            <span class="summary-label">Category:</span>
            {{ $summary['category'] }}
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Items:</span>
            {{ $summary['total_items'] }}
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Cost:</span>
            <span style="font-family: DejaVu Sans; sans-serif;">₹</span>{{ number_format($summary['total_cost'], 2) }}
        </div>
        <div class="summary-row">
            <span class="summary-label">Total Transactions:</span>
            {{ $summary['total_transactions'] }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Category</th>
                <th>Unit</th>
                <th class="text-right">Total Quantity</th>
                <th class="text-right">Total Cost</th>
                <th class="text-right">Average Cost</th>
                <th class="text-right">Transactions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td>{{ $item['name'] }}</td>
                <td>{{ $item['code'] }}</td>
                <td>{{ $item['category'] }}</td>
                <td>{{ $item['unit'] }}</td>
                <td class="text-right">{{ number_format($item['total_quantity'], 2) }}</td>
                <td class="text-right"><span style="font-family: DejaVu Sans; sans-serif;">₹</span>{{ number_format($item['total_cost'], 2) }}</td>
                <td class="text-right"><span style="font-family: DejaVu Sans; sans-serif;">₹</span>{{ number_format($item['average_cost'], 2) }}</td>
                <td class="text-right">{{ $item['transaction_count'] }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5"><strong>Total</strong></td>
                <td class="text-right"><strong><span style="font-family: DejaVu Sans; sans-serif;">₹</span>{{ number_format($summary['total_cost'], 2) }}</strong></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on {{ date('d-m-Y H:i:s') }}</p>
    </div>
</body>

</html>