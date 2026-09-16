@extends('layouts.public')

@section('title', 'Use Cases — Obscura')

@section('content')
<style>
    .uc-hero {
        text-align: center; padding: 80px 24px 48px;
        max-width: 640px; margin: 0 auto;
    }
    .uc-hero h1 { font-size: 2.5rem; margin-bottom: 12px; }
    .uc-hero p { font-size: 1.125rem; color: hsl(var(--muted-foreground)); line-height: 1.6; }
    .uc-section { max-width: 800px; margin: 0 auto; padding: 0 24px 64px; }
    .use-case {
        border: 1px solid hsl(var(--border));
        border-radius: var(--radius);
        padding: 32px; margin-bottom: 20px;
    }
    .use-case h2 { font-size: 1.25rem; margin-bottom: 4px; }
    .use-case .tag {
        display: inline-block; font-size: 0.75rem; font-weight: 500;
        color: hsl(var(--muted-foreground));
        border: 1px solid hsl(var(--border));
        padding: 2px 10px; border-radius: 9999px; margin-bottom: 12px;
    }
    .use-case p { font-size: 0.9375rem; color: hsl(var(--muted-foreground)); line-height: 1.7; }
    .use-case ul {
        margin-top: 16px; padding-left: 20px;
        font-size: 0.875rem; color: hsl(var(--muted-foreground));
        list-style: disc; line-height: 1.8;
    }
</style>

<section class="uc-hero">
    <h1>Use Cases</h1>
    <p>How teams and individuals use Obscura for private, encrypted image sharing.</p>
</section>

<section class="uc-section">

    <div class="use-case">
        <span class="tag">Photographers</span>
        <h2>Client Galleries</h2>
        <p>
            Deliver proof galleries to clients without exposing them publicly.
            Create a collection per client, generate a scoped access code with a 7-day expiry,
            and share the link. Clients don't need accounts — just the code.
            When the shoot is over, revoke the code or re-key the workspace.
        </p>
        <ul>
            <li>Scoped access codes per client or per gallery</li>
            <li>Time-boxed delivery — auto-expire after delivery window</li>
            <li>Revoke access instantly after final delivery</li>
            <li>Zero-knowledge — the server never sees your photos</li>
        </ul>
    </div>

    <div class="use-case">
        <span class="tag">Creative Teams</span>
        <h2>Collaborative Workspaces</h2>
        <p>
            Joint galleries let editors and contributors upload and organize media together.
            Every member gets their own sealed copy of the workspace key.
            When someone leaves, re-key to rotate the DEK and revoke their access —
            even if they had the key cached.
        </p>
        <ul>
            <li>Joint galleries with editor/viewer roles</li>
            <li>Per-member key sealing — each member unwraps with their own private key</li>
            <li>Hard revocation — re-key locks out removed members completely</li>
            <li>Encrypted names, descriptions, and comments</li>
        </ul>
    </div>

    <div class="use-case">
        <span class="tag">Legal &amp; Compliance</span>
        <h2>Confidential Evidence</h2>
        <p>
            Share sensitive visual evidence — site inspections, product photos,
            documentation scans — without the content touching an unencrypted server.
            Access codes can be limited to a single use or a few hours,
            and audit logs track every view.
        </p>
        <ul>
            <li>End-to-end encrypted — server stores only ciphertext</li>
            <li>Single-use or time-limited access codes</li>
            <li>Audit trail for every action</li>
            <li>No plaintext on the server — reduces breach exposure</li>
        </ul>
    </div>

    <div class="use-case">
        <span class="tag">Personal</span>
        <h2>Family Archives</h2>
        <p>
            Keep family photos private without trusting a cloud provider's
            encryption-at-rest promises. Obscura encrypts in your browser —
            the server literally cannot read your photos. Share specific
            galleries with family via access codes, keep the rest private.
        </p>
        <ul>
            <li>Browser-side encryption — zero trust in the server</li>
            <li>Share only what you choose, revoke anytime</li>
            <li>Recovery code protects against lost passwords</li>
            <li>Self-hostable on any PHP hosting</li>
        </ul>
    </div>

    <div class="use-case">
        <span class="tag">Journalists &amp; Researchers</span>
        <h2>Source Material</h2>
        <p>
            Receive sensitive photos, documents, and field imagery from sources
            without creating a plaintext trail. Sources upload via a joint gallery
            or a scoped access code — no account needed, no identifying metadata
            stored in the clear. When the story is done, revoke access and re-key.
        </p>
        <ul>
            <li>Sources upload without accounts — just an access code</li>
            <li>Content encrypted before it touches the server</li>
            <li>Audit log records every access for your records</li>
            <li>Re-key wipes all outstanding access in one step</li>
        </ul>
    </div>

    <div class="use-case">
        <span class="tag">Events &amp; Venues</span>
        <h2>Event Photo Delivery</h2>
        <p>
            Event photographers and venues can create a workspace per event,
            organize galleries by session or day, and hand out access codes to
            attendees. Codes can be set to expire after the event or after a
            download window closes — no lingering access, no forgotten links.
        </p>
        <ul>
            <li>One workspace per event, one gallery per session</li>
            <li>Print access codes on badges, signage, or programs</li>
            <li>Attendees view photos instantly — no app download</li>
            <li>Auto-expiring codes close access when the event ends</li>
        </ul>
    </div>

</section>
@endsection
