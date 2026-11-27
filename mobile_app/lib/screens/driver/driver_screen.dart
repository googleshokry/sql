import 'dart:async';

import 'package:latlong2/latlong.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/i18n.dart';
import '../../core/location_tracker.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../widgets/common.dart';
import '../../widgets/live_map.dart';

/// تطبيق الطيار: الشيفت + الموقع الحالي + مراحل توصيل الطلبات المسندة له
class DriverScreen extends StatefulWidget {
  const DriverScreen({super.key});

  @override
  State<DriverScreen> createState() => _DriverScreenState();
}

class _DriverScreenState extends State<DriverScreen> {
  late final LocationTracker tracker;
  List<Order>? orders;
  Map<String, dynamic>? summary;
  GeoPoint? serverLocation;
  String? error;
  Timer? timer;
  bool _resumed = false;

  @override
  void initState() {
    super.initState();
    tracker = LocationTracker(context.read<Session>().api);
    load();
    timer = Timer.periodic(const Duration(seconds: 30), (_) => load());
  }

  @override
  void dispose() {
    timer?.cancel();
    tracker.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final res = await context.read<Session>().api.get('driver/orders');
      if (!mounted) return;
      setState(() {
        orders = (res['data'] as List).map((o) => Order.fromJson(o)).toList();
        summary = res['summary'];
        serverLocation = GeoPoint.fromJson(res['driver']?['location']);
        error = null;
      });
      // لو كان في الشيفت قبل ما يقفل التطبيق نكمل التتبع
      if (!_resumed && res['driver']?['is_online'] == true && !tracker.online) {
        _resumed = true;
        tracker.goOnline();
      }
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    }
  }

  Future<void> _toggleShift(bool on) async {
    if (on) {
      final ok = await tracker.goOnline();
      if (!ok && mounted && tracker.error != null) toast(context, tracker.error!, error: true);
    } else {
      final active = orders?.where((o) => o.isOpen).length ?? 0;
      if (active > 0 && !await confirm(context, tr('معاك {0} طلب لسه متسلمش. تقفل الشيفت؟', [active]))) return;
      await tracker.goOffline();
    }
    load();
  }

  Future<void> _stage(Order o, String action, {String? question}) async {
    if (question != null && !await confirm(context, question)) return;
    if (!mounted) return;
    final res = await guard(context, () => context.read<Session>().api.post('driver/orders/${o.id}/$action'));
    if (res != null && mounted) {
      toast(context, tr('تم'));
      load();
    }
  }

  Future<void> _failed(Order o) async {
    final reasons = [tr('العميل مش بيرد'), tr('العنوان غلط'), tr('العميل رفض الطلب'), tr('العميل طلب التأجيل')];
    final other = TextEditingController();
    String? picked;
    final reason = await showDialog<String>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => AlertDialog(
          title: Text(tr('تعذر التسليم')),
          content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Wrap(spacing: 6, runSpacing: 6, children: [
              for (final r in reasons) ChoiceChip(label: Text(r), selected: picked == r, onSelected: (_) => set(() => picked = r)),
            ]),
            const SizedBox(height: 8),
            TextField(controller: other, decoration: InputDecoration(labelText: tr('سبب آخر'))),
            const SizedBox(height: 8),
            Text(tr('الطلب هيرجع للمطعم يتعين لطيار تاني'), style: const TextStyle(fontSize: 12, color: Colors.grey)),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c), child: Text(tr('إلغاء'))),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: Colors.red),
              onPressed: () => Navigator.pop(c, other.text.trim().isNotEmpty ? other.text.trim() : picked),
              child: Text(tr('تأكيد')),
            ),
          ],
        ),
      ),
    );
    if (reason == null || reason.isEmpty || !mounted) return;
    await guard(context, () => context.read<Session>().api.post('driver/orders/${o.id}/failed', {'reason': reason}));
    if (mounted) {
      toast(context, tr('تم إرجاع الطلب للمطعم'));
      load();
    }
  }

  void _navigate(Order o) {
    final d = o.destination;
    if (d != null) {
      openUrl('https://www.google.com/maps/dir/?api=1&destination=${d.lat},${d.lng}&travelmode=driving');
    } else if (o.address != null) {
      openMap('${o.address} ${o.deliveryArea ?? ''}');
    }
  }

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider.value(
      value: tracker,
      child: Builder(builder: (context) {
        final t = context.watch<LocationTracker>();
        final cur = context.read<Session>().currency;
        if (orders == null) return error != null ? ErrorView(error!, onRetry: load) : const Center(child: CircularProgressIndicator());

        final active = orders!.where((o) => o.isOpen).toList();
        final done = orders!.where((o) => !o.isOpen).toList();
        final me = t.last != null ? LatLng(t.last!.latitude, t.last!.longitude)
            : (serverLocation != null ? LatLng(serverLocation!.lat, serverLocation!.lng) : null);
        final dests = active.where((o) => o.destination != null).toList();

        return RefreshIndicator(
          onRefresh: load,
          child: ListView(padding: const EdgeInsets.all(12), children: [
            _ShiftCard(tracker: t, onChanged: _toggleShift),
            const SizedBox(height: 10),
            LiveMap(
              height: 230,
              pins: [
                if (me != null) MapPin(me, child: DriverPin(live: t.online)),
                for (final o in dests) MapPin(LatLng(o.destination!.lat, o.destination!.lng), size: 30, child: const HomePin()),
              ],
              lines: [
                if (me != null)
                  for (final o in dests) [me, LatLng(o.destination!.lat, o.destination!.lng)],
              ],
            ),
            const SizedBox(height: 10),
            Row(children: [
              Expanded(child: _box(tr('سلمت النهارده'), '${summary?['delivered_today'] ?? 0}', Colors.green)),
              const SizedBox(width: 8),
              Expanded(child: _box(tr('كاش معاك للتسليم'), '${money(summary?['cash_to_hand_over'] ?? 0)} $cur', kBrand)),
            ]),
            const SizedBox(height: 12),
            Text(tr('طلبات حالية ({0})', [active.length]), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            if (active.isEmpty) Padding(padding: const EdgeInsets.all(32), child: EmptyView(tr('مفيش طلبات دلوقتي'), icon: Icons.delivery_dining)),
            for (final o in active) _orderCard(o, cur),
            if (done.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(tr('تم النهارده'), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              for (final o in done)
                ListTile(
                  title: Text('#${o.number} — ${o.customerName ?? ''}'),
                  subtitle: Text(o.deliveryArea ?? ''),
                  trailing: Text(money(o.total)),
                  leading: Icon(o.status == 'cancelled' ? Icons.cancel : Icons.check_circle, color: statusColor(o.status)),
                ),
            ],
          ]),
        );
      }),
    );
  }

  Widget _orderCard(Order o, String cur) {
    final stage = o.deliveryStage ?? 'assigned';
    const steps = ['assigned', 'picked_up', 'arrived', 'delivered'];
    final idx = steps.indexOf(stage);
    final labels = [tr('مسند لك'), tr('استلمت'), tr('وصلت'), tr('تم التسليم')];

    Widget action;
    switch (stage) {
      case 'assigned':
        action = FilledButton.icon(
          onPressed: () => _stage(o, 'pickup'),
          icon: const Icon(Icons.shopping_bag_outlined),
          label: Text(tr('استلمت من المطعم')),
        );
      case 'picked_up':
        action = FilledButton.icon(
          style: FilledButton.styleFrom(backgroundColor: Colors.indigo),
          onPressed: () => _stage(o, 'arrived'),
          icon: const Icon(Icons.location_on),
          label: Text(tr('وصلت للعميل')),
        );
      default:
        action = FilledButton.icon(
          style: FilledButton.styleFrom(backgroundColor: Colors.green.shade700),
          onPressed: () => _stage(o, 'delivered',
              question: o.isPaid ? tr('تم التسليم؟') : tr('تم التسليم وحصلت {0}؟', [money(o.total)])),
          icon: const Icon(Icons.check),
          label: Text(tr('تم التسليم')),
        );
    }

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            Text('#${o.number}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const Spacer(),
            Text('${money(o.total)} $cur', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: kBrand)),
          ]),
          const SizedBox(height: 8),
          // شريط المراحل
          Row(children: [
            for (var i = 0; i < steps.length; i++) ...[
              Expanded(
                child: Column(children: [
                  Container(height: 5, decoration: BoxDecoration(
                      color: i <= idx ? kBrand : Colors.grey.shade300, borderRadius: BorderRadius.circular(4))),
                  const SizedBox(height: 4),
                  Text(labels[i], style: TextStyle(fontSize: 11, fontWeight: i == idx ? FontWeight.bold : FontWeight.normal,
                      color: i <= idx ? null : Colors.grey)),
                ]),
              ),
              if (i < steps.length - 1) const SizedBox(width: 4),
            ],
          ]),
          const SizedBox(height: 8),
          Text(o.isPaid ? tr('مدفوع مسبقاً ({0})', [o.paymentLabel]) : tr('حصّل كاش من العميل'),
              style: TextStyle(color: o.isPaid ? Colors.green : Colors.red, fontWeight: FontWeight.bold)),
          const Divider(),
          Text(o.customerName ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
          Text('${o.deliveryArea ?? ''} — ${o.address ?? ''}'),
          if (o.destination != null)
            Text(tr('📍 العميل محدد موقعه على الخريطة'), style: TextStyle(fontSize: 12, color: Colors.blue.shade700)),
          const SizedBox(height: 4),
          Text(o.items.map((i) => '${i.qty}× ${i.name}').join(tr('، ')), style: const TextStyle(color: Colors.grey)),
          if (o.notes != null) Text(tr('ملاحظات: {0}', [o.notes]), style: const TextStyle(color: Colors.orange)),
          const SizedBox(height: 10),
          Row(children: [
            if (o.customerPhone != null)
              IconButton.filledTonal(tooltip: tr('اتصال'), onPressed: () => callPhone(o.customerPhone!), icon: const Icon(Icons.call)),
            const SizedBox(width: 6),
            IconButton.filledTonal(tooltip: tr('الاتجاهات'), onPressed: () => _navigate(o), icon: const Icon(Icons.navigation_outlined)),
            const SizedBox(width: 6),
            IconButton(
              tooltip: tr('تعذر التسليم'),
              color: Colors.red,
              onPressed: () => _failed(o),
              icon: const Icon(Icons.report_problem_outlined),
            ),
            const Spacer(),
            action,
          ]),
        ]),
      ),
    );
  }

  Widget _box(String label, String value, Color color) => Card(
        elevation: 0,
        color: color.withValues(alpha: .1),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(children: [
            Text(label, style: TextStyle(color: color)),
            const SizedBox(height: 4),
            FittedBox(child: Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
          ]),
        ),
      );
}

class _ShiftCard extends StatelessWidget {
  const _ShiftCard({required this.tracker, required this.onChanged});
  final LocationTracker tracker;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final t = tracker;
    final color = t.online ? Colors.green : Colors.grey;
    String status;
    if (!t.online) {
      status = tr('خارج الشيفت — المطعم مش شايف موقعك');
    } else if (t.lastSent == null) {
      status = tr('جاري تحديد موقعك...');
    } else {
      status = tr('الموقع اتبعت {0} • الدقة {1} م', [timeAgo(t.lastSent!), t.last?.accuracy.round() ?? '-']);
    }

    return Card(
      color: color.withValues(alpha: .08),
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            Icon(t.online ? Icons.gps_fixed : Icons.gps_off, color: color),
            const SizedBox(width: 8),
            Expanded(
              child: Text(t.online ? tr('في الشيفت') : tr('خارج الشيفت'),
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: color)),
            ),
            if (t.busy) const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
            Switch(value: t.online, onChanged: t.busy ? null : onChanged),
          ]),
          Text(status, style: const TextStyle(fontSize: 12)),
          if (t.last != null)
            Text('${t.last!.latitude.toStringAsFixed(5)}, ${t.last!.longitude.toStringAsFixed(5)}',
                textDirection: TextDirection.ltr, style: const TextStyle(fontSize: 11, color: Colors.grey)),
          if (t.error != null) Text(t.error!, style: const TextStyle(fontSize: 12, color: Colors.red)),
        ]),
      ),
    );
  }
}
