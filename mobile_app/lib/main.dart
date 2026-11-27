import 'package:cupertino_ui/cupertino_ui.dart' show GlobalCupertinoLocalizations;
import 'package:flutter_localizations/flutter_localizations.dart' show GlobalWidgetsLocalizations;
import 'package:intl/date_symbol_data_local.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import 'core/cart.dart';
import 'core/session.dart';
import 'screens/customer/customer_home.dart';
import 'screens/driver/driver_screen.dart';
import 'screens/orders/drivers_map_screen.dart';
import 'screens/orders/orders_screen.dart';
import 'screens/owner/dashboard_screen.dart';
import 'screens/pos/pos_screen.dart';
import 'screens/restaurant_code_screen.dart';
import 'screens/welcome_screen.dart';
import 'widgets/common.dart';
import 'core/app_mode.dart';
import 'core/i18n.dart';

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
    final lang = context.watch<Session>().lang;
    return MaterialApp(
      // تغيير اللغة يعيد بناء التطبيق بالكامل (الاتجاه والنصوص)
      key: ValueKey(lang),
      title: isDriverApp ? tr('طيار المطعم') : tr('كاشير المطعم'),
      debugShowCheckedModeBanner: false,
      locale: Locale(lang),
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
    if (!session.hasRestaurant) return const RestaurantCodeScreen();
    if (isDriverApp) {
      if (!session.loggedIn) return const StaffLoginScreen(driverApp: true);
      if (session.role != 'driver') return const _DriversOnly();
    }
    if (!session.loggedIn) return const WelcomeScreen();
    return switch (session.role) {
      'customer' => const CustomerHome(),
      'driver' => StaffShell(pages: [AppPage(tr('طلباتي'), Icons.delivery_dining, DriverScreen())]),
      'admin' => StaffShell(pages: [
          AppPage(tr('التقارير'), Icons.insights, DashboardScreen()),
          AppPage(tr('الكاشير'), Icons.point_of_sale, PosScreen()),
          AppPage(tr('الطلبات'), Icons.receipt_long, OrdersScreen()),
          AppPage(tr('الطيارين'), Icons.map_outlined, DriversMapScreen()),
        ]),
      _ => StaffShell(pages: [
          AppPage(tr('الكاشير'), Icons.point_of_sale, PosScreen()),
          AppPage(tr('الطلبات'), Icons.receipt_long, OrdersScreen()),
          AppPage(tr('الطيارين'), Icons.map_outlined, DriversMapScreen()),
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
          const LangButton(),
          Center(child: Text(session.user?['name'] ?? '', style: const TextStyle(fontSize: 13))),
          IconButton(
            tooltip: tr('تسجيل الخروج'),
            icon: const Icon(Icons.logout),
            onPressed: () async {
              if (await confirm(context, tr('تسجيل الخروج؟'))) {
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

/// تطبيق الطيار مفتوح بحساب مش طيار
class _DriversOnly extends StatelessWidget {
  const _DriversOnly();

  @override
  Widget build(BuildContext context) => Scaffold(
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.no_accounts, size: 64, color: Colors.grey),
              const SizedBox(height: 12),
              Text(tr('التطبيق ده للطيارين بس. استخدم تطبيق الكاشير للحسابات التانية.'), textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(onPressed: () => context.read<Session>().logout(), child: Text(tr('تسجيل الخروج'))),
            ]),
          ),
        ),
      );
}
