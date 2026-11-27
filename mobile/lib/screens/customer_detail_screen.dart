import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../l10n.dart';
import '../models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';

/// Customer card with account statement and a quick receipt voucher.
class CustomerDetailScreen extends StatefulWidget {
  const CustomerDetailScreen({super.key, required this.customer});

  final Customer customer;

  @override
  State<CustomerDetailScreen> createState() => _CustomerDetailScreenState();
}

class _CustomerDetailScreenState extends State<CustomerDetailScreen> {
  Map<String, dynamic>? _statement;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final data = await context.read<AppState>().api.get('customers/${widget.customer.id}/statement');
      if (mounted) setState(() => _statement = data as Map<String, dynamic>);
    } catch (e) {
      if (!mounted) return;
      handleError(context, e);
      setState(() => _error = e.toString());
    }
  }

  Future<void> _receipt() async {
    final state = context.read<AppState>();
    final amount = TextEditingController();
    final notes = TextEditingController();
    int? treasuryId = state.lookups?.defaultTreasuryId;
    String method = 'cash';

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: Text(context.t('receipt_voucher')),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              TextField(controller: amount, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: context.t('amount'))),
              DropdownButtonFormField<int>(
                initialValue: treasuryId,
                decoration: InputDecoration(labelText: context.t('treasury')),
                items: (state.lookups?.treasuries ?? []).map((t) => DropdownMenuItem(value: t.id, child: Text(t.name))).toList(),
                onChanged: (v) => setLocal(() => treasuryId = v),
              ),
              DropdownButtonFormField<String>(
                initialValue: method,
                decoration: InputDecoration(labelText: context.t('payment_method')),
                items: (state.lookups?.paymentMethods ?? []).map((m) => DropdownMenuItem(value: m.key, child: Text(m.label))).toList(),
                onChanged: (v) => setLocal(() => method = v ?? 'cash'),
              ),
              TextField(controller: notes, decoration: InputDecoration(labelText: context.t('notes'))),
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
      final data = await state.api.post('payments', {
        'customer_id': widget.customer.id,
        'treasury_id': treasuryId,
        'amount': double.tryParse(amount.text) ?? 0,
        'method': method,
        'notes': notes.text.isEmpty ? null : notes.text,
      });
      if (!mounted) return;
      showMessage(context, context.t('payment_saved'));
      _load();
      final printUrl = (data['data'] as Map)['print_url']?.toString();
      if (printUrl != null) openUrl(context, printUrl);
    } catch (e) {
      if (mounted) handleError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.customer;
    final s = _statement;
    final rows = (s?['rows'] as List? ?? []).cast<Map<String, dynamic>>();

    return Scaffold(
      appBar: AppBar(
        title: Text(c.name),
        actions: [
          if (c.phone != null && c.phone!.isNotEmpty && c.phone != '-')
            IconButton(icon: const Icon(Icons.call_outlined), onPressed: () => launchUrl(Uri.parse('tel:${c.phone}'))),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(onPressed: _receipt, icon: const Icon(Icons.payments_outlined), label: Text(context.t('receipt_voucher'))),
      body: _error != null && s == null
          ? ErrorView(message: _error!, onRetry: _load)
          : s == null
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(padding: const EdgeInsets.fromLTRB(12, 12, 12, 96), children: [
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(context.t('balance'), style: Theme.of(context).textTheme.bodySmall),
                          Text(money(context, toDouble(s['closing'])),
                              style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold, color: toDouble(s['closing']) > 0 ? Theme.of(context).colorScheme.error : Colors.green)),
                          const SizedBox(height: 8),
                          if (c.phone != null) Text('${context.t('phone')}: ${c.phone}'),
                          if (c.taxNumber != null && c.taxNumber!.isNotEmpty) Text('${context.t('tax_number')}: ${c.taxNumber}'),
                        ]),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(8),
                      child: Text('${context.t('statement')} (${s['from']} → ${s['to']})', style: Theme.of(context).textTheme.titleMedium),
                    ),
                    Card(
                      child: Column(children: [
                        ListTile(dense: true, title: Text(context.t('opening')), trailing: Text(number(toDouble(s['opening'])))),
                        const Divider(height: 1),
                        if (rows.isEmpty) const Padding(padding: EdgeInsets.all(24), child: EmptyView()),
                        ...rows.map((r) {
                          final debit = toDouble(r['debit']);
                          final credit = toDouble(r['credit']);
                          return ListTile(
                            dense: true,
                            title: Text(r['description']?.toString() ?? '', maxLines: 2, overflow: TextOverflow.ellipsis),
                            subtitle: Text('${r['date']} • ${debit > 0 ? '${context.t('debit')} ${number(debit)}' : '${context.t('credit')} ${number(credit)}'}'),
                            trailing: Text(number(toDouble(r['balance'])), style: const TextStyle(fontWeight: FontWeight.w600)),
                          );
                        }),
                        const Divider(height: 1),
                        ListTile(dense: true, title: Text(context.t('closing'), style: const TextStyle(fontWeight: FontWeight.bold)), trailing: Text(number(toDouble(s['closing'])), style: const TextStyle(fontWeight: FontWeight.bold))),
                      ]),
                    ),
                  ]),
                ),
    );
  }
}
