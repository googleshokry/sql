import 'package:material_ui/material_ui.dart';

import 'models.dart';

class CartLine {
  CartLine(this.product, {this.qty = 1, this.notes = ''});
  final Product product;
  int qty;
  String notes;

  double get total => product.price * qty;

  Map<String, dynamic> toJson() => {'product_id': product.id, 'qty': qty, if (notes.isNotEmpty) 'notes': notes};
}

/// سلة مشتركة بين شاشة الكاشير وتطبيق العميل
class Cart extends ChangeNotifier {
  final List<CartLine> lines = [];

  bool get isEmpty => lines.isEmpty;
  int get count => lines.fold(0, (s, l) => s + l.qty);
  double get subtotal => lines.fold(0, (s, l) => s + l.total);

  int qtyOf(Product p) => lines.where((l) => l.product.id == p.id).fold(0, (s, l) => s + l.qty);

  void add(Product p) {
    final i = lines.indexWhere((l) => l.product.id == p.id && l.notes.isEmpty);
    if (i >= 0) {
      lines[i].qty++;
    } else {
      lines.add(CartLine(p));
    }
    notifyListeners();
  }

  void inc(CartLine l) {
    l.qty++;
    notifyListeners();
  }

  void dec(CartLine l) {
    l.qty--;
    if (l.qty <= 0) lines.remove(l);
    notifyListeners();
  }

  void remove(CartLine l) {
    lines.remove(l);
    notifyListeners();
  }

  void setNotes(CartLine l, String notes) {
    l.notes = notes.trim();
    notifyListeners();
  }

  void clear() {
    lines.clear();
    notifyListeners();
  }

  List<Map<String, dynamic>> toJson() => lines.map((l) => l.toJson()).toList();
}

/// نفس معادلة السيرفر (OrderTotals) لعرض الإجمالي قبل الإرسال
class Totals {
  Totals({required this.subtotal, required this.discount, required this.service, required this.tax, required this.deliveryFee});
  final double subtotal, discount, service, tax, deliveryFee;
  double get total => subtotal - discount + service + tax + deliveryFee;

  static double _r(double v) => (v * 100).roundToDouble() / 100;

  factory Totals.calc({
    required double subtotal,
    required double discount,
    required String type,
    double deliveryFee = 0,
    double taxRate = 0,
    double serviceRate = 0,
  }) {
    final d = _r(discount.clamp(0, subtotal).toDouble());
    final after = subtotal - d;
    final service = type == 'dine_in' ? _r(after * serviceRate / 100) : 0.0;
    final tax = _r((after + service) * taxRate / 100);
    return Totals(
      subtotal: _r(subtotal),
      discount: d,
      service: service,
      tax: tax,
      deliveryFee: type == 'delivery' ? deliveryFee : 0,
    );
  }
}
