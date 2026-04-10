<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Appointment Confirmed</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; color: #333; }
    .wrapper { max-width: 560px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .header { background: #2563eb; color: #fff; padding: 28px 32px; }
    .header h1 { margin: 0; font-size: 22px; }
    .header p  { margin: 6px 0 0; font-size: 14px; opacity: .85; }
    .body { padding: 28px 32px; }
    .body p { font-size: 15px; line-height: 1.6; margin: 0 0 14px; }
    .detail-table { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px; }
    .detail-table td { padding: 9px 12px; border-bottom: 1px solid #eee; }
    .detail-table td:first-child { color: #666; width: 38%; }
    .detail-table td:last-child { font-weight: 600; }
    .notice { background: #eff6ff; border-left: 4px solid #2563eb; padding: 12px 16px; border-radius: 0 4px 4px 0; font-size: 13px; color: #1e40af; margin: 18px 0 0; }
    .footer { background: #f9fafb; padding: 18px 32px; font-size: 12px; color: #888; text-align: center; border-top: 1px solid #eee; }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <h1>Appointment Confirmed</h1>
      <p>{{ $appointment->settings->company_name ?? $settings->company_name ?? config('app.name') }}</p>
    </div>

    <div class="body">
      @php
        $clientName = $appointment->client
            ? trim(($appointment->client->first_name ?? '') . ' ' . ($appointment->client->last_name ?? ''))
            : ($appointment->client_name ?: 'there');
      @endphp

      <p>Hi {{ $clientName }},</p>
      <p>Your appointment has been confirmed. Here are the details:</p>

      <table class="detail-table">
        <tr>
          <td>Date</td>
          <td>{{ $appointment->start_at?->format('l, d F Y') }}</td>
        </tr>
        <tr>
          <td>Time</td>
          <td>{{ $appointment->start_at?->format('H:i') }} – {{ $appointment->end_at?->format('H:i') }}</td>
        </tr>
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
          <td>Phone</td>
          <td>{{ $settings->company_phone_number }}</td>
        </tr>
        @endif
      </table>

      @if($appointment->notes)
        <p><strong>Notes:</strong> {{ $appointment->notes }}</p>
      @endif

      <div class="notice">
        You will receive a reminder 24 hours before your appointment.
        To cancel or reschedule, please contact us directly.
      </div>
    </div>

    <div class="footer">
      {{ $settings->company_name ?? config('app.name') }}
      @if(!empty($settings->company_phone_number)) &bull; {{ $settings->company_phone_number }} @endif
      <br>This is an automated message — please do not reply.
    </div>

  </div>
</body>
</html>
