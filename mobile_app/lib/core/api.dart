import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'i18n.dart' as i18n;
import 'i18n.dart';

/// رابط السيرفر الافتراضي — يمكن تغييره من داخل التطبيق
/// أو وقت البناء: flutter build apk --dart-define=API_URL=https://pos.example.com
const String kDefaultServerUrl = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000');

class ApiException implements Exception {
  ApiException(this.message, [this.statusCode]);
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class Api {
  Api._();
  static final Api instance = Api._();

  String serverUrl = kDefaultServerUrl;
  String? token;

  static const _kServer = 'server_url';
  static const _kToken = 'token';

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    serverUrl = prefs.getString(_kServer) ?? kDefaultServerUrl;
    token = prefs.getString(_kToken);
  }

  Future<void> setServerUrl(String url) async {
    serverUrl = url.trim().replaceAll(RegExp(r'/+$'), '');
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kServer, serverUrl);
  }

  Future<void> setToken(String? value) async {
    token = value;
    final prefs = await SharedPreferences.getInstance();
    if (value == null) {
      await prefs.remove(_kToken);
    } else {
      await prefs.setString(_kToken, value);
    }
  }

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final q = query?.map((k, v) => MapEntry(k, '$v'))?..removeWhere((k, v) => v == 'null' || v.isEmpty);
    return Uri.parse('$serverUrl/api/$path').replace(queryParameters: (q == null || q.isEmpty) ? null : q);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Accept-Language': i18n.currentLang,
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<dynamic> get(String path, [Map<String, dynamic>? query]) =>
      _send(() => http.get(_uri(path, query), headers: _headers));

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) =>
      _send(() => http.post(_uri(path), headers: _headers, body: jsonEncode(body ?? {})));

  Future<dynamic> put(String path, [Map<String, dynamic>? body]) =>
      _send(() => http.put(_uri(path), headers: _headers, body: jsonEncode(body ?? {})));

  Future<dynamic> _send(Future<http.Response> Function() request) async {
    http.Response res;
    try {
      res = await request().timeout(const Duration(seconds: 20));
    } catch (_) {
      throw ApiException(tr('تعذر الاتصال بالسيرفر — تأكد من الإنترنت ورابط السيرفر'));
    }

    dynamic body;
    try {
      body = res.body.isEmpty ? null : jsonDecode(utf8.decode(res.bodyBytes));
    } catch (_) {
      body = null;
    }

    if (res.statusCode >= 200 && res.statusCode < 300) return body;

    String message = tr('حدث خطأ ({0})', [res.statusCode]);
    if (body is Map) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        message = first is List && first.isNotEmpty ? '${first.first}' : '$first';
      } else if (body['message'] is String && (body['message'] as String).isNotEmpty) {
        message = body['message'];
      }
    }
    if (res.statusCode == 401) message = tr('انتهت الجلسة، سجل الدخول مرة أخرى');
    throw ApiException(message, res.statusCode);
  }
}
