import 'package:intl/intl.dart' as intl;
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../widgets/common.dart';
import '../../core/i18n.dart';

/// تطبيق المالك: مبيعات وأرباح وتقارير بالفترة
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  DateTimeRange range = DateTimeRange(start: DateUtils.dateOnly(DateTime.now()), end: DateUtils.dateOnly(DateTime.now()));
  Map<String, dynamic>? data;
  String? error;

  final _fmt = intl.DateFormat('yyyy-MM-dd');

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final res = await context.read<Session>().api.get('dashboard', {'from': _fmt.format(range.start), 'to': _fmt.format(range.end)});
      if (!mounted) return;
      setState(() {
        data = res;
        error = null;
      });
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    }
  }

  void _preset(int days) {
    final today = DateUtils.dateOnly(DateTime.now());
    setState(() => range = DateTimeRange(start: today.subtract(Duration(days: days)), end: today));
    load();
  }

  @override
  Widget build(BuildContext context) {
    final cur = context.read<Session>().currency;
    final d = data;
    if (d == null) return error != null ? ErrorView(error!, onRetry: load) : const Center(child: CircularProgressIndicator());

    double n(String k) => (d[k] as num?)?.toDouble() ?? 0;
    final daily = (d['daily'] as List).cast<Map>();
    final maxDay = daily.fold<double>(0, (m, x) => (x['total'] as num).toDouble() > m ? (x['total'] as num).toDouble() : m);

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(padding: const EdgeInsets.all(12), children: [
        Wrap(spacing: 6, runSpacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [
          ActionChip(label: Text(tr('اليوم')), onPressed: () => _preset(0)),
          ActionChip(label: Text(tr('7 أيام')), onPressed: () => _preset(6)),
          ActionChip(label: Text(tr('30 يوم')), onPressed: () => _preset(29)),
          ActionChip(
            avatar: const Icon(Icons.date_range, size: 18),
            label: Text('${_fmt.format(range.start)} ← ${_fmt.format(range.end)}'),
            onPressed: () async {
              final r = await showDateRangePicker(context: context, firstDate: DateTime(2020), lastDate: DateTime.now(), initialDateRange: range);
              if (r != null) {
                setState(() => range = r);
                load();
              }
            },
          ),
        ]),
        const SizedBox(height: 12),
        GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: MediaQuery.sizeOf(context).width > 700 ? 4 : 2,
          mainAxisSpacing: 8,
          crossAxisSpacing: 8,
          childAspectRatio: 1.7,
          children: [
            _Stat(tr('المبيعات'), '${money(n('sales'))} $cur', Icons.payments, Colors.green),
            _Stat(tr('عدد الطلبات'), '${d['orders_count']}', Icons.receipt_long, Colors.blue),
            _Stat(tr('متوسط الطلب'), money(n('average_order')), Icons.trending_up, Colors.purple),
            _Stat(tr('ربح تقديري'), money(n('estimated_profit')), Icons.savings, Colors.teal),
            _Stat(tr('غير محصل'), money(n('unpaid')), Icons.hourglass_bottom, Colors.red),
            _Stat(tr('طلبات مفتوحة'), '${d['open_orders']}', Icons.pending_actions, Colors.orange),
            _Stat(tr('الخصومات'), money(n('discounts')), Icons.discount, Colors.pink),
            _Stat(tr('ملغي'), '${d['cancelled']}', Icons.cancel, Colors.grey),
          ],
        ),
        if (daily.length > 1) _Section(tr('المبيعات اليومية'), [
          for (final x in daily)
            _Bar(label: (x['day'] as String).substring(5), value: (x['total'] as num).toDouble(), max: maxDay),
        ]),
        _Section(tr('حسب نوع الطلب'), [
          for (final x in (d['by_type'] as List))
            ListTile(dense: true, title: Text(x['label']), subtitle: Text(tr('{0} طلب', [x['count']])), trailing: Text(money(x['total']))),
        ]),
        _Section(tr('طرق الدفع'), [
          for (final x in (d['by_payment'] as List))
            ListTile(dense: true, title: Text(x['label'] ?? '-'), trailing: Text(money(x['total']))),
        ]),
        _Section(tr('الأكثر مبيعاً'), [
          for (final (i, x) in (d['top_products'] as List).indexed)
            ListTile(dense: true, leading: CircleAvatar(radius: 13, child: Text('${i + 1}', style: const TextStyle(fontSize: 12))),
                title: Text(x['name']), subtitle: Text(tr('{0} قطعة', [x['qty']])), trailing: Text(money(x['total']))),
        ]),
        _Section(tr('الطيارين'), [
          for (final x in (d['drivers'] as List))
            ListTile(dense: true, leading: const Icon(Icons.delivery_dining), title: Text(x['name']),
                subtitle: Text(tr('{0} طلب', [x['orders']])), trailing: Text(money(x['total']))),
        ]),
      ]),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat(this.label, this.value, this.icon, this.color);
  final String label, value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Card(
        elevation: 0,
        color: color.withValues(alpha: .08),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
            Row(children: [Icon(icon, color: color, size: 18), const SizedBox(width: 6), Text(label, style: TextStyle(color: color))]),
            const SizedBox(height: 6),
            FittedBox(child: Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
          ]),
        ),
      );
}

class _Section extends StatelessWidget {
  const _Section(this.title, this.children);
  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    if (children.isEmpty) return const SizedBox.shrink();
    return Card(
      margin: const EdgeInsets.only(top: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 4),
          ...children,
        ]),
      ),
    );
  }
}

class _Bar extends StatelessWidget {
  const _Bar({required this.label, required this.value, required this.max});
  final String label;
  final double value, max;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          SizedBox(width: 48, child: Text(label, style: const TextStyle(fontSize: 12))),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(value: max == 0 ? 0 : value / max, minHeight: 14, color: kBrand, backgroundColor: kBrand.withValues(alpha: .1)),
            ),
          ),
          SizedBox(width: 70, child: Text(money(value), textAlign: TextAlign.end, style: const TextStyle(fontSize: 12))),
        ]),
      );
}
