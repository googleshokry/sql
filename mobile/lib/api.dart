import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}

/// Thin JSON client for the Smart ERP `/api/v1` endpoints.
class ApiClient {
  ApiClient({required this.baseUrl, this.token, this.locale = 'ar', http.Client? client})
      : _client = client ?? http.Client();

  final String baseUrl;
  String? token;
  String locale;
  final http.Client _client;

  static String normalizeBaseUrl(String url) {
    var value = url.trim();
    if (value.isEmpty) return value;
    if (!value.startsWith('http://') && !value.startsWith('https://')) {
      value = 'https://$value';
    }
    while (value.endsWith('/')) {
      value = value.substring(0, value.length - 1);
    }
    if (value.endsWith('/admin')) value = value.substring(0, value.length - 6);
    return value;
  }

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final params = <String, String>{};
    query?.forEach((key, value) {
      if (value != null && value.toString().isNotEmpty) params[key] = value.toString();
    });
    return Uri.parse('$baseUrl/api/v1/$path').replace(queryParameters: params.isEmpty ? null : params);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Accept-Language': locale,
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _client.get(_uri(path, query), headers: _headers));

  Future<dynamic> post(String path, Map<String, dynamic> body) =>
      _send(() => _client.post(_uri(path), headers: _headers, body: jsonEncode(body)));

  Future<dynamic> put(String path, Map<String, dynamic> body) =>
      _send(() => _client.put(_uri(path), headers: _headers, body: jsonEncode(body)));

  Future<dynamic> _send(Future<http.Response> Function() request) async {
    http.Response response;
    try {
      response = await request().timeout(const Duration(seconds: 30));
    } on SocketException {
      throw ApiException(locale == 'ar' ? 'تعذّر الاتصال بالسيرفر' : 'Could not reach the server');
    } on TimeoutException {
      throw ApiException(locale == 'ar' ? 'انتهت مهلة الاتصال' : 'Connection timed out');
    } on http.ClientException catch (e) {
      throw ApiException(e.message);
    }

    dynamic body;
    try {
      body = response.body.isEmpty ? null : jsonDecode(utf8.decode(response.bodyBytes));
    } catch (_) {
      body = null;
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    }

    var message = 'HTTP ${response.statusCode}';
    if (body is Map) {
      final errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        message = first is List && first.isNotEmpty ? first.first.toString() : first.toString();
      } else if (body['message'] != null && body['message'].toString().isNotEmpty) {
        message = body['message'].toString();
      }
    } else if (body == null && response.statusCode == 404) {
      message = locale == 'ar' ? 'الرابط غير صحيح - تأكد من رابط السيرفر' : 'Not found - check the server URL';
    }
    throw ApiException(message, statusCode: response.statusCode);
  }
}
