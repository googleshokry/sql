import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:restaurant_pos_app/core/translations_en.dart';

/// كل نص في tr('...') لازم يكون ليه ترجمة إنجليزي
void main() {
  test('every tr() key has an English translation', () {
    final key = RegExp(r"\btr\('((?:[^'\\]|\\.)*)'");
    final missing = <String>{};
    for (final f in Directory('lib').listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'))) {
      for (final m in key.allMatches(f.readAsStringSync())) {
        final k = m.group(1)!.replaceAll(r"\'", "'");
        if (!translationsEn.containsKey(k)) missing.add(k);
      }
    }
    expect(missing, isEmpty);
  });
}
