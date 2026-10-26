@extends('emails.layout')

@section('title', 'Your staff password was reset')

@section('content')
    <p style="margin:0 0 12px;font-size:16px;">Hi {{ $staffName }},</p>
    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
        Your Mavies Bugasan staff password has been reset. Use this temporary password to sign in:
    </p>
    <div style="margin:0 0 16px;text-align:center;">
        <span style="display:inline-block;font-size:20px;font-weight:bold;letter-spacing:2px;color:#2D4A1E;background:#F5FAF0;border:1px solid #E3EBD9;border-radius:10px;padding:12px 20px;">{{ $temporaryPassword }}</span>
    </div>
    <p style="margin:0;font-size:14px;line-height:1.6;">
        For your security, please change this password after signing in.
    </p>
@endsection
