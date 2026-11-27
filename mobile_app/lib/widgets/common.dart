import 'package:intl/intl.dart' as intl;
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api.dart';
import '../core/models.dart';
import '../core/session.dart';
import '../core/i18n.dart';

const kBrand = Color(0xFFF97316);

String money(num v) => intl.NumberFormat('#,##0.##', 'en').format(v);

String timeAgo(DateTime d) {
  final diff = DateTime.now().difference(d);
  if (diff.inMinutes < 1) return tr('الآن');
  if (diff.inMinutes < 60) return tr('منذ {0} د', [diff.inMinutes]);
  if (diff.inHours < 24) return tr('منذ {0} س', [diff.inHours]);
  return intl.DateFormat('d/M h:mm a').format(d);
}

void toast(BuildContext context, String msg, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(msg), backgroundColor: error ? Colors.red.shade700 : Colors.green.shade700));
}

/// ينفذ طلب للسيرفر ويعرض الخطأ لو حصل
Future<T?> guard<T>(BuildContext context, Future<T> Function() fn) async {
  try {
    return await fn();
  } on ApiException catch (e) {
    if (context.mounted) toast(context, e.message, error: true);
  } catch (e) {
    if (context.mounted) toast(context, tr('خطأ غير متوقع: {0}', [e]), error: true);
  }
  return null;
}

Future<void> openUrl(String url) => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
Future<void> callPhone(String phone) => launchUrl(Uri.parse('tel:$phone'));
Future<void> openMap(String address) =>
    launchUrl(Uri.parse('https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(address)}'),
        mode: LaunchMode.externalApplication);

Color statusColor(String status) => switch (status) {
      'pending' => Colors.grey,
      'preparing' => Colors.orange,
      'ready' => Colors.blue,
      'out_for_delivery' => Colors.indigo,
      'completed' => Colors.green,
      'cancelled' => Colors.red,
      _ => Colors.grey,
    };

class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key, this.label});
  final String status;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final c = statusColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: c.withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
      child: Text(label ?? kStatusLabels[status] ?? status,
          style: TextStyle(color: c, fontSize: 12, fontWeight: FontWeight.bold)),
    );
  }
}

class ErrorView extends StatelessWidget {
  const ErrorView(this.message, {super.key, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: Text(tr('إعادة المحاولة'))),
          ]),
        ),
      );
}

class EmptyView extends StatelessWidget {
  const EmptyView(this.message, {super.key, this.icon = Icons.inbox_outlined});
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 56, color: Colors.grey.shade400),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: Colors.grey.shade600)),
        ]),
      );
}

class ProductImage extends StatelessWidget {
  const ProductImage(this.url, {super.key, this.size = 64});
  final String? url;
  final double size;

  @override
  Widget build(BuildContext context) {
    final placeholder = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: kBrand.withValues(alpha: .08), borderRadius: BorderRadius.circular(10)),
      child: Icon(Icons.restaurant, color: kBrand.withValues(alpha: .6), size: size * .45),
    );
    if (url == null) return placeholder;
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Image.network(url!, width: size, height: size, fit: BoxFit.cover, errorBuilder: (_, _, _) => placeholder),
    );
  }
}

class SumRow extends StatelessWidget {
  const SumRow(this.label, this.value, {super.key, this.bold = false, this.color});
  final String label;
  final String value;
  final bool bold;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
        fontWeight: bold ? FontWeight.w800 : FontWeight.normal, fontSize: bold ? 18 : 14, color: color);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(children: [Text(label, style: style), const Spacer(), Text(value, style: style)]),
    );
  }
}

Future<bool> confirm(BuildContext context, String message) async =>
    await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(tr('لا'))),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: Text(tr('نعم'))),
        ],
      ),
    ) ??
    false;

/// زر تبديل اللغة (EN / ع)
class LangButton extends StatelessWidget {
  const LangButton({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    return TextButton(
      onPressed: session.toggleLang,
      child: Text(session.lang == 'ar' ? 'EN' : 'ع', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
    );
  }
}
