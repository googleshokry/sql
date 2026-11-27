import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:material_ui/material_ui.dart';

import 'common.dart';

class MapPin {
  MapPin(this.point, {required this.child, this.size = 40});
  final LatLng point;
  final Widget child;
  final double size;
}

/// خريطة OpenStreetMap بسيطة مع علامات وخطوط
class LiveMap extends StatelessWidget {
  const LiveMap({super.key, required this.pins, this.lines = const [], this.height = 260});
  final List<MapPin> pins;
  final List<List<LatLng>> lines;
  final double height;

  static const _fallback = LatLng(30.0444, 31.2357); // القاهرة

  @override
  Widget build(BuildContext context) {
    final points = pins.map((p) => p.point).toList();
    final options = points.length > 1
        ? MapOptions(
            initialCameraFit: CameraFit.bounds(bounds: LatLngBounds.fromPoints(points), padding: const EdgeInsets.all(48), maxZoom: 16))
        : MapOptions(initialCenter: points.isEmpty ? _fallback : points.first, initialZoom: points.isEmpty ? 11 : 15);

    return SizedBox(
      height: height,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: FlutterMap(
          // نعيد ضبط الكاميرا لما عدد العلامات يتغير
          key: ValueKey(points.length),
          options: options,
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.restaurantpos.restaurant_pos_app',
            ),
            if (lines.isNotEmpty)
              PolylineLayer(polylines: [
                for (final l in lines)
                  Polyline(points: l, color: kBrand, strokeWidth: 3, pattern: StrokePattern.dashed(segments: const [10, 8])),
              ]),
            MarkerLayer(markers: [
              for (final p in pins) Marker(point: p.point, width: p.size, height: p.size, child: p.child),
            ]),
            const SimpleAttributionWidget(source: Text('OpenStreetMap')),
          ],
        ),
      ),
    );
  }
}

class DriverPin extends StatelessWidget {
  const DriverPin({super.key, this.live = true, this.label});
  final bool live;
  final String? label;

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          color: live ? kBrand : Colors.grey,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 3),
          boxShadow: const [BoxShadow(blurRadius: 6, color: Colors.black38)],
        ),
        alignment: Alignment.center,
        child: label == null
            ? const Icon(Icons.delivery_dining, color: Colors.white, size: 20)
            : Text(label!, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
      );
}

class HomePin extends StatelessWidget {
  const HomePin({super.key});

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          color: Colors.blue.shade700,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.white, width: 2),
          boxShadow: const [BoxShadow(blurRadius: 6, color: Colors.black38)],
        ),
        child: const Icon(Icons.home, color: Colors.white, size: 18),
      );
}
