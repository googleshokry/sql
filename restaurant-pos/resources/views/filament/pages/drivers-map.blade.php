<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>

    <style>
        .dm { display: grid; grid-template-columns: 340px 1fr; gap: 16px; }
        @media (max-width: 1000px) { .dm { grid-template-columns: 1fr; } }
        .dm-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; }
        .dark .dm-card { background: #18181b; border-color: #3f3f46; }
        .dm-list { max-height: calc(100vh - 220px); overflow-y: auto; }
        .dm-driver { padding: 12px; border-bottom: 1px solid #e5e7eb; cursor: pointer; }
        .dark .dm-driver { border-color: #3f3f46; }
        .dm-driver:hover { background: rgba(249, 115, 22, .06); }
        .dm-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-inline-end: 6px; }
        .dm-muted { color: #6b7280; font-size: 12px; }
        .dm-order { font-size: 12px; margin-top: 6px; padding: 6px 8px; border-radius: 8px; background: rgba(249, 115, 22, .08); }
        .dm-badge { font-size: 11px; padding: 1px 8px; border-radius: 999px; background: #e0e7ff; color: #3730a3; }
        #dm-map { height: calc(100vh - 220px); min-height: 420px; border-radius: 12px; z-index: 0; }
        .dm-pin { background: #f97316; color: #fff; border: 2px solid #fff; border-radius: 50%; width: 34px !important; height: 34px !important;
                  display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; box-shadow: 0 2px 6px rgba(0,0,0,.35); }
        .dm-pin.off { background: #9ca3af; }
        .dm-dest { background: #2563eb; color: #fff; border: 2px solid #fff; border-radius: 6px; width: 26px !important; height: 26px !important;
                   display: flex; align-items: center; justify-content: center; font-size: 14px; box-shadow: 0 2px 6px rgba(0,0,0,.3); }
    </style>

    <div class="dm" wire:poll.15s="refreshDrivers"
         x-data="driversMap(@js($drivers), @js(['online' => __('متصل'), 'offline' => __('غير متصل'), 'orders' => __('طلبات'), 'call' => __('اتصال')]))"
         x-init="init(); $wire.$watch('drivers', d => render(d))">
        <div class="dm-card dm-list">
            @forelse($drivers as $d)
                <div class="dm-driver" wire:key="d-{{ $d['id'] }}" x-on:click="focus({{ $d['id'] }})">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <div style="font-weight:700">
                            <span class="dm-dot" style="background: {{ $d['live'] ? '#22c55e' : '#9ca3af' }}"></span>{{ $d['name'] }}
                        </div>
                        @if($d['phone'])<a href="tel:{{ $d['phone'] }}" class="dm-muted" x-on:click.stop>📞 {{ $d['phone'] }}</a>@endif
                    </div>
                    <div class="dm-muted">
                        {{ $d['live'] ? __('متصل') : ($d['is_online'] ? __('الموقع غير متحدث') : __('خارج الشيفت')) }}
                        @if($d['last_seen']) · {{ __('آخر تحديث') }} {{ $d['last_seen'] }} @endif
                    </div>
                    @foreach($d['orders'] as $o)
                        <div class="dm-order">
                            <b>#{{ $o['number'] }}</b> <span class="dm-badge">{{ $o['stage_label'] }}</span>
                            <div>{{ $o['customer'] }} — {{ $o['area'] }}</div>
                            <div class="dm-muted">{{ $o['address'] }}</div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div style="padding:24px;text-align:center" class="dm-muted">{{ __('لا يوجد طيارين') }}</div>
            @endforelse
        </div>

        <div class="dm-card" wire:ignore>
            <div id="dm-map"></div>
        </div>
    </div>

    <script>
        function driversMap(initial, t) {
            return {
                map: null, layer: null, markers: {}, first: true,
                init() {
                    const center = @js([(float) config('pos.map_lat'), (float) config('pos.map_lng')]);
                    this.map = L.map('dm-map').setView(center, 12);
                    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19, attribution: '&copy; OpenStreetMap',
                    }).addTo(this.map);
                    this.layer = L.layerGroup().addTo(this.map);
                    this.render(initial);
                },
                render(drivers) {
                    this.layer.clearLayers();
                    this.markers = {};
                    const bounds = [];
                    for (const d of drivers) {
                        if (d.location) {
                            const icon = L.divIcon({ className: 'dm-pin' + (d.live ? '' : ' off'), html: d.name.trim().charAt(0), iconSize: [34, 34] });
                            const m = L.marker([d.location.lat, d.location.lng], { icon }).addTo(this.layer)
                                .bindPopup(`<b>${d.name}</b><br>${d.live ? t.online : t.offline} · ${d.last_seen ?? ''}<br>${t.orders}: ${d.orders.length}`
                                    + (d.phone ? `<br><a href="tel:${d.phone}">${t.call}</a>` : ''));
                            this.markers[d.id] = m;
                            bounds.push([d.location.lat, d.location.lng]);
                        }
                        for (const o of d.orders) {
                            if (o.lat && o.lng) {
                                const icon = L.divIcon({ className: 'dm-dest', html: '🏠', iconSize: [26, 26] });
                                L.marker([o.lat, o.lng], { icon }).addTo(this.layer).bindPopup(`<b>#${o.number}</b><br>${o.customer ?? ''}<br>${o.address ?? ''}`);
                                bounds.push([o.lat, o.lng]);
                                if (d.location) L.polyline([[d.location.lat, d.location.lng], [o.lat, o.lng]], { color: '#f97316', dashArray: '6 6', weight: 2 }).addTo(this.layer);
                            }
                        }
                    }
                    if (this.first && bounds.length) {
                        this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                        this.first = false;
                    }
                },
                focus(id) {
                    const m = this.markers[id];
                    if (m) { this.map.setView(m.getLatLng(), 16); m.openPopup(); }
                },
            };
        }
    </script>
</x-filament-panels::page>
