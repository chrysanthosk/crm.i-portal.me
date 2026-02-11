<!doctype html>
<html>
  <body>
    <p>You requested to change your email address for <strong>{{ config('app.name') }}</strong>.</p>
    <p>Click the link below to confirm:</p>
    <p><a href="{{ $confirmUrl }}">{{ $confirmUrl }}</a></p>
    <p>If you did not request this, you can ignore this email.</p>
  </body>
</html>
