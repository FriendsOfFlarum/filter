{{-- Wraps the admin-authored body in core's informational email layout. --}}
<x-mail::html.information :title="$title" :body="$html" />
