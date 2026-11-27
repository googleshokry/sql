import 'dart:ui' show PlatformDispatcher;

import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'i18n.dart' as i18n;
import 'i18n.dart';
import 'models.dart';

/// حالة تسجيل الدخول + البيانات المشتركة (المنيو والإعدادات)
class Session extends ChangeNotifier {
  final api = Api.instance;

  String? actor; // staff | customer
  Map<String, dynamic>? user;
  AppSettings? settings;
  List<Category> menu = [];
  List<DeliveryArea> areas = [];
  bool ready = false;

  String get role => actor == 'customer' ? 'customer' : (user?['role'] ?? '');
  bool get loggedIn => api.token != null && user != null;
  String get currency => settings?.currency ?? '';

  String get lang => i18n.currentLang;

  /// تغيير اللغة: يحفظ الاختيار ويعيد تحميل المنيو بالأسماء المترجمة
  Future<void> setLang(String value) async {
    i18n.currentLang = value == 'en' ? 'en' : 'ar';
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('lang', i18n.currentLang);
    notifyListeners();
    if (loggedIn) {
      try {
        await loadCatalog();
      } catch (_) {}
    }
  }

  Future<void> toggleLang() => setLang(lang == 'ar' ? 'en' : 'ar');

  Future<void> boot() async {
    final prefs = await SharedPreferences.getInstance();
    // أول مرة: لغة الموبايل (عربي لو الموبايل عربي، غير كده إنجليزي)
    i18n.currentLang = prefs.getString('lang') ??
        (PlatformDispatcher.instance.locale.languageCode == 'ar' ? 'ar' : 'en');
    await api.load();
    if (api.token != null) {
      try {
        final me = await api.get('me');
        actor = me['actor'];
        user = Map<String, dynamic>.from(me['user']);
      } catch (_) {
        await api.setToken(null);
      }
    }
    ready = true;
    notifyListeners();
    if (loggedIn) {
      await loadCatalog();
    } else if (hasRestaurant) {
      try {
        settings = AppSettings.fromJson(await api.get('settings'));
        notifyListeners();
      } catch (_) {}
    }
  }

  Future<void> loadCatalog() async {
    final results = await Future.wait([api.get('settings'), api.get('menu'), api.get('delivery-areas')]);
    settings = AppSettings.fromJson(results[0]);
    menu = (results[1]['data'] as List).map((c) => Category.fromJson(c)).toList();
    areas = (results[2]['data'] as List).map((a) => DeliveryArea.fromJson(a)).toList();
    notifyListeners();
  }

  /// اسم المطعم الحالي (من الإعدادات)
  String? get restaurantName => settings?.restaurantName;

  /// يتأكد إن كود المطعم موجود ويحفظه
  Future<void> setRestaurant(String code) async {
    final clean = code.trim().toLowerCase();
    if (clean.isEmpty) throw ApiException(tr('اكتب كود المطعم'));
    final previous = api.tenant;
    await api.setTenant(clean);
    try {
      final res = await api.get('settings');
      settings = AppSettings.fromJson(res);
      notifyListeners();
    } on ApiException catch (e) {
      // 402 = المطعم موجود بس الاشتراك منتهي
      if (e.statusCode == 402) {
        notifyListeners();
        return;
      }
      await api.setTenant(previous);
      throw ApiException(e.statusCode == 404 ? tr('كود المطعم غير موجود') : e.message, e.statusCode);
    }
  }

  Future<void> clearRestaurant() async {
    if (loggedIn) await logout();
    await api.setTenant(null);
    settings = null;
    notifyListeners();
  }

  bool get hasRestaurant => (api.tenant ?? '').isNotEmpty;

  Future<void> staffLogin(String email, String password) =>
      _auth('staff/login', {'email': email, 'password': password, 'device_name': 'mobile'});

  Future<void> customerLogin(String phone, String password) =>
      _auth('customer/login', {'phone': phone, 'password': password});

  Future<void> customerRegister(Map<String, dynamic> data) => _auth('customer/register', data);

  Future<void> _auth(String path, Map<String, dynamic> body) async {
    final res = await api.post(path, body);
    await api.setToken(res['token']);
    actor = res['actor'];
    user = Map<String, dynamic>.from(res['user']);
    await loadCatalog();
    notifyListeners();
  }

  void updateUser(Map<String, dynamic> u) {
    user = u;
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await api.post('logout');
    } catch (_) {}
    await api.setToken(null);
    user = null;
    actor = null;
    notifyListeners();
  }
}
