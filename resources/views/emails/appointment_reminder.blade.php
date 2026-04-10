<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Appointment Reminder</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #333; }
    .wrapper { max-width: 560px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #f59e0b; color: #fff; padding: 28px 32px; }
    .header h1 { margin: 0; font-size: 22px; }
    .header p  { margin: 6px 0 0; font-size: 14px; opacity: .9; }
    .body { padding: 28px 32px; }
    .body p { font-size: 15px; line-height: 1.6; margin: 0 0 14px; }
    .highlight { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 16px 20px; margin: 18px 0; }
    .highlight .big-time { font-size: 28px; font-weight: bold; color: #92400e; }
    .highlight .big-date { font-size: 16px; color: #b45309; margin-top: 2px; }
    .detail-table { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px; }
    .detail-table td { padding: 9px 12px; border-bottom: 1px solid #eee; }
    .detail-table td:first-child { color: #666; width: 38%; }
    .detail-table td:last-child { font-weight: 600; }
    .footer { background: #f9fafb; padding: 18px 32px; font-size: 12px; color: #888; text-align: center; border-top: 1px solid #eee; }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <h1>Appointment Tomorrow</h1>
      <p>{{ $settings->company_name ?? config('app.name') }}</p>
    </div>

    <div class="body">
      @php
        $clientName = $appointment->client
            ? trim(($appointment->client->first_name ?? '') . ' ' . ($appointment->client->last_name ?? ''))
            : ($appointment->client_name ?: 'there');

        $customMessage = trim((string)($settings->email_appointments_reminder_message ?? ''));
        $customMessage = $customMessage !== ''
            ? strtr($customMessage, [
                '{date}'         => $appointment->start_at?->format('Y-m-d') ?? '',
                '{time}'         => $appointment->start_at?->format('H:i') ?? '',
                '{company_name}' => $settings->company_name ?? '',
              ])
            : null;
      @endphp

      <p>Hi {{ $clientName }},</p>

      @if($customMessage)
        <p>{{ $customMessage }}</p>
      @else
        <p>This is a friendly reminder that you have an appointment <strong>tomorrow</strong>.</p>
      @endif

      <div class="highlight">
        <div class="big-time">{{ $appointment->start_at?->format('H:i') }}</div>
        <div class="big-date">{{ $appointment->start_at?->format('l, d F Y') }}</div>
      </div>

      <table class="detail-table">
        @if($appointment->service)
        <tr>
          <td>Service</td>
          <td>{{ $appointment->service->name }}</td>
        </tr>
        @endif
        @if($appointment->staff?->user)
        <tr>
          <td>With</td>
          <td>{{ $appointment->staff->user->name }}</td>
        </tr>
        @endif
        @if(!empty($settings->company_address))
        <tr>
          <td>Location</td>
          <td>{{ $settings->company_address }}</td>
        </tr>
        @endif
        @if(!empty($settings->company_phone_number))
        <tr>
          <td>Contact</td>
          <td>{{ $settings->company_phone_number }}</td>
        </tr>
        @endif
      </table>

      <p style="font-size:13px; color:#666;">
        To cancel or reschedule, please contact us as soon as possible.
      </p>
    </div>

    <div class="footer">
      {{ $settings->company_name ?? config('app.name') }}
      @if(!empty($settings->company_phone_number)) &bull; {{ $settings->company_phone_number }} @endif
      <br>This is an automated reminder — please do not reply.
    </div>

  </div>
</body>
</html>
