import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../core/cart.dart';
import '../core/models.dart';
import '../core/session.dart';
import 'common.dart';
import '../core/i18n.dart';

/// أقسام + بحث + شبكة أصناف — تضيف للسلة عند الضغط
class MenuGrid extends StatefulWidget {
  const MenuGrid({super.key});

  @override
  State<MenuGrid> createState() => _MenuGridState();
}

class _MenuGridState extends State<MenuGrid> {
  int? categoryId;
  String search = '';

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final cart = context.watch<Cart>();
    final products = session.menu
        .where((c) => categoryId == null || c.id == categoryId)
        .expand((c) => c.products)
        .where((p) => search.isEmpty || p.name.contains(search))
        .toList();

    return RefreshIndicator(
      onRefresh: () => guard(context, session.loadCatalog),
      child: CustomScrollView(slivers: [
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: TextField(
              onChanged: (v) => setState(() => search = v.trim()),
              decoration: InputDecoration(
                hintText: tr('ابحث عن صنف...'),
                prefixIcon: const Icon(Icons.search),
                isDense: true,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ),
        ),
        SliverToBoxAdapter(
          child: SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              children: [
                _chip(tr('الكل'), categoryId == null, () => setState(() => categoryId = null)),
                for (final c in session.menu) _chip(c.name, categoryId == c.id, () => setState(() => categoryId = c.id)),
              ],
            ),
          ),
        ),
        if (products.isEmpty)
          SliverFillRemaining(hasScrollBody: false, child: EmptyView(tr('لا توجد أصناف'), icon: Icons.no_food_outlined))
        else
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 100),
            sliver: SliverGrid(
              gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                  maxCrossAxisExtent: 170, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: .82),
              delegate: SliverChildBuilderDelegate(
                (context, i) => _ProductTile(product: products[i], qty: cart.qtyOf(products[i]), currency: session.currency),
                childCount: products.length,
              ),
            ),
          ),
      ]),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) => Padding(
        padding: const EdgeInsetsDirectional.only(end: 6),
        child: ChoiceChip(
          label: Text(label),
          selected: selected,
          onSelected: (_) => onTap(),
          selectedColor: kBrand,
          labelStyle: TextStyle(color: selected ? Colors.white : null),
          showCheckmark: false,
        ),
      );
}

class _ProductTile extends StatelessWidget {
  const _ProductTile({required this.product, required this.qty, required this.currency});
  final Product product;
  final int qty;
  final String currency;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: qty > 0 ? kBrand : Colors.grey.shade300, width: qty > 0 ? 2 : 1),
      ),
      elevation: 0,
      child: InkWell(
        onTap: () => context.read<Cart>().add(product),
        child: Stack(children: [
          Padding(
            padding: const EdgeInsets.all(8),
            child: Column(children: [
              Expanded(child: LayoutBuilder(builder: (_, c) => ProductImage(product.image, size: c.maxHeight))),
              const SizedBox(height: 6),
              Text(product.name, maxLines: 2, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w600)),
              Text('${money(product.price)} $currency', style: const TextStyle(color: kBrand, fontWeight: FontWeight.w800)),
            ]),
          ),
          if (qty > 0)
            PositionedDirectional(
              top: 6,
              end: 6,
              child: CircleAvatar(radius: 13, backgroundColor: kBrand,
                  child: Text('$qty', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold))),
            ),
        ]),
      ),
    );
  }
}

/// سطور السلة (+ / - / ملاحظة)
class CartLines extends StatelessWidget {
  const CartLines({super.key});

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<Cart>();
    if (cart.isEmpty) {
      return Padding(padding: EdgeInsets.all(24), child: EmptyView(tr('السلة فارغة'), icon: Icons.shopping_cart_outlined));
    }
    return Column(children: [
      for (final l in cart.lines)
        ListTile(
          contentPadding: EdgeInsets.zero,
          dense: true,
          title: Text(l.product.name, style: const TextStyle(fontWeight: FontWeight.w600)),
          subtitle: Text(l.notes.isEmpty ? '${money(l.product.price)} × ${l.qty} = ${money(l.total)}' : '${money(l.total)} • ${l.notes}'),
          onLongPress: () => _editNote(context, l),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            IconButton(visualDensity: VisualDensity.compact, icon: const Icon(Icons.edit_note), tooltip: tr('ملاحظة'), onPressed: () => _editNote(context, l)),
            IconButton.outlined(visualDensity: VisualDensity.compact, icon: const Icon(Icons.remove), onPressed: () => cart.dec(l)),
            SizedBox(width: 28, child: Text('${l.qty}', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.bold))),
            IconButton.outlined(visualDensity: VisualDensity.compact, icon: const Icon(Icons.add), onPressed: () => cart.inc(l)),
          ]),
        ),
    ]);
  }

  Future<void> _editNote(BuildContext context, CartLine line) async {
    final ctrl = TextEditingController(text: line.notes);
    final note = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(tr('ملاحظة: {0}', [line.product.name])),
        content: TextField(controller: ctrl, autofocus: true, decoration: InputDecoration(hintText: tr('بدون بصل، سبايسي...'))),
        actions: [FilledButton(onPressed: () => Navigator.pop(c, ctrl.text), child: Text(tr('حفظ')))],
      ),
    );
    if (note != null && context.mounted) context.read<Cart>().setNotes(line, note);
  }
}

/// شريط أسفل الشاشة يعرض عدد الأصناف والإجمالي
class CartBar extends StatelessWidget {
  const CartBar({super.key, required this.onTap, this.label});
  final VoidCallback onTap;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<Cart>();
    final currency = context.read<Session>().currency;
    if (cart.isEmpty) return const SizedBox.shrink();
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: FilledButton(
          style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14)),
          onPressed: onTap,
          child: Row(children: [
            CircleAvatar(radius: 14, backgroundColor: Colors.white,
                child: Text('${cart.count}', style: const TextStyle(color: kBrand, fontWeight: FontWeight.bold, fontSize: 13))),
            const SizedBox(width: 12),
            Text(label ?? tr('إتمام الطلب'), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            const Spacer(),
            Text('${money(cart.subtotal)} $currency', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          ]),
        ),
      ),
    );
  }
}
