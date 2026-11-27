import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:restaurant_pos_app/core/cart.dart';
import 'package:restaurant_pos_app/core/models.dart';
import 'package:restaurant_pos_app/core/session.dart';
import 'package:restaurant_pos_app/main.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  test('totals match server formula', () {
    final t = Totals.calc(subtotal: 145, discount: 0, type: 'dine_in', serviceRate: 10);
    expect(t.service, 14.5);
    expect(t.total, 159.5);

    final d = Totals.calc(subtotal: 190, discount: 500, type: 'delivery', deliveryFee: 35);
    expect(d.discount, 190); // الخصم لا يتجاوز المجموع
    expect(d.total, 35);

    final takeaway = Totals.calc(subtotal: 100, discount: 0, type: 'takeaway', deliveryFee: 35, serviceRate: 12);
    expect(takeaway.total, 100); // لا خدمة ولا توصيل
  });

  test('cart merges same product and removes at zero', () {
    final cart = Cart();
    final p = Product(id: 1, categoryId: 1, name: 'شاي', price: 15);
    cart.add(p);
    cart.add(p);
    expect(cart.count, 2);
    expect(cart.subtotal, 30);
    cart.dec(cart.lines.first);
    cart.dec(cart.lines.first);
    expect(cart.isEmpty, true);
  });

  testWidgets('shows welcome screen when logged out', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final session = Session();
    await session.boot();
    await tester.pumpWidget(MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: session),
        ChangeNotifierProvider(create: (_) => Cart()),
      ],
      child: const PosApp(),
    ));
    await tester.pumpAndSettle();
    expect(find.text('اطلب أكلك (عميل)'), findsOneWidget);
    expect(find.textContaining('دخول الموظفين'), findsOneWidget);
  });
}
