@extends('emails.layout')

@section('title', 'Verify your email')

@section('content')
    <p style="margin:0 0 12px;font-size:16px;">Hi {{ $customerName }},</p>
    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
        Use this code to verify your email address and finish setting up your Mavies Bugasan account.
    </p>
    <div style="margin:0 0 16px;text-align:center;">
        <span style="display:inline-block;font-size:32px;font-weight:bold;letter-spacing:8px;color:#2D4A1E;background:#F5FAF0;border:1px solid #E3EBD9;border-radius:10px;padding:14px 22px;">{{ $code }}</span>
    </div>
    <p style="margin:0;font-size:14px;line-height:1.6;">
        This code expires in 10 minutes. If you did not create an account, you can ignore this email.
    </p>
@endsection
