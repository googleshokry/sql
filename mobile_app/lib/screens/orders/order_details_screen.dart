import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/models.dart';
import '../../core/session.dart';
import '../../widgets/common.dart';
import '../../core/i18n.dart';

/// تفاصيل الطلب — للكاشير/المدير (مع الإجراءات) أو للعميل (تتبع الحالة)
class OrderDetailsScreen extends StatefulWidget {
  const OrderDetailsScreen({super.key, required this.orderId, this.customer = false});
  final int orderId;
  final bool customer;

  @override
  State<OrderDetailsScreen> createState() => _OrderDetailsScreenState();
}

class _OrderDetailsScreenState extends State<OrderDetailsScreen> {
  Order? order;
  String? error;
  Timer? timer;

  String get _path => widget.customer ? 'customer/orders/${widget.orderId}' : 'orders/${widget.orderId}';

  @override
  void initState() {
    super.initState();
    load();
    // العميل يتابع حالة الطلب أوتوماتيك
    if (widget.customer) timer = Timer.periodic(const Duration(seconds: 15), (_) => load());
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final res = await context.read<Session>().api.get(_path);
      if (mounted) setState(() => order = Order.fromJson(res['data']));
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    }
  }

  Future<void> _action(String path, [Map<String, dynamic>? body]) async {
    final res = await guard(context, () => context.read<Session>().api.post(path, body));
    if (res != null && mounted) {
      setState(() => order = Order.fromJson(res['data']));
      toast(context, tr('تم'));
    }
  }

  Future<void> _pay() async {
    final o = order!;
    String method = 'cash';
    final paid = TextEditingController(text: money(o.total).replaceAll(',', ''));
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => AlertDialog(
          title: Text(tr('تحصيل {0}', [money(o.total)])),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            Wrap(spacing: 6, children: [
              for (final m in kPaymentLabels.entries)
                ChoiceChip(label: Text(m.value), selected: method == m.key, onSelected: (_) => set(() => method = m.key)),
            ]),
            if (method == 'cash')
              TextField(
                controller: paid,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: tr('المبلغ المستلم')),
              ),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: Text(tr('إلغاء'))),
            FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(tr('تحصيل'))),
          ],
        ),
      ),
    );
    if (ok != true) return;
    await _action('orders/${o.id}/pay', {
      'payment_method': method,
      if (method == 'cash' && paid.text.isNotEmpty) 'paid_amount': double.tryParse(paid.text),
    });
    if (mounted && order!.changeAmount > 0) toast(context, tr('الباقي للعميل: {0}', [money(order!.changeAmount)]));
  }

  Future<void> _changeStatus() async {
    final isAdmin = context.read<Session>().role == 'admin';
    final status = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          for (final s in kStatusLabels.entries)
            if (s.key != 'cancelled' || isAdmin)
              ListTile(
                leading: Icon(Icons.circle, color: statusColor(s.key), size: 14),
                title: Text(s.value),
                selected: order!.status == s.key,
                onTap: () => Navigator.pop(c, s.key),
              ),
        ]),
      ),
    );
    if (status == null || !mounted) return;
    if (status == 'cancelled' && !await confirm(context, tr('إلغاء الطلب؟'))) return;
    await _action('orders/${order!.id}/status', {'status': status});
  }

  Future<void> _assignDriver() async {
    final res = await guard(context, () => context.read<Session>().api.get('pos/drivers'));
    if (res == null || !mounted) return;
    final drivers = (res['data'] as List).map((d) => Driver.fromJson(d)).toList();
    final id = await showModalBottomSheet<int>(
      context: context,
      builder: (c) => SafeArea(
        child: ListView(shrinkWrap: true, children: [
          for (final d in drivers)
            ListTile(leading: const Icon(Icons.delivery_dining), title: Text(d.name), subtitle: Text(d.phone ?? ''),
                onTap: () => Navigator.pop(c, d.id)),
        ]),
      ),
    );
    if (id != null) await _action('orders/${order!.id}/driver', {'driver_id': id});
  }

  @override
  Widget build(BuildContext context) {
    final cur = context.read<Session>().currency;
    final o = order;

    return Scaffold(
      appBar: AppBar(
        title: Text(o == null ? tr('الطلب') : tr('طلب #{0}', [o.number])),
        actions: [
          if (o != null)
            IconButton(tooltip: tr('الفاتورة / طباعة'), icon: const Icon(Icons.receipt_long), onPressed: () => openUrl(o.receiptUrl)),
        ],
      ),
      body: o == null
          ? (error != null ? ErrorView(error!, onRetry: load) : const Center(child: CircularProgressIndicator()))
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(padding: const EdgeInsets.all(16), children: [
                if (widget.customer) _Tracker(o) else Row(children: [
                  StatusChip(o.status, label: o.statusLabel),
                  const SizedBox(width: 8),
                  Text(o.typeLabel, style: const TextStyle(fontWeight: FontWeight.bold)),
                  const Spacer(),
                  Text(timeAgo(o.createdAt), style: const TextStyle(color: Colors.grey)),
                ]),
                const SizedBox(height: 12),
                if (o.tableName != null) ListTile(leading: const Icon(Icons.table_restaurant), title: Text(tr('طاولة {0}', [o.tableName]))),
                if (o.customerName != null && !widget.customer)
                  ListTile(
                    leading: const Icon(Icons.person_outline),
                    title: Text(o.customerName!),
                    subtitle: Text(o.customerPhone ?? ''),
                    trailing: o.customerPhone == null ? null : IconButton(icon: const Icon(Icons.call), onPressed: () => callPhone(o.customerPhone!)),
                  ),
                if (o.address != null)
                  ListTile(
                    leading: const Icon(Icons.location_on_outlined),
                    title: Text(o.address!),
                    subtitle: o.deliveryArea == null ? null : Text(o.deliveryArea!),
                  ),
                if (o.driverName != null)
                  ListTile(
                    leading: const Icon(Icons.delivery_dining),
                    title: Text(tr('الطيار: {0}', [o.driverName])),
                    trailing: o.driverPhone == null ? null : IconButton(icon: const Icon(Icons.call), onPressed: () => callPhone(o.driverPhone!)),
                  ),
                const Divider(),
                for (final i in o.items)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(radius: 14, child: Text('${i.qty}', style: const TextStyle(fontSize: 12))),
                    title: Text(i.name),
                    subtitle: i.notes == null ? null : Text(i.notes!),
                    trailing: Text(money(i.total)),
                  ),
                const Divider(),
                SumRow(tr('المجموع'), money(o.subtotal)),
                if (o.discount > 0) SumRow(tr('الخصم'), '- ${money(o.discount)}'),
                if (o.service > 0) SumRow(tr('الخدمة'), money(o.service)),
                if (o.tax > 0) SumRow(tr('الضريبة'), money(o.tax)),
                if (o.deliveryFee > 0) SumRow(tr('التوصيل'), money(o.deliveryFee)),
                SumRow(tr('الإجمالي'), '${money(o.total)} $cur', bold: true, color: kBrand),
                SumRow(tr('الدفع'), o.isPaid ? (o.paymentLabel ?? tr('مدفوع')) : tr('غير مدفوع'), color: o.isPaid ? Colors.green : Colors.red),
                if (o.notes != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(tr('ملاحظات: {0}', [o.notes]))),
                const SizedBox(height: 20),
                if (widget.customer) ...[
                  if (o.status == 'pending')
                    OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                      onPressed: () async {
                        if (await confirm(context, tr('إلغاء الطلب؟'))) _action('customer/orders/${o.id}/cancel');
                      },
                      icon: const Icon(Icons.close),
                      label: Text(tr('إلغاء الطلب')),
                    ),
                ] else if (o.status != 'cancelled') ...[
                  if (!o.isPaid)
                    FilledButton.icon(
                      style: FilledButton.styleFrom(backgroundColor: Colors.green.shade700, padding: const EdgeInsets.all(14)),
                      onPressed: _pay,
                      icon: const Icon(Icons.payments_outlined),
                      label: Text(tr('تحصيل')),
                    ),
                  const SizedBox(height: 8),
                  Row(children: [
                    Expanded(child: OutlinedButton.icon(onPressed: _changeStatus, icon: const Icon(Icons.sync), label: Text(tr('تغيير الحالة')))),
                    if (o.type == 'delivery') ...[
                      const SizedBox(width: 8),
                      Expanded(child: OutlinedButton.icon(onPressed: _assignDriver, icon: const Icon(Icons.delivery_dining), label: Text(tr('تعيين طيار')))),
                    ],
                  ]),
                ],
              ]),
            ),
    );
  }
}

/// شريط تتبع الطلب للعميل
class _Tracker extends StatelessWidget {
  const _Tracker(this.order);
  final Order order;

  @override
  Widget build(BuildContext context) {
    if (order.status == 'cancelled') {
      return Card(color: Color(0xFFFEE2E2), child: ListTile(leading: Icon(Icons.cancel, color: Colors.red), title: Text(tr('تم إلغاء الطلب'))));
    }
    final steps = [
      ('pending', tr('تم الاستلام'), Icons.receipt_long),
      ('preparing', tr('بيتحضر'), Icons.soup_kitchen),
      if (order.type == 'delivery') ('out_for_delivery', tr('في الطريق'), Icons.delivery_dining) else ('ready', tr('جاهز للاستلام'), Icons.shopping_bag),
      ('completed', tr('تم التسليم'), Icons.check_circle),
    ];
    const order_ = ['pending', 'preparing', 'ready', 'out_for_delivery', 'completed'];
    final current = order_.indexOf(order.status);

    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
        child: Row(children: [
          for (final (i, s) in steps.indexed) ...[
            Expanded(
              child: Column(children: [
                Icon(s.$3, size: 30, color: order_.indexOf(s.$1) <= current ? kBrand : Colors.grey.shade300),
                const SizedBox(height: 4),
                Text(s.$2, textAlign: TextAlign.center, style: TextStyle(fontSize: 12,
                    fontWeight: order_.indexOf(s.$1) <= current ? FontWeight.bold : FontWeight.normal)),
              ]),
            ),
            if (i < steps.length - 1) Icon(Icons.chevron_right, color: Colors.grey.shade400),
          ],
        ]),
      ),
    );
  }
}
