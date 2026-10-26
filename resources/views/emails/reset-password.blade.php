@extends('emails.layout')

@section('title', 'Reset your password')

@section('content')
    <p style="margin:0 0 12px;font-size:16px;">Hi {{ $customerName }},</p>
    <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
        We received a request to reset your Mavies Bugasan password. Click the button below to choose a new one.
    </p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
        <tr>
            <td style="background:#5D8F2F;border-radius:10px;">
                <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 26px;color:#FFFFFF;font-size:15px;font-weight:bold;text-decoration:none;">Reset password</a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#8A9A7E;">
        If the button does not work, copy this link into your browser:<br>
        <span style="color:#5D8F2F;word-break:break-all;">{{ $resetUrl }}</span>
    </p>
    <p style="margin:0;font-size:14px;line-height:1.6;">
        This link expires in {{ $expiresMinutes }} minutes. If you did not request a reset, you can safely ignore this email.
    </p>
@endsection
