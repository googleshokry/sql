import 'package:flutter_test/flutter_test.dart';
import 'package:smart_erp/models.dart';
import 'package:smart_erp/state/cart.dart';

Product product(int id, double price, {double tax = 15}) => Product.fromJson({
      'id': id,
      'name': 'P$id',
      'sku': 'P$id',
      'price': price,
      'tax_rate': tax,
      'track_stock': true,
      'quantity': 10,
      'min_stock': 0,
    });

void main() {
  test('adding the same product increases quantity', () {
    final cart = Cart()
      ..add(product(1, 100))
      ..add(product(1, 100));

    expect(cart.count, 1);
    expect(cart.lines.first.quantity, 2);
    expect(cart.total, 230);
  });

  test('invoice discount reduces the tax base proportionally (same as the server)', () {
    final cart = Cart()
      ..add(product(1, 1000))
      ..add(product(1, 1000))
      ..setDiscount(100);

    expect(cart.subtotal, 2000);
    expect(cart.tax, closeTo(285, 0.001));
    expect(cart.total, 2185);
  });

  test('quantity zero removes the line and discount cannot exceed subtotal', () {
    final cart = Cart()..add(product(2, 50, tax: 0));
    cart.setDiscount(500);
    expect(cart.total, 0);

    cart.updateLine(cart.lines.first, quantity: 0);
    expect(cart.isEmpty, isTrue);
  });
}
