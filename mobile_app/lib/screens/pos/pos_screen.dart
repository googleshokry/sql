import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/cart.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../widgets/common.dart';
import '../../widgets/menu_grid.dart';
import '../orders/order_details_screen.dart';
import '../../core/i18n.dart';

/// شاشة الكاشير: على التابلت قائمتين جنب بعض، وعلى الموبايل المنيو + صفحة الدفع
class PosScreen extends StatelessWidget {
  const PosScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      if (c.maxWidth >= 840) {
        return const Row(children: [
          Expanded(child: MenuGrid()),
          VerticalDivider(width: 1),
          SizedBox(width: 400, child: CheckoutPanel()),
        ]);
      }
      return Scaffold(
        body: const MenuGrid(),
        bottomNavigationBar: CartBar(
          onTap: () => Navigator.push(context, MaterialPageRoute(
            builder: (_) => Scaffold(appBar: AppBar(title: Text(tr('الطلب'))), body: const CheckoutPanel(popOnDone: true)),
          )),
        ),
      );
    });
  }
}

class CheckoutPanel extends StatefulWidget {
  const CheckoutPanel({super.key, this.popOnDone = false});
  final bool popOnDone;

  @override
  State<CheckoutPanel> createState() => _CheckoutPanelState();
}

class _CheckoutPanelState extends State<CheckoutPanel> {
  String type = 'takeaway';
  int? tableId;
  int? areaId;
  int? driverId;
  String payment = 'cash';
  bool discountPercent = false;
  bool sending = false;

  List<RestaurantTable> tables = [];
  List<Driver> drivers = [];

  final phone = TextEditingController();
  final name = TextEditingController();
  final address = TextEditingController();
  final discount = TextEditingController();
  final paid = TextEditingController();
  final notes = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _loadLists();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _loadLists() async {
    final api = context.read<Session>().api;
    await guard(context, () async {
      final r = await Future.wait([api.get('pos/tables'), api.get('pos/drivers')]);
      if (!mounted) return;
      setState(() {
        tables = (r[0]['data'] as List).map((t) => RestaurantTable.fromJson(t)).toList();
        drivers = (r[1]['data'] as List).map((d) => Driver.fromJson(d)).toList();
      });
    });
  }

  void _lookup(String value) {
    _debounce?.cancel();
    if (value.trim().length < 8) return;
    _debounce = Timer(const Duration(milliseconds: 600), () async {
      final res = await guard(context, () => context.read<Session>().api.get('pos/customers/lookup', {'phone': value.trim()}));
      final c = res?['data'];
      if (c == null || !mounted) return;
      setState(() {
        name.text = c['name'] ?? '';
        address.text = c['address'] ?? '';
        areaId = c['delivery_area_id'];
      });
      toast(context, tr('عميل مسجل: {0}', [c['name']]));
    });
  }

  Totals _totals(Session s, Cart cart) {
    final d = double.tryParse(discount.text) ?? 0;
    final fee = type == 'delivery' && areaId != null
        ? s.areas.where((a) => a.id == areaId).map((a) => a.fee).firstOrNull ?? 0.0
        : 0.0;
    return Totals.calc(
      subtotal: cart.subtotal,
      discount: discountPercent ? cart.subtotal * d.clamp(0, 100) / 100 : d,
      type: type,
      deliveryFee: fee,
      taxRate: s.settings?.taxRate ?? 0,
      serviceRate: s.settings?.serviceRate ?? 0,
    );
  }

  Future<void> _submit(bool pay) async {
    final session = context.read<Session>();
    final cart = context.read<Cart>();
    setState(() => sending = true);
    final res = await guard(context, () => session.api.post('pos/orders', {
          'type': type,
          'items': cart.toJson(),
          if (type == 'dine_in') 'table_id': tableId,
          if (phone.text.trim().isNotEmpty) 'customer': {'phone': phone.text.trim(), 'name': name.text.trim()},
          if (type == 'delivery') ...{'delivery_area_id': areaId, 'address': address.text.trim(), 'driver_id': driverId},
          'discount': double.tryParse(discount.text) ?? 0,
          'discount_type': discountPercent ? 'percent' : 'amount',
          'notes': notes.text.trim(),
          'pay': pay,
          'payment_method': payment,
          if (pay && paid.text.trim().isNotEmpty) 'paid_amount': double.tryParse(paid.text),
        }));
    if (!mounted) return;
    setState(() => sending = false);
    if (res == null) return;

    final order = Order.fromJson(res['data']);
    cart.clear();
    for (final c in [phone, name, address, discount, paid, notes]) {
      c.clear();
    }
    setState(() {
      tableId = null;
      driverId = null;
      areaId = null;
    });
    _loadLists();

    final change = order.changeAmount > 0 ? tr(' — الباقي {0}', [money(order.changeAmount)]) : '';
    toast(context, tr('تم حفظ الطلب {0}{1}', [order.number, change]));
    if (widget.popOnDone) Navigator.pop(context);
    Navigator.push(context, MaterialPageRoute(builder: (_) => OrderDetailsScreen(orderId: order.id)));
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final cart = context.watch<Cart>();
    final t = _totals(session, cart);
    final cur = session.currency;
    final openTable = tables.where((x) => x.id == tableId).firstOrNull;
    final due = t.total + (openTable?.openOrderTotal ?? 0);
    final paidValue = double.tryParse(paid.text) ?? 0;

    return ListView(padding: const EdgeInsets.all(12), children: [
      SegmentedButton<String>(
        segments: [
          ButtonSegment(value: 'dine_in', label: Text(tr('صالة')), icon: Icon(Icons.table_restaurant)),
          ButtonSegment(value: 'takeaway', label: Text(tr('تيك أواي')), icon: Icon(Icons.shopping_bag_outlined)),
          ButtonSegment(value: 'delivery', label: Text(tr('دليفري')), icon: Icon(Icons.delivery_dining)),
        ],
        selected: {type},
        onSelectionChanged: (s) => setState(() => type = s.first),
      ),
      const SizedBox(height: 12),
      if (type == 'dine_in') ...[
        Wrap(spacing: 6, runSpacing: 6, children: [
          for (final table in tables)
            ChoiceChip(
              label: Text(table.name),
              selected: tableId == table.id,
              onSelected: (_) => setState(() => tableId = tableId == table.id ? null : table.id),
              side: BorderSide(color: table.occupied ? Colors.red : Colors.green, width: 1.5),
              selectedColor: kBrand,
              showCheckmark: false,
            ),
        ]),
        if (openTable?.openOrderTotal != null)
          Container(
            margin: const EdgeInsets.only(top: 8),
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(color: Colors.amber.shade50, borderRadius: BorderRadius.circular(8)),
            child: Text(tr('على الطاولة طلب مفتوح بقيمة {0} — الأصناف الجديدة هتتضاف عليه', [money(openTable!.openOrderTotal!)]),
                style: TextStyle(color: Colors.amber.shade900, fontSize: 13)),
          ),
      ] else ...[
        Row(children: [
          Expanded(
            child: TextField(
              controller: phone,
              onChanged: _lookup,
              keyboardType: TextInputType.phone,
              textDirection: TextDirection.ltr,
              decoration: InputDecoration(labelText: type == 'delivery' ? tr('موبايل العميل') : tr('الموبايل (اختياري)'), isDense: true),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(child: TextField(controller: name, decoration: InputDecoration(labelText: tr('الاسم'), isDense: true))),
        ]),
      ],
      if (type == 'delivery') ...[
        const SizedBox(height: 8),
        Row(children: [
          Expanded(
            child: DropdownButtonFormField<int>(
              initialValue: areaId,
              isExpanded: true,
              decoration: InputDecoration(labelText: tr('المنطقة'), isDense: true),
              items: [for (final a in session.areas) DropdownMenuItem(value: a.id, child: Text('${a.name} (${money(a.fee)})'))],
              onChanged: (v) => setState(() => areaId = v),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: DropdownButtonFormField<int?>(
              initialValue: driverId,
              isExpanded: true,
              decoration: InputDecoration(labelText: tr('الطيار'), isDense: true),
              items: [
                DropdownMenuItem(value: null, child: Text(tr('لاحقاً'))),
                for (final d in drivers) DropdownMenuItem(value: d.id, child: Text(d.name)),
              ],
              onChanged: (v) => setState(() => driverId = v),
            ),
          ),
        ]),
        const SizedBox(height: 8),
        TextField(controller: address, maxLines: 2, decoration: InputDecoration(labelText: tr('العنوان بالتفصيل'), isDense: true)),
      ],
      const Divider(height: 24),
      const CartLines(),
      const Divider(height: 24),
      Row(children: [
        Expanded(
          child: TextField(
            controller: discount,
            onChanged: (_) => setState(() {}),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: tr('خصم'),
              isDense: true,
              suffix: InkWell(
                onTap: () => setState(() => discountPercent = !discountPercent),
                child: Text(discountPercent ? '%' : cur, style: const TextStyle(color: kBrand, fontWeight: FontWeight.bold)),
              ),
            ),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(child: TextField(controller: notes, decoration: InputDecoration(labelText: tr('ملاحظات الطلب'), isDense: true))),
      ]),
      const SizedBox(height: 12),
      SumRow(tr('المجموع'), money(t.subtotal)),
      if (t.discount > 0) SumRow(tr('الخصم'), '- ${money(t.discount)}'),
      if (t.service > 0) SumRow(tr('الخدمة'), money(t.service)),
      if (t.tax > 0) SumRow(tr('الضريبة'), money(t.tax)),
      if (t.deliveryFee > 0) SumRow(tr('التوصيل'), money(t.deliveryFee)),
      SumRow(tr('الإجمالي'), '${money(t.total)} $cur', bold: true, color: kBrand),
      if (openTable?.openOrderTotal != null) SumRow(tr('إجمالي الطاولة'), money(due), bold: true),
      const SizedBox(height: 12),
      Wrap(spacing: 6, children: [
        for (final m in kPaymentLabels.entries)
          ChoiceChip(
            label: Text(m.value),
            selected: payment == m.key,
            onSelected: (_) => setState(() => payment = m.key),
            selectedColor: kBrand,
            showCheckmark: false,
          ),
      ]),
      if (payment == 'cash') ...[
        const SizedBox(height: 8),
        Row(children: [
          Expanded(
            child: TextField(
              controller: paid,
              onChanged: (_) => setState(() {}),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(labelText: tr('المبلغ المستلم'), isDense: true),
            ),
          ),
          const SizedBox(width: 12),
          Text(tr('الباقي: {0}', [money(paidValue > due ? paidValue - due : 0)]),
              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green)),
        ]),
        Wrap(spacing: 6, children: [
          for (final b in [50, 100, 200, 500])
            ActionChip(label: Text('$b'), onPressed: () => setState(() => paid.text = '$b')),
        ]),
      ],
      const SizedBox(height: 16),
      FilledButton.icon(
        style: FilledButton.styleFrom(backgroundColor: Colors.green.shade700, padding: const EdgeInsets.all(16)),
        onPressed: cart.isEmpty || sending ? null : () => _submit(true),
        icon: const Icon(Icons.payments_outlined),
        label: Text(tr('دفع ({0})', [money(due)]), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
      ),
      const SizedBox(height: 8),
      Row(children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: cart.isEmpty || sending ? null : () => _submit(false),
            icon: const Icon(Icons.save_outlined),
            label: Text(tr('حفظ بدون دفع')),
          ),
        ),
        const SizedBox(width: 8),
        TextButton.icon(
          onPressed: cart.isEmpty ? null : () async {
            if (await confirm(context, tr('مسح الطلب الحالي؟'))) cart.clear();
          },
          icon: const Icon(Icons.delete_outline),
          label: Text(tr('مسح')),
        ),
      ]),
    ]);
  }
}
