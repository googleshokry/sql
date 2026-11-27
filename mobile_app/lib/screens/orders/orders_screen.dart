import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/models.dart';
import '../../core/session.dart';
import '../../widgets/common.dart';
import 'order_details_screen.dart';

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({super.key});

  static const tabs = {
    'open': 'المفتوحة',
    'app': 'طلبات التطبيق',
    'unpaid': 'غير مدفوع',
    'today': 'اليوم',
    'all': 'الكل',
  };

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: tabs.length,
      child: Column(children: [
        TabBar(isScrollable: true, tabAlignment: TabAlignment.start, tabs: [for (final t in tabs.values) Tab(text: t)]),
        Expanded(child: TabBarView(children: [for (final f in tabs.keys) OrdersList(filter: f)])),
      ]),
    );
  }
}

class OrdersList extends StatefulWidget {
  const OrdersList({super.key, required this.filter});
  final String filter;

  @override
  State<OrdersList> createState() => _OrdersListState();
}

class _OrdersListState extends State<OrdersList> with AutomaticKeepAliveClientMixin {
  List<Order>? orders;
  String? error;
  String search = '';
  Timer? timer;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    load();
    timer = Timer.periodic(const Duration(seconds: 20), (_) => load(silent: true));
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> load({bool silent = false}) async {
    try {
      final res = await context.read<Session>().api.get('orders', {'filter': widget.filter, 'search': search});
      if (!mounted) return;
      setState(() {
        orders = (res['data'] as List).map((o) => Order.fromJson(o)).toList();
        error = null;
      });
    } catch (e) {
      if (mounted && !silent) setState(() => error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final cur = context.read<Session>().currency;
    if (error != null && orders == null) return ErrorView(error!, onRetry: load);
    if (orders == null) return const Center(child: CircularProgressIndicator());

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(padding: const EdgeInsets.only(bottom: 24), children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            onSubmitted: (v) {
              search = v.trim();
              load();
            },
            decoration: InputDecoration(
              hintText: 'رقم الطلب أو موبايل العميل',
              prefixIcon: const Icon(Icons.search),
              isDense: true,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
        ),
        if (orders!.isEmpty) const Padding(padding: EdgeInsets.only(top: 80), child: EmptyView('لا توجد طلبات')),
        for (final o in orders!)
          Card(
            margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: ListTile(
              onTap: () async {
                await Navigator.push(context, MaterialPageRoute(builder: (_) => OrderDetailsScreen(orderId: o.id)));
                load(silent: true);
              },
              leading: CircleAvatar(
                backgroundColor: statusColor(o.status).withValues(alpha: .15),
                child: Icon(
                  switch (o.type) { 'dine_in' => Icons.table_restaurant, 'delivery' => Icons.delivery_dining, _ => Icons.shopping_bag_outlined },
                  color: statusColor(o.status),
                ),
              ),
              title: Row(children: [
                Text('#${o.number}', style: const TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(width: 8),
                StatusChip(o.status, label: o.statusLabel),
                if (o.source == 'customer_app') ...[
                  const SizedBox(width: 4),
                  const Icon(Icons.phone_iphone, size: 16, color: Colors.green),
                ],
              ]),
              subtitle: Text([
                o.typeLabel,
                if (o.tableName != null) o.tableName!,
                if (o.customerName != null) o.customerName!,
                timeAgo(o.createdAt),
              ].join(' • ')),
              trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text('${money(o.total)} $cur', style: const TextStyle(fontWeight: FontWeight.bold)),
                Text(o.isPaid ? 'مدفوع' : 'غير مدفوع',
                    style: TextStyle(fontSize: 12, color: o.isPaid ? Colors.green : Colors.red)),
              ]),
            ),
          ),
      ]),
    );
  }
}
