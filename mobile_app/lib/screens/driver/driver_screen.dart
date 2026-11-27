import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/models.dart';
import '../../core/session.dart';
import '../../widgets/common.dart';
import '../../core/i18n.dart';

/// تطبيق الطيار: الطلبات المسندة له + اتصال + خرائط + تم التسليم
class DriverScreen extends StatefulWidget {
  const DriverScreen({super.key});

  @override
  State<DriverScreen> createState() => _DriverScreenState();
}

class _DriverScreenState extends State<DriverScreen> {
  List<Order>? orders;
  Map<String, dynamic>? summary;
  String? error;
  Timer? timer;

  @override
  void initState() {
    super.initState();
    load();
    timer = Timer.periodic(const Duration(seconds: 30), (_) => load());
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final res = await context.read<Session>().api.get('driver/orders');
      if (!mounted) return;
      setState(() {
        orders = (res['data'] as List).map((o) => Order.fromJson(o)).toList();
        summary = res['summary'];
        error = null;
      });
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    }
  }

  Future<void> _act(Order o, String action, String question) async {
    if (!await confirm(context, question)) return;
    if (!mounted) return;
    final res = await guard(context, () => context.read<Session>().api.post('driver/orders/${o.id}/$action'));
    if (res != null && mounted) {
      toast(context, tr('تم'));
      load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final cur = context.read<Session>().currency;
    if (orders == null) return error != null ? ErrorView(error!, onRetry: load) : const Center(child: CircularProgressIndicator());

    final active = orders!.where((o) => o.isOpen).toList();
    final done = orders!.where((o) => !o.isOpen).toList();

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(padding: const EdgeInsets.all(12), children: [
        Row(children: [
          Expanded(child: _box(tr('سلمت النهارده'), '${summary?['delivered_today'] ?? 0}', Colors.green)),
          const SizedBox(width: 8),
          Expanded(child: _box(tr('كاش معاك للتسليم'), '${money(summary?['cash_to_hand_over'] ?? 0)} $cur', kBrand)),
        ]),
        const SizedBox(height: 12),
        Text(tr('طلبات حالية ({0})', [active.length]), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        if (active.isEmpty) Padding(padding: EdgeInsets.all(32), child: EmptyView(tr('مفيش طلبات دلوقتي'), icon: Icons.delivery_dining)),
        for (final o in active)
          Card(
            margin: const EdgeInsets.symmetric(vertical: 6),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(children: [
                  Text('#${o.number}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  const SizedBox(width: 8),
                  StatusChip(o.status, label: o.statusLabel),
                  const Spacer(),
                  Text('${money(o.total)} $cur', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: kBrand)),
                ]),
                Text(o.isPaid ? tr('مدفوع مسبقاً ({0})', [o.paymentLabel]) : tr('حصّل كاش من العميل'),
                    style: TextStyle(color: o.isPaid ? Colors.green : Colors.red, fontWeight: FontWeight.bold)),
                const Divider(),
                Text(o.customerName ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                Text('${o.deliveryArea ?? ''} — ${o.address ?? ''}'),
                const SizedBox(height: 4),
                Text(o.items.map((i) => '${i.qty}× ${i.name}').join(tr('، ')), style: const TextStyle(color: Colors.grey)),
                if (o.notes != null) Text(tr('ملاحظات: {0}', [o.notes]), style: const TextStyle(color: Colors.orange)),
                const SizedBox(height: 8),
                Row(children: [
                  if (o.customerPhone != null)
                    IconButton.filledTonal(onPressed: () => callPhone(o.customerPhone!), icon: const Icon(Icons.call)),
                  const SizedBox(width: 6),
                  if (o.address != null)
                    IconButton.filledTonal(onPressed: () => openMap('${o.address} ${o.deliveryArea ?? ''}'), icon: const Icon(Icons.map_outlined)),
                  const Spacer(),
                  if (o.status != 'out_for_delivery')
                    OutlinedButton(onPressed: () => _act(o, 'pickup', tr('استلمت الطلب وخارج للتوصيل؟')), child: Text(tr('استلمت')))
                  else
                    FilledButton.icon(
                      style: FilledButton.styleFrom(backgroundColor: Colors.green.shade700),
                      onPressed: () => _act(o, 'delivered', o.isPaid ? tr('تم التسليم؟') : tr('تم التسليم وحصلت {0}؟', [money(o.total)])),
                      icon: const Icon(Icons.check),
                      label: Text(tr('تم التسليم')),
                    ),
                ]),
              ]),
            ),
          ),
        if (done.isNotEmpty) ...[
          const SizedBox(height: 12),
          Text(tr('تم النهارده'), style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
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
