@extends('emails.layout')

@section('title', 'Your password was reset')

@section('content')
    <p style="margin:0 0 12px;font-size:16px;">Hi {{ $customerName }},</p>
    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
        An administrator has reset your Mavies Bugasan password. Use this temporary password to sign in:
    </p>
    <div style="margin:0 0 16px;text-align:center;">
        <span style="display:inline-block;font-size:20px;font-weight:bold;letter-spacing:2px;color:#2D4A1E;background:#F5FAF0;border:1px solid #E3EBD9;border-radius:10px;padding:12px 20px;">{{ $temporaryPassword }}</span>
    </div>
    <p style="margin:0;font-size:14px;line-height:1.6;">
        For your security, please sign in and change this password right away from your account page.
    </p>
@endsection
