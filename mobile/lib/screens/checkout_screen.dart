import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../models.dart';
import '../state/app_state.dart';
import '../state/cart.dart';
import '../widgets/common.dart';
import 'customer_picker.dart';
import 'invoice_detail_screen.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _paid = TextEditingController();
  final _discount = TextEditingController();
  int? _treasuryId;
  int? _warehouseId;
  String _method = 'cash';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final lookups = context.read<AppState>().lookups;
    _treasuryId = lookups?.defaultTreasuryId;
    _warehouseId = lookups?.defaultWarehouseId;
    final cart = context.read<Cart>();
    _discount.text = cart.discount > 0 ? cart.discount.toString() : '';
    _paid.text = cart.total.toStringAsFixed(2);
  }

  double get _paidValue => double.tryParse(_paid.text) ?? 0;

  Future<void> _submit() async {
    final cart = context.read<Cart>();
    if (cart.isEmpty) return;
    if (_paidValue < cart.total && cart.customer == null) {
      showMessage(context, context.t('credit_requires_customer'), error: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final data = await context.read<AppState>().api.post('sales-invoices', {
        'customer_id': cart.customer?.id,
        'warehouse_id': _warehouseId,
        'treasury_id': _treasuryId,
        'payment_method': _method,
        'discount': cart.discount,
        'paid': _paidValue > cart.total ? cart.total : _paidValue,
        'items': cart.lines.map((l) => l.toJson()).toList(),
      });
      if (!mounted) return;
      final invoice = Invoice.fromJson(data['data'] as Map<String, dynamic>);
      final change = _paidValue - invoice.total;
      cart.clear();
      showMessage(context, '${context.t('sale_done')} ${invoice.number}${change > 0 ? ' • ${context.t('change')}: ${number(change)}' : ''}');
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(id: invoice.id)));
    } catch (e) {
      if (mounted) handleError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editLine(CartLine line) async {
    final cart = context.read<Cart>();
    final q = TextEditingController(text: qty(line.quantity));
    final p = TextEditingController(text: line.unitPrice.toString());
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(line.product.name),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: q, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: context.t('quantity'))),
          const SizedBox(height: 8),
          TextField(controller: p, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: context.t('price'))),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(context.t('cancel'))),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(context.t('save'))),
        ],
      ),
    );
    if (ok == true) {
      cart.updateLine(line, quantity: double.tryParse(q.text) ?? line.quantity, unitPrice: double.tryParse(p.text) ?? line.unitPrice);
      _paid.text = cart.total.toStringAsFixed(2);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<Cart>();
    final lookups = context.watch<AppState>().lookups;
    final remaining = cart.total - _paidValue;

    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('cart')),
        actions: [
          IconButton(
            tooltip: context.t('clear'),
            icon: const Icon(Icons.delete_sweep_outlined),
            onPressed: cart.isEmpty
                ? null
                : () async {
                    final ok = await showDialog<bool>(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        content: Text(context.t('confirm_clear')),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(context.t('no'))),
                          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(context.t('yes'))),
                        ],
                      ),
                    );
                    if (ok == true && context.mounted) {
                      cart.clear();
                      Navigator.pop(context);
                    }
                  },
          ),
        ],
      ),
      body: cart.isEmpty
          ? Center(child: Text(context.t('cart_empty')))
          : ListView(padding: const EdgeInsets.all(12), children: [
              Card(
                child: ListTile(
                  leading: const Icon(Icons.person_outline),
                  title: Text(cart.customer?.name ?? context.t('cash_customer')),
                  subtitle: Text(context.t('customer')),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () async {
                    final customer = await pickCustomer(context);
                    if (customer != null) cart.setCustomer(customer.id == 0 ? null : customer);
                  },
                ),
              ),
              Card(
                child: Column(
                  children: cart.lines
                      .map((l) => ListTile(
                            title: Text(l.product.name),
                            subtitle: Text('${qty(l.quantity)} × ${number(l.unitPrice)}'),
                            onTap: () => _editLine(l),
                            trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                              IconButton(icon: const Icon(Icons.remove_circle_outline), onPressed: () {
                                cart.updateLine(l, quantity: l.quantity - 1);
                                _paid.text = cart.total.toStringAsFixed(2);
                              }),
                              Text(number(l.total), style: const TextStyle(fontWeight: FontWeight.bold)),
                              IconButton(icon: const Icon(Icons.add_circle_outline), onPressed: () {
                                cart.updateLine(l, quantity: l.quantity + 1);
                                _paid.text = cart.total.toStringAsFixed(2);
                              }),
                            ]),
                          ))
                      .toList(),
                ),
              ),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(children: [
                    _row(context.t('subtotal'), number(cart.subtotal)),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _discount,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(labelText: context.t('discount'), isDense: true, border: const OutlineInputBorder()),
                      onChanged: (v) {
                        cart.setDiscount(double.tryParse(v) ?? 0);
                        _paid.text = cart.total.toStringAsFixed(2);
                      },
                    ),
                    const SizedBox(height: 8),
                    _row(context.t('tax'), number(cart.tax)),
                    const Divider(),
                    _row(context.t('total'), money(context, cart.total), bold: true),
                  ]),
                ),
              ),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(children: [
                    if (lookups != null) ...[
                      DropdownButtonFormField<String>(
                        initialValue: _method,
                        decoration: InputDecoration(labelText: context.t('payment_method'), border: const OutlineInputBorder(), isDense: true),
                        items: lookups.paymentMethods.map((m) => DropdownMenuItem(value: m.key, child: Text(m.label))).toList(),
                        onChanged: (v) => setState(() => _method = v ?? 'cash'),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int>(
                        initialValue: _treasuryId,
                        decoration: InputDecoration(labelText: context.t('treasury'), border: const OutlineInputBorder(), isDense: true),
                        items: lookups.treasuries.map((t) => DropdownMenuItem(value: t.id, child: Text(t.name))).toList(),
                        onChanged: (v) => setState(() => _treasuryId = v),
                      ),
                      if (lookups.warehouses.length > 1) ...[
                        const SizedBox(height: 12),
                        DropdownButtonFormField<int>(
                          initialValue: _warehouseId,
                          decoration: InputDecoration(labelText: context.t('warehouse'), border: const OutlineInputBorder(), isDense: true),
                          items: lookups.warehouses.map((w) => DropdownMenuItem(value: w.id, child: Text(w.name))).toList(),
                          onChanged: (v) => setState(() => _warehouseId = v),
                        ),
                      ],
                      const SizedBox(height: 12),
                    ],
                    Row(children: [
                      Expanded(
                        child: TextField(
                          controller: _paid,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: InputDecoration(labelText: context.t('paid'), border: const OutlineInputBorder(), isDense: true),
                          onChanged: (_) => setState(() {}),
                        ),
                      ),
                      const SizedBox(width: 8),
                      OutlinedButton(onPressed: () => setState(() => _paid.text = cart.total.toStringAsFixed(2)), child: Text(context.t('full_amount'))),
                    ]),
                    const SizedBox(height: 8),
                    if (remaining < -0.004) _row(context.t('change'), number(-remaining), color: Colors.green),
                    if (remaining > 0.004) _row(context.t('on_credit'), number(remaining), color: Theme.of(context).colorScheme.error),
                  ]),
                ),
              ),
              const SizedBox(height: 80),
            ]),
      bottomNavigationBar: cart.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: FilledButton.icon(
                  style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                  onPressed: _busy ? null : _submit,
                  icon: _busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.check_circle_outline),
                  label: Text('${context.t('checkout')} • ${money(context, cart.total)}'),
                ),
              ),
            ),
    );
  }

  Widget _row(String label, String value, {bool bold = false, Color? color}) {
    final style = TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal, fontSize: bold ? 18 : 14, color: color);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(label, style: style), Text(value, style: style)]),
    );
  }
}
