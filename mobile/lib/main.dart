import 'package:flutter_localizations/flutter_localizations.dart' show GlobalWidgetsLocalizations;
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'l10n.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'state/app_state.dart';
import 'state/cart.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();

  runApp(MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => AppState(prefs)),
      ChangeNotifierProvider(create: (_) => Cart()),
    ],
    child: const SmartErpApp(),
  ));
}

class SmartErpApp extends StatelessWidget {
  const SmartErpApp({super.key});

  @override
  Widget build(BuildContext context) {
    final state = context.watch<AppState>();
    const seed = Color(0xFF4F46E5);

    return MaterialApp(
      title: 'Smart ERP',
      debugShowCheckedModeBanner: false,
      locale: state.locale,
      supportedLocales: AppStrings.supportedLocales,
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ],
      theme: ThemeData(colorSchemeSeed: seed, useMaterial3: true, brightness: Brightness.light),
      darkTheme: ThemeData(colorSchemeSeed: seed, useMaterial3: true, brightness: Brightness.dark),
      home: state.isLoggedIn ? const HomeScreen() : const LoginScreen(),
    );
  }
}
