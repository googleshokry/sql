import 'dart:async';

import 'package:latlong2/latlong.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';

import '../../core/i18n.dart';
import '../../core/session.dart';
import '../../widgets/common.dart';
import '../../widgets/live_map.dart';

/// خريطة الطيارين المباشرة (كاشير / مدير)
class DriversMapScreen extends StatefulWidget {
  const DriversMapScreen({super.key});

  @override
  State<DriversMapScreen> createState() => _DriversMapScreenState();
}

class _DriversMapScreenState extends State<DriversMapScreen> {
  List<Map<String, dynamic>>? drivers;
  String? error;
  Timer? timer;

  @override
  void initState() {
    super.initState();
    load();
    timer = Timer.periodic(const Duration(seconds: 20), (_) => load());
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final res = await context.read<Session>().api.get('drivers/locations');
      if (!mounted) return;
      setState(() {
        drivers = (res['data'] as List).cast<Map<String, dynamic>>();
        error = null;
      });
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    if (drivers == null) return error != null ? ErrorView(error!, onRetry: load) : const Center(child: CircularProgressIndicator());

    LatLng? at(Map? l) => l == null || l['lat'] == null ? null : LatLng((l['lat'] as num).toDouble(), (l['lng'] as num).toDouble());
    final pins = <MapPin>[];
    final lines = <List<LatLng>>[];
    for (final d in drivers!) {
      final p = at(d['location']);
      if (p != null) pins.add(MapPin(p, child: DriverPin(live: d['live'] == true, label: (d['name'] as String).trim().characters.first)));
      for (final o in (d['orders'] as List)) {
        final dest = at(o);
        if (dest != null) {
          pins.add(MapPin(dest, size: 28, child: const HomePin()));
          if (p != null) lines.add([p, dest]);
        }
      }
    }

    return RefreshIndicator(
      onRefresh: load,
      child: ListView(padding: const EdgeInsets.all(12), children: [
        LiveMap(height: MediaQuery.sizeOf(context).height * .42, pins: pins, lines: lines),
        const SizedBox(height: 8),
        if (drivers!.isEmpty) EmptyView(tr('لا يوجد طيارين')),
        for (final d in drivers!)
          Card(
            child: ExpansionTile(
              leading: CircleAvatar(
                backgroundColor: (d['live'] == true ? Colors.green : Colors.grey).withValues(alpha: .15),
                child: Icon(Icons.delivery_dining, color: d['live'] == true ? Colors.green : Colors.grey),
              ),
              title: Text(d['name'], style: const TextStyle(fontWeight: FontWeight.bold)),
              subtitle: Text([
                d['live'] == true ? tr('متصل') : (d['is_online'] == true ? tr('الموقع غير متحدث') : tr('خارج الشيفت')),
                if (d['last_seen'] != null) d['last_seen'],
                tr('{0} طلب', [(d['orders'] as List).length]),
              ].join(' • ')),
              trailing: d['phone'] == null ? null : IconButton(icon: const Icon(Icons.call), onPressed: () => callPhone(d['phone'])),
              children: [
                for (final o in (d['orders'] as List))
                  ListTile(
                    dense: true,
                    title: Text('#${o['number']} — ${o['customer'] ?? ''}'),
                    subtitle: Text('${o['area'] ?? ''} • ${o['address'] ?? ''}'),
                    trailing: Text(o['stage_label'] ?? '', style: const TextStyle(color: kBrand, fontWeight: FontWeight.bold)),
                  ),
              ],
            ),
          ),
      ]),
    );
  }
}
