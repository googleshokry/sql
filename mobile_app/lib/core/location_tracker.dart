import 'dart:async';

import 'package:flutter/foundation.dart' show defaultTargetPlatform, kIsWeb, TargetPlatform;
import 'package:geolocator/geolocator.dart';
import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'i18n.dart';

/// تتبع موقع الطيار وإرساله للسيرفر طول ما هو في الشيفت
class LocationTracker extends ChangeNotifier {
  LocationTracker(this.api);
  final Api api;

  bool online = false;
  bool busy = false;
  Position? last;
  DateTime? lastSent;
  String? error;

  StreamSubscription<Position>? _sub;
  Timer? _heartbeat;

  /// يتأكد إن خدمة الموقع شغالة وإن فيه إذن
  Future<bool> _ensurePermission() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      error = tr('خدمة الموقع (GPS) مقفولة — شغلها من إعدادات الموبايل');
      return false;
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      error = tr('لازم تسمح للتطبيق بالوصول للموقع عشان المطعم يتابع التوصيل');
      return false;
    }
    error = null;
    return true;
  }

  LocationSettings get _settings {
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      return AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 20,
        intervalDuration: const Duration(seconds: 15),
        // إشعار ثابت = التتبع يكمل والموبايل في الجيب
        foregroundNotificationConfig: ForegroundNotificationConfig(
          notificationTitle: tr('أنت في الشيفت'),
          notificationText: tr('المطعم بيتابع موقعك لحد ما تقفل الشيفت'),
          enableWakeLock: true,
          setOngoing: true,
        ),
      );
    }
    if (!kIsWeb && (defaultTargetPlatform == TargetPlatform.iOS || defaultTargetPlatform == TargetPlatform.macOS)) {
      return AppleSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 20,
        activityType: ActivityType.automotiveNavigation,
        pauseLocationUpdatesAutomatically: false,
        allowBackgroundLocationUpdates: true,
        showBackgroundLocationIndicator: true,
      );
    }
    return const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 20);
  }

  Future<bool> goOnline() async {
    if (online) return true;
    busy = true;
    notifyListeners();
    try {
      if (!await _ensurePermission()) return false;
      await api.post('driver/shift', {'online': true});
      online = true;

      _sub = Geolocator.getPositionStream(locationSettings: _settings).listen(
        (p) => _send(p),
        onError: (e) {
          error = '$e';
          notifyListeners();
        },
      );
      // نبضة كل دقيقة حتى لو الطيار واقف (عشان يفضل ظاهر متصل)
      _heartbeat = Timer.periodic(const Duration(seconds: 60), (_) async {
        try {
          _send(await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high)), force: true);
        } catch (_) {}
      });
      try {
        _send(await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high)), force: true);
      } catch (_) {}
      return true;
    } on ApiException catch (e) {
      error = e.message;
      return false;
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> goOffline() async {
    busy = true;
    notifyListeners();
    await _stop();
    try {
      await api.post('driver/shift', {'online': false});
    } catch (_) {}
    busy = false;
    notifyListeners();
  }

  Future<void> _stop() async {
    await _sub?.cancel();
    _sub = null;
    _heartbeat?.cancel();
    _heartbeat = null;
    online = false;
  }

  Future<void> _send(Position p, {bool force = false}) async {
    last = p;
    notifyListeners();
    // مش أكتر من مرة كل 10 ثواني
    if (!force && lastSent != null && DateTime.now().difference(lastSent!) < const Duration(seconds: 10)) return;
    try {
      await api.post('driver/location', {
        'lat': p.latitude,
        'lng': p.longitude,
        'accuracy': p.accuracy,
        'speed': p.speed,
      });
      lastSent = DateTime.now();
      error = null;
    } on ApiException catch (e) {
      error = e.message;
    }
    notifyListeners();
  }

  /// موقع لمرة واحدة (للعميل وهو بيحدد عنوانه)
  static Future<Position?> currentPosition(BuildContext context) async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) return null;
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) return null;
      return await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high));
    } catch (_) {
      return null;
    }
  }

  @override
  void dispose() {
    _stop();
    super.dispose();
  }
}
