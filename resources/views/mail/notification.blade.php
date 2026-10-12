<x-mail::message>
# {{ $title }}

@if($recipientName)
{{ $recipientName }} 您好,
@endif

{{ $body }}

@if($actionUrl)
<x-mail::button :url="$actionUrl">
立即查看
</x-mail::button>
@endif

@if($senderName)
來自:{{ $senderName }}
@endif

<small>這是 {{ $appName }} 的系統通知信,請勿直接回覆。若不想再收到 Email 通知,可登入後到「個人帳號設定」關閉。</small>
</x-mail::message>
