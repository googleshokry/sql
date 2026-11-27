/// 'full' = التطبيق الكامل (عميل + موظفين)، 'driver' = تطبيق الطيار فقط
/// يتحدد من نقطة البداية: lib/main.dart أو lib/main_driver.dart
String appMode = 'full';

bool get isDriverApp => appMode == 'driver';
