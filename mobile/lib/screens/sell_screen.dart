import 'dart:async';

import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../api.dart';
import '../l10n.dart';
import '../models.dart';
import '../state/app_state.dart';
import '../state/cart.dart';
import '../widgets/common.dart';
import 'checkout_screen.dart';
import 'scanner_screen.dart';

class SellScreen extends StatefulWidget {
  const SellScreen({super.key});

  @override
  State<SellScreen> createState() => _SellScreenState();
}

class _SellScreenState extends State<SellScreen> {
  final _search = TextEditingController();
  Timer? _debounce;
  List<Product> _products = [];
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final state = context.read<AppState>();
    final cart = context.read<Cart>();
    try {
      final data = await state.api.get('products', query: {
        'search': _search.text,
        'warehouse_id': state.lookups?.defaultWarehouseId,
        'price_list': cart.customer?.priceList,
        'per_page': 60,
      });
      if (!mounted) return;
      setState(() => _products = (data['data'] as List).map((e) => Product.fromJson(e as Map<String, dynamic>)).toList());
    } catch (e) {
      if (!mounted) return;
      if (e is ApiException && e.isUnauthorized) handleError(context, e);
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _scan() async {
    final code = await Navigator.push<String>(context, MaterialPageRoute(builder: (_) => const ScannerScreen()));
    if (code == null || !mounted) return;
    try {
      final data = await context.read<AppState>().api.get('products/barcode/${Uri.encodeComponent(code)}',
          query: {'price_list': context.read<Cart>().customer?.priceList});
      if (!mounted) return;
      final product = Product.fromJson(data['data'] as Map<String, dynamic>);
      context.read<Cart>().add(product);
      showMessage(context, '✓ ${product.name}');
    } catch (e) {
      if (mounted) showMessage(context, '${context.t('product_not_found')}: $code', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<Cart>();

    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('sell')),
        actions: [IconButton(tooltip: context.t('scan_barcode'), onPressed: _scan, icon: const Icon(Icons.qr_code_scanner))],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(64),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
            child: SearchBar(
              controller: _search,
              hintText: context.t('search_products'),
              leading: const Icon(Icons.search),
              onChanged: (_) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 400), _load);
              },
            ),
          ),
        ),
      ),
      body: _error != null && _products.isEmpty
          ? ErrorView(message: _error!, onRetry: _load)
          : _loading && _products.isEmpty
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _products.isEmpty
                      ? ListView(children: const [SizedBox(height: 120), EmptyView(icon: Icons.inventory_2_outlined)])
                      : GridView.builder(
                          padding: const EdgeInsets.fromLTRB(12, 12, 12, 110),
                          gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 200, childAspectRatio: 1.05, mainAxisSpacing: 8, crossAxisSpacing: 8),
                          itemCount: _products.length,
                          itemBuilder: (context, i) => _ProductTile(product: _products[i], onTap: () => cart.add(_products[i])),
                        ),
                ),
      bottomSheet: cart.isEmpty ? null : _CartBar(cart: cart),
    );
  }
}

class _ProductTile extends StatelessWidget {
  const _ProductTile({required this.product, required this.onTap});

  final Product product;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Expanded(
              child: product.imageUrl != null
                  ? Image.network(product.imageUrl!, fit: BoxFit.contain, errorBuilder: (_, _, _) => Icon(Icons.inventory_2, color: scheme.outline, size: 36))
                  : Icon(Icons.inventory_2, color: scheme.outline, size: 36),
            ),
            const SizedBox(height: 6),
            Text(product.name, maxLines: 2, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            Text(number(product.price), textAlign: TextAlign.center, style: TextStyle(color: scheme.primary, fontWeight: FontWeight.bold)),
            Text(
              product.trackStock ? '${context.t('available')}: ${qty(product.quantity ?? 0)}' : context.t('service'),
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11, color: product.isLow ? scheme.error : scheme.outline),
            ),
          ]),
        ),
      ),
    );
  }
}

class _CartBar extends StatelessWidget {
  const _CartBar({required this.cart});

  final Cart cart;

  @override
  Widget build(BuildContext context) {
    return Material(
      elevation: 8,
      color: Theme.of(context).colorScheme.primaryContainer,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          child: Row(children: [
            Badge(label: Text('${cart.count}'), child: const Icon(Icons.shopping_cart)),
            const SizedBox(width: 16),
            Expanded(child: Text(money(context, cart.total), style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold))),
            FilledButton.icon(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CheckoutScreen())),
              icon: const Icon(Icons.payments_outlined),
              label: Text(context.t('checkout')),
            ),
          ]),
        ),
      ),
    );
  }
}
