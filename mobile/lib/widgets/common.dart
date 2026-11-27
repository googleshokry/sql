import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api.dart';
import '../l10n.dart';
import '../state/app_state.dart';

final _number = NumberFormat('#,##0.00', 'en');

String money(BuildContext context, double value) {
  final currency = context.read<AppState>().lookups?.currency ?? '';
  return '${_number.format(value)} $currency'.trim();
}

String number(double value) => _number.format(value);

String qty(double value) => value == value.roundToDouble() ? value.toInt().toString() : value.toString();

void showMessage(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(message),
    backgroundColor: error ? Theme.of(context).colorScheme.error : null,
  ));
}

/// Shows an API error and signs out if the token is no longer valid.
void handleError(BuildContext context, Object error) {
  if (error is ApiException && error.isUnauthorized) {
    context.read<AppState>().logout(callServer: false);
    showMessage(context, context.t('session_expired'), error: true);
    return;
  }
  showMessage(context, error.toString(), error: true);
}

Future<void> openUrl(BuildContext context, String? url) async {
  if (url == null) return;
  final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) showMessage(context, url, error: true);
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.cloud_off, size: 48, color: Theme.of(context).colorScheme.error),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.tonal(onPressed: onRetry, child: Text(context.t('retry'))),
        ]),
      ),
    );
  }
}

class EmptyView extends StatelessWidget {
  const EmptyView({super.key, this.icon = Icons.inbox_outlined});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 48, color: Theme.of(context).colorScheme.outline),
        const SizedBox(height: 8),
        Text(context.t('no_data'), style: TextStyle(color: Theme.of(context).colorScheme.outline)),
      ]),
    );
  }
}

class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status, required this.label});

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'paid' => Colors.green,
      'partial' => Colors.orange,
      'unpaid' => Colors.red,
      _ => Colors.grey,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(20)),
      child: Text(label, style: TextStyle(color: color.shade700, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

/// Generic paginated list over a Laravel paginated API resource.
class PagedList<T> extends StatefulWidget {
  const PagedList({
    super.key,
    required this.path,
    required this.fromJson,
    required this.itemBuilder,
    this.query = const {},
    this.emptyIcon = Icons.inbox_outlined,
  });

  final String path;
  final Map<String, dynamic> query;
  final T Function(Map<String, dynamic>) fromJson;
  final Widget Function(BuildContext, T) itemBuilder;
  final IconData emptyIcon;

  @override
  State<PagedList<T>> createState() => PagedListState<T>();
}

class PagedListState<T> extends State<PagedList<T>> {
  final List<T> _items = [];
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  @override
  void didUpdateWidget(covariant PagedList<T> oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.query.toString() != widget.query.toString() || oldWidget.path != widget.path) refresh();
  }

  Future<void> refresh() async {
    _items.clear();
    _page = 1;
    _hasMore = true;
    await _load();
  }

  Future<void> _load() async {
    if (_loading || !_hasMore) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await context.read<AppState>().api.get(widget.path, query: {...widget.query, 'page': _page});
      final list = (data['data'] as List).map((e) => widget.fromJson(e as Map<String, dynamic>)).toList();
      final meta = data['meta'] as Map<String, dynamic>?;
      if (!mounted) return;
      setState(() {
        _items.addAll(list);
        _hasMore = meta != null && (meta['current_page'] as int) < (meta['last_page'] as int);
        _page++;
      });
    } catch (e) {
      if (!mounted) return;
      if (e is ApiException && e.isUnauthorized) handleError(context, e);
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null && _items.isEmpty) return ErrorView(message: _error!, onRetry: refresh);
    if (_loading && _items.isEmpty) return const Center(child: CircularProgressIndicator());

    return RefreshIndicator(
      onRefresh: refresh,
      child: _items.isEmpty
          ? ListView(children: [const SizedBox(height: 120), EmptyView(icon: widget.emptyIcon)])
          : NotificationListener<ScrollNotification>(
              onNotification: (n) {
                if (n.metrics.pixels > n.metrics.maxScrollExtent - 200) _load();
                return false;
              },
              child: ListView.separated(
                padding: const EdgeInsets.only(bottom: 96),
                itemCount: _items.length + (_hasMore ? 1 : 0),
                separatorBuilder: (_, _) => const Divider(height: 1),
                itemBuilder: (context, index) {
                  if (index >= _items.length) {
                    return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
                  }
                  return widget.itemBuilder(context, _items[index]);
                },
              ),
            ),
    );
  }
}
