import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';

class InvoiceDetailScreen extends StatefulWidget {
  const InvoiceDetailScreen({super.key, required this.id});

  final int id;

  @override
  State<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends State<InvoiceDetailScreen> {
  Invoice? _invoice;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final data = await context.read<AppState>().api.get('sales-invoices/${widget.id}');
      if (mounted) setState(() => _invoice = Invoice.fromJson(data['data'] as Map<String, dynamic>));
    } catch (e) {
      if (!mounted) return;
      handleError(context, e);
      setState(() => _error = e.toString());
    }
  }

  Future<void> _collect(Invoice invoice) async {
    final state = context.read<AppState>();
    final amount = TextEditingController(text: invoice.remaining.toStringAsFixed(2));
    final reference = TextEditingController();
    int? treasuryId = state.lookups?.defaultTreasuryId;
    String method = 'cash';

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: Text(context.t('collect_payment')),
          content: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              TextField(controller: amount, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: context.t('amount'))),
              const SizedBox(height: 8),
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
              TextField(controller: reference, decoration: InputDecoration(labelText: context.t('reference'))),
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
      await state.api.post('sales-invoices/${invoice.id}/payments', {
        'amount': double.tryParse(amount.text) ?? 0,
        'treasury_id': treasuryId,
        'method': method,
        'reference': reference.text,
      });
      if (!mounted) return;
      showMessage(context, context.t('payment_saved'));
      _load();
    } catch (e) {
      if (mounted) handleError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final inv = _invoice;

    return Scaffold(
      appBar: AppBar(
        title: Text(inv?.number ?? context.t('invoice')),
        actions: [
          if (inv != null)
            PopupMenuButton<String>(
              icon: const Icon(Icons.print_outlined),
              onSelected: (v) => openUrl(context, v == 'a4' ? inv.printUrl : inv.receiptUrl),
              itemBuilder: (_) => [
                PopupMenuItem(value: 'a4', child: Text('${context.t('print')} (A4)')),
                PopupMenuItem(value: 'thermal', child: Text(context.t('receipt'))),
              ],
            ),
        ],
      ),
      body: _error != null && inv == null
          ? ErrorView(message: _error!, onRetry: _load)
          : inv == null
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(padding: const EdgeInsets.all(12), children: [
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                            Text(inv.number, style: Theme.of(context).textTheme.titleLarge),
                            StatusChip(status: inv.paymentStatus, label: inv.paymentStatusLabel),
                          ]),
                          const SizedBox(height: 8),
                          Text('${context.t('date')}: ${inv.date}'),
                          Text('${context.t('customer')}: ${inv.customerName ?? context.t('cash_customer')}'),
                        ]),
                      ),
                    ),
                    Card(
                      child: Column(
                        children: inv.items
                            .map((i) => ListTile(
                                  title: Text(i.description),
                                  subtitle: Text('${qty(i.quantity)} × ${number(i.unitPrice)}'),
                                  trailing: Text(number(i.total), style: const TextStyle(fontWeight: FontWeight.w600)),
                                ))
                            .toList(),
                      ),
                    ),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(children: [
                          _row(context.t('subtotal'), number(inv.subtotal - inv.discount)),
                          if (inv.discount > 0) _row(context.t('discount'), number(inv.discount)),
                          _row(context.t('tax'), number(inv.tax)),
                          const Divider(),
                          _row(context.t('total'), money(context, inv.total), bold: true),
                          _row(context.t('paid'), number(inv.paid), color: Colors.green),
                          _row(context.t('remaining'), number(inv.remaining), color: inv.remaining > 0 ? Theme.of(context).colorScheme.error : null),
                        ]),
                      ),
                    ),
                  ]),
                ),
      bottomNavigationBar: inv != null && inv.remaining > 0
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: FilledButton.icon(
                  style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
                  onPressed: () => _collect(inv),
                  icon: const Icon(Icons.payments_outlined),
                  label: Text(context.t('collect_payment')),
                ),
              ),
            )
          : null,
    );
  }

  Widget _row(String label, String value, {bool bold = false, Color? color}) {
    final style = TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal, fontSize: bold ? 18 : 14, color: color);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(label, style: style), Text(value, style: style)]),
    );
  }
}
