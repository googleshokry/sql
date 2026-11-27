import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();

    return Scaffold(
      appBar: AppBar(title: Text(context.t('more'))),
      body: ListView(children: [
        ListTile(
          leading: const CircleAvatar(child: Icon(Icons.person)),
          title: Text(state.userName ?? ''),
          subtitle: Text(state.userEmail ?? ''),
        ),
        const Divider(),
        ListTile(
          leading: const Icon(Icons.inventory_2_outlined),
          title: Text(context.t('products')),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ProductsScreen())),
        ),
        ListTile(
          leading: const Icon(Icons.call_received),
          title: Text(context.t('receipts')),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReceiptsScreen())),
        ),
        ListTile(
          leading: const Icon(Icons.money_off_outlined),
          title: Text(context.t('expenses')),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ExpensesScreen())),
        ),
        const Divider(),
        ListTile(
          leading: const Icon(Icons.language),
          title: Text(context.t('language')),
          trailing: SegmentedButton<String>(
            segments: const [ButtonSegment(value: 'ar', label: Text('ع')), ButtonSegment(value: 'en', label: Text('EN'))],
            selected: {state.locale.languageCode},
            onSelectionChanged: (v) => state.setLocale(v.first),
          ),
        ),
        ListTile(leading: const Icon(Icons.dns_outlined), title: Text(context.t('server')), subtitle: Text(state.serverUrl, textDirection: TextDirection.ltr)),
        ListTile(
          leading: Icon(Icons.logout, color: Theme.of(context).colorScheme.error),
          title: Text(context.t('logout'), style: TextStyle(color: Theme.of(context).colorScheme.error)),
          onTap: () => state.logout(),
        ),
      ]),
    );
  }
}

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  String _search = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('products')),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            child: SearchBar(
              hintText: context.t('search_products'),
              leading: const Icon(Icons.search),
              onChanged: (v) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 400), () => setState(() => _search = v));
              },
            ),
          ),
        ),
      ),
      body: PagedList<Product>(
        path: 'products',
        query: {'search': _search},
        fromJson: Product.fromJson,
        emptyIcon: Icons.inventory_2_outlined,
        itemBuilder: (context, p) => ListTile(
          title: Text(p.name),
          subtitle: Text([p.sku, p.barcode, p.category].whereType<String>().where((s) => s.isNotEmpty).join(' • ')),
          trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(number(p.price), style: const TextStyle(fontWeight: FontWeight.bold)),
            Text(
              p.trackStock ? '${context.t('stock')}: ${qty(p.quantity ?? 0)}' : context.t('service'),
              style: TextStyle(fontSize: 12, color: p.isLow ? Theme.of(context).colorScheme.error : null),
            ),
          ]),
        ),
      ),
    );
  }
}

class ReceiptsScreen extends StatelessWidget {
  const ReceiptsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.t('receipts'))),
      body: PagedList<PaymentRecord>(
        path: 'payments',
        query: const {'direction': 'in'},
        fromJson: PaymentRecord.fromJson,
        itemBuilder: (context, p) => ListTile(
          title: Text('${p.number} • ${p.party ?? ''}'),
          subtitle: Text('${p.date} • ${p.methodLabel}${p.invoice != null ? ' • ${p.invoice}' : ''}'),
          trailing: Text(money(context, p.amount), style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.green)),
          onTap: () => openUrl(context, p.printUrl),
        ),
      ),
    );
  }
}

class ExpensesScreen extends StatefulWidget {
  const ExpensesScreen({super.key});

  @override
  State<ExpensesScreen> createState() => _ExpensesScreenState();
}

class _ExpensesScreenState extends State<ExpensesScreen> {
  final _listKey = GlobalKey<PagedListState<ExpenseRecord>>();

  Future<void> _add() async {
    final state = context.read<AppState>();
    final lookups = state.lookups;
    if (lookups == null) return;
    final amount = TextEditingController();
    final tax = TextEditingController(text: '0');
    final description = TextEditingController();
    int? categoryId = lookups.expenseCategories.isEmpty ? null : lookups.expenseCategories.first.id;
    int? treasuryId = lookups.defaultTreasuryId;

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: Text(context.t('add_expense')),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              DropdownButtonFormField<int>(
                initialValue: categoryId,
                decoration: InputDecoration(labelText: context.t('expense_category')),
                items: lookups.expenseCategories.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                onChanged: (v) => setLocal(() => categoryId = v),
              ),
              DropdownButtonFormField<int>(
                initialValue: treasuryId,
                decoration: InputDecoration(labelText: context.t('treasury')),
                items: lookups.treasuries.map((t) => DropdownMenuItem(value: t.id, child: Text(t.name))).toList(),
                onChanged: (v) => setLocal(() => treasuryId = v),
              ),
              TextField(controller: amount, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: context.t('amount'))),
              TextField(controller: tax, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: context.t('tax_rate'))),
              TextField(controller: description, decoration: InputDecoration(labelText: context.t('description'))),
            ]),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(context.t('cancel'))),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(context.t('save'))),
          ],
        ),
      ),
    );
    if (ok != true || !mounted) return;

    try {
      await state.api.post('expenses', {
        'expense_category_id': categoryId,
        'treasury_id': treasuryId,
        'amount': double.tryParse(amount.text) ?? 0,
        'tax_rate': double.tryParse(tax.text) ?? 0,
        'description': description.text,
      });
      if (!mounted) return;
      showMessage(context, context.t('saved'));
      _listKey.currentState?.refresh();
    } catch (e) {
      if (mounted) handleError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.t('expenses'))),
      floatingActionButton: FloatingActionButton(onPressed: _add, tooltip: context.t('add_expense'), child: const Icon(Icons.add)),
      body: PagedList<ExpenseRecord>(
        key: _listKey,
        path: 'expenses',
        fromJson: ExpenseRecord.fromJson,
        itemBuilder: (context, e) => ListTile(
          title: Text(e.category),
          subtitle: Text('${e.number} • ${e.date}${e.description != null && e.description!.isNotEmpty ? ' • ${e.description}' : ''}'),
          trailing: Text(money(context, e.total), style: const TextStyle(fontWeight: FontWeight.bold)),
        ),
      ),
    );
  }
}
