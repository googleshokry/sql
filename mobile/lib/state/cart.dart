import 'package:material_ui/material_ui.dart';

import '../models.dart';

class CartLine {
  CartLine({required this.product, required this.quantity, required this.unitPrice});

  final Product product;
  double quantity;
  double unitPrice;

  double get net => quantity * unitPrice;

  double get tax => net * product.taxRate / 100;

  double get total => net + tax;

  Map<String, dynamic> toJson() => {
        'product_id': product.id,
        'quantity': quantity,
        'unit_price': unitPrice,
        'tax_rate': product.taxRate,
      };
}

/// Shopping cart for the mobile sales screen (mirrors the server's totals logic).
class Cart extends ChangeNotifier {
  final List<CartLine> lines = [];
  Customer? customer;
  double discount = 0;

  int get count => lines.length;

  bool get isEmpty => lines.isEmpty;

  double get subtotal => lines.fold(0, (sum, l) => sum + l.net);

  double get discountAmount => discount.clamp(0, subtotal).toDouble();

  double get tax {
    final raw = lines.fold<double>(0, (sum, l) => sum + l.tax);
    return subtotal > 0 ? raw * (1 - discountAmount / subtotal) : 0;
  }

  double get total => _round(subtotal - discountAmount + _round(tax));

  static double _round(double v) => (v * 100).roundToDouble() / 100;

  void add(Product product) {
    final existing = lines.where((l) => l.product.id == product.id);
    if (existing.isNotEmpty) {
      existing.first.quantity += 1;
    } else {
      lines.add(CartLine(product: product, quantity: 1, unitPrice: product.price));
    }
    notifyListeners();
  }

  void updateLine(CartLine line, {double? quantity, double? unitPrice}) {
    if (quantity != null) line.quantity = quantity;
    if (unitPrice != null) line.unitPrice = unitPrice;
    if (line.quantity <= 0) lines.remove(line);
    notifyListeners();
  }

  void remove(CartLine line) {
    lines.remove(line);
    notifyListeners();
  }

  void setCustomer(Customer? value) {
    customer = value;
    notifyListeners();
  }

  void setDiscount(double value) {
    discount = value < 0 ? 0 : value;
    notifyListeners();
  }

  void clear() {
    lines.clear();
    customer = null;
    discount = 0;
    notifyListeners();
  }
}
