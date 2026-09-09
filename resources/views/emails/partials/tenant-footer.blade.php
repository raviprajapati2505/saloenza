@if(!empty($portalName))
    <p style="margin-top: 24px; color: #64748b; font-size: 12px;">
        — {{ $portalName }}
        @if(!empty($supportEmail))
            · {{ $supportEmail }}
        @endif
        @if(!empty($supportPhone))
            · {{ $supportPhone }}
        @endif
    </p>
@endif
