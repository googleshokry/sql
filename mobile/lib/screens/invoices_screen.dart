import 'dart:async';

import 'package:material_ui/material_ui.dart';

import '../l10n.dart';
import '../models.dart';
import '../widgets/common.dart';
import 'invoice_detail_screen.dart';

class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  String _status = '';
  String _search = '';
  Timer? _debounce;
  final _listKey = GlobalKey<PagedListState<Invoice>>();

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filters = {'': 'all', 'unpaid': 'unpaid', 'partial': 'partial', 'paid': 'paid_status'};

    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('invoices')),
        actions: [IconButton(onPressed: () => _listKey.currentState?.refresh(), icon: const Icon(Icons.refresh))],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(112),
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: SearchBar(
                hintText: context.t('search'),
                leading: const Icon(Icons.search),
                onChanged: (v) {
                  _debounce?.cancel();
                  _debounce = Timer(const Duration(milliseconds: 400), () => setState(() => _search = v));
                },
              ),
            ),
            SizedBox(
              height: 56,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                children: filters.entries
                    .map((e) => Padding(
                          padding: const EdgeInsetsDirectional.only(end: 8),
                          child: ChoiceChip(label: Text(context.t(e.value)), selected: _status == e.key, onSelected: (_) => setState(() => _status = e.key)),
                        ))
                    .toList(),
              ),
            ),
          ]),
        ),
      ),
      body: PagedList<Invoice>(
        key: _listKey,
        path: 'sales-invoices',
        query: {'payment_status': _status, 'search': _search},
        fromJson: Invoice.fromJson,
        emptyIcon: Icons.receipt_long_outlined,
        itemBuilder: (context, inv) => ListTile(
          title: Text(inv.number, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Text('${inv.date} • ${inv.customerName ?? context.t('cash_customer')}'),
          trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(money(context, inv.total), style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            StatusChip(status: inv.paymentStatus, label: inv.paymentStatusLabel),
          ]),
          onTap: () async {
            await Navigator.push(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(id: inv.id)));
            _listKey.currentState?.refresh();
          },
        ),
      ),
    );
  }
}
