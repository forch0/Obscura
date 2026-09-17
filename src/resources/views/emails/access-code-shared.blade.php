<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
    <div style="max-width:480px;margin:40px auto;background:#ffffff;border:1px solid #e5e5e5;border-radius:12px;padding:40px;text-align:center">
        <p style="font-size:20px;font-weight:700;letter-spacing:-0.025em;margin:0 0 24px">Obscura</p>
        <p style="font-size:15px;color:#404040;line-height:1.6;margin:0 0 24px">
            Someone has shared an encrypted workspace with you.<br>
            Use this access code to enter:
        </p>
        <div style="font-family:ui-monospace,monospace;font-size:22px;letter-spacing:0.15em;font-weight:600;background:#f5f5f5;border:1px solid #e5e5e5;border-radius:8px;padding:16px;margin:0 0 24px">
            {{ $rawCode }}
        </div>
        <a href="{{ route('enter') }}" style="display:inline-block;background:#0a0a0a;color:#fafafa;text-decoration:none;font-size:14px;font-weight:500;padding:12px 28px;border-radius:999px">
            Enter code
        </a>
        <p style="font-size:12px;color:#737373;margin:24px 0 0">
            This code expires {{ $code->expires_at->format('M j, Y \a\t g:i A') }}.
            @if($code->max_uses > 0) It can be used up to {{ $code->max_uses }} time{{ $code->max_uses === 1 ? '' : 's' }}. @endif
        </p>
    </div>
    <p style="text-align:center;font-size:12px;color:#a3a3a3;margin:16px 0">
        Obscura — zero-trust content storage
    </p>
</body>
</html>
