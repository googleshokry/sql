import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../l10n.dart';
import '../state/app_state.dart';
import '../state/cart.dart';
import '../widgets/common.dart';
import 'customers_screen.dart';
import 'dashboard_screen.dart';
import 'invoices_screen.dart';
import 'more_screen.dart';
import 'sell_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final state = context.read<AppState>();
      if (state.lookups == null) {
        try {
          await state.loadLookups();
        } catch (e) {
          if (mounted) handleError(context, e);
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final cartCount = context.watch<Cart>().count;
    final pages = [
      DashboardScreen(onOpenSell: () => setState(() => _index = 1)),
      const SellScreen(),
      const InvoicesScreen(),
      const CustomersScreen(),
      const MoreScreen(),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.dashboard_outlined), selectedIcon: const Icon(Icons.dashboard), label: context.t('dashboard')),
          NavigationDestination(
            icon: Badge(isLabelVisible: cartCount > 0, label: Text('$cartCount'), child: const Icon(Icons.point_of_sale_outlined)),
            selectedIcon: const Icon(Icons.point_of_sale),
            label: context.t('sell'),
          ),
          NavigationDestination(icon: const Icon(Icons.receipt_long_outlined), selectedIcon: const Icon(Icons.receipt_long), label: context.t('invoices')),
          NavigationDestination(icon: const Icon(Icons.people_outline), selectedIcon: const Icon(Icons.people), label: context.t('customers')),
          NavigationDestination(icon: const Icon(Icons.more_horiz), label: context.t('more')),
        ],
      ),
    );
  }
}
