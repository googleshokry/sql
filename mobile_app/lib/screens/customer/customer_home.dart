import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/cart.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../widgets/common.dart';
import '../../widgets/menu_grid.dart';
import '../orders/order_details_screen.dart';
import '../../core/i18n.dart';
import '../../core/location_tracker.dart';

/// تطبيق العميل: المنيو + السلة + طلباتي + حسابي
class CustomerHome extends StatefulWidget {
  const CustomerHome({super.key});

  @override
  State<CustomerHome> createState() => _CustomerHomeState();
}

class _CustomerHomeState extends State<CustomerHome> {
  int tab = 0;
  final _ordersKey = GlobalKey<_MyOrdersState>();

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    return Scaffold(
      appBar: AppBar(title: Text(session.settings?.restaurantName ?? ''), actions: const [LangButton()]),
      body: IndexedStack(index: tab, children: [
        const MenuGrid(),
        MyOrders(key: _ordersKey),
        const _Profile(),
      ]),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      floatingActionButton: tab == 0
          ? CartBar(
              label: tr('السلة'),
              onTap: () async {
                final placed = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const CustomerCheckout()));
                if (placed == true) {
                  setState(() => tab = 1);
                  _ordersKey.currentState?.load();
                }
              },
            )
          : null,
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        onDestinationSelected: (i) {
          setState(() => tab = i);
          if (i == 1) _ordersKey.currentState?.load();
        },
        destinations: [
          NavigationDestination(icon: Icon(Icons.restaurant_menu), label: tr('المنيو')),
          NavigationDestination(icon: Icon(Icons.receipt_long), label: tr('طلباتي')),
          NavigationDestination(icon: Icon(Icons.person_outline), label: tr('حسابي')),
        ],
      ),
    );
  }
}

class CustomerCheckout extends StatefulWidget {
  const CustomerCheckout({super.key});

  @override
  State<CustomerCheckout> createState() => _CustomerCheckoutState();
}

class _CustomerCheckoutState extends State<CustomerCheckout> {
  String type = 'delivery';
  String payment = 'cash';
  int? areaId;
  late final TextEditingController address;
  final notes = TextEditingController();
  bool sending = false;
  double? lat, lng;
  bool locating = false;

  Future<void> _locate() async {
    setState(() => locating = true);
    final p = await LocationTracker.currentPosition(context);
    if (!mounted) return;
    setState(() {
      locating = false;
      if (p != null) {
        lat = p.latitude;
        lng = p.longitude;
      }
    });
    toast(context, p != null ? tr('تم تحديد موقعك') : tr('مقدرناش نحدد موقعك — اسمح بالوصول للموقع وشغل GPS'), error: p == null);
  }

  @override
  void initState() {
    super.initState();
    final user = context.read<Session>().user ?? {};
    address = TextEditingController(text: user['address'] ?? '');
    areaId = user['delivery_area_id'];
  }

  Future<void> submit() async {
    final session = context.read<Session>();
    final cart = context.read<Cart>();
    setState(() => sending = true);
    final res = await guard(context, () => session.api.post('customer/orders', {
          'type': type,
          'items': cart.toJson(),
          if (type == 'delivery') ...{'delivery_area_id': areaId, 'address': address.text.trim(), if (lat != null) 'lat': lat, if (lat != null) 'lng': lng},
          'payment_method': payment,
          'notes': notes.text.trim(),
        }));
    if (!mounted) return;
    setState(() => sending = false);
    if (res == null) return;

    final order = Order.fromJson(res['data']);
    cart.clear();
    if (type == 'delivery') session.updateUser({...?session.user, 'address': address.text.trim(), 'delivery_area_id': areaId});
    Navigator.pop(context, true);
    Navigator.push(context, MaterialPageRoute(builder: (_) => OrderDetailsScreen(orderId: order.id, customer: true)));
    toast(context, tr('تم إرسال طلبك #{0} 🎉', [order.number]));
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final cart = context.watch<Cart>();
    final fee = type == 'delivery' && areaId != null ? session.areas.where((a) => a.id == areaId).map((a) => a.fee).firstOrNull ?? 0.0 : 0.0;
    final t = Totals.calc(subtotal: cart.subtotal, discount: 0, type: type, deliveryFee: fee, taxRate: session.settings?.taxRate ?? 0);

    return Scaffold(
      appBar: AppBar(title: Text(tr('السلة'))),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        const CartLines(),
        const Divider(height: 24),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(value: 'delivery', label: Text(tr('توصيل')), icon: Icon(Icons.delivery_dining)),
            ButtonSegment(value: 'takeaway', label: Text(tr('استلام من المطعم')), icon: Icon(Icons.storefront)),
          ],
          selected: {type},
          onSelectionChanged: (s) => setState(() => type = s.first),
        ),
        if (type == 'delivery') ...[
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: areaId,
            decoration: InputDecoration(labelText: tr('المنطقة')),
            items: [for (final a in session.areas) DropdownMenuItem(value: a.id, child: Text(tr('{0} — توصيل {1}', [a.name, money(a.fee)])))],
            onChanged: (v) => setState(() => areaId = v),
          ),
          const SizedBox(height: 8),
          TextField(controller: address, maxLines: 2, decoration: InputDecoration(labelText: tr('العنوان بالتفصيل (شارع، عمارة، دور، شقة)'))),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: locating ? null : _locate,
            icon: locating
                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                : Icon(lat != null ? Icons.check_circle : Icons.my_location, color: lat != null ? Colors.green : null),
            label: Text(lat != null ? tr('تم تحديد موقعك على الخريطة') : tr('حدد موقعي الحالي (يساعد الطيار يوصلك أسرع)')),
          ),
        ],
        const SizedBox(height: 8),
        TextField(controller: notes, decoration: InputDecoration(labelText: tr('ملاحظات (اختياري)'))),
        const SizedBox(height: 12),
        Text(tr('طريقة الدفع'), style: TextStyle(fontWeight: FontWeight.bold)),
        Wrap(spacing: 6, children: [
          for (final m in kPaymentLabels.entries)
            ChoiceChip(label: Text(type == 'delivery' && m.key == 'cash' ? tr('كاش عند الاستلام') : m.value),
                selected: payment == m.key, onSelected: (_) => setState(() => payment = m.key)),
        ]),
        const Divider(height: 24),
        SumRow(tr('المجموع'), money(t.subtotal)),
        if (t.tax > 0) SumRow(tr('الضريبة'), money(t.tax)),
        if (t.deliveryFee > 0) SumRow(tr('التوصيل'), money(t.deliveryFee)),
        SumRow(tr('الإجمالي'), '${money(t.total)} ${session.currency}', bold: true, color: kBrand),
        const SizedBox(height: 16),
        FilledButton(
          style: FilledButton.styleFrom(padding: const EdgeInsets.all(16)),
          onPressed: cart.isEmpty || sending ? null : submit,
          child: sending ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2)) : Text(tr('تأكيد الطلب'), style: TextStyle(fontSize: 16)),
        ),
      ]),
    );
  }
}

class MyOrders extends StatefulWidget {
  const MyOrders({super.key});

  @override
  State<MyOrders> createState() => _MyOrdersState();
}

class _MyOrdersState extends State<MyOrders> {
  List<Order>? orders;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final res = await context.read<Session>().api.get('customer/orders');
      if (mounted) setState(() => orders = (res['data'] as List).map((o) => Order.fromJson(o)).toList());
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final cur = context.read<Session>().currency;
    if (orders == null) return error != null ? ErrorView(error!, onRetry: load) : const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: load,
      child: orders!.isEmpty
          ? ListView(children: [SizedBox(height: 120), EmptyView(tr('لسه معملتش طلبات'))])
          : ListView(padding: const EdgeInsets.all(12), children: [
              for (final o in orders!)
                Card(
                  child: ListTile(
                    onTap: () async {
                      await Navigator.push(context, MaterialPageRoute(builder: (_) => OrderDetailsScreen(orderId: o.id, customer: true)));
                      load();
                    },
                    title: Row(children: [Text('#${o.number}'), const SizedBox(width: 8), StatusChip(o.status, label: o.statusLabel)]),
                    subtitle: Text('${o.items.map((i) => i.name).join(tr('، '))}\n${timeAgo(o.createdAt)}', maxLines: 2, overflow: TextOverflow.ellipsis),
                    isThreeLine: true,
                    trailing: Text('${money(o.total)} $cur', style: const TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ),
            ]),
    );
  }
}

class _Profile extends StatefulWidget {
  const _Profile();

  @override
  State<_Profile> createState() => _ProfileState();
}

class _ProfileState extends State<_Profile> {
  late final TextEditingController name;
  late final TextEditingController address;
  int? areaId;

  @override
  void initState() {
    super.initState();
    final u = context.read<Session>().user ?? {};
    name = TextEditingController(text: u['name'] ?? '');
    address = TextEditingController(text: u['address'] ?? '');
    areaId = u['delivery_area_id'];
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    return ListView(padding: const EdgeInsets.all(16), children: [
      ListTile(leading: const Icon(Icons.phone), title: Text(session.user?['phone'] ?? '')),
      TextField(controller: name, decoration: InputDecoration(labelText: tr('الاسم'))),
      const SizedBox(height: 8),
      DropdownButtonFormField<int>(
        initialValue: areaId,
        decoration: InputDecoration(labelText: tr('المنطقة')),
        items: [for (final a in session.areas) DropdownMenuItem(value: a.id, child: Text(a.name))],
        onChanged: (v) => setState(() => areaId = v),
      ),
      const SizedBox(height: 8),
      TextField(controller: address, maxLines: 2, decoration: InputDecoration(labelText: tr('العنوان'))),
      const SizedBox(height: 16),
      FilledButton(
        onPressed: () async {
          final res = await guard(context, () => session.api.put('customer/profile', {
                'name': name.text.trim(), 'address': address.text.trim(), 'delivery_area_id': areaId,
              }));
          if (res != null && context.mounted) {
            session.updateUser(Map<String, dynamic>.from(res['user']));
            toast(context, tr('تم الحفظ'));
          }
        },
        child: Text(tr('حفظ')),
      ),
      const SizedBox(height: 24),
      OutlinedButton.icon(
        style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
        onPressed: () async {
          if (await confirm(context, tr('تسجيل الخروج؟'))) session.logout();
        },
        icon: const Icon(Icons.logout),
        label: Text(tr('تسجيل الخروج')),
      ),
    ]);
  }
}
