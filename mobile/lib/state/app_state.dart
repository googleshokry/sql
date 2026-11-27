import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api.dart';
import '../models.dart';

/// Session, server and language settings, persisted on the device.
class AppState extends ChangeNotifier {
  AppState(this._prefs) {
    serverUrl = _prefs.getString('server_url') ?? '';
    token = _prefs.getString('token');
    userName = _prefs.getString('user_name');
    userEmail = _prefs.getString('user_email');
    locale = Locale(_prefs.getString('locale') ?? 'ar');
  }

  final SharedPreferences _prefs;

  String serverUrl = '';
  String? token;
  String? userName;
  String? userEmail;
  Locale locale = const Locale('ar');
  Lookups? lookups;

  bool get isLoggedIn => token != null && serverUrl.isNotEmpty;

  ApiClient get api => ApiClient(baseUrl: serverUrl, token: token, locale: locale.languageCode);

  Future<void> login(String server, String email, String password) async {
    final base = ApiClient.normalizeBaseUrl(server);
    final client = ApiClient(baseUrl: base, locale: locale.languageCode);
    final data = await client.post('auth/login', {'email': email.trim(), 'password': password, 'device_name': 'android'});

    serverUrl = base;
    token = data['token'] as String;
    userName = data['user']['name']?.toString();
    userEmail = data['user']['email']?.toString();
    await _prefs.setString('server_url', serverUrl);
    await _prefs.setString('token', token!);
    await _prefs.setString('user_name', userName ?? '');
    await _prefs.setString('user_email', userEmail ?? '');

    // keep the server-side preference in sync with the app language
    try {
      await api.put('auth/locale', {'locale': locale.languageCode});
    } catch (_) {}

    await loadLookups();
    notifyListeners();
  }

  Future<void> loadLookups() async {
    final data = await api.get('lookups');
    lookups = Lookups.fromJson(data as Map<String, dynamic>);
    notifyListeners();
  }

  Future<void> setLocale(String code) async {
    locale = Locale(code);
    await _prefs.setString('locale', code);
    notifyListeners();
    if (isLoggedIn) {
      try {
        await api.put('auth/locale', {'locale': code});
        await loadLookups();
      } catch (_) {}
    }
  }

  Future<void> logout({bool callServer = true}) async {
    if (callServer && isLoggedIn) {
      try {
        await api.post('auth/logout', {});
      } catch (_) {}
    }
    token = null;
    lookups = null;
    await _prefs.remove('token');
    notifyListeners();
  }
}
