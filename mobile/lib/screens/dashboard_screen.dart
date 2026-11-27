import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import 'invoice_detail_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.onOpenSell});

  final VoidCallback onOpenSell;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Map<String, dynamic>? _data;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final data = await context.read<AppState>().api.get('dashboard');
      if (mounted) setState(() => _data = data as Map<String, dynamic>);
    } catch (e) {
      if (!mounted) return;
      handleError(context, e);
      setState(() => _error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    final lookups = state.lookups;
    final company = lookups == null ? context.t('app_name') : (state.locale.languageCode == 'en' && lookups.companyNameEn.isNotEmpty ? lookups.companyNameEn : lookups.companyName);

    return Scaffold(
      appBar: AppBar(title: Text(company), actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))]),
      floatingActionButton: FloatingActionButton.extended(onPressed: widget.onOpenSell, icon: const Icon(Icons.add_shopping_cart), label: Text(context.t('new_sale'))),
      body: _error != null && _data == null
          ? ErrorView(message: _error!, onRetry: _load)
          : _data == null
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(onRefresh: _load, child: _content(context, _data!)),
    );
  }

  Widget _content(BuildContext context, Map<String, dynamic> d) {
    final tiles = [
      _Kpi(context.t('sales_today'), money(context, toDouble(d['sales_today'])), Icons.today, Colors.indigo, subtitle: '${d['invoices_today']} ${context.t('invoices_count')}'),
      _Kpi(context.t('sales_month'), money(context, toDouble(d['sales_month'])), Icons.calendar_month, Colors.teal),
      _Kpi(context.t('profit_month'), money(context, toDouble(d['profit_month'])), Icons.trending_up, toDouble(d['profit_month']) >= 0 ? Colors.green : Colors.red),
      _Kpi(context.t('cash_balance'), money(context, toDouble(d['cash_balance'])), Icons.account_balance_wallet, Colors.blue),
      _Kpi(context.t('receivables'), money(context, toDouble(d['receivables'])), Icons.call_received, Colors.orange),
      _Kpi(context.t('payables'), money(context, toDouble(d['payables'])), Icons.call_made, Colors.redAccent),
      _Kpi(context.t('expenses_month'), money(context, toDouble(d['expenses_month'])), Icons.money_off, Colors.blueGrey),
      _Kpi(context.t('low_stock'), '${d['low_stock_count']}', Icons.warning_amber, (d['low_stock_count'] as int) > 0 ? Colors.red : Colors.green),
    ];
    final days = (d['last_7_days'] as List).cast<Map<String, dynamic>>();
    final maxDay = days.fold<double>(1, (m, e) => toDouble(e['total']) > m ? toDouble(e['total']) : m);
    final latest = (d['latest_invoices'] as List).map((e) => Invoice.fromJson(e as Map<String, dynamic>)).toList();

    return ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
      GridView.count(
        crossAxisCount: MediaQuery.of(context).size.width > 600 ? 4 : 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        childAspectRatio: 1.55,
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        children: tiles,
      ),
      const SizedBox(height: 16),
      Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(context.t('last_7_days'), style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 16),
            SizedBox(
              height: 140,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: days.map((e) {
                  final h = 110 * toDouble(e['total']) / maxDay;
                  return Expanded(
                    child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
                      Container(
                        height: h < 2 ? 2 : h,
                        margin: const EdgeInsets.symmetric(horizontal: 4),
                        decoration: BoxDecoration(color: Theme.of(context).colorScheme.primary, borderRadius: BorderRadius.circular(4)),
                      ),
                      const SizedBox(height: 4),
                      Text(e['date'].toString().substring(8), style: const TextStyle(fontSize: 11)),
                    ]),
                  );
                }).toList(),
              ),
            ),
          ]),
        ),
      ),
      const SizedBox(height: 8),
      Padding(padding: const EdgeInsets.all(8), child: Text(context.t('latest_invoices'), style: Theme.of(context).textTheme.titleMedium)),
      Card(
        child: Column(
          children: latest.isEmpty
              ? [const Padding(padding: EdgeInsets.all(24), child: EmptyView())]
              : latest
                  .map((inv) => ListTile(
                        title: Text(inv.number),
                        subtitle: Text(inv.customerName ?? context.t('cash_customer')),
                        trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                          Text(money(context, inv.total), style: const TextStyle(fontWeight: FontWeight.bold)),
                          StatusChip(status: inv.paymentStatus, label: inv.paymentStatusLabel),
                        ]),
                        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(id: inv.id))),
                      ))
                  .toList(),
        ),
      ),
    ]);
  }
}

class _Kpi extends StatelessWidget {
  const _Kpi(this.title, this.value, this.icon, this.color, {this.subtitle});

  final String title;
  final String value;
  final IconData icon;
  final Color color;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Row(children: [
            Icon(icon, size: 18, color: color),
            const SizedBox(width: 6),
            Expanded(child: Text(title, style: Theme.of(context).textTheme.bodySmall, overflow: TextOverflow.ellipsis)),
          ]),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: AlignmentDirectional.centerStart,
            child: Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold, color: color)),
          ),
          if (subtitle != null) Text(subtitle!, style: Theme.of(context).textTheme.bodySmall),
        ]),
      ),
    );
  }
}
