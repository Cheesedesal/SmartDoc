<div class="notification-center" data-notification-center data-endpoint="{{ route('notifications.index') }}">
    <button class="notification-bell" type="button" aria-label="Notifications" aria-expanded="false" data-notification-toggle>
        <span aria-hidden="true">♧</span>
        <span class="notification-count" data-notification-count hidden>0</span>
    </button>
    <section class="notification-popover" data-notification-panel hidden>
        <div class="notification-popover-heading"><strong>Notifications</strong><button type="button" data-notification-read-all>Mark all read</button></div>
        <div class="notification-items" data-notification-items><p class="muted-copy">Loading notifications…</p></div>
    </section>
</div>
