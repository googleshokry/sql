import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../models.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';
import 'customer_detail_screen.dart';

/// Dialog to create a customer on the server. Returns the created customer.
Future<Customer?> showAddCustomerDialog(BuildContext context) async {
  final name = TextEditingController();
  final phone = TextEditingController();
  final vat = TextEditingController();
  final city = TextEditingController();
  final form = GlobalKey<FormState>();

  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(context.t('add_customer')),
      content: Form(
        key: form,
        child: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextFormField(controller: name, decoration: InputDecoration(labelText: context.t('name')), validator: (v) => v == null || v.trim().isEmpty ? context.t('required') : null),
            TextFormField(controller: phone, keyboardType: TextInputType.phone, decoration: InputDecoration(labelText: context.t('phone'))),
            TextFormField(controller: vat, decoration: InputDecoration(labelText: context.t('tax_number'))),
            TextFormField(controller: city, decoration: InputDecoration(labelText: context.t('city'))),
          ]),
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(context.t('cancel'))),
        FilledButton(onPressed: () => form.currentState!.validate() ? Navigator.pop(ctx, true) : null, child: Text(context.t('save'))),
      ],
    ),
  );
  if (ok != true || !context.mounted) return null;

  try {
    final data = await context.read<AppState>().api.post('customers', {
      'name': name.text.trim(),
      'phone': phone.text.trim(),
      'tax_number': vat.text.trim(),
      'city': city.text.trim(),
    });
    if (context.mounted) showMessage(context, context.t('saved'));
    return Customer.fromJson(data['data'] as Map<String, dynamic>);
  } catch (e) {
    if (context.mounted) handleError(context, e);
    return null;
  }
}

class CustomersScreen extends StatefulWidget {
  const CustomersScreen({super.key});

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends State<CustomersScreen> {
  String _search = '';
  Timer? _debounce;
  final _listKey = GlobalKey<PagedListState<Customer>>();

  @override
  void dispose() {
    _debounce?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('customers')),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            child: SearchBar(
              hintText: context.t('search'),
              leading: const Icon(Icons.search),
              onChanged: (v) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 400), () => setState(() => _search = v));
              },
            ),
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton(
        tooltip: context.t('add_customer'),
        onPressed: () async {
          final created = await showAddCustomerDialog(context);
          if (created != null) _listKey.currentState?.refresh();
        },
        child: const Icon(Icons.person_add_alt),
      ),
      body: PagedList<Customer>(
        key: _listKey,
        path: 'customers',
        query: {'search': _search},
        fromJson: Customer.fromJson,
        emptyIcon: Icons.people_outline,
        itemBuilder: (context, c) => ListTile(
          leading: CircleAvatar(child: Text(c.name.isEmpty ? '?' : c.name.characters.first)),
          title: Text(c.name),
          subtitle: Text([c.phone, c.city].whereType<String>().where((s) => s.isNotEmpty).join(' • ')),
          trailing: Text(number(c.balance), style: TextStyle(fontWeight: FontWeight.bold, color: c.balance > 0 ? Theme.of(context).colorScheme.error : Colors.green)),
          onTap: () async {
            await Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerDetailScreen(customer: c)));
            _listKey.currentState?.refresh();
          },
        ),
      ),
    );
  }
}
