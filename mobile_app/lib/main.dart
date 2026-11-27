import 'package:cupertino_ui/cupertino_ui.dart' show GlobalCupertinoLocalizations;
import 'package:flutter_localizations/flutter_localizations.dart' show GlobalWidgetsLocalizations;
import 'package:intl/date_symbol_data_local.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import 'core/cart.dart';
import 'core/session.dart';
import 'screens/customer/customer_home.dart';
import 'screens/driver/driver_screen.dart';
import 'screens/orders/orders_screen.dart';
import 'screens/owner/dashboard_screen.dart';
import 'screens/pos/pos_screen.dart';
import 'screens/welcome_screen.dart';
import 'widgets/common.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('ar');
  final session = Session();
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: session..boot()),
        ChangeNotifierProvider(create: (_) => Cart()),
      ],
      child: const PosApp(),
    ),
  );
}

class PosApp extends StatelessWidget {
  const PosApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'كاشير المطعم',
      debugShowCheckedModeBanner: false,
      locale: const Locale('ar'),
      supportedLocales: const [Locale('ar'), Locale('en')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: kBrand),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
      ),
      darkTheme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: kBrand, brightness: Brightness.dark),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
      ),
      home: const Root(),
    );
  }
}

class Root extends StatelessWidget {
  const Root({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    if (!session.ready) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    if (!session.loggedIn) return const WelcomeScreen();
    return switch (session.role) {
      'customer' => const CustomerHome(),
      'driver' => const StaffShell(pages: [AppPage('طلباتي', Icons.delivery_dining, DriverScreen())]),
      'admin' => const StaffShell(pages: [
          AppPage('التقارير', Icons.insights, DashboardScreen()),
          AppPage('الكاشير', Icons.point_of_sale, PosScreen()),
          AppPage('الطلبات', Icons.receipt_long, OrdersScreen()),
        ]),
      _ => const StaffShell(pages: [
          AppPage('الكاشير', Icons.point_of_sale, PosScreen()),
          AppPage('الطلبات', Icons.receipt_long, OrdersScreen()),
        ]),
    };
  }
}

class AppPage {
  const AppPage(this.title, this.icon, this.body);
  final String title;
  final IconData icon;
  final Widget body;
}

/// هيكل تطبيق الموظفين (شريط علوي + تنقل سفلي حسب الصلاحية)
class StaffShell extends StatefulWidget {
  const StaffShell({super.key, required this.pages});
  final List<AppPage> pages;

  @override
  State<StaffShell> createState() => _StaffShellState();
}

class _StaffShellState extends State<StaffShell> {
  int index = 0;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final page = widget.pages[index];
    return Scaffold(
      appBar: AppBar(
        title: Text(page.title),
        actions: [
          Center(child: Text(session.user?['name'] ?? '', style: const TextStyle(fontSize: 13))),
          IconButton(
            tooltip: 'تسجيل الخروج',
            icon: const Icon(Icons.logout),
            onPressed: () async {
              if (await confirm(context, 'تسجيل الخروج؟')) {
                if (context.mounted) context.read<Cart>().clear();
                session.logout();
              }
            },
          ),
        ],
      ),
      body: IndexedStack(index: index, children: [for (final p in widget.pages) p.body]),
      bottomNavigationBar: widget.pages.length < 2
          ? null
          : NavigationBar(
              selectedIndex: index,
              onDestinationSelected: (i) => setState(() => index = i),
              destinations: [for (final p in widget.pages) NavigationDestination(icon: Icon(p.icon), label: p.title)],
            ),
    );
  }
}
