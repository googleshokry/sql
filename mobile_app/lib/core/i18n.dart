import 'translations_en.dart';

/// اللغة الحالية: 'ar' أو 'en'
String currentLang = 'ar';

bool get isArabic => currentLang == 'ar';

/// ترجمة نص — المفتاح هو النص العربي، و {0} {1} أماكن القيم
/// tr('طلب #{0}', [order.number])
String tr(String ar, [List<Object?> args = const []]) {
  var text = currentLang == 'en' ? (translationsEn[ar] ?? ar) : ar;
  for (var i = 0; i < args.length; i++) {
    text = text.replaceAll('{$i}', '${args[i] ?? ''}');
  }
  return text;
}
