import 'core/app_mode.dart';
import 'main.dart' as app;

/// تطبيق الطيار المنفصل:
/// flutter build apk --flavor driver -t lib/main_driver.dart
Future<void> main() async {
  appMode = 'driver';
  await app.main();
}
