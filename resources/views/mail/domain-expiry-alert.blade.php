<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Expiry Alert</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        h2 { color: #c0392b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .urgent { color: #c0392b; font-weight: bold; }
        .warning { color: #e67e22; font-weight: bold; }
        .ok { color: #27ae60; }
    </style>
</head>
<body>
    <h2>Domain Expiry Alert</h2>
    <p>
        The following {{ $domains->count() === 1 ? 'domain expires' : 'domains expire' }}
        within <strong>{{ $alertDays }} days</strong> and {{ $domains->count() === 1 ? 'does' : 'do' }}
        not have auto-renewal enabled. Please renew {{ $domains->count() === 1 ? 'it' : 'them' }} before expiry.
    </p>

    <table>
        <thead>
            <tr>
                <th>Domain</th>
                <th>Expires</th>
                <th>Days Left</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($domains as $domain)
            @php $days = $domain->days_until_expiry; @endphp
            <tr>
                <td><strong>{{ $domain->name }}</strong></td>
                <td>{{ $domain->expires_at->format('Y-m-d') }}</td>
                <td class="{{ $days <= 7 ? 'urgent' : ($days <= 30 ? 'warning' : 'ok') }}">
                    {{ $days }} day{{ $days === 1 ? '' : 's' }}
                </td>
                <td>{{ $domain->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 24px; font-size: 12px; color: #999;">
        This alert was generated automatically by My Domains.
    </p>
</body>
</html>
