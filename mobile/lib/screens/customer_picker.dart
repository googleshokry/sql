import 'dart:async';

import 'package:material_ui/material_ui.dart';

import '../l10n.dart';
import '../models.dart';
import '../widgets/common.dart';
import 'customers_screen.dart';

/// Bottom sheet to choose a customer. Returns a customer with id 0 for "cash customer".
Future<Customer?> pickCustomer(BuildContext context) {
  return showModalBottomSheet<Customer>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (_) => const _CustomerPicker(),
  );
}

class _CustomerPicker extends StatefulWidget {
  const _CustomerPicker();

  @override
  State<_CustomerPicker> createState() => _CustomerPickerState();
}

class _CustomerPickerState extends State<_CustomerPicker> {
  String _search = '';
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.of(context).size.height * .85,
      child: Column(children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(children: [
            Expanded(
              child: SearchBar(
                hintText: context.t('search'),
                leading: const Icon(Icons.search),
                onChanged: (v) {
                  _debounce?.cancel();
                  _debounce = Timer(const Duration(milliseconds: 400), () => setState(() => _search = v));
                },
              ),
            ),
            IconButton.filledTonal(
              tooltip: context.t('add_customer'),
              icon: const Icon(Icons.person_add_alt),
              onPressed: () async {
                final created = await showAddCustomerDialog(context);
                if (created != null && context.mounted) Navigator.pop(context, created);
              },
            ),
          ]),
        ),
        ListTile(
          leading: const Icon(Icons.person_outline),
          title: Text(context.t('cash_customer')),
          onTap: () => Navigator.pop(context, Customer.fromJson({'id': 0, 'name': context.t('cash_customer')})),
        ),
        const Divider(height: 1),
        Expanded(
          child: PagedList<Customer>(
            path: 'customers',
            query: {'search': _search},
            fromJson: Customer.fromJson,
            itemBuilder: (context, c) => ListTile(
              title: Text(c.name),
              subtitle: Text([c.code, c.phone].whereType<String>().where((s) => s.isNotEmpty).join(' • ')),
              trailing: Text(number(c.balance)),
              onTap: () => Navigator.pop(context, c),
            ),
          ),
        ),
      ]),
    );
  }
}
